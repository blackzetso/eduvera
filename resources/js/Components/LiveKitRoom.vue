<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount, nextTick, watch } from 'vue'
import {
    Room,
    RoomEvent,
    Track,
    VideoPresets,
    createLocalTracks,
    LocalParticipant,
} from 'livekit-client'
import Swal from 'sweetalert2'
import WhiteboardPanel from './WhiteboardPanel.vue'

const props = defineProps({
    wsUrl:       { type: String, required: true },
    token:       { type: String, required: true },
    isTeacher:   { type: Boolean, default: false },
    myName:      { type: String, default: 'مشارك' },
    teacherName: { type: String, default: '' },
    dockChat:    { type: Boolean, default: false },
    streamId:    { type: Number, default: null },
    uploadRecordingUrl: { type: String, default: null },
    uploadWbMediaUrl:   { type: String, default: null },
})

const emit = defineEmits(['leave', 'recording-ready'])

// ─── State ────────────────────────────────────────────────────────────────────
const connected       = ref(false)
const connecting      = ref(true)
const errorMsg        = ref(null)
const isMicOn         = ref(false)
const isCameraOn      = ref(false)
const isScreenSharing = ref(false)
const showChat        = ref(false)
const micLockedByTeacher    = ref(false) // student: teacher locked the mic

let _recordingActive = false  // true from mr.start() until _deliverBlob() fires
const allMuted              = ref(false)  // teacher: current mute-all state
const chatLockedByTeacher   = ref(false) // student: teacher locked the chat
const allChatLocked         = ref(false)  // teacher: current chat-lock state
const cameraLockedByTeacher = ref(false) // student: teacher locked the camera
const allCameraLocked       = ref(false)  // teacher: current camera-lock state
const showParticipants = ref(false)
const chatInput       = ref('')
const messages        = ref([])
const unreadCount     = ref(0)
const activeSpeakerIds = ref(new Set())

// ─── Recording ────────────────────────────────────────────────────────────────
const isRecording      = ref(false)
const recordingType    = ref(null)   // 'local' | 'server'
const mediaRecorder    = ref(null)
const recordedChunks   = ref([])
const uploadProgress   = ref(0)      // 0-100
const isUploading      = ref(false)
const uploadError      = ref(null)
const uploadSuccess    = ref(false)

// ─── WB PDF overlay ────────────────────────────────────────────────────────────
const showWbPdf          = ref(false)
const wbPdfCurrentImg    = ref(null)    // current page dataURL (for sync)
const wbPdfCurrentFileId = ref(null)    // current page fileId
const wbPdfCurrentW      = ref(900)     // current page canvas width
const wbPdfCurrentH      = ref(600)     // current page canvas height
const wbPdfCurrent       = ref(0)
const wbPdfTotal         = ref(0)
const wbPdfPages         = ref([])      // [{ dataURL, fileId, w, h }, …] (teacher only)

// ─── WB Video overlay ──────────────────────────────────────────────────────────
const showWbVideo      = ref(false)
const wbVideoUrl       = ref('')

// ─── Whiteboard ────────────────────────────────────────────────────────────────
const showWhiteboard     = ref(false)
const whiteboardWriters  = ref(new Set())   // participant identities with draw permission
const raisedHands        = ref([])          // [{ identity, name }] — whiteboard draw requests
const whiteboardPanelRef = ref(null)
const lastWbElements     = ref([])
const isHandRaised       = ref(false)       // student's own WB-draw hand-raise state
const localIdentity      = ref(null)
let   wbUpdateTimer      = null
let   recordingRafId     = null   // requestAnimationFrame id for canvas draw loop
let   recordingCanvas    = null   // off-screen canvas used during recording

// ─── General hand-raise ───────────────────────────────────────────────────────
const generalRaisedHands = ref([])   // [{ identity, name, ts }] — visible to teacher
const myHandRaised       = ref(false)  // student's own general hand state

// ─── Reactions ────────────────────────────────────────────────────────────────
const REACTIONS          = ['👍','❤️','😄','🎉','👏','🔥','😮','👋']
const showReactionPicker = ref(false)
const activeReactions    = ref([])   // [{ id, emoji, name, x }] floating on screen

// participants map: identity → { name, videoTrack, audioTrack, isMuted, videoEl }
const participants  = reactive(new Map())
const localVideoEl  = ref(null)
const localScreenEl = ref(null)  // screen share video element (teacher PiP host)
const roomEl        = ref(null)  // root element for fullscreen
const isFullscreen  = ref(false)

let room = null

// ─── Helpers ──────────────────────────────────────────────────────────────────
const participantList     = computed(() => [...participants.values()])
const totalCount          = computed(() => participants.size + 1) // +1 for local
const isWhiteboardWriter  = computed(() =>
    props.isTeacher || whiteboardWriters.value.has(localIdentity.value)
)
const isMobileViewport    = ref(typeof window !== 'undefined' ? window.innerWidth < 992 : false)
const useDockedChat       = computed(() => props.dockChat && !isMobileViewport.value)

// Layout: teacher or first-remote is "main", rest go in strip
// Prefer the participant whose identity starts with teacherName (e.g. "super admin-BaUye7")
const mainRemote = computed(() => {
    if (props.isTeacher || participantList.value.length === 0) return null
    if (props.teacherName) {
        const tName = props.teacherName.toLowerCase()
        const found = participantList.value.find(p =>
            p.identity.toLowerCase().startsWith(tName + '-') ||
            p.identity.toLowerCase() === tName
        )
        if (found) return found
    }
    // fallback: first remote participant
    return participantList.value[0]
})
const showLocalAsMain = computed(() => props.isTeacher || !mainRemote.value)
const stripRemotes    = computed(() => props.isTeacher ? participantList.value : participantList.value.slice(1))
const mainSpeaking    = computed(() => {
    if (!room) return false
    if (showLocalAsMain.value) return activeSpeakerIds.value.has(room.localParticipant?.identity)
    return mainRemote.value ? activeSpeakerIds.value.has(mainRemote.value.identity) : false
})

function formatTime(d) {
    return d.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' })
}

function nameInitials(name = '') {
    const parts = name.trim().split(/\s+/).filter(Boolean)
    if (parts.length === 0) return '؟'
    if (parts.length === 1) return parts[0].charAt(0).toUpperCase()
    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase()
}

function nameToColor(name = '') {
    const colors = ['#e53935','#8e24aa','#1e88e5','#00897b','#43a047','#f4511e','#6d4c41','#3949ab','#00acc1','#d81b60']
    let hash = 0
    for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash)
    return colors[Math.abs(hash) % colors.length]
}

// ─── Connect ──────────────────────────────────────────────────────────────────
onMounted(async () => {
    room = new Room({
        adaptiveStream:   true,
        dynacast:         true,
        videoCaptureDefaults: { resolution: VideoPresets.h720.resolution },
    })

    isMobileViewport.value = window.innerWidth < 992
    window.addEventListener('resize', handleViewportResize)

    document.addEventListener('fullscreenchange', onFullscreenChange)

    setupListeners()

    try {
        await room.connect(props.wsUrl, props.token)
        connected.value  = true
        connecting.value = false
        localIdentity.value = room.localParticipant.identity

        // Attach already-subscribed remote tracks
        room.remoteParticipants.forEach(p => {
            addParticipant(p)   // ← must be FIRST so entry has identity/name
            p.trackPublications.forEach(pub => {
                if (pub.isSubscribed && pub.track) attachRemoteTrack(pub.track, p)
            })
        })

        // Render room UI FIRST, then enable devices in background (don't block UI)
        await nextTick()

        if (props.isTeacher) {
            room.localParticipant.enableCameraAndMicrophone()
                .then(() => {
                    isMicOn.value    = true
                    isCameraOn.value = true
                    return nextTick()
                })
                .then(() => attachLocalVideo())
                .then(() => promptScreenRecording())
                .catch(e => console.warn('[LiveKit] camera/mic error:', e))
        } else {
            room.localParticipant.setMicrophoneEnabled(true)
                .then(() => { isMicOn.value = true })
                .catch(e => console.warn('[LiveKit] mic error:', e))
        }

    } catch (e) {
        connecting.value = false
        const detail = e?.message || e?.toString() || ''
        errorMsg.value = detail
            ? `تعذّر الاتصال: ${detail}`
            : 'تعذّر الاتصال بالغرفة. تحقق من إعدادات LiveKit.'
        console.error('[LiveKit connect] wsUrl:', props.wsUrl, '\nError:', e)
    }
})

async function retryConnect() {
    errorMsg.value   = null
    connecting.value = true
    try {
        await room.connect(props.wsUrl, props.token)
        connected.value  = true
        connecting.value = false
        localIdentity.value = room.localParticipant.identity
        room.remoteParticipants.forEach(p => {
            addParticipant(p)
            p.trackPublications.forEach(pub => {
                if (pub.isSubscribed && pub.track) attachRemoteTrack(pub.track, p)
            })
        })
        await nextTick()
        if (props.isTeacher) {
            room.localParticipant.enableCameraAndMicrophone()
                .then(() => { isMicOn.value = true; isCameraOn.value = true; return nextTick() })
                .then(() => attachLocalVideo())
                .then(() => promptScreenRecording())
                .catch(e => console.warn('[LiveKit] camera/mic error:', e))
        } else {
            room.localParticipant.setMicrophoneEnabled(true)
                .then(() => { isMicOn.value = true })
                .catch(e => console.warn('[LiveKit] mic error:', e))
        }
    } catch (e) {
        connecting.value = false
        const detail = e?.message || e?.toString() || ''
        errorMsg.value = detail
            ? `تعذّر الاتصال: ${detail}`
            : 'تعذّر الاتصال بالغرفة. تحقق من إعدادات LiveKit.'
        console.error('[LiveKit retry] wsUrl:', props.wsUrl, '\nError:', e)
    }
}

onBeforeUnmount(() => {
    room?.disconnect()
    document.removeEventListener('fullscreenchange', onFullscreenChange)
    window.removeEventListener('resize', handleViewportResize)
    if (wbUpdateTimer) clearTimeout(wbUpdateTimer)
})

function handleViewportResize() {
    isMobileViewport.value = window.innerWidth < 992
}

watch(useDockedChat, (docked) => {
    if (docked) showChat.value = false
})

function onFullscreenChange() {
    isFullscreen.value = !!document.fullscreenElement
}

function toggleFullscreen() {
    if (!document.fullscreenElement) {
        roomEl.value?.requestFullscreen()
    } else {
        document.exitFullscreen()
    }
}

