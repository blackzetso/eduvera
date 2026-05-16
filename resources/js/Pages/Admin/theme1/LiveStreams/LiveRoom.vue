<script setup>
import { ref, computed, onMounted, onBeforeUnmount, shallowRef } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { toast } from 'vue3-toastify'
import Swal from 'sweetalert2'
import LiveKitRoom from '@/components/LiveKitRoom.vue'
import LiveStreamQuizPanel from '@/components/LiveStreamQuizPanel.vue'

// ...
const isMobile = ref(typeof window !== 'undefined' ? window.innerWidth < 768 : false)
function handleResize() { isMobile.value = window.innerWidth < 768 }

const props = defineProps({
    stream:         Object,
    jitsiRoom:      String,
    teacherName:    String,
    studentJoinUrl: { type: String, default: null },
    livekitWsUrl:   { type: String, default: null },
    livekitToken:   { type: String, default: null },
    routePrefix:    { type: String, default: 'admin' },
    watermark:      { type: Object, default: null },
})

const classDashboard = computed(() => props.stream.classroom_dashboard ?? 'jitsi')
const useLiveKit     = computed(() => classDashboard.value === 'livekit' && props.livekitWsUrl && props.livekitToken)

// ─── Quiz panel ───────────────────────────────────────────────────────────────
const quizPanelOpen = ref(false)

// ─── Timer ────────────────────────────────────────────────────────────────────
const elapsed      = ref(props.stream.elapsed_seconds ?? 0)
const timerHandle  = ref(null)
const iframeReady  = ref(false)
const ending       = ref(false)
const autoEnded    = ref(false)

// Countdown in seconds — calculated server-side to avoid timezone issues
const remaining  = ref(
    props.stream.seconds_until_end != null
        ? Math.max(0, props.stream.seconds_until_end)
        : Infinity
)

function formatElapsed(s) {
    const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), sec = s % 60
    const pad = n => String(n).padStart(2, '0')
    return h > 0 ? `${pad(h)}:${pad(m)}:${pad(sec)}` : `${pad(m)}:${pad(sec)}`
}

function formatRemaining(s) {
    if (s === Infinity || s < 0) return null
    const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), sec = s % 60
    const pad = n => String(n).padStart(2, '0')
    return h > 0 ? `${pad(h)}:${pad(m)}:${pad(sec)}` : `${pad(m)}:${pad(sec)}`
}

async function autoEndStream() {
    toast.warning('انتهى وقت البث، جارٍ إنهاؤه تلقائياً...')
    ending.value = true
    router.patch(route(`${props.routePrefix}.live-streams.update-status`, props.stream.id), { status: 'ended' }, {
        onSuccess: () => router.visit(route(`${props.routePrefix}.live-streams.show`, props.stream.id)),
        onError:   () => { toast.error('حدث خطأ في الإنهاء التلقائي'); ending.value = false },
    })
}

// Auto-stop recording then navigate away
async function autoStopRecordingAndEnd() {
    if (useLiveKit.value && liveKitRef.value) await stopAndAwaitSave()
    autoEndStream()
}

// ─── Jitsi iframe URL (teacher = moderator as first to enter) ─────────────────
const jitsiSrc = computed(() => {
    const name = encodeURIComponent(props.teacherName)
    const buttons = encodeURIComponent(JSON.stringify([
        'microphone','camera','desktop',
        'chat','participants-pane',
        'raise-hand','fullscreen','hangup',
    ]))
    return `https://meet.jit.si/${props.jitsiRoom}`
        + `#userInfo.displayName=${name}`
        + `&config.prejoinPageEnabled=false`
        + `&config.startWithAudioMuted=false`
        + `&config.startWithVideoMuted=false`
        + `&config.disableDeepLinking=true`
        + `&config.disableInviteFunctions=true`
        + `&config.toolbarButtons=${buttons}`
        + `&interfaceConfig.SHOW_JITSI_WATERMARK=false`
        + `&interfaceConfig.SHOW_BRAND_WATERMARK=false`
        + `&interfaceConfig.TOOLBAR_ALWAYS_VISIBLE=true`
})
// ─── Live extension during stream ──────────────────────────────────────────
const EXTENSION_OPTIONS    = [10, 15, 20, 25, 30]
const extensionRequesting  = ref(null)   // null | minutes
const currentTotalDuration = ref(
    props.stream.seconds_until_end != null ? props.stream.seconds_until_end : Infinity
)

