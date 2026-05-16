import { useState, useCallback, useRef, useEffect } from 'react'
import { createRoot } from 'react-dom/client'
import { Excalidraw } from '@excalidraw/excalidraw'
import '@excalidraw/excalidraw/index.css'

// Hide Excalidraw's built-in bottom-right controls (we render our own footer)
const HIDE_FOOTER_CSS = `
    .layer-ui__wrapper__footer-right,
    .layer-ui__wrapper__footer-center,
    .zoom-actions,
    .undo-redo-buttons,
    .help-icon,
    .library-button,
    [aria-label="Library"],
    .layer-ui__wrapper__library,
    .sidebar-trigger,
    .default-sidebar-trigger { display: none !important; }

    /* Keep PiP camera visible on top when the whiteboard is in fullscreen */
    :fullscreen .lk-pip,
    :-webkit-full-screen .lk-pip {
        position: fixed !important;
        top: 12px !important;
        right: 12px !important;
        z-index: 9999 !important;
    }
`

const btnStyle = {
    background: '#fff',
    border: '1px solid #d0d0d0',
    borderRadius: '6px',
    padding: '5px 12px',
    cursor: 'pointer',
    fontSize: '15px',
    fontWeight: '600',
    color: '#333',
    lineHeight: 1,
    minWidth: '34px',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    transition: 'background .15s',
}

const zoomStyle = {
    fontSize: '13px',
    color: '#444',
    cursor: 'pointer',
    minWidth: '48px',
    textAlign: 'center',
    padding: '5px 8px',
    border: '1px solid #d0d0d0',
    borderRadius: '6px',
    background: '#fff',
    userSelect: 'none',
}

const mediaBtn = {
    background: '#f0f4ff',
    border: '1px solid #b0c0e8',
    borderRadius: '6px',
    padding: '5px 10px',
    cursor: 'pointer',
    fontSize: '13px',
    fontWeight: '600',
    color: '#224499',
    display: 'flex',
    alignItems: 'center',
    gap: '4px',
    whiteSpace: 'nowrap',
    transition: 'background .15s',
}