// ─── Room listeners ───────────────────────────────────────────────────────────
function setupListeners() {
    room
        .on(RoomEvent.ParticipantConnected, p => {
            addParticipant(p)
            addSystemMessage(`${p.name || p.identity} انضم للغرفة`)
            // send current lock states to new joiners
            if (props.isTeacher) {
                const locks = [
                    allMuted.value        && { cmd: 'mute_all',     delay: 1000 },
                    allChatLocked.value   && { cmd: 'lock_chat',    delay: 1100 },
                    allCameraLocked.value && { cmd: 'lock_camera',  delay: 1200 },
                ].filter(Boolean)
                locks.forEach(({ cmd, delay }) => setTimeout(() => {
                    const payload = JSON.stringify({ type: 'cmd', cmd })
                    room.localParticipant.publishData(new TextEncoder().encode(payload), { reliable: true, destinationIdentities: [p.identity] })
                }, delay))
                // Sync whiteboard state to new joiner
                if (showWhiteboard.value) {
                    setTimeout(() => {
                        const wbPayload = JSON.stringify({ type: 'whiteboard_toggle', visible: true })
                        room.localParticipant.publishData(new TextEncoder().encode(wbPayload), { reliable: true, destinationIdentities: [p.identity] })
                        if (lastWbElements.value.length > 0) {
                            const elemPayload = JSON.stringify({ type: 'whiteboard_update', elements: lastWbElements.value })
                            room.localParticipant.publishData(new TextEncoder().encode(elemPayload), { reliable: true, destinationIdentities: [p.identity] })
                        }
                    }, 1500)
                }
                // Sync PDF to new joiner
                if (showWbPdf.value && wbPdfCurrentImg.value) {
                    setTimeout(() => {
                        const p2 = JSON.stringify({
                            type:   'wb_pdf_page',
                            page:   wbPdfCurrent.value,
                            total:  wbPdfTotal.value,
                            dataURL: wbPdfCurrentImg.value,
                            fileId: wbPdfCurrentFileId.value,
                            w:      wbPdfCurrentW.value,
                            h:      wbPdfCurrentH.value,
                        })
                        room.localParticipant.publishData(new TextEncoder().encode(p2), { reliable: true, destinationIdentities: [p.identity] })
                    }, 2000)
                }
                // Sync video overlay to new joiner
                if (showWbVideo.value && wbVideoUrl.value) {
                    setTimeout(() => {
                        const p2 = JSON.stringify({ type: 'wb_video_open', url: wbVideoUrl.value })
                        room.localParticipant.publishData(new TextEncoder().encode(p2), { reliable: true, destinationIdentities: [p.identity] })
                    }, 2100)
                }
            }
        })
        .on(RoomEvent.ParticipantDisconnected, p => {
            removeParticipant(p)
            addSystemMessage(`${p.name || p.identity} غادر الغرفة`)
        })
        .on(RoomEvent.TrackSubscribed, (track, _pub, participant) => {
            addParticipant(participant)  // ensure entry exists before attaching track
            attachRemoteTrack(track, participant)
        })
        .on(RoomEvent.TrackUnsubscribed, (track, _pub, participant) => {
            detachRemoteTrack(track, participant)
        })
        .on(RoomEvent.ActiveSpeakersChanged, speakers => {
            activeSpeakerIds.value = new Set(speakers.map(s => s.identity))
        })
        .on(RoomEvent.DataReceived, (data, participant) => {
            handleDataMessage(data, participant)
        })
        .on(RoomEvent.TrackMuted, (_pub, participant) => {
            updateParticipantMute(participant)
        })
        .on(RoomEvent.TrackUnmuted, (_pub, participant) => {
            updateParticipantMute(participant)
        })
        .on(RoomEvent.LocalTrackPublished, () => {
            nextTick(() => {
                attachLocalVideo()
                attachLocalScreen()
            })
        })
        .on(RoomEvent.Disconnected, () => {
            connected.value = false
        })
}

// ─── Participant management ───────────────────────────────────────────────────
function addParticipant(p) {
    if (!participants.has(p.identity)) {
        participants.set(p.identity, {
            identity:    p.identity,
            name:        p.name || p.identity,
            videoTrack:  null,
            screenTrack: null,
            audioTrack:  null,
            videoEl:     null,
            screenVideoEl: null,
            isMuted:     false,
            isCameraOff: false,
        })
    }
}

function removeParticipant(p) {
    participants.delete(p.identity)
}

function updateParticipantMute(p) {
    const entry = participants.get(p.identity)
    if (!entry) return
    const micPub = [...p.trackPublications.values()].find(t => t.kind === Track.Kind.Audio)
    entry.isMuted = micPub?.isMuted ?? false
    const camPub = [...p.trackPublications.values()].find(t => t.source === Track.Source.Camera)
    entry.isCameraOff = !camPub || camPub.isMuted
}

// ─── Track attachment ─────────────────────────────────────────────────────────
function attachLocalVideo() {
    if (!localVideoEl.value) return
    const camPub = room.localParticipant.getTrackPublication(Track.Source.Camera)
    if (camPub?.track) {
        camPub.track.attach(localVideoEl.value)
    }
}

function attachLocalScreen() {
    if (!localScreenEl.value) return
    const screenPub = room.localParticipant.getTrackPublication(Track.Source.ScreenShare)
    if (screenPub?.track) {
        screenPub.track.attach(localScreenEl.value)
    }
}

function attachRemoteTrack(track, participant) {
    const entry = participants.get(participant.identity) || {
        identity: participant.identity,
        name:     participant.name || participant.identity,
        videoTrack: null, screenTrack: null, audioTrack: null,
        videoEl: null, screenVideoEl: null,
        isMuted: false, isCameraOff: false,
    }
    if (track.kind === Track.Kind.Video) {
        if (track.source === Track.Source.ScreenShare) {
            entry.screenTrack = track
            nextTick(() => {
                if (entry.screenVideoEl) track.attach(entry.screenVideoEl)
            })
        } else {
            entry.videoTrack = track
            nextTick(() => {
                if (entry.videoEl) track.attach(entry.videoEl)
            })
        }
    } else if (track.kind === Track.Kind.Audio) {
        entry.audioTrack = track
        track.attach()
    }
    participants.set(participant.identity, entry)
}

function detachRemoteTrack(track, participant) {
    const entry = participants.get(participant.identity)
    if (!entry) return
    track.detach()
    if (track.kind === Track.Kind.Video) {
        if (track.source === Track.Source.ScreenShare) entry.screenTrack = null
        else entry.videoTrack = null
    }
    if (track.kind === Track.Kind.Audio) entry.audioTrack = null
}

function setVideoEl(identity, el) {
    const entry = participants.get(identity)
    if (!entry) return
    entry.videoEl = el
    if (entry.videoTrack && el) entry.videoTrack.attach(el)
}

function setScreenEl(identity, el) {
    const entry = participants.get(identity)
    if (!entry) return
    entry.screenVideoEl = el
    if (entry.screenTrack && el) entry.screenTrack.attach(el)
}

// ─── Controls ─────────────────────────────────────────────────────────────────
async function toggleMic() {
    if (!props.isTeacher && micLockedByTeacher.value) return  // blocked by teacher
    await room.localParticipant.setMicrophoneEnabled(!isMicOn.value)
    isMicOn.value = !isMicOn.value
}

// Re-enforce teacher lock: if student somehow unmutes while locked, re-mute immediately
watch(isMicOn, (newVal) => {
    if (newVal && !props.isTeacher && micLockedByTeacher.value) {
        room.localParticipant.setMicrophoneEnabled(false)
        isMicOn.value = false
    }
})

// Re-attach local video whenever the DOM element changes (e.g. when whiteboard opens/closes
// and the <video> ref is recreated in a different template branch)
watch(localVideoEl, (el) => {
    if (el && isCameraOn.value) {
        nextTick(() => attachLocalVideo())
    }
})

async function toggleCamera() {
    if (!props.isTeacher && cameraLockedByTeacher.value) return  // blocked by teacher
    await room.localParticipant.setCameraEnabled(!isCameraOn.value)
    isCameraOn.value = !isCameraOn.value
    await nextTick()
    attachLocalVideo()
}

// Re-enforce camera lock
watch(isCameraOn, (newVal) => {
    if (newVal && !props.isTeacher && cameraLockedByTeacher.value) {
        room.localParticipant.setCameraEnabled(false)
        isCameraOn.value = false
        nextTick(() => attachLocalVideo())
    }
})

async function toggleScreenShare() {
    try {
        await room.localParticipant.setScreenShareEnabled(!isScreenSharing.value)
        isScreenSharing.value = !isScreenSharing.value
        await nextTick()
        attachLocalVideo()
        attachLocalScreen()
    } catch {
        isScreenSharing.value = false
    }
}

// Teacher mutes/unmutes all students via data channel
function muteAll() {
    if (!props.isTeacher) return
    allMuted.value = !allMuted.value
    const cmd = allMuted.value ? 'mute_all' : 'unmute_all'
    const payload = JSON.stringify({ type: 'cmd', cmd })
    room.localParticipant.publishData(new TextEncoder().encode(payload), { reliable: true })
    addSystemMessage(allMuted.value ? 'قام المدرس بكتم الجميع' : 'قام المدرس بفتح الكتم عن الجميع')
}

function lockAllChat() {
    if (!props.isTeacher) return
    allChatLocked.value = !allChatLocked.value
    const cmd = allChatLocked.value ? 'lock_chat' : 'unlock_chat'
    const payload = JSON.stringify({ type: 'cmd', cmd })
    room.localParticipant.publishData(new TextEncoder().encode(payload), { reliable: true })
    addSystemMessage(allChatLocked.value ? 'قام المدرس بقفل الشات' : 'قام المدرس بفتح الشات')
}

function lockAllCamera() {
    if (!props.isTeacher) return
    allCameraLocked.value = !allCameraLocked.value
    const cmd = allCameraLocked.value ? 'lock_camera' : 'unlock_camera'
    const payload = JSON.stringify({ type: 'cmd', cmd })
    room.localParticipant.publishData(new TextEncoder().encode(payload), { reliable: true })
    addSystemMessage(allCameraLocked.value ? 'قام المدرس بقفل الكاميرا للجميع' : 'قام المدرس بفتح الكاميرا للجميع')
}