const warningDismissed     = ref(false)
const showExtensionDropdown = ref(false)   // top-bar pill dropdown

const showExtensionPanel = computed(() =>
    remaining.value !== Infinity &&
    remaining.value <= 300 &&
    remaining.value > 0
)

// When time drops into warning range, always re-show the full banner
function onRemainingWarning() {
    warningDismissed.value = false
}

async function requestExtension(minutes) {
    if (extensionRequesting.value !== null) return
    extensionRequesting.value = minutes
    try {
        const csrf = document.head.querySelector('meta[name="csrf-token"]')?.content ?? ''
        const res  = await fetch(route(`${props.routePrefix}.live-streams.request-extension`, props.stream.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ minutes }),
        })
        const data = await res.json()
        if (res.ok && data.success) {
            remaining.value           += minutes * 60
            currentTotalDuration.value += minutes * 60
            toast.success(data.message || `تم تمديد البث بـ ${minutes} دقيقة`)
        } else {
            toast.error(data.message || 'حدث خطأ')
        }
    } catch (e) {
        toast.error('حدث خطأ في الاتصال')
    } finally {
        extensionRequesting.value = null
    }
}

// Poll every 20 s to detect admin cancellation of an extension
let pollHandle = null
async function pollRemaining() {
    if (ending.value || autoEnded.value || currentTotalDuration.value === Infinity) return
    try {
        const res  = await fetch(route(`${props.routePrefix}.live-streams.remaining-seconds`, props.stream.id), {
            headers: { 'Accept': 'application/json' },
        })
        const data = await res.json()
        if (data.seconds_until_end != null) {
            const diff = data.seconds_until_end - currentTotalDuration.value
            if (diff !== 0) {
                remaining.value            = Math.max(0, remaining.value + diff)
                currentTotalDuration.value = data.seconds_until_end
                if (diff < 0) {
                    toast.warning(`تم تقليص وقت البث بـ ${Math.abs(Math.round(diff / 60))} دقيقة من قِبَل الأدمن`)
                }
            }
        }
    } catch (e) { /* ignore */ }
}
// ─── Recording (proxied from LiveKitRoom) ────────────────────────────────────
const liveKitRef = shallowRef(null)

let _activeSaveDialog = null  // open Swal promise — prevents double-open
let _saveDoneResolve  = null  // resolve callback set BEFORE stopRecording() is called
const SAVE_DIALOG_WAIT_MS = 10 * 60 * 1000  // keep end flow paused up to 10 minutes

function showSaveDialog(blob) {
    if (_activeSaveDialog) return _activeSaveDialog   // already open — don't duplicate

    const p = (async () => {
        const result = await Swal.fire({
            title: '✅ تم تسجيل البث بنجاح',
            html: `<p>حجم الملف: <strong>${(blob.size / 1024 / 1024).toFixed(1)} MB</strong></p><p>كيف تريد حفظ التسجيل؟</p>`,
            showDenyButton: true,
            showCancelButton: true,
            confirmButtonText: '💾 حفظ على الجهاز',
            denyButtonText: '☁️ رفع على السيرفر',
            cancelButtonText: 'تجاهل',
            allowOutsideClick: false,
            confirmButtonColor: '#198754',
            denyButtonColor: '#0d6efd',
        })
        if (result.isConfirmed) {
            if (liveKitRef.value) {
                liveKitRef.value.downloadRecording(blob)
            } else {
                const url = URL.createObjectURL(blob)
                const a   = document.createElement('a')
                a.href    = url
                a.download = `recording-${props.stream.id}-${Date.now()}.webm`
                a.click()
                setTimeout(() => URL.revokeObjectURL(url), 10000)
            }
        } else if (result.isDenied) {
            toast.info('جارٍ رفع التسجيل على السيرفر…')
            const ok = await liveKitRef.value?.uploadRecording(blob)
            if (ok) toast.success('تم رفع التسجيل على السيرفر بنجاح')
            else toast.error('فشل رفع التسجيل على السيرفر')
        }
    })()
    _activeSaveDialog = p
    p.finally(() => {
        if (_activeSaveDialog === p) _activeSaveDialog = null
        // Signal stopAndAwaitSave() that the teacher has responded
        if (_saveDoneResolve) { _saveDoneResolve(); _saveDoneResolve = null }
    })
    return p
}