export function mountExcalidraw(domNode, {
    onChange,
    readOnly        = true,
    initialElements = [],
    isTeacher       = false,
    onPdfFile, onVideoFile, onVideoUrl,
    onPdfPrev, onPdfNext, onPdfClose,
    onVideoClose, onVideoPlay, onVideoPause, onVideoSeeked,
} = {}) {
    const shared = {
        excalidrawAPI: null, setViewMode: null, isRemoteUpdate: false,
        setPdfShow: null, setPdfCurrent: null, setPdfTotal: null,
        setVideoShow: null, setVideoUrl: null, setLoading: null,
        videoElRef: { current: null },
    }

    function App() {
        const [excalidrawAPI, setExcalidrawAPI] = useState(null)
        const [viewMode, setViewMode]           = useState(readOnly)
        const [zoom, setZoom]                   = useState(1)
        const [isFullscreen, setIsFullscreen]   = useState(false)
        const lastZoomRef                       = useRef(1)
        const lastElementsKeyRef                = useRef('')
        const containerRef                      = useRef(null)

        // ─ PDF / Video state ──────────────────────────────────────────────────────
        const pdfInputRef                        = useRef(null)
        const videoInputRef                      = useRef(null)
        const videoElRef                         = useRef(null)
        const [pdfShow,    setPdfShow]            = useState(false)
        const [pdfCurrent, setPdfCurrent]         = useState(0)
        const [pdfTotal,   setPdfTotal]           = useState(0)
        const [videoShow,  setVideoShow]          = useState(false)
        const [videoUrl,   setVideoUrl]           = useState('')
        const [videoPos,   setVideoPos]           = useState({ x: 20, y: 20, w: 480, h: 270 })
        const [videoInputShow,  setVideoInputShow] = useState(false)
        const [videoInputText,  setVideoInputText] = useState('')
        const [loading,    setLoading]            = useState(false)
        const videoDragState = useRef({ mode: null, sx: 0, sy: 0, ox: 0, oy: 0, ow: 0, oh: 0 })

        useEffect(() => {
            const onFsChange = () => setIsFullscreen(!!document.fullscreenElement)
            document.addEventListener('fullscreenchange', onFsChange)
            return () => document.removeEventListener('fullscreenchange', onFsChange)
        }, [])

        const toggleFullscreen = () => {
            if (!document.fullscreenElement) {
                // Go fullscreen on the parent .lk-wb-inset so the PiP camera is included
                const target = domNode.closest('.lk-wb-inset') || containerRef.current
                target?.requestFullscreen()
            } else {
                document.exitFullscreen()
            }
        }

        // keep shared in sync
        shared.excalidrawAPI = excalidrawAPI
        shared.setViewMode   = setViewMode
        shared.setPdfShow    = setPdfShow
        shared.setPdfCurrent = setPdfCurrent
        shared.setPdfTotal   = setPdfTotal
        shared.setVideoShow  = setVideoShow
        shared.setVideoUrl   = setVideoUrl
        shared.setLoading    = setLoading
        shared.videoElRef    = videoElRef

        const handleChange = useCallback((elements, appState) => {
            if (shared.isRemoteUpdate) return

            // track zoom for display
            const z = appState?.zoom?.value ?? 1
            if (Math.abs(z - lastZoomRef.current) > 0.001) {
                lastZoomRef.current = z
                setZoom(z)
            }

            // element change detection — include versionNonce to catch fileId / property updates
            const key = elements.map(e => `${e.id}:${e.versionNonce ?? 0}`).join('|')
            if (key === lastElementsKeyRef.current) return
            lastElementsKeyRef.current = key
            onChange?.(elements)
        }, [])

        const handleUndo = () => excalidrawAPI?.undo?.()
        const handleRedo = () => excalidrawAPI?.redo?.()

        const handleZoomOut = () => {
            if (!excalidrawAPI) return
            const cur = excalidrawAPI.getAppState().zoom.value
            excalidrawAPI.updateScene({ appState: { zoom: { value: Math.max(+(cur - 0.1).toFixed(2), 0.1) } } })
        }
        const handleZoomIn = () => {
            if (!excalidrawAPI) return
            const cur = excalidrawAPI.getAppState().zoom.value
            excalidrawAPI.updateScene({ appState: { zoom: { value: Math.min(+(cur + 0.1).toFixed(2), 5) } } })
        }
        const handleResetZoom = () => {
            excalidrawAPI?.updateScene({ appState: { zoom: { value: 1 } } })
        }

        return (
            <div ref={containerRef} style={{ display: 'flex', flexDirection: 'column', width: '100%', height: '100%', background: '#fff' }}>
                {/* Excalidraw canvas — fills remaining height */}
                <div style={{ flex: 1, minHeight: 0, position: 'relative' }}>
                    <style>{HIDE_FOOTER_CSS}</style>

                    <Excalidraw
                        excalidrawAPI={setExcalidrawAPI}
                        initialData={{ elements: initialElements, appState: { viewModeEnabled: readOnly } }}
                        onChange={handleChange}
                        viewModeEnabled={viewMode}
                        langCode="ar"
                        theme="light"
                        UIOptions={{ canvasActions: { saveToActiveFile: false, loadScene: false }, tools: { image: false } }}
                    />

                    {/* PDF nav bar */}
                    {pdfShow && (
                        <div style={{
                            position: 'absolute', bottom: 10, left: '50%', transform: 'translateX(-50%)',
                            zIndex: 60, display: 'flex', alignItems: 'center', gap: 8,
                            background: 'rgba(0,0,0,0.78)', borderRadius: 10, padding: '7px 14px',
                            pointerEvents: 'auto',
                        }}>
                            {isTeacher && (
                                <button onMouseDown={e => e.preventDefault()} onClick={() => onPdfPrev?.()}
                                    disabled={pdfCurrent <= 0}
                                    style={{ background: 'rgba(255,255,255,.15)', border: '1px solid rgba(255,255,255,.4)', borderRadius: 6, padding: '4px 12px', cursor: 'pointer', color: '#fff', fontWeight: 700, opacity: pdfCurrent <= 0 ? 0.4 : 1 }}>&#8249; السابق</button>
                            )}
                            <span style={{ color: '#fff', fontSize: 13, fontWeight: 700, minWidth: 52, textAlign: 'center' }}>{pdfCurrent + 1} / {pdfTotal}</span>
                            {isTeacher && (
                                <>
                                    <button onMouseDown={e => e.preventDefault()} onClick={() => onPdfNext?.()}
                                        disabled={pdfCurrent >= pdfTotal - 1}
                                        style={{ background: 'rgba(255,255,255,.15)', border: '1px solid rgba(255,255,255,.4)', borderRadius: 6, padding: '4px 12px', cursor: 'pointer', color: '#fff', fontWeight: 700, opacity: pdfCurrent >= pdfTotal - 1 ? 0.4 : 1 }}>التالي &#8250;</button>
                                    <button onMouseDown={e => e.preventDefault()} onClick={() => onPdfClose?.()}
                                        style={{ background: '#dc3545', border: 'none', borderRadius: 6, padding: '4px 12px', cursor: 'pointer', color: '#fff', fontWeight: 700 }}>✕ إغلاق</button>
                                </>
                            )}
                        </div>
                    )}

                    {/* Video — draggable & resizable floating panel */}
                    {videoShow && videoUrl && (
                        <div style={{
                            position: 'absolute', left: videoPos.x, top: videoPos.y,
                            width: videoPos.w, height: videoPos.h,
                            zIndex: 50, background: '#111', borderRadius: 8,
                            boxShadow: '0 4px 24px rgba(0,0,0,.55)',
                            display: 'flex', flexDirection: 'column', overflow: 'hidden',
                            userSelect: 'none',
                        }}>
                            {/* Drag handle */}
                            <div
                                onPointerDown={e => {
                                    e.currentTarget.setPointerCapture(e.pointerId)
                                    videoDragState.current = { mode: 'drag', sx: e.clientX, sy: e.clientY, ox: videoPos.x, oy: videoPos.y, ow: videoPos.w, oh: videoPos.h }
                                }}
                                onPointerMove={e => {
                                    if (videoDragState.current.mode !== 'drag') return
                                    const { sx, sy, ox, oy } = videoDragState.current
                                    setVideoPos(p => ({ ...p, x: ox + e.clientX - sx, y: oy + e.clientY - sy }))
                                }}
                                onPointerUp={() => { videoDragState.current.mode = null }}
                                style={{ height: 28, background: '#1a1a2e', display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '0 10px', cursor: 'grab', flexShrink: 0 }}
                            >
                                <span style={{ color: '#9ab', fontSize: 12, fontWeight: 600 }}>▶ فيديو — اسحب للتحريك</span>
                                {isTeacher && (
                                    <button
                                        onPointerDown={e => e.stopPropagation()}
                                        onClick={() => onVideoClose?.()}
                                        style={{ background: '#dc3545', color: '#fff', border: 'none', borderRadius: '50%', width: 20, height: 20, fontSize: 11, cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' }}
                                    >✕</button>
                                )}
                            </div>
                            {/* Video element */}
                            <video ref={videoElRef} src={videoUrl} controls controlsList="nodownload"
                                style={{ flex: 1, width: '100%', objectFit: 'contain', background: '#000', minHeight: 0 }}
                                onPlay={() => onVideoPlay?.(videoElRef.current?.currentTime ?? 0)}
                                onPause={() => onVideoPause?.(videoElRef.current?.currentTime ?? 0)}
                                onSeeked={() => onVideoSeeked?.(videoElRef.current?.currentTime ?? 0)}
                            />
                            {/* Resize handle */}
                            <div
                                onPointerDown={e => {
                                    e.stopPropagation()
                                    e.currentTarget.setPointerCapture(e.pointerId)
                                    videoDragState.current = { mode: 'resize', sx: e.clientX, sy: e.clientY, ox: videoPos.x, oy: videoPos.y, ow: videoPos.w, oh: videoPos.h }
                                }}
                                onPointerMove={e => {
                                    if (videoDragState.current.mode !== 'resize') return
                                    const { sx, sy, ow, oh } = videoDragState.current
                                    setVideoPos(p => ({ ...p, w: Math.max(220, ow + e.clientX - sx), h: Math.max(140, oh + e.clientY - sy) }))
                                }}
                                onPointerUp={() => { videoDragState.current.mode = null }}
                                style={{ position: 'absolute', bottom: 0, right: 0, width: 20, height: 20, cursor: 'se-resize', zIndex: 2 }}
                            >
                                <svg width="20" height="20" style={{ display: 'block' }}>
                                    <polyline points="5,20 20,5 20,20" fill="rgba(255,255,255,.3)" />
                                </svg>
                            </div>
                        </div>
                    )}

                    {/* Video URL input popup */}
                    {isTeacher && videoInputShow && !videoShow && (
                        <div style={{ position: 'absolute', bottom: 10, left: '50%', transform: 'translateX(-50%)', zIndex: 65, display: 'flex', alignItems: 'center', gap: 6, background: '#fff', border: '1px solid #d0d0d0', borderRadius: 10, padding: '8px 12px', boxShadow: '0 4px 20px rgba(0,0,0,.2)', minWidth: 300, maxWidth: '90%' }}>
                            <input value={videoInputText} onChange={e => setVideoInputText(e.target.value)}
                                placeholder="الصق رابط الفيديو هنا…"
                                style={{ flex: 1, border: '1px solid #d0d0d0', borderRadius: 6, padding: '5px 8px', fontSize: 13 }}
                                onKeyDown={e => { if (e.key === 'Enter' && videoInputText.trim()) { onVideoUrl?.(videoInputText.trim()); setVideoInputShow(false); setVideoInputText('') } }}
                            />
                            <button disabled={!videoInputText.trim()} style={{ ...mediaBtn, background: '#224499', color: '#fff', border: 'none' }}
                                onClick={() => { if (videoInputText.trim()) { onVideoUrl?.(videoInputText.trim()); setVideoInputShow(false); setVideoInputText('') } }}>فتح</button>
                            <button style={mediaBtn} onClick={() => { setVideoInputShow(false); setVideoInputText('') }}>إلغاء</button>
                        </div>
                    )}

                    {/* Loading spinner */}
                    {loading && (
                        <div style={{ position: 'absolute', inset: 0, zIndex: 70, background: 'rgba(255,255,255,.88)', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', pointerEvents: 'none' }}>
                            <div style={{ width: 44, height: 44, border: '4px solid #e0e0e0', borderTop: '4px solid #224499', borderRadius: '50%', animation: 'wbspin .8s linear infinite' }}></div>
                            <style>{'@keyframes wbspin { to { transform: rotate(360deg) } }'}</style>
                            <p style={{ marginTop: 12, color: '#333', fontWeight: 600, fontSize: 14 }}>جارٍ التحميل…</p>
                        </div>
                    )}
                </div>

                {/* Custom footer bar */}
                <div style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    gap: '6px',
                    padding: '6px 14px',
                    background: '#f5f5f5',
                    borderTop: '1px solid #e0e0e0',
                    flexShrink: 0,
                }}>
                    <button onMouseDown={e => e.preventDefault()} onClick={handleUndo} title="تراجع (Ctrl+Z)" style={btnStyle}>↺</button>
                    <button onMouseDown={e => e.preventDefault()} onClick={handleRedo} title="إعادة (Ctrl+Y)" style={btnStyle}>↻</button>
                    <div style={{ width: '1px', height: '22px', background: '#d0d0d0', margin: '0 4px' }} />
                    <button onMouseDown={e => e.preventDefault()} onClick={handleZoomOut} title="تصغير" style={btnStyle}>−</button>
                    <span onMouseDown={e => e.preventDefault()} onClick={handleResetZoom} title="إعادة ضبط التكبير" style={zoomStyle}>
                        {Math.round(zoom * 100)}%
                    </span>
                    <button onMouseDown={e => e.preventDefault()} onClick={handleZoomIn} title="تكبير" style={btnStyle}>+</button>                    {isTeacher && (
                        <>
                            <div style={{ width: '1px', height: '22px', background: '#d0d0d0', margin: '0 4px' }} />
                            <button onMouseDown={e => e.preventDefault()} onClick={() => pdfInputRef.current?.click()}
                                title="رفع PDF وعرضه على اللوحة"
                                style={{ ...mediaBtn, ...(pdfShow ? { background: '#224499', color: '#fff' } : {}) }}>&#128196; PDF</button>
                            <button onMouseDown={e => e.preventDefault()}
                                onClick={() => videoShow ? onVideoClose?.() : setVideoInputShow(v => !v)}
                                title="عرض فيديو على اللوحة"
                                style={{ ...mediaBtn, ...(videoShow ? { background: '#dc3545', color: '#fff' } : videoInputShow ? { background: '#aa5500', color: '#fff' } : {}) }}>
                                &#127916; {videoShow ? 'إغلاق' : 'فيديو'}
                            </button>
                            {videoInputShow && !videoShow && (
                                <button onMouseDown={e => e.preventDefault()} onClick={() => videoInputRef.current?.click()}
                                    title="رفع ملف فيديو" style={mediaBtn}>&#9729;&#65039; ملف</button>
                            )}
                            {/* Hidden file inputs */}
                            <input ref={pdfInputRef} type="file" accept=".pdf,application/pdf" style={{ display: 'none' }}
                                onChange={e => { const f = e.target?.files?.[0]; if (f) { onPdfFile?.(f); e.target.value = '' } }} />
                            <input ref={videoInputRef} type="file" accept="video/mp4,video/webm,video/ogg,video/quicktime" style={{ display: 'none' }}
                                onChange={e => { const f = e.target?.files?.[0]; if (f) { onVideoFile?.(f); e.target.value = ''; setVideoInputShow(false) } }} />
                        </>
                    )}                    <div style={{ width: '1px', height: '22px', background: '#d0d0d0', margin: '0 4px' }} />
                    <button
                        onMouseDown={e => e.preventDefault()}
                        onClick={toggleFullscreen}
                        title={isFullscreen ? 'خروج من ملء الشاشة (Esc)' : 'ملء الشاشة'}
                        style={btnStyle}
                    >{isFullscreen ? '⊡' : '⛶'}</button>
                </div>
            </div>
        )
    }

    const root = createRoot(domNode)
    root.render(<App />)

    return {
        updateScene(elements) {
            shared.isRemoteUpdate = true
            shared.excalidrawAPI?.updateScene({ elements })
            setTimeout(() => { shared.isRemoteUpdate = false }, 200)
        },
        clearBoard() {
            shared.isRemoteUpdate = true
            shared.excalidrawAPI?.updateScene({ elements: [] })
            setTimeout(() => { shared.isRemoteUpdate = false }, 200)
        },
        setReadOnly(value)    { shared.setViewMode?.(value) },
        showPdf(dataURL, fileId, cur, tot, w, h) {
            const api = shared.excalidrawAPI
            if (!api) return
            // Register the file data so Excalidraw can render the image
            api.addFiles([{ id: fileId, dataURL, mimeType: 'image/jpeg', created: Date.now(), lastRetrieved: Date.now() }])
            // Only the teacher inserts/replaces the image element in the scene.
            // Students receive the element via the normal whiteboard_update channel.
            if (isTeacher) {
                const all      = api.getSceneElements()
                const oldPdf   = all.find(el => el.id === '__wb_pdf__')
                const existing = all.filter(el => el.id !== '__wb_pdf__')
                const st       = api.getAppState()
                const zm       = st.zoom?.value ?? 1
                // Preserve position/size if already placed; otherwise centre in view
                const cx = oldPdf ? oldPdf.x     : (-st.scrollX + st.width  / 2) / zm - w / 2
                const cy = oldPdf ? oldPdf.y     : (-st.scrollY + st.height / 2) / zm - h / 2
                const cw = oldPdf ? oldPdf.width  : w
                const ch = oldPdf ? oldPdf.height : h
                api.updateScene({
                    elements: [...existing, {
                        ...(oldPdf ?? {}),
                        type: 'image', id: '__wb_pdf__',
                        // Must be strictly greater than Excalidraw's stored version or the update is ignored
                        version:      (oldPdf?.version ?? 0) + 1,
                        versionNonce: Math.floor(Math.random() * 2 ** 31),
                        index:        oldPdf?.index ?? 'a0',
                        isDeleted: false,
                        x: cx, y: cy, width: cw, height: ch,
                        angle:           oldPdf?.angle ?? 0,
                        opacity:         100,
                        strokeColor:     '#1e1e1e',
                        backgroundColor: 'transparent',
                        fillStyle: 'solid', roughness: 0, strokeWidth: 1, strokeStyle: 'solid',
                        seed:        oldPdf?.seed ?? Math.floor(Math.random() * 2 ** 31),
                        groupIds: [], frameId: null, roundness: null, boundElements: null,
                        updated: Date.now(), link: null, locked: false,
                        fileId, status: 'saved', scale: [1, 1],
                    }],
                })
            }
            shared.setPdfShow?.(true)
            shared.setPdfCurrent?.(cur)
            shared.setPdfTotal?.(tot)
        },
        updatePdfPage(dataURL, fileId, cur, tot, w, h) {
            this.showPdf(dataURL, fileId, cur, tot, w, h)
        },
        addFile(fileId, dataURL) {
            const api = shared.excalidrawAPI
            if (!api) return
            api.addFiles([{ id: fileId, dataURL, mimeType: 'image/jpeg', created: Date.now(), lastRetrieved: Date.now() }])
        },
        closePdf() {
            const api = shared.excalidrawAPI
            if (api && isTeacher) {
                const remaining = api.getSceneElements().filter(el => el.id !== '__wb_pdf__')
                api.updateScene({ elements: remaining })
            }
            shared.setPdfShow?.(false)
            shared.setPdfCurrent?.(0)
            shared.setPdfTotal?.(0)
        },
        showVideo(url)  { shared.setVideoUrl?.(url); shared.setVideoShow?.(true) },
        closeVideo()    { shared.setVideoShow?.(false); shared.setVideoUrl?.('') },
        syncVideo(action, time) {
            const el = shared.videoElRef?.current
            if (!el) return
            el.currentTime = time
            if (action === 'play')  el.play().catch(() => {})
            else if (action === 'pause') el.pause()
        },
        setLoading(value) { shared.setLoading?.(value) },
        unmount() { root.unmount() },
    }
}