// ─── Data channel (chat + commands) ──────────────────────────────────────────
async function handleDataMessage(data, sender) {
    try {
        const parsed = JSON.parse(new TextDecoder().decode(data))

        if (parsed.type === 'chat') {
            messages.value.push({
                id:      Date.now(),
                from:    sender?.name || sender?.identity || 'مجهول',
                text:    parsed.text,
                time:    new Date(),
                isLocal: false,
            })
            if (!showChat.value && !useDockedChat.value) unreadCount.value++
        } else if (parsed.type === 'cmd' && parsed.cmd === 'mute_all') {
            if (!props.isTeacher) {
                micLockedByTeacher.value = true
                room.localParticipant.setMicrophoneEnabled(false)
                isMicOn.value = false
                addSystemMessage('تم كتم الميكروفون من قِبل المدرس')
            }
        } else if (parsed.type === 'cmd' && parsed.cmd === 'unmute_all') {
            if (!props.isTeacher) {
                micLockedByTeacher.value = false
                addSystemMessage('يمكنك الآن تفعيل الميكروفون')
            }
        } else if (parsed.type === 'cmd' && parsed.cmd === 'lock_chat') {
            if (!props.isTeacher) {
                chatLockedByTeacher.value = true
                addSystemMessage('تم قفل الشات من قِبل المدرس')
            }
        } else if (parsed.type === 'cmd' && parsed.cmd === 'unlock_chat') {
            if (!props.isTeacher) {
                chatLockedByTeacher.value = false
                addSystemMessage('تم فتح الشات')
            }
        } else if (parsed.type === 'cmd' && parsed.cmd === 'lock_camera') {
            if (!props.isTeacher) {
                cameraLockedByTeacher.value = true
                room.localParticipant.setCameraEnabled(false)
                isCameraOn.value = false
                nextTick(() => attachLocalVideo())
                addSystemMessage('تم قفل الكاميرا من قِبل المدرس')
            }
        } else if (parsed.type === 'cmd' && parsed.cmd === 'unlock_camera') {
            if (!props.isTeacher) {
                cameraLockedByTeacher.value = false
                addSystemMessage('يمكنك الآن تفعيل الكاميرا')
            }
        } else if (parsed.type === 'whiteboard_toggle') {
            showWhiteboard.value = parsed.visible
            if (parsed.visible && lastWbElements.value.length > 0) {
                nextTick(() => whiteboardPanelRef.value?.updateScene(lastWbElements.value))
            }
            addSystemMessage(parsed.visible ? '🖊️ فُتحت اللوحة البيضاء' : '📕 أُغلقت اللوحة البيضاء')
        } else if (parsed.type === 'whiteboard_update') {
            lastWbElements.value = parsed.elements
            nextTick(() => whiteboardPanelRef.value?.updateScene(parsed.elements))
        } else if (parsed.type === 'whiteboard_clear') {
            lastWbElements.value = []
            whiteboardPanelRef.value?.clearBoard()
            addSystemMessage('🧽 تم مسح اللوحة البيضاء')
        } else if (parsed.type === 'whiteboard_permission') {
            const newSet = new Set(whiteboardWriters.value)
            if (parsed.granted) { newSet.add(parsed.identity) } else { newSet.delete(parsed.identity) }
            whiteboardWriters.value = newSet
            if (!props.isTeacher && parsed.identity === localIdentity.value) {
                addSystemMessage(parsed.granted ? '✏️ منحك المدرس صلاحية الرسم' : '🚫 سحب المدرس صلاحية الرسم منك')
                isHandRaised.value = false
                nextTick(() => whiteboardPanelRef.value?.setReadOnly(!parsed.granted))
            }
        } else if (parsed.type === 'whiteboard_hand_raise') {
            if (props.isTeacher && !raisedHands.value.find(h => h.identity === parsed.identity)) {
                raisedHands.value = [...raisedHands.value, { identity: parsed.identity, name: parsed.name }]
            }
        } else if (parsed.type === 'whiteboard_hand_lower') {
            raisedHands.value = raisedHands.value.filter(h => h.identity !== parsed.identity)

        // ─── General hand-raise ───────────────────────────────────────────
        } else if (parsed.type === 'hand_raise') {
            if (!generalRaisedHands.value.find(h => h.identity === parsed.identity)) {
                generalRaisedHands.value = [...generalRaisedHands.value, { identity: parsed.identity, name: parsed.name, ts: Date.now() }]
            }
            if (!props.isTeacher) {
                addSystemMessage(`🙋 ${parsed.name} رفع يده`)
            }
        } else if (parsed.type === 'hand_lower') {
            generalRaisedHands.value = generalRaisedHands.value.filter(h => h.identity !== parsed.identity)

        // ─── Reactions ───────────────────────────────────────────────────
        } else if (parsed.type === 'reaction') {
            spawnReaction(parsed.emoji, parsed.name)

        // ─── WB PDF ─────────────────────────────────
        } else if (parsed.type === 'wb_pdf_page') {
            showWbPdf.value          = true
            wbPdfCurrent.value       = parsed.page
            wbPdfTotal.value         = parsed.total
            wbPdfCurrentImg.value    = parsed.dataURL
            wbPdfCurrentFileId.value = parsed.fileId ?? null
            wbPdfCurrentW.value      = parsed.w ?? 900
            wbPdfCurrentH.value      = parsed.h ?? 600
            nextTick(() => whiteboardPanelRef.value?.showPdf(
                parsed.dataURL, parsed.fileId, parsed.page, parsed.total,
                parsed.w ?? 900, parsed.h ?? 600
            ))
        } else if (parsed.type === 'wb_pdf_close') {
            showWbPdf.value          = false
            wbPdfCurrentImg.value    = null
            wbPdfCurrentFileId.value = null
            whiteboardPanelRef.value?.closePdf()

        // ─── WB Video ────────────────────────────────
        } else if (parsed.type === 'wb_video_open') {
            wbVideoUrl.value  = parsed.url
            showWbVideo.value = true
            nextTick(() => whiteboardPanelRef.value?.showVideo(parsed.url))
        } else if (parsed.type === 'wb_video_close') {
            showWbVideo.value = false
            wbVideoUrl.value  = ''
            whiteboardPanelRef.value?.closeVideo()
        } else if (parsed.type === 'wb_video_sync') {
            if (!props.isTeacher) {
                whiteboardPanelRef.value?.syncVideo(parsed.action, parsed.time)
            }
        }
    } catch {}
}

function sendMessage() {
    const text = chatInput.value.trim()
    if (!text) return
    if (!props.isTeacher && chatLockedByTeacher.value) return  // blocked by teacher
    const payload = JSON.stringify({ type: 'chat', text })
    room.localParticipant.publishData(new TextEncoder().encode(payload), { reliable: true })
    messages.value.push({
        id:      Date.now(),
        from:    props.myName,
        text,
        time:    new Date(),
        isLocal: true,
    })
    chatInput.value = ''
}

function addSystemMessage(text) {
    messages.value.push({ id: Date.now(), system: true, text, time: new Date() })
}

function openChat() {
    showChat.value    = true
    unreadCount.value = 0
}

// ─── Recording ────────────────────────────────────────────────────────────────
async function startRecording() {
    if (isRecording.value) return

    // Capture the full browser tab exactly as the teacher sees it (video + tab audio)
    let displayStream
    try {
        displayStream = await navigator.mediaDevices.getDisplayMedia({
            video: {
                displaySurface: 'browser',
                frameRate:      { ideal: 30, max: 60 },
                width:          { ideal: 1920 },
                height:         { ideal: 1080 },
            },
            audio:               false,  // we capture audio directly from LiveKit tracks
            preferCurrentTab:    true,
            selfBrowserSurface:  'include',
            surfaceSwitching:    'exclude',
            monitorTypeSurfaces: 'exclude',
        })
    } catch (err) {
        // User cancelled picker — silently ignore
        if (err.name === 'NotAllowedError' || err.name === 'AbortError') return
        // Retry without advanced constraints (Firefox / older Chrome)
        try {
            displayStream = await navigator.mediaDevices.getDisplayMedia({ video: true, audio: false })
        } catch (e2) {
            addSystemMessage('⚠️ تعذّر بدء التسجيل: ' + e2.message)
            return
        }
    }

    const videoTrack  = displayStream.getVideoTracks()[0]

    // Mix audio directly from LiveKit tracks (reliable — no need for tab-audio permission)
    const recAudioCtx = new (window.AudioContext || window.webkitAudioContext)()
    const audioDest   = recAudioCtx.createMediaStreamDestination()

    // Remote participants — their audio is already decoded WebRTC tracks
    for (const [, participant] of room.remoteParticipants) {
        for (const [, pub] of participant.trackPublications) {
            if (pub.kind === Track.Kind.Audio && pub.track?.mediaStreamTrack) {
                try {
                    const src = recAudioCtx.createMediaStreamSource(
                        new MediaStream([pub.track.mediaStreamTrack])
                    )
                    src.connect(audioDest)
                } catch (_) {}
            }
        }
    }

    // Add local microphone on top
    const micPub = room.localParticipant.getTrackPublication(Track.Source.Microphone)
    if (micPub?.track?.mediaStreamTrack) {
        const micSrc = recAudioCtx.createMediaStreamSource(
            new MediaStream([micPub.track.mediaStreamTrack])
        )
        micSrc.connect(audioDest)
    }

    const combinedStream = new MediaStream([videoTrack])
    const mixedAudioTrack = audioDest.stream.getAudioTracks()[0]
    if (mixedAudioTrack) combinedStream.addTrack(mixedAudioTrack)

    // Pick best codec
    const mimeType = [
        'video/webm;codecs=vp9,opus',
        'video/webm;codecs=vp8,opus',
        'video/webm',
    ].find(m => MediaRecorder.isTypeSupported(m)) || ''

    recordedChunks.value = []
    uploadError.value    = null
    uploadSuccess.value  = false

    const mr = new MediaRecorder(combinedStream, mimeType
        ? { mimeType, videoBitsPerSecond: 8_000_000, audioBitsPerSecond: 128_000 }
        : {})

    mr.ondataavailable = (e) => {
        if (e.data && e.data.size > 0) recordedChunks.value.push(e.data)
    }
    mr.onstop = () => {
        combinedStream.getTracks().forEach(t => t.stop())
        displayStream.getTracks().forEach(t => t.stop())
        recAudioCtx.close().catch(() => {})
        recordingCanvas = null
        const blob = new Blob(recordedChunks.value, { type: mr.mimeType || 'video/webm' })
        _deliverBlob(blob)
    }

    // Handle teacher clicking browser's native "Stop sharing" button
    videoTrack.onended = () => {
        if (isRecording.value) stopRecording()
    }

    isRecording.value   = true
    recordingType.value = null
    _recordingActive    = true
    mr.start(500)
    mediaRecorder.value = mr
    addSystemMessage('🔴 بدأ تسجيل الحصة تلقائياً')
}

function stopRecording() {
    if (!isRecording.value || !mediaRecorder.value) return
    isRecording.value = false
    if (recordingRafId) {
        cancelAnimationFrame(recordingRafId)
        recordingRafId = null
    }
    // tracks and audioCtx are cleaned up inside mr.onstop
    mediaRecorder.value.stop()
    addSystemMessage('⬛ تم إيقاف التسجيل')
}

// ─── Prompt teacher to choose recording mode (needs user gesture for getDisplayMedia) ──
async function promptScreenRecording() {
    const { isConfirmed } = await Swal.fire({
        title: '🔴 تسجيل البث',
        html: '<p style="direction:rtl;font-size:0.95rem">سيتم تسجيل شاشتك الكاملة مع الصوت.<br>اختر <strong>هذه التبويبة</strong> في نافذة المتصفح لتسجيل كل شيء.</p>',
        confirmButtonText: '▶ ابدأ التسجيل الكامل',
        cancelButtonText: 'كاميرا فقط',
        showCancelButton: true,
        allowOutsideClick: false,
        confirmButtonColor: '#dc3545',
    })
    if (isConfirmed) {
        await startRecording()       // full tab — getDisplayMedia called inside user-gesture callback ✓
    } else {
        await startCamMicRecording() // fallback: camera + mic only
    }
}

// ─── Cam+Mic recording (auto-starts, no screen-share permission needed) ────────
async function startCamMicRecording() {
    if (isRecording.value) return

    const cameraPub = room.localParticipant.getTrackPublication(Track.Source.Camera)
    const micPub    = room.localParticipant.getTrackPublication(Track.Source.Microphone)

    // Build mixed audio: teacher mic + all remote participants
    const recAudioCtx = new (window.AudioContext || window.webkitAudioContext)()
    const audioDest   = recAudioCtx.createMediaStreamDestination()

    for (const [, participant] of room.remoteParticipants) {
        for (const [, pub] of participant.trackPublications) {
            if (pub.kind === Track.Kind.Audio && pub.track?.mediaStreamTrack) {
                try {
                    recAudioCtx.createMediaStreamSource(
                        new MediaStream([pub.track.mediaStreamTrack])
                    ).connect(audioDest)
                } catch (_) {}
            }
        }
    }
    if (micPub?.track?.mediaStreamTrack) {
        try {
            recAudioCtx.createMediaStreamSource(
                new MediaStream([micPub.track.mediaStreamTrack])
            ).connect(audioDest)
        } catch (_) {}
    }

    const recordStream = new MediaStream()
    const videoTrack   = cameraPub?.track?.mediaStreamTrack
    if (videoTrack) recordStream.addTrack(videoTrack)
    const mixedAudio = audioDest.stream.getAudioTracks()[0]
    if (mixedAudio) recordStream.addTrack(mixedAudio)

    if (recordStream.getTracks().length === 0) {
        recAudioCtx.close().catch(() => {})
        addSystemMessage('⚠️ لا توجد كاميرا أو ميكروفون للتسجيل')
        return
    }

    recordedChunks.value = []
    uploadError.value    = null
    uploadSuccess.value  = false

    const mimeType = [
        'video/webm;codecs=vp9,opus',
        'video/webm;codecs=vp8,opus',
        'video/webm',
    ].find(m => MediaRecorder.isTypeSupported(m)) || ''

    const mr = new MediaRecorder(recordStream,
        mimeType ? { mimeType, videoBitsPerSecond: 4_000_000, audioBitsPerSecond: 128_000 } : {})

    mr.ondataavailable = (e) => {
        if (e.data && e.data.size > 0) recordedChunks.value.push(e.data)
    }
    mr.onstop = () => {
        recAudioCtx.close().catch(() => {})
        const blob = new Blob(recordedChunks.value, { type: mr.mimeType || 'video/webm' })
        _deliverBlob(blob)
    }

    isRecording.value   = true
    recordingType.value = 'cam'
    _recordingActive    = true
    mr.start(500)
    mediaRecorder.value = mr
    addSystemMessage('🔴 بدأ تسجيل الحصة تلقائياً')
}