// ALWAYS show dialog when recording finishes — no conditions, no flags to check.
// This fires via the @recording-ready emit from LiveKitRoom.
function onRecordingReady(blob) {
    showSaveDialog(blob)
}

// Stop recording (if active) and wait for the teacher to respond to the save dialog.
// _saveDoneResolve is assigned synchronously inside new Promise() before stopRecording()
// is ever called, so mr.onstop firing later (as a macrotask) will always find it set.
function stopAndAwaitSave() {
    if (_activeSaveDialog) return _activeSaveDialog   // dialog already open (manual stop) — wait for it

    // Use hasActiveRecording() — a plain function that checks both _recordingActive and isRecording.value
    // This avoids the Vue ref-unwrapping ambiguity of liveKitRef.value?.isRecording?.value
    const isRec = liveKitRef.value?.hasActiveRecording?.()
    if (!isRec) return Promise.resolve()  // nothing to stop

    const p = new Promise(resolve => {
        _saveDoneResolve = resolve
        // Safety timeout: if mr.onstop never fires or dialog gets stuck, unblock after configured window
        setTimeout(() => { if (_saveDoneResolve === resolve) { _saveDoneResolve = null; resolve() } }, SAVE_DIALOG_WAIT_MS)
    })
    liveKitRef.value.stopRecording()
    return p
}

// ─── Watermark ───────────────────────────────────────────────────────────────
const watermarkStyle = computed(() => {
    if (!props.watermark?.url) return {}
    const pos  = props.watermark.position ?? 'bottom-right'
    const opac = (props.watermark.opacity ?? 20) / 100
    const sz   = (props.watermark.size   ?? 300)
    // Map 300-1000 → 5%-100% of viewport
    const pct  = Math.round(((sz - 300) / 700) * 95 + 5)
    const base = { position: 'fixed', zIndex: 9999, pointerEvents: 'none', userSelect: 'none', opacity: opac, width: pct + 'vw', height: pct + 'vh', objectFit: 'contain' }
    switch (pos) {
        case 'top-left':    return { ...base, top: 0, left: 0 }
        case 'top-right':   return { ...base, top: 0, right: 0 }
        case 'bottom-left': return { ...base, bottom: 0, left: 0 }
        case 'center':      return { ...base, top: '50%', left: '50%', transform: 'translate(-50%,-50%)' }
        default:            return { ...base, bottom: 0, right: 0 }
    }
})

// ─── Copy student join link ───────────────────────────────────────────────────
function copyStudentLink() {
    if (props.studentJoinUrl) {
        navigator.clipboard.writeText(props.studentJoinUrl)
        toast.success('تم نسخ رابط الطلاب')
    }
}