// Deliver blob — called from both mr.onstop handlers
function _deliverBlob(blob) {
    _recordingActive = false
    emit('recording-ready', blob)
}

function downloadRecording(blob) {
    const url = URL.createObjectURL(blob)
    const a   = document.createElement('a')
    a.href    = url
    a.download = `recording-${props.streamId || Date.now()}.webm`
    a.click()
    setTimeout(() => URL.revokeObjectURL(url), 10000)
}

async function uploadRecording(blob) {
    if (!props.uploadRecordingUrl) {
        alert('لا يمكن رفع التسجيل: رابط الرفع غير محدد.')
        return false
    }
    isUploading.value   = true
    uploadProgress.value = 0
    uploadError.value   = null

    const formData = new FormData()
    formData.append('recording', blob, `recording-${props.streamId}.webm`)

    try {
        await new Promise((resolve, reject) => {
            const xsrfToken = decodeURIComponent(
                (document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/) || [])[1] || ''
            )
            const xhr = new XMLHttpRequest()
            xhr.open('POST', props.uploadRecordingUrl)
            xhr.setRequestHeader('X-XSRF-TOKEN', xsrfToken)
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest')
            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) uploadProgress.value = Math.round((e.loaded / e.total) * 100)
            }
            xhr.onload = () => {
                if (xhr.status >= 200 && xhr.status < 300) resolve(JSON.parse(xhr.responseText))
                else reject(new Error(xhr.responseText || `HTTP ${xhr.status}`))
            }
            xhr.onerror  = () => reject(new Error('خطأ في الشبكة'))
            xhr.onabort  = () => reject(new Error('تم إلغاء الرفع'))
            xhr.send(formData)
        })
        uploadSuccess.value  = true
        uploadProgress.value = 100
        addSystemMessage('✅ تم رفع التسجيل على السيرفر بنجاح')
        return true
    } catch (err) {
        uploadError.value = err.message || 'فشل رفع التسجيل'
        addSystemMessage('❌ فشل رفع التسجيل: ' + uploadError.value)
        return false
    } finally {
        isUploading.value = false
    }
}

// ─── WB PDF ───────────────────────────────────────────────────────────────────
async function handleWbPdfFile(file) {
    if (!file) return
    whiteboardPanelRef.value?.setLoading(true)
    try {
        const pdfjsLib = await import('pdfjs-dist')
        pdfjsLib.GlobalWorkerOptions.workerSrc = new URL(
            'pdfjs-dist/build/pdf.worker.min.mjs', import.meta.url
        ).href
        const objectUrl = URL.createObjectURL(file)
        const pdf = await pdfjsLib.getDocument(objectUrl).promise
        const pages = []
        for (let i = 1; i <= pdf.numPages; i++) {
            const page   = await pdf.getPage(i)
            const scale  = 900 / page.getViewport({ scale: 1 }).width
            const vp     = page.getViewport({ scale })
            const canvas = document.createElement('canvas')
            canvas.width  = vp.width
            canvas.height = vp.height
            await page.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise
            pages.push({
                dataURL: canvas.toDataURL('image/jpeg', 0.75),
                fileId:  crypto.randomUUID(),
                w:       canvas.width,
                h:       canvas.height,
            })
        }
        URL.revokeObjectURL(objectUrl)
        wbPdfPages.value         = pages
        wbPdfTotal.value         = pdf.numPages
        wbPdfCurrent.value       = 0
        showWbPdf.value          = true
        wbPdfCurrentImg.value    = pages[0].dataURL
        wbPdfCurrentFileId.value = pages[0].fileId
        wbPdfCurrentW.value      = pages[0].w
        wbPdfCurrentH.value      = pages[0].h
        whiteboardPanelRef.value?.showPdf(pages[0].dataURL, pages[0].fileId, 0, pdf.numPages, pages[0].w, pages[0].h)
        broadcastWbPdfPage(0)
        addSystemMessage(`📄 تم فتح PDF — ${pdf.numPages} صفحة`)
    } catch (err) {
        addSystemMessage('⚠️ خطأ في قراءة PDF: ' + err.message)
    } finally {
        whiteboardPanelRef.value?.setLoading(false)
    }
}

function broadcastWbPdfPage(n) {
    const pg = wbPdfPages.value[n]
    wbPdfCurrent.value       = n
    wbPdfCurrentImg.value    = pg.dataURL
    wbPdfCurrentFileId.value = pg.fileId
    wbPdfCurrentW.value      = pg.w
    wbPdfCurrentH.value      = pg.h
    whiteboardPanelRef.value?.showPdf(pg.dataURL, pg.fileId, n, wbPdfTotal.value, pg.w, pg.h)
    const payload = JSON.stringify({
        type:   'wb_pdf_page',
        page:   n,
        total:  wbPdfTotal.value,
        dataURL: pg.dataURL,
        fileId: pg.fileId,
        w:      pg.w,
        h:      pg.h,
    })
    room.localParticipant.publishData(new TextEncoder().encode(payload), { reliable: true })
}

function wbPdfPrev() {
    if (wbPdfCurrent.value > 0) { wbPdfCurrent.value--; broadcastWbPdfPage(wbPdfCurrent.value) }
}
function wbPdfNext() {
    if (wbPdfCurrent.value < wbPdfTotal.value - 1) { wbPdfCurrent.value++; broadcastWbPdfPage(wbPdfCurrent.value) }
}
function closeWbPdf() {
    showWbPdf.value          = false
    wbPdfPages.value         = []
    wbPdfCurrentImg.value    = null
    wbPdfCurrentFileId.value = null
    whiteboardPanelRef.value?.closePdf()
    room.localParticipant.publishData(
        new TextEncoder().encode(JSON.stringify({ type: 'wb_pdf_close' })), { reliable: true }
    )
    addSystemMessage('📄 تم إغلاق PDF')
}

// ─── WB Video ─────────────────────────────────────────────────────────────────
async function handleWbVideoFile(file) {
    if (!file) return
    if (!props.uploadWbMediaUrl) {
        addSystemMessage('⚠️ رابط رفع الوسائط غير محدد')
        return
    }
    addSystemMessage('☁️ جارٍ رفع الفيديو…')
    const formData = new FormData()
    formData.append('file', file)
    try {
        const url = await new Promise((resolve, reject) => {
            const xsrfToken = decodeURIComponent(
                (document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/) || [])[1] || ''
            )
            const xhr = new XMLHttpRequest()
            xhr.open('POST', props.uploadWbMediaUrl)
            xhr.setRequestHeader('X-XSRF-TOKEN', xsrfToken)
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest')
            xhr.onload = () => {
                if (xhr.status >= 200 && xhr.status < 300) resolve(JSON.parse(xhr.responseText).url)
                else reject(new Error(`HTTP ${xhr.status}`))
            }
            xhr.onerror = () => reject(new Error('خطأ في الشبكة'))
            xhr.send(formData)
        })
        openWbVideo(url)
    } catch (err) {
        addSystemMessage('❌ فشل رفع الفيديو: ' + err.message)
    }
}

function openWbVideo(url) {
    wbVideoUrl.value  = url
    showWbVideo.value = true
    whiteboardPanelRef.value?.showVideo(url)
    const payload = JSON.stringify({ type: 'wb_video_open', url })
    room.localParticipant.publishData(new TextEncoder().encode(payload), { reliable: true })
    addSystemMessage('🎬 تم فتح الفيديو')
}

function closeWbVideo() {
    showWbVideo.value = false
    wbVideoUrl.value  = ''
    whiteboardPanelRef.value?.closeVideo()
    room.localParticipant.publishData(
        new TextEncoder().encode(JSON.stringify({ type: 'wb_video_close' })), { reliable: true }
    )
    addSystemMessage('🎬 تم إغلاق الفيديو')
}

function onWbVideoPlay(time) {
    if (!props.isTeacher) return
    const payload = JSON.stringify({ type: 'wb_video_sync', action: 'play', time })
    room.localParticipant.publishData(new TextEncoder().encode(payload), { reliable: true })
}
function onWbVideoPause(time) {
    if (!props.isTeacher) return
    const payload = JSON.stringify({ type: 'wb_video_sync', action: 'pause', time })
    room.localParticipant.publishData(new TextEncoder().encode(payload), { reliable: true })
}
function onWbVideoSeeked(time) {
    if (!props.isTeacher) return
    const payload = JSON.stringify({ type: 'wb_video_sync', action: 'seek', time })
    room.localParticipant.publishData(new TextEncoder().encode(payload), { reliable: true })
}

// ─── Whiteboard ───────────────────────────────────────────────────────────────
function toggleWhiteboard() {
    if (!props.isTeacher) return
    showWhiteboard.value = !showWhiteboard.value
    const payload = JSON.stringify({ type: 'whiteboard_toggle', visible: showWhiteboard.value })
    room.localParticipant.publishData(new TextEncoder().encode(payload), { reliable: true })
    if (showWhiteboard.value) {
        addSystemMessage('🖊️ فتحت اللوحة البيضاء للجميع')
        if (lastWbElements.value.length > 0) {
            setTimeout(() => {
                const ep = JSON.stringify({ type: 'whiteboard_update', elements: lastWbElements.value })
                room.localParticipant.publishData(new TextEncoder().encode(ep), { reliable: true })
            }, 800)
        }
    } else {
        addSystemMessage('📕 أغلقت اللوحة البيضاء')
    }
}

function clearWhiteboard() {
    if (!props.isTeacher) return
    lastWbElements.value = []
    whiteboardPanelRef.value?.clearBoard()
    const payload = JSON.stringify({ type: 'whiteboard_clear' })
    room.localParticipant.publishData(new TextEncoder().encode(payload), { reliable: true })
    addSystemMessage('🧽 مسحت اللوحة البيضاء')
}

function grantDraw(identity, name) {
    if (!props.isTeacher) return
    const newSet = new Set(whiteboardWriters.value)
    newSet.add(identity)
    whiteboardWriters.value = newSet
    raisedHands.value = raisedHands.value.filter(h => h.identity !== identity)
    const payload = JSON.stringify({ type: 'whiteboard_permission', identity, name, granted: true })
    room.localParticipant.publishData(new TextEncoder().encode(payload), { reliable: true })
    addSystemMessage(`✏️ منحت ${name} صلاحية الرسم`)
}