// ─── End stream ───────────────────────────────────────────────────────────────
async function confirmEnd() {
    const result = await Swal.fire({
        title: 'إنهاء البث؟',
        text: 'سيتم إنهاء البث وتسجيله كمنتهٍ.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#0d6efd',
        confirmButtonText: 'نعم، أنهِه البث',
        cancelButtonText: 'إلغاء',
    })
    if (!result.isConfirmed) return
    ending.value = true

    if (useLiveKit.value && liveKitRef.value) await stopAndAwaitSave()

    router.patch(route(`${props.routePrefix}.live-streams.update-status`, props.stream.id), { status: 'ended' }, {
        onSuccess: () => router.visit(route(`${props.routePrefix}.live-streams.show`, props.stream.id)),
        onError:   () => { toast.error('حدث خطأ'); ending.value = false },
    })
}

onMounted(() => {
    window.addEventListener('resize', handleResize)
    timerHandle.value = setInterval(() => {
        elapsed.value++
        if (remaining.value !== Infinity) {
            if (remaining.value > 0) {
                remaining.value--
                // Re-show banner when crossing into warning range
                if (remaining.value === 300) {
                    warningDismissed.value = false
                }
            }
            if (remaining.value === 0 && !ending.value && !autoEnded.value) {
                autoEnded.value = true
                remaining.value = Infinity   // freeze timer — stream stays visible while dialog is open
                autoStopRecordingAndEnd()
            }
        }
    }, 1000)
    pollHandle = setInterval(pollRemaining, 20000)
})

onBeforeUnmount(() => {
    window.removeEventListener('resize', handleResize)
    if (timerHandle.value) clearInterval(timerHandle.value)
    if (pollHandle)        clearInterval(pollHandle)
})
</script>

<template>
    <Head :title="`🔴 ${stream.title}`" />

    <div class="d-flex flex-column" style="height:100vh;background:#0f0f1a;overflow:hidden;">

        <!-- Watermark overlay -->
        <img
            v-if="watermark?.url"
            :src="watermark.url"
            :style="watermarkStyle"
            draggable="false"
            alt=""
        />

        <!-- ── TOP BAR ──────────────────────────────────────────────────────── -->
        <div class="d-flex align-items-center gap-2 px-3 py-2 flex-shrink-0"
            style="background:#12122a;border-bottom:1px solid #2a2a4a;">

            <span class="badge bg-danger px-2 py-1 d-flex align-items-center gap-1" style="font-size:13px;">
                <span class="live-dot"></span>مباشر
            </span>

            <span class="text-white font-monospace flex-shrink-0" style="font-size:14px;">{{ formatElapsed(elapsed) }}</span>

            <div class="flex-grow-1 text-center d-none d-md-block" style="min-width:0;">
                <div class="text-white fw-semibold" style="font-size:15px;">{{ stream.title }}</div>
                <div class="text-secondary" style="font-size:11px;">{{ stream.subject }}</div>
            </div>

            <div class="d-flex align-items-center gap-1 gap-sm-2 ms-auto flex-shrink-0">

                <!-- Compact warning pill: shown when banner is dismissed but time is still in warning range -->
                <div
                    v-if="remaining !== Infinity && remaining <= 300 && remaining > 0 && warningDismissed"
                    class="position-relative"
                >
                    <button
                        @click="showExtensionDropdown = !showExtensionDropdown"
                        class="btn btn-sm btn-danger d-flex align-items-center gap-1"
                        style="font-size:12px;animation:blink-warning 1.4s step-start infinite;"
                        title="تمديد البث"
                    >
                        <i class="bi bi-hourglass-split"></i>
                        <span>{{ Math.floor(remaining / 60) }}:{{ String(remaining % 60).padStart(2,'0') }}</span>
                        <i class="bi bi-chevron-down" style="font-size:10px;"></i>
                    </button>
                    <!-- Extension dropdown -->
                    <div
                        v-if="showExtensionDropdown"
                        class="position-absolute d-flex flex-column gap-1 p-2"
                        style="top:calc(100% + 4px);left:0;background:#1a0514;border:1px solid #5a1030;border-radius:6px;z-index:9999;min-width:140px;"
                    >
                        <div class="text-warning small fw-semibold mb-1">تمديد البث:</div>
                        <div class="d-flex flex-wrap gap-1">
                            <button
                                v-for="mins in EXTENSION_OPTIONS"
                                :key="mins"
                                class="btn btn-outline-info btn-sm py-0 px-2"
                                style="font-size:11px;"
                                :disabled="extensionRequesting !== null"
                                @click="requestExtension(mins); showExtensionDropdown = false"
                            >
                                <span v-if="extensionRequesting === mins" class="spinner-border spinner-border-sm" style="width:9px;height:9px;"></span>
                                <span v-else>+{{ mins }}د</span>
                            </button>
                        </div>
                        <button
                            @click="warningDismissed = false; showExtensionDropdown = false"
                            class="btn btn-sm btn-outline-secondary mt-1"
                            style="font-size:11px;"
                        >إظهار التنبيه</button>
                    </div>
                </div>

                <!-- Recording indicator removed -->

                <button @click="copyStudentLink" class="btn btn-sm btn-outline-light" title="رابط الطلاب">
                    <i class="bi bi-link-45deg"></i>
                    <span class="d-none d-lg-inline ms-1">رابط الطلاب</span>
                </button>
                <!-- Quiz panel toggle (LiveKit only) -->
                <button
                    v-if="useLiveKit"
                    @click="quizPanelOpen = !quizPanelOpen"
                    class="btn btn-sm"
                    :class="quizPanelOpen ? 'btn-warning' : 'btn-outline-warning'"
                    title="الأسئلة التفاعلية"
                >
                    <i class="bi bi-patch-question"></i>
                    <span class="d-none d-lg-inline ms-1">الأسئلة</span>
                </button>
                <button @click="confirmEnd" class="btn btn-sm btn-danger" :disabled="ending">
                    <span v-if="ending" class="spinner-border spinner-border-sm"></span>
                    <i v-else class="bi bi-stop-circle"></i>
                    <span class="d-none d-sm-inline ms-1">إنهاء البث</span>
                </button>
            </div>
        </div>

        <!-- ── COUNTDOWN WARNING + EXTENSION PANEL ────────────────────────────── -->
        <div
            v-if="remaining !== Infinity && remaining <= 300 && remaining > 0 && !warningDismissed"
            class="flex-shrink-0"
            style="background:#1a0514;border-bottom:1px solid #5a1030;"
        >
            <!-- Warning line + dismiss button -->
            <div class="d-flex align-items-center justify-content-center position-relative py-1 fw-bold" style="background:#dc3545;color:#fff;font-size:13px;animation:blink-warning 1s step-start infinite;">
                <span>⚠️ سينتهي البث تلقائياً خلال {{ Math.floor(remaining / 60) > 0 ? Math.floor(remaining / 60) + ' دقيقة و' : '' }} {{ remaining % 60 }} ثانية!</span>
                <button
                    @click="warningDismissed = true"
                    class="btn btn-link p-0 position-absolute"
                    style="left:8px;top:50%;transform:translateY(-50%);color:#fff;font-size:16px;line-height:1;text-decoration:none;"
                    title="إخفاء التنبيه"
                >×</button>
            </div>
            <!-- Extension options -->
            <div class="d-flex align-items-center justify-content-center gap-2 flex-wrap py-2 px-3">
                <span class="text-warning small fw-semibold">
                    <i class="bi bi-hourglass-split me-1"></i>تمديد البث؟
                </span>
                <button
                    v-for="mins in EXTENSION_OPTIONS"
                    :key="mins"
                    class="btn btn-outline-info btn-sm py-0 px-2"
                    style="font-size:12px;"
                    :disabled="extensionRequesting !== null"
                    @click="requestExtension(mins)"
                >
                    <span v-if="extensionRequesting === mins" class="spinner-border spinner-border-sm me-1" style="width:10px;height:10px;"></span>
                    +{{ mins }}د
                </button>
                <span v-if="routePrefix === 'admin'" class="text-muted small">(في انتظار موافقة الأدمن)</span>
            </div>
        </div>

        <!-- ── LIVEKIT ROOM + QUIZ PANEL ─────────────────────────────────────── -->
        <div v-if="useLiveKit" class="flex-grow-1 d-flex" style="overflow:hidden;">
            <LiveKitRoom
                ref="liveKitRef"
                :wsUrl="livekitWsUrl"
                :token="livekitToken"
                :isTeacher="true"
                :myName="teacherName"
                :dockChat="true"
                :streamId="stream.id"
                :uploadRecordingUrl="route(`${routePrefix}.live-streams.upload-recording`, stream.id)"
                :uploadWbMediaUrl="route(`${routePrefix}.live-streams.upload-wb-media`, stream.id)"
                @leave="confirmEnd"
                @recording-ready="onRecordingReady"
                style="flex:1;min-width:0;"
            />
            <!-- Quiz Panel: full-screen overlay on mobile, sidebar on desktop -->
            <Transition :name="isMobile ? 'quiz-slide-up' : 'quiz-slide'">
                <div v-if="quizPanelOpen"
                     :class="isMobile ? 'quiz-panel-overlay' : 'quiz-panel-sidebar'"
                >
                    <LiveStreamQuizPanel
                        :streamId="stream.id"
                        :routePrefix="routePrefix"
                        @close="quizPanelOpen = false"
                        style="height:100%;width:100%;"
                    />
                </div>
            </Transition>
        </div>

        <!-- ── JITSI IFRAME (fallback for jitsi dashboard) ──────────────────── -->
        <div v-else class="flex-grow-1 position-relative">
            <div v-if="!iframeReady"
                class="position-absolute d-flex flex-column align-items-center justify-content-center"
                style="inset:0;background:#0f0f1a;z-index:10;">
                <div class="spinner-border text-primary mb-3" style="width:3rem;height:3rem;"></div>
                <p class="text-white">جارٍ تحميل غرفة البث...</p>
                <p class="text-secondary small">يرجى السماح للمتصفح بالوصول للكاميرا والميكروفون</p>
            </div>

            <iframe
                :src="jitsiSrc"
                allow="camera *; microphone *; fullscreen *; display-capture *; autoplay *"
                style="width:100%;height:100%;border:none;"
                @load="iframeReady = true"
            ></iframe>
        </div>
    </div>