function revokeDraw(identity) {
    if (!props.isTeacher) return
    const newSet = new Set(whiteboardWriters.value)
    newSet.delete(identity)
    whiteboardWriters.value = newSet
    const p = participantList.value.find(x => x.identity === identity)
    const name = p?.name || identity
    const payload = JSON.stringify({ type: 'whiteboard_permission', identity, name, granted: false })
    room.localParticipant.publishData(new TextEncoder().encode(payload), { reliable: true })
    addSystemMessage(`🚫 سحبت صلاحية الرسم من ${name}`)
}

function raiseHand() {
    if (props.isTeacher || isWhiteboardWriter.value) return
    isHandRaised.value = true
    const payload = JSON.stringify({ type: 'whiteboard_hand_raise', identity: room.localParticipant.identity, name: props.myName })
    room.localParticipant.publishData(new TextEncoder().encode(payload), { reliable: true })
}

function lowerHand() {
    isHandRaised.value = false
    const payload = JSON.stringify({ type: 'whiteboard_hand_lower', identity: room.localParticipant.identity })
    room.localParticipant.publishData(new TextEncoder().encode(payload), { reliable: true })
}

// ─── General raise hand ───────────────────────────────────────────────────────
function toggleHandRaise() {
    if (props.isTeacher) return
    if (myHandRaised.value) {
        myHandRaised.value = false
        const payload = JSON.stringify({ type: 'hand_lower', identity: room.localParticipant.identity })
        room.localParticipant.publishData(new TextEncoder().encode(payload), { reliable: true })
    } else {
        myHandRaised.value = true
        const payload = JSON.stringify({ type: 'hand_raise', identity: room.localParticipant.identity, name: props.myName })
        room.localParticipant.publishData(new TextEncoder().encode(payload), { reliable: true })
        addSystemMessage('🙋 رفعت يدك')
    }
}

function dismissHand(identity) {
    generalRaisedHands.value = generalRaisedHands.value.filter(h => h.identity !== identity)
}

function dismissAllHands() {
    generalRaisedHands.value = []
}

// ─── Reactions ────────────────────────────────────────────────────────────────
function sendReaction(emoji) {
    showReactionPicker.value = false
    const payload = JSON.stringify({ type: 'reaction', emoji, name: props.myName })
    room.localParticipant.publishData(new TextEncoder().encode(payload), { reliable: false })
    spawnReaction(emoji, props.myName)
}

function spawnReaction(emoji, name) {
    const id = Date.now() + Math.random()
    const x  = 5 + Math.random() * 80   // % from left
    activeReactions.value = [...activeReactions.value, { id, emoji, name, x }]
    setTimeout(() => {
        activeReactions.value = activeReactions.value.filter(r => r.id !== id)
    }, 3000)
}

function onWhiteboardChange(elements) {
    if (!isWhiteboardWriter.value) return
    lastWbElements.value = elements
    if (wbUpdateTimer) clearTimeout(wbUpdateTimer)
    wbUpdateTimer = setTimeout(() => {
        const payload = JSON.stringify({ type: 'whiteboard_update', elements })
        room.localParticipant.publishData(new TextEncoder().encode(payload), { reliable: false })
        wbUpdateTimer = null
    }, 100)
}

defineExpose({ startRecording, startCamMicRecording, stopRecording, isRecording, isUploading, recordingType, downloadRecording, uploadRecording, hasActiveRecording: () => _recordingActive || isRecording.value })
</script>