</template>

<style scoped>
.live-dot {
    display: inline-block;
    width: 8px; height: 8px;
    border-radius: 50%;
    background: #fff;
    animation: pulse 1.4s ease-in-out infinite;
}
@keyframes pulse {
    0%,100% { opacity: 1; transform: scale(1); }
    50%      { opacity: .4; transform: scale(.8); }
}

/* Desktop sidebar */
.quiz-panel-sidebar {
    flex-shrink: 0;
    width: 380px;
    height: 100%;
    border-right: 1px solid #2a2a4a;
}

/* Mobile overlay */
.quiz-panel-overlay {
    position: fixed;
    inset: 0;
    z-index: 1050;
    width: 100%;
    height: 100%;
}

/* Desktop: slide from right */
.quiz-slide-enter-active,
.quiz-slide-leave-active { transition: width 0.25s ease; overflow: hidden; }
.quiz-slide-enter-from,
.quiz-slide-leave-to    { width: 0 !important; }
.quiz-slide-enter-to,
.quiz-slide-leave-from  { width: 380px !important; }

/* Mobile: slide from bottom */
.quiz-slide-up-enter-active,
.quiz-slide-up-leave-active { transition: transform 0.3s cubic-bezier(0.32, 0.72, 0, 1); }
.quiz-slide-up-enter-from,
.quiz-slide-up-leave-to    { transform: translateY(100%); }
.quiz-slide-up-enter-to,
.quiz-slide-up-leave-from  { transform: translateY(0); }

@keyframes blink-warning {
    0%, 49% { opacity: 1; }
    50%, 100% { opacity: 0.4; }
}
</style>