<template>
    <div class="lk-room" dir="rtl" ref="roomEl">

        <!-- ── Error ──────────────────────────────────────────────────────────── -->
        <div v-if="errorMsg"
            class="lk-center text-danger fw-semibold text-center px-3">
            <i class="bi bi-exclamation-triangle-fill fs-3 mb-2 d-block"></i>
            <p class="mb-3" style="direction:ltr;word-break:break-word;max-width:480px;">{{ errorMsg }}</p>
            <button @click="retryConnect" class="btn btn-outline-danger btn-sm px-4">
                <i class="bi bi-arrow-clockwise me-1"></i>إعادة المحاولة
            </button>
        </div>

        <!-- ── Connecting ─────────────────────────────────────────────────────── -->
        <div v-else-if="connecting" class="lk-center">
            <div class="spinner-border text-primary mb-3" style="width:3rem;height:3rem;"></div>
            <p class="text-white fw-semibold mb-1">جارٍ الاتصال بالغرفة…</p>
            <p class="text-secondary small">يرجى السماح بالوصول للكاميرا والميكروفون</p>
        </div>

        <!-- ── Main room ───────────────────────────────────────────────────────── -->
        <template v-else>

            <!-- ── Video column: layout + controls (+ absolute panels) ── -->
            <div class="lk-video-col">

            <!-- ── Body row: chat | main video | students strip ── -->
            <div class="lk-body">

            <!-- ── Docked chat (right side in RTL = first in DOM) ──────── -->
            <div v-if="useDockedChat" class="lk-chat-dock">
                <div class="lk-chat-dock-header">
                    <i class="bi bi-chat-dots-fill text-primary"></i>
                    <span>الشات</span>
                    <span class="ms-auto text-secondary" style="font-size:11px;">
                        {{ messages.filter(m => !m.system).length }} رسالة
                    </span>
                </div>
                <div class="lk-chat-messages" ref="chatMessagesEl">
                    <div v-for="msg in messages" :key="msg.id" class="mb-2">
                        <div v-if="msg.system" class="lk-system-msg">{{ msg.text }}</div>
                        <div v-else :class="['lk-msg', msg.isLocal ? 'lk-msg--local' : 'lk-msg--remote']">
                            <div class="lk-msg-sender">{{ msg.from }} · {{ formatTime(msg.time) }}</div>
                            <div class="lk-msg-text">{{ msg.text }}</div>
                        </div>
                    </div>
                    <div v-if="messages.length === 0" class="lk-chat-empty">
                        <i class="bi bi-chat-dots fs-2 mb-2 d-block"></i>
                        لا توجد رسائل بعد
                    </div>
                </div>
                <div class="lk-chat-input">
                    <template v-if="!isTeacher && chatLockedByTeacher">
                        <div class="lk-chat-locked">
                            <i class="bi bi-lock-fill text-danger me-1"></i>
                            <span>الشات مقفول من قِبل المدرس</span>
                        </div>
                    </template>
                    <template v-else>
                        <input v-model="chatInput" class="form-control form-control-sm" placeholder="اكتب رسالة…"
                            style="background:#1a1a3a;color:#fff;border-color:#3a3a5a;" @keyup.enter="sendMessage" />
                        <button class="btn btn-primary btn-sm ms-1" @click="sendMessage"><i class="bi bi-send-fill"></i></button>
                    </template>
                </div>
            </div>

            <!-- ── Main video (center, fills remaining space) ── -->
            <div class="lk-layout">

                <!-- MAIN tile -->
                <div class="lk-main" :class="{ 'lk-main--speaking': mainSpeaking }">

                    <!-- Teacher view: local video is main -->
                    <template v-if="showLocalAsMain">
                        <!-- WHITEBOARD MODE: fills main tile -->
                        <template v-if="showWhiteboard">
                            <div class="lk-wb-inset">
                                <WhiteboardPanel
                                    ref="whiteboardPanelRef"
                                    :readOnly="!isWhiteboardWriter"
                                    :initialElements="lastWbElements"
                                    :isTeacher="isTeacher"
                                    :uploadWbMediaUrl="uploadWbMediaUrl"
                                    @change="onWhiteboardChange"
                                    @pdf-file="handleWbPdfFile"
                                    @video-file="handleWbVideoFile"
                                    @video-url="openWbVideo"
                                    @pdf-prev="wbPdfPrev"
                                    @pdf-next="wbPdfNext"
                                    @pdf-close="closeWbPdf"
                                    @video-close="closeWbVideo"
                                    @video-play="onWbVideoPlay"
                                    @video-pause="onWbVideoPause"
                                    @video-seeked="onWbVideoSeeked"
                                />
                            </div>
                            <!-- Camera PiP over whiteboard when camera is on -->
                            <div v-if="isCameraOn" class="lk-pip">
                                <video ref="localVideoEl" autoplay muted playsinline class="lk-pip-video"></video>
                                <div class="lk-pip-name">{{ myName }}</div>
                            </div>
                        </template>
                        <!-- SCREEN SHARE MODE -->
                        <template v-else-if="isScreenSharing">
                            <video ref="localScreenEl" autoplay muted playsinline class="lk-video"></video>
                            <div v-if="isCameraOn" class="lk-pip">
                                <video ref="localVideoEl" autoplay muted playsinline class="lk-pip-video"></video>
                                <div class="lk-pip-name">{{ myName }}</div>
                            </div>
                        </template>
                        <!-- NORMAL CAMERA MODE -->
                        <template v-else>
                            <video ref="localVideoEl" autoplay muted playsinline class="lk-video"></video>
                            <div v-if="!isCameraOn" class="lk-avatar">
                                <div class="lk-avatar-initials" :style="`background:${nameToColor(myName)}`">{{ nameInitials(myName) }}</div>
                                <div class="lk-avatar-name">{{ myName }}</div>
                            </div>
                        </template>
                        <div class="lk-tile-info">
                            <span class="lk-name">
                                {{ myName }}
                                <span v-if="isTeacher" class="badge bg-danger ms-1" style="font-size:10px;">مدرس</span>
                            </span>
                            <i v-if="!isMicOn" class="bi bi-mic-mute-fill text-danger ms-1"></i>
                        </div>
                    </template>

                    <!-- Student view: teacher (first remote) is main -->
                    <template v-else-if="mainRemote">
                        <!-- WHITEBOARD MODE: fills main tile -->
                        <template v-if="showWhiteboard">
                            <div class="lk-wb-inset">
                                <WhiteboardPanel
                                    ref="whiteboardPanelRef"
                                    :readOnly="!isWhiteboardWriter"
                                    :initialElements="lastWbElements"
                                    :isTeacher="isTeacher"
                                    :uploadWbMediaUrl="uploadWbMediaUrl"
                                    @change="onWhiteboardChange"
                                    @pdf-file="handleWbPdfFile"
                                    @video-file="handleWbVideoFile"
                                    @video-url="openWbVideo"
                                    @pdf-prev="wbPdfPrev"
                                    @pdf-next="wbPdfNext"
                                    @pdf-close="closeWbPdf"
                                    @video-close="closeWbVideo"
                                    @video-play="onWbVideoPlay"
                                    @video-pause="onWbVideoPause"
                                    @video-seeked="onWbVideoSeeked"
                                />
                            </div>
                            <!-- Teacher camera PiP over whiteboard when camera is on -->
                            <div v-if="mainRemote.videoTrack && !mainRemote.isCameraOff" class="lk-pip">
                                <video :ref="el => setVideoEl(mainRemote.identity, el)" autoplay playsinline class="lk-pip-video"></video>
                                <div class="lk-pip-name">{{ mainRemote.name }}</div>
                            </div>
                        </template>
                        <!-- SCREEN SHARE MODE -->
                        <template v-else-if="mainRemote.screenTrack">
                            <video :ref="el => setScreenEl(mainRemote.identity, el)" autoplay playsinline class="lk-video"></video>
                            <div v-if="mainRemote.videoTrack && !mainRemote.isCameraOff" class="lk-pip">
                                <video :ref="el => setVideoEl(mainRemote.identity, el)" autoplay playsinline class="lk-pip-video"></video>
                                <div class="lk-pip-name">{{ mainRemote.name }}</div>
                            </div>
                        </template>
                        <!-- NORMAL CAMERA MODE -->
                        <template v-else>
                            <video :ref="el => setVideoEl(mainRemote.identity, el)" autoplay playsinline class="lk-video"></video>
                            <div v-if="!mainRemote.videoTrack" class="lk-avatar">
                                <div class="lk-avatar-initials" :style="`background:${nameToColor(mainRemote.name)}`">{{ nameInitials(mainRemote.name) }}</div>
                                <div class="lk-avatar-name">{{ mainRemote.name }}</div>
                            </div>
                        </template>
                        <div class="lk-tile-info">
                            <span class="lk-name">{{ mainRemote.name }}</span>
                            <i v-if="mainRemote.isMuted" class="bi bi-mic-mute-fill text-danger ms-1"></i>
                        </div>
                    </template>

                    <div v-if="mainSpeaking" class="lk-speaking-ring"></div>

                </div><!-- /lk-main -->

            </div><!-- /lk-layout -->

            <!-- ── Vertical strip (left side in RTL = last in DOM) ── -->
            <!-- Teacher: shows remote students | Student: shows self + other students -->
            <div v-if="isTeacher ? stripRemotes.length > 0 : !showLocalAsMain" class="lk-vstrip">

                <!-- Self-view tile (student only) -->
                <div v-if="!isTeacher" class="lk-strip-tile">
                    <video ref="localVideoEl" autoplay muted playsinline class="lk-video"></video>
                    <div v-if="!isCameraOn" class="lk-avatar lk-avatar--mini">
                        <div class="lk-avatar-initials lk-avatar-initials--mini" :style="`background:${nameToColor(myName)}`">{{ nameInitials(myName) }}</div>
                    </div>
                    <div class="lk-tile-info">
                        <span class="lk-name" style="font-size:10px;">{{ myName }}</span>
                        <i v-if="!isMicOn" class="bi bi-mic-mute-fill text-danger" style="font-size:10px;"></i>
                    </div>
                </div>

                <!-- Remote tiles (teacher: all students | student: other students) -->
                <div v-for="p in stripRemotes" :key="p.identity"
                     class="lk-strip-tile"
                     :class="{ 'lk-strip-tile--speaking': activeSpeakerIds.has(p.identity) }">
                    <video :ref="el => setVideoEl(p.identity, el)" autoplay playsinline class="lk-video"></video>
                    <div v-if="!p.videoTrack" class="lk-avatar lk-avatar--mini">
                        <div class="lk-avatar-initials lk-avatar-initials--mini" :style="`background:${nameToColor(p.name)}`">{{ nameInitials(p.name) }}</div>
                    </div>
                    <div class="lk-tile-info">
                        <span class="lk-name" style="font-size:10px;">{{ p.name }}</span>
                        <i v-if="p.isMuted" class="bi bi-mic-mute-fill text-danger" style="font-size:10px;"></i>
                    </div>
                    <div v-if="activeSpeakerIds.has(p.identity)" class="lk-speaking-ring"></div>
                </div>

            </div>

            </div><!-- /lk-body -->

            <!-- ── General raised-hands bar (teacher only) ───────────────────── -->
            <div v-if="isTeacher && generalRaisedHands.length > 0" class="lk-hands-bar">
                <span class="lk-hands-bar__title">🙋 رفع اليد ({{ generalRaisedHands.length }}):</span>
                <div v-for="hand in generalRaisedHands" :key="hand.identity" class="lk-hands-bar__item">
                    <span class="lk-hands-bar__name">{{ hand.name }}</span>
                    <button @click="dismissHand(hand.identity)" class="lk-hands-bar__dismiss" title="تجاهل">✕</button>
                </div>
                <button v-if="generalRaisedHands.length > 1" @click="dismissAllHands" class="lk-hands-bar__dismiss-all">مسح الكل</button>
            </div>

            <!-- ── WB draw-requests bar (teacher only, when whiteboard open) ──── -->
            <div v-if="isTeacher && raisedHands.length > 0" class="lk-raised-hands-bar">
                <span class="lk-wb-notice">✏️ طلبات الرسم:</span>
                <div v-for="hand in raisedHands" :key="hand.identity" class="lk-raised-hand-item">
                    <span>{{ hand.name }}</span>
                    <button @click="grantDraw(hand.identity, hand.name)"
                        class="btn btn-success py-0 px-2 ms-1" style="font-size:11px;">✅</button>
                    <button @click="raisedHands = raisedHands.filter(h => h.identity !== hand.identity)"
                        class="btn btn-danger py-0 px-2 ms-1" style="font-size:11px;">❌</button>
                </div>
            </div>

            <!-- ── Floating reactions overlay ─────────────────────────────────── -->
            <div class="lk-reactions-overlay" aria-hidden="true">
                <transition-group name="reaction-float" tag="div">
                    <div v-for="r in activeReactions" :key="r.id"
                        class="lk-reaction-bubble"
                        :style="{ left: r.x + '%' }">
                        <span class="lk-reaction-emoji">{{ r.emoji }}</span>
                        <span class="lk-reaction-name">{{ r.name }}</span>
                    </div>
                </transition-group>
            </div>

            <!-- ── Control bar ──────────────────────────────────────────────── -->
            <div class="lk-controls" style="flex-shrink:0;">

                <div class="lk-controls-center">

                <!-- Mic -->
                <button @click="toggleMic"
                    class="lk-btn"
                    :class="isMicOn ? 'lk-btn--on' : (!isTeacher && micLockedByTeacher ? 'lk-btn--locked' : 'lk-btn--off')"
                    :title="!isTeacher && micLockedByTeacher ? 'الميك مكتوم من قِبل المدرس' : (isMicOn ? 'كتم الميك' : 'فتح الميك')">
                    <i :class="isMicOn ? 'bi bi-mic-fill' : (!isTeacher && micLockedByTeacher ? 'bi bi-lock-fill' : 'bi bi-mic-mute-fill')"></i>
                    <span>{{ !isTeacher && micLockedByTeacher ? 'مكتوم' : (isMicOn ? 'ميك' : 'مكتوم') }}</span>
                </button>

                <!-- Camera -->
                <button @click="toggleCamera"
                    class="lk-btn"
                    :class="isCameraOn ? 'lk-btn--on' : (!isTeacher && cameraLockedByTeacher ? 'lk-btn--locked' : 'lk-btn--off')"
                    :title="!isTeacher && cameraLockedByTeacher ? 'الكاميرا مقفولة من قِبل المدرس' : (isCameraOn ? 'إيقاف الكاميرا' : 'تشغيل الكاميرا')">
                    <i :class="isCameraOn ? 'bi bi-camera-video-fill' : (!isTeacher && cameraLockedByTeacher ? 'bi bi-lock-fill' : 'bi bi-camera-video-off-fill')"></i>
                    <span>كاميرا</span>
                </button>

                <!-- Screen share (teacher only) -->
                <button v-if="isTeacher" @click="toggleScreenShare"
                    class="lk-btn" :class="isScreenSharing ? 'lk-btn--active' : 'lk-btn--on'"
                    title="مشاركة الشاشة">
                    <i :class="isScreenSharing ? 'bi bi-stop-circle-fill' : 'bi bi-display'"></i>
                    <span>{{ isScreenSharing ? 'إيقاف' : 'شير' }}</span>
                </button>

                <!-- Mute all (teacher only) -->
                <button v-if="isTeacher" @click="muteAll"
                    class="lk-btn"
                    :class="allMuted ? 'lk-btn--muted-all' : 'lk-btn--warning'"
                    :title="allMuted ? 'فتح الكتم عن الجميع' : 'كتم الجميع'">
                    <i :class="allMuted ? 'bi bi-mic-mute-fill' : 'bi bi-mic-fill'"></i>
                    <span>{{ allMuted ? 'فتح الكل' : 'كتم الكل' }}</span>
                </button>

                <!-- Lock chat for all (teacher only) -->
                <button v-if="isTeacher" @click="lockAllChat"
                    class="lk-btn"
                    :class="allChatLocked ? 'lk-btn--muted-all' : 'lk-btn--warning'"
                    :title="allChatLocked ? 'فتح الشات للجميع' : 'قفل الشات للجميع'">
                    <i :class="allChatLocked ? 'bi bi-chat-square-x-fill' : 'bi bi-chat-dots-fill'"></i>
                    <span>{{ allChatLocked ? 'فتح الشات' : 'قفل الشات' }}</span>
                </button>

                <!-- Lock camera for all (teacher only) -->
                <button v-if="isTeacher" @click="lockAllCamera"
                    class="lk-btn"
                    :class="allCameraLocked ? 'lk-btn--muted-all' : 'lk-btn--warning'"
                    :title="allCameraLocked ? 'فتح الكاميرا للجميع' : 'قفل الكاميرا للجميع'">
                    <i :class="allCameraLocked ? 'bi bi-camera-video-off-fill' : 'bi bi-camera-video-fill'"></i>
                    <span>{{ allCameraLocked ? 'فتح الكاميرا' : 'قفل الكاميرا' }}</span>
                </button>

                <!-- Participants -->
                <button @click="showParticipants = !showParticipants" class="lk-btn lk-btn--on" title="المشاركون">
                    <i class="bi bi-people-fill"></i>
                    <span>{{ totalCount }}</span>
                </button>

                <!-- Chat button: hidden when chat is docked as side panel -->
                <button v-if="!useDockedChat" @click="openChat()" class="lk-btn lk-btn--on position-relative" title="الشات">
                    <i class="bi bi-chat-dots-fill"></i>
                    <span>شات</span>
                    <span v-if="unreadCount > 0"
                        class="position-absolute top-0 start-0 translate-middle badge rounded-pill bg-danger"
                        style="font-size:10px;">
                        {{ unreadCount }}
                    </span>
                </button>

                <!-- Recording buttons (teacher only) — now shown in the top bar of LiveRoom.vue -->

                <!-- Whiteboard toggle (teacher only) -->
                <button v-if="isTeacher" @click="toggleWhiteboard"
                    class="lk-btn" :class="showWhiteboard ? 'lk-btn--active' : 'lk-btn--on'"
                    title="اللوحة البيضاء">
                    <i class="bi bi-pencil-square"></i>
                    <span>لوحة</span>
                </button>

                <!-- Clear whiteboard (teacher only, when open) -->
                <button v-if="isTeacher && showWhiteboard" @click="clearWhiteboard"
                    class="lk-btn lk-btn--warning"
                    title="مسح اللوحة">
                    <i class="bi bi-eraser-fill"></i>
                    <span>مسح</span>
                </button>

                <!-- Record button (teacher only) -->
                <button v-if="isTeacher" @click="isRecording ? stopRecording() : startCamMicRecording()"
                    class="lk-btn"
                    :class="isRecording ? 'lk-btn--recording' : 'lk-btn--on'"
                    :title="isRecording ? 'إيقاف التسجيل' : 'بدء تسجيل البث'">
                    <span style="font-size:16px;">{{ isRecording ? '⏹️' : '🔴' }}</span>
                    <span>{{ isRecording ? 'إيقاف' : 'تسجيل' }}</span>
                </button>

                <!-- Raise hand (student only) -->
                <button v-if="!isTeacher" @click="toggleHandRaise"
                    class="lk-btn"
                    :class="myHandRaised ? 'lk-btn--hand-raised' : 'lk-btn--on'"
                    :title="myHandRaised ? 'خفض اليد' : 'رفع اليد'">
                    <span style="font-size:16px;">🙋</span>
                    <span>{{ myHandRaised ? 'خفض' : 'يد' }}</span>
                </button>

                <!-- Reactions (all) -->
                <div class="lk-reaction-wrap" style="position:relative;">
                    <button @click="showReactionPicker = !showReactionPicker"
                        class="lk-btn lk-btn--on"
                        title="تفاعل">
                        <span style="font-size:16px;">😊</span>
                        <span>تفاعل</span>
                    </button>
                    <div v-if="showReactionPicker" class="lk-reaction-picker">
                        <button v-for="emoji in REACTIONS" :key="emoji"
                            class="lk-reaction-pick-btn"
                            @click="sendReaction(emoji)">{{ emoji }}</button>
                    </div>
                </div>

                <!-- Leave -->
                <button @click="$emit('leave')" class="lk-btn lk-btn--leave" title="مغادرة">
                    <i class="bi bi-telephone-x-fill"></i>
                    <span>خروج</span>
                </button>

                </div><!-- /lk-controls-center -->

                <!-- Upload progress bar -->
                <div v-if="isUploading || uploadSuccess || uploadError"
                    class="lk-upload-bar"
                    :class="{ 'lk-upload-bar--success': uploadSuccess, 'lk-upload-bar--error': uploadError }">
                    <template v-if="isUploading">
                        <div class="lk-upload-fill" :style="`width:${uploadProgress}%`"></div>
                        <span class="lk-upload-label">جارٍ رفع التسجيل… {{ uploadProgress }}%</span>
                    </template>
                    <template v-else-if="uploadSuccess">
                        <span class="lk-upload-label">✅ تم رفع التسجيل بنجاح</span>
                    </template>
                    <template v-else-if="uploadError">
                        <span class="lk-upload-label">❌ {{ uploadError }}</span>
                    </template>
                </div>

                <!-- Fullscreen — pinned to far right -->
                <button @click="toggleFullscreen" class="lk-btn lk-btn--on lk-btn--fullscreen" :title="isFullscreen ? 'خروج من الشاشة الكاملة' : 'شاشة كاملة'">
                    <i :class="isFullscreen ? 'bi bi-fullscreen-exit' : 'bi bi-fullscreen'"></i>
                </button>

            </div>

            <!-- ── Participants panel ─────────────────────────────────────────── -->
            <transition name="slide">
                <div v-if="showParticipants" class="lk-panel lk-panel--left">
                    <div class="lk-panel-header">
                        <span>المشاركون ({{ totalCount }})</span>
                        <button class="btn-close btn-close-white btn-sm" @click="showParticipants = false"></button>
                    </div>
                    <div class="lk-panel-body">
                        <!-- Local -->
                        <div class="lk-participant-item">
                            <i class="bi bi-person-circle text-primary me-2"></i>
                            <span class="flex-grow-1">{{ myName }} (أنت)</span>
                            <span v-if="isTeacher" class="badge bg-danger">مدرس</span>
                            <i v-if="!isMicOn" class="bi bi-mic-mute-fill text-danger ms-1"></i>
                        </div>
                        <!-- Remote -->
                        <div v-for="p in participantList" :key="p.identity" class="lk-participant-item">
                            <i class="bi bi-person-circle text-secondary me-2"></i>
                            <span class="flex-grow-1" :class="{ 'text-success fw-semibold': activeSpeakerIds.has(p.identity) }">
                                {{ p.name }}
                                <span v-if="activeSpeakerIds.has(p.identity)" class="speaking-badge">🟢 يتكلم</span>
                                <span v-if="isTeacher && whiteboardWriters.has(p.identity)" class="badge bg-success ms-1" style="font-size:9px;">✏️</span>
                            </span>
                            <button v-if="isTeacher && whiteboardWriters.has(p.identity)"
                                @click="revokeDraw(p.identity)"
                                class="btn btn-outline-warning py-0 px-1 ms-1"
                                style="font-size:10px;" title="سحب صلاحية الرسم">✏️❌</button>
                            <i v-if="p.isMuted" class="bi bi-mic-mute-fill text-danger"></i>
                        </div>
                    </div>
                </div>
            </transition>

            <!-- ── Floating chat panel (student / non-docked only) ─────────────── -->
            <transition name="slide">
                <div v-if="showChat && !useDockedChat" :class="['lk-panel', isMobileViewport ? 'lk-panel--bottom' : 'lk-panel--right']">
                    <div class="lk-panel-header">
                        <span>الشات</span>
                        <button class="btn-close btn-close-white btn-sm" @click="showChat = false"></button>
                    </div>
                    <div class="lk-chat-messages" ref="chatMessagesEl">
                        <div v-for="msg in messages" :key="msg.id" class="mb-2">
                            <div v-if="msg.system" class="lk-system-msg">{{ msg.text }}</div>
                            <div v-else :class="['lk-msg', msg.isLocal ? 'lk-msg--local' : 'lk-msg--remote']">
                                <div class="lk-msg-sender">{{ msg.from }} · {{ formatTime(msg.time) }}</div>
                                <div class="lk-msg-text">{{ msg.text }}</div>
                            </div>
                        </div>
                        <div v-if="messages.length === 0" class="text-center text-secondary small mt-4">
                            لا توجد رسائل بعد
                        </div>
                    </div>
                    <div class="lk-chat-input">
                        <template v-if="!isTeacher && chatLockedByTeacher">
                            <div class="lk-chat-locked">
                                <i class="bi bi-lock-fill text-danger me-1"></i>
                                <span>الشات مقفول من قِبل المدرس</span>
                            </div>
                        </template>
                        <template v-else>
                            <input v-model="chatInput" class="form-control form-control-sm" placeholder="اكتب رسالة…"
                                style="background:#1a1a3a;color:#fff;border-color:#3a3a5a;" @keyup.enter="sendMessage" />
                            <button class="btn btn-primary btn-sm ms-1" @click="sendMessage"><i class="bi bi-send-fill"></i></button>
                        </template>
                    </div>
                </div>
            </transition>

            </div><!-- /lk-video-col -->

        </template>
    </div>
</template>

<style scoped>
.lk-room {
    position: relative;
    width: 100%;
    height: 100%;
    background: #0f0f1a;
    display: flex;
    flex-direction: row;   /* row: video-col + optional chat-dock side by side */
    overflow: hidden;
}

/* ── Video column: wraps lk-body + lk-controls ──────────── */
.lk-video-col {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-width: 0;
}

/* ── Body row: lk-layout + lk-chat-dock side by side ─────── */
.lk-body {
    flex: 1;
    display: flex;
    flex-direction: row;
    overflow: hidden;
    min-height: 0;
    gap: 8px;
    padding: 8px 8px 0;
    position: relative;   /* anchor floating panels */
}

/* ── Docked chat: same height as video area ──────────────── */
.lk-chat-dock {
    width: 300px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    background: #12122a;
    border: 2px solid #3a3a6a;
    border-radius: 10px;
    overflow: hidden;
}
.lk-chat-dock-header {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 11px 14px;
    background: #1a1a40;
    border-bottom: 2px solid #3a3a6a;
    color: #fff;
    font-size: 14px;
    font-weight: 700;
}

/* ── Center overlay ─────────────────────────────────────── */
.lk-center {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #fff;
}

/* ── New layout: main + strip ─────────────────────────── */
.lk-layout {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    gap: 6px;
    min-height: 0;
}

/* ── Main tile (large, fills remaining space) ───────────── */
.lk-main {
    flex: 1;
    position: relative;
    background: #1a1a2e;
    border-radius: 10px;
    overflow: hidden;
    border: 2px solid transparent;
    transition: border-color .2s;
    min-height: 0;
}
.lk-main.lk-main--speaking {
    border-color: #2ecc71;
    box-shadow: 0 0 0 3px rgba(46,204,113,.35);
}

/* ── Vertical students strip (left column, teacher view) ── */
.lk-vstrip {
    flex-shrink: 0;
    width: 130px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    overflow-y: auto;
    overflow-x: hidden;
    scrollbar-width: thin;
    scrollbar-color: #2a2a4a transparent;
    border-radius: 10px;
}
.lk-vstrip::-webkit-scrollbar         { width: 4px; }
.lk-vstrip::-webkit-scrollbar-track   { background: transparent; }
.lk-vstrip::-webkit-scrollbar-thumb   { background: #2a2a4a; border-radius: 4px; }

/* ── Strip tile (thumbnail — used in vstrip) ─────────────── */
.lk-strip-tile {
    position: relative;
    flex-shrink: 0;
    width: 100%;
    aspect-ratio: 4 / 3;
    background: #1a1a2e;
    border-radius: 8px;
    overflow: hidden;
    border: 2px solid transparent;
    transition: border-color .2s;
}
.lk-strip-tile.lk-strip-tile--speaking {
    border-color: #2ecc71;
    box-shadow: 0 0 0 2px rgba(46,204,113,.4);
}

/* ── Mini avatar (for strip tiles) ─────────────────────── */
.lk-avatar--mini { gap: 4px; }
.lk-avatar-initials--mini {
    width: 42px;
    height: 42px;
    font-size: 16px;
}

.lk-video {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: contain;
    background: #0f0f1a;
}

.lk-avatar {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: #1a1a2e;
    gap: 10px;
}

.lk-avatar-initials {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    font-weight: 700;
    color: #fff;
    box-shadow: 0 2px 12px rgba(0,0,0,.4);
    flex-shrink: 0;
}

.lk-avatar-name {
    color: #ccc;
    font-size: 13px;
    font-weight: 600;
    text-align: center;
    padding: 0 8px;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.lk-tile-info {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 6px 10px;
    background: linear-gradient(transparent, rgba(0,0,0,.7));
    display: flex;
    align-items: center;
    gap: 4px;
}
.lk-name {
    color: #fff;
    font-size: 13px;
    font-weight: 600;
}

.lk-speaking-ring {
    position: absolute;
    inset: -2px;
    border-radius: 12px;
    border: 3px solid #2ecc71;
    pointer-events: none;
    animation: ring-pulse 1s ease-in-out infinite;
}

/* ── Picture-in-Picture (camera over screen share) ── */
.lk-pip {
    position: absolute;
    top: 12px;
    right: 12px;
    width: 180px;
    aspect-ratio: 16/9;
    border-radius: 10px;
    overflow: hidden;
    border: 2px solid rgba(255,255,255,0.25);
    box-shadow: 0 4px 20px rgba(0,0,0,0.6);
    z-index: 20;
    background: #0f0f1a;
}
.lk-pip-video {
    width: 100%; height: 100%;
    object-fit: cover;
    display: block;
}
.lk-pip-name {
    position: absolute;
    bottom: 4px;
    left: 6px;
    font-size: 10px;
    color: #fff;
    background: rgba(0,0,0,0.5);
    padding: 1px 5px;
    border-radius: 4px;
}
@keyframes ring-pulse {
    0%,100% { opacity: 1; }
    50%      { opacity: .4; }
}

/* ── Controls ───────────────────────────────────────────── */
.lk-controls {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 10px 16px;
    background: #12122a;
    border-top: 1px solid #2a2a4a;
    flex-wrap: wrap;
    position: relative;
}
.lk-controls-center {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;
}

.lk-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3px;
    border: none;
    border-radius: 10px;
    padding: 8px 14px;
    font-size: 11px;
    cursor: pointer;
    transition: all .2s;
    min-width: 56px;
}
.lk-btn i { font-size: 18px; }
.lk-btn--on      { background: #2a2a4a; color: #fff; }
.lk-btn--off     { background: #3d1a1a; color: #ff6b6b; }
.lk-btn--active  { background: #1a3d2e; color: #2ecc71; }
.lk-btn--warning { background: #3d3000; color: #f1c40f; }
.lk-btn--locked  { background: #3a0000; color: #e74c3c; cursor: not-allowed; opacity: 0.85; }
.lk-btn--muted-all { background: #5a0000; color: #ff4444; animation: pulse-red 1.5s infinite; }
.lk-chat-locked { flex: 1; display: flex; align-items: center; justify-content: center; padding: 6px; color: #888; font-size: 12px; background: #1a0000; border-radius: 6px; border: 1px solid #5a0000; }
@keyframes pulse-red {
    0%, 100% { box-shadow: 0 0 0 0 rgba(255,68,68,0.4); }
    50%       { box-shadow: 0 0 0 6px rgba(255,68,68,0); }
}
.lk-btn--leave   { background: #dc3545; color: #fff; }
.lk-btn--rec     { background: #2a1a1a; color: #ff6b6b; border: 1px solid #ff6b6b50; }
.lk-btn--rec:hover { background: #3a2020; }
.lk-btn--recording-on { background: #ff4444; color: #fff; animation: pulse-red 1.2s infinite; }
.lk-rec-dot {
    display: inline-block;
    width: 10px; height: 10px;
    border-radius: 50%;
    background: #fff;
    margin-left: 4px;
    animation: blink 1s step-start infinite;
}
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:0} }

/* ── WB Media overlays ──────────────────────────────────────────────────────── */
.wb-media-overlay {
    position: absolute;
    inset: 0;
    z-index: 20;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: rgba(0,0,0,0.82);
}
.wb-media-overlay--loading {
    pointer-events: none;
}
.wb-media-overlay__img {
    max-width: 100%;
    max-height: calc(100% - 52px);
    object-fit: contain;
    border-radius: 4px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.7);
}
.wb-media-overlay__bar {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px;
    background: rgba(0,0,0,0.65);
    border-radius: 8px;
    margin-top: 8px;
}
.wb-bar-btn {
    background: #fff;
    border: none;
    border-radius: 6px;
    padding: 4px 12px;
    font-size: 13px;
    cursor: pointer;
    font-weight: 600;
}
.wb-bar-btn:disabled { opacity: 0.35; cursor: default; }
.wb-bar-btn--close { background: #dc3545; color: #fff; }
.wb-bar-page { color: #fff; font-size: 13px; font-weight: 600; min-width: 60px; text-align: center; }

.wb-media-overlay--video { background: rgba(0,0,0,0.9); }
.wb-media-overlay__video {
    max-width: 96%;
    max-height: calc(100% - 16px);
    border-radius: 6px;
}
.wb-video-close {
    position: absolute;
    top: 8px;
    left: 8px;
    background: #dc3545;
    color: #fff;
    border: none;
    border-radius: 50%;
    width: 28px;
    height: 28px;
    font-size: 14px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 30;
}
.wb-video-url-box {
    position: absolute;
    bottom: 70px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    align-items: center;
    background: #1a1a3a;
    border: 1px solid #3a3a6a;
    border-radius: 8px;
    padding: 8px 12px;
    gap: 6px;
    z-index: 25;
    min-width: 320px;
    max-width: 90%;
}

/* Upload progress bar */
.lk-upload-bar {
    position: relative;
    height: 24px;
    background: #1a1a3a;
    border-top: 1px solid #3a3a6a;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.lk-upload-bar--success { background: #0d2a1a; border-top-color: #198754; }
.lk-upload-bar--error   { background: #2a0d0d; border-top-color: #dc3545; }
.lk-upload-fill {
    position: absolute;
    left: 0; top: 0; height: 100%;
    background: #0d6efd40;
    transition: width 0.3s;
}
.lk-upload-label {
    position: relative;
    font-size: 11px;
    color: #aaa;
    z-index: 1;
}
.lk-btn--fullscreen {
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    min-width: 38px; width: 38px; height: 38px;
    padding: 0;
    border-radius: 50%;
    font-size: 15px;
}
.lk-btn:hover { filter: brightness(1.2); transform: translateY(-1px); }

/* ── Panels ─────────────────────────────────────────────── */
.lk-panel {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 280px;
    background: #12122a;
    border: 1px solid #2a2a4a;
    display: flex;
    flex-direction: column;
    z-index: 20;
}
.lk-panel--left  { left: 0; border-right-color: #2a2a4a; }
.lk-panel--right { right: 0; border-left-color: #2a2a4a; }
.lk-panel--bottom {
    left: 8px;
    right: 8px;
    top: auto;
    bottom: 96px;
    width: auto;
    height: min(42vh, 360px);
    border-radius: 12px;
    border-color: #2a2a4a;
}

.lk-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 14px;
    background: #1a1a3a;
    color: #fff;
    font-weight: 600;
    font-size: 14px;
    border-bottom: 1px solid #2a2a4a;
}

.lk-panel-body {
    flex: 1;
    overflow-y: auto;
    padding: 8px;
}

.lk-participant-item {
    display: flex;
    align-items: center;
    padding: 8px 6px;
    border-radius: 8px;
    color: #ccc;
    font-size: 13px;
    border-bottom: 1px solid #1e1e3a;
}
.speaking-badge { font-size: 11px; margin-right: 4px; }

/* ── Chat ────────────────────────────────────────────────── */
.lk-chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 10px;
    scrollbar-width: none;
}
.lk-chat-messages::-webkit-scrollbar { display: none; }
.lk-chat-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    min-height: 120px;
    color: #555;
    font-size: 13px;
    text-align: center;
}
.lk-system-msg {
    text-align: center;
    font-size: 11px;
    color: #666;
    margin: 4px 0;
}
.lk-msg { margin-bottom: 8px; }
.lk-msg-sender { font-size: 10px; color: #888; margin-bottom: 2px; }
.lk-msg-text {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 8px;
    font-size: 13px;
    max-width: 220px;
    word-break: break-word;
}
.lk-msg--local  .lk-msg-text { background: #1e4a8a; color: #fff; margin-right: auto; }
.lk-msg--remote .lk-msg-text { background: #1a1a3a; color: #ddd; border: 1px solid #2a2a4a; }

.lk-chat-input {
    padding: 10px;
    border-top: 1px solid #2a2a4a;
    display: flex;
    align-items: center;
}

/* ── Transitions ─────────────────────────────────────────── */
.slide-enter-active, .slide-leave-active { transition: transform .25s ease; }
.slide-enter-from.lk-panel--right, .slide-leave-to.lk-panel--right { transform: translateX(100%); }
.slide-enter-from.lk-panel--left,  .slide-leave-to.lk-panel--left  { transform: translateX(-100%); }
.slide-enter-from.lk-panel--bottom, .slide-leave-to.lk-panel--bottom { transform: translateY(100%); }

/* ── Whiteboard inset (fills the main tile absolutely) ─────── */
.lk-wb-inset {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    background: #fff;
    border-radius: 10px;
    overflow: hidden;
    z-index: 1;
}

/* Raised-hands notification bar */
.lk-raised-hands-bar {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px 12px;
    background: #0a1f0a;
    border-top: 1px solid #1a4a1a;
    flex-wrap: wrap;
}
.lk-raised-hand-item {
    display: flex;
    align-items: center;
    gap: 5px;
    background: #0f2a0f;
    border: 1px solid #2a6a2a;
    border-radius: 20px;
    padding: 2px 10px;
    font-size: 12px;
    color: #a8ffa8;
}
.lk-wb-notice {
    font-size: 12px;
    color: #4a9a4a;
    font-weight: 600;
}

/* ── Recording button ────────────────────────────────────── */
.lk-btn--recording {
    background: #8b0000 !important;
    border-color: #ff2222 !important;
    color: #fff !important;
    animation: rec-pulse .9s ease-in-out infinite alternate;
}
@keyframes rec-pulse {
    from { box-shadow: 0 0 0 0 rgba(255,40,40,.7); }
    to   { box-shadow: 0 0 0 7px rgba(255,40,40,0); }
}

/* ── General raised-hands notification bar ───────────────── */
.lk-hands-bar {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 14px;
    background: #1a1200;
    border-top: 1px solid #4a3800;
    flex-wrap: wrap;
}
.lk-hands-bar__title {
    font-size: 12px;
    font-weight: 700;
    color: #ffd966;
    white-space: nowrap;
}
.lk-hands-bar__item {
    display: flex;
    align-items: center;
    gap: 4px;
    background: #2a2000;
    border: 1px solid #6a5000;
    border-radius: 20px;
    padding: 2px 10px;
    font-size: 12px;
    color: #ffe080;
}
.lk-hands-bar__name { max-width: 100px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.lk-hands-bar__dismiss {
    background: none; border: none; color: #ffa0a0; cursor: pointer;
    font-size: 11px; padding: 0 2px; line-height: 1;
}
.lk-hands-bar__dismiss-all {
    background: #3a1000; border: 1px solid #8a3000; border-radius: 12px;
    color: #ffb080; font-size: 11px; padding: 2px 10px; cursor: pointer;
}
.lk-btn--hand-raised {
    background: #ffc107 !important;
    color: #1a0a00 !important;
    border-color: #e6a800 !important;
    animation: hand-pulse .8s ease-in-out infinite alternate;
}
@keyframes hand-pulse {
    from { box-shadow: 0 0 0 0 rgba(255,193,7,.6); }
    to   { box-shadow: 0 0 0 6px rgba(255,193,7,0); }
}

/* ── Reactions ───────────────────────────────────────────── */
.lk-reaction-wrap { position: relative; }
.lk-reaction-picker {
    position: absolute;
    bottom: calc(100% + 8px);
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 6px;
    background: #1e1e2e;
    border: 1px solid #3a3a5a;
    border-radius: 28px;
    padding: 6px 10px;
    z-index: 200;
    white-space: nowrap;
    box-shadow: 0 4px 20px rgba(0,0,0,.5);
}
.lk-reaction-pick-btn {
    background: none; border: none; font-size: 22px;
    cursor: pointer; padding: 2px 4px; line-height: 1;
    border-radius: 8px; transition: transform .1s;
}
.lk-reaction-pick-btn:hover { transform: scale(1.3); }

.lk-reactions-overlay {
    position: absolute;
    inset: 0;
    pointer-events: none;
    overflow: hidden;
    z-index: 90;
}
.lk-reaction-bubble {
    position: absolute;
    bottom: 60px;
    display: flex;
    flex-direction: column;
    align-items: center;
    animation: reaction-rise 3s ease-out forwards;
}
.lk-reaction-emoji { font-size: 34px; line-height: 1; }
.lk-reaction-name  { font-size: 11px; color: #fff; text-shadow: 0 1px 3px #000; margin-top: 2px; white-space: nowrap; }

@keyframes reaction-rise {
    0%   { transform: translateY(0)    scale(1);    opacity: 1; }
    70%  { transform: translateY(-160px) scale(1.1); opacity: 1; }
    100% { transform: translateY(-240px) scale(.8);  opacity: 0; }
}
.reaction-float-enter-active { animation: reaction-rise 3s ease-out forwards; }
.reaction-float-leave-active  { display: none; }

@media (max-width: 991.98px) {
    .lk-chat-dock {
        display: none;
    }

    .lk-panel--bottom {
        bottom: 88px;
        height: min(46vh, 380px);
    }
}
</style>
