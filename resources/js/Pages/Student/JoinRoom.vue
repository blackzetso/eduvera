<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { Head, usePage, router } from '@inertiajs/vue3'
import axios from 'axios'
import LiveKitRoom from '@/components/LiveKitRoom.vue'
import LiveStreamQuizOverlay from '@/components/LiveStreamQuizOverlay.vue'

const props = defineProps({
    stream:       Object,
    jitsiRoom:    String,
    livekitWsUrl: { type: String, default: null },
    watermark:    { type: Object, default: null },
})

// ─── Auth user ───────────────────────────────────────────────────────────────
const authUser = usePage().props.auth?.user

// ─── Stage: 'form' | 'room' ──────────────────────────────────────────────────
const stage        = ref('form')
const name         = ref(authUser?.name || '')
const nameErr      = ref('')
const joining      = ref(false)
const livekitToken = ref(null)

// ─── Waiting room state ───────────────────────────────────────────────────────
const currentStatus = ref(props.stream.status)
let pollTimer       = null

// Parse start_datetime as LOCAL time (browser interprets 'Y-m-d H:i' without TZ as local)
const startDate = props.stream.start_datetime
    ? new Date(props.stream.start_datetime.replace(' ', 'T'))
    : null

// Reactive "now" updated every second
const nowMs = ref(Date.now())
let tickTimer = null

// Seconds until start — computed client-side from local clock (avoids server UTC offset bug)
const secondsUntilStart = computed(() =>
    startDate ? Math.max(0, Math.round((startDate.getTime() - nowMs.value) / 1000)) : 0
)

// Before scheduled time
const beforeStart = computed(() => secondsUntilStart.value > 0)

// Scheduled time reached but teacher hasn't started yet
const waitingForTeacher = computed(() =>
    !beforeStart.value && currentStatus.value === 'scheduled'
)

const streamOver = computed(() => currentStatus.value === 'ended')
const isLive     = computed(() => currentStatus.value === 'live')

// Countdown display
const countdownDisplay = computed(() => {
    const s = secondsUntilStart.value
    const h = Math.floor(s / 3600)
    const m = Math.floor((s % 3600) / 60)
    const sec = s % 60
    if (h > 0) return `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(sec).padStart(2,'0')}`
    return `${String(m).padStart(2,'0')}:${String(sec).padStart(2,'0')}`
})

const isLiveKit = computed(() => (props.stream.classroom_dashboard ?? 'jitsi') === 'livekit' && props.livekitWsUrl)

// ─── Watermark ──────────────────────────────────────────────────────────
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

// ─── Jitsi URL ────────────────────────────────────────────────────────────────
const jitsiSrc = computed(() => {
    if (!name.value.trim()) return ''
    const n = encodeURIComponent(name.value.trim())
    return `https://meet.jit.si/${props.jitsiRoom}`
        + `#config.prejoinPageEnabled=false`
        + `&config.startWithAudioMuted=true`
        + `&config.startWithVideoMuted=true`
        + `&config.disableDeepLinking=true`
        + `&config.toolbarButtons=["microphone","camera","chat","participants-pane","raise-hand","fullscreen","hangup"]`
        + `&userInfo.displayName="${n}"`
})

const iframeReady = ref(false)

// ─── Poll stream status from server ──────────────────────────────────────────
async function pollStatus() {
    try {
        const res = await axios.get(`/join/${props.stream.id}/status`)
        currentStatus.value = res.data.status
    } catch (_) { /* silent */ }
}

// ─── Start timers on mount ────────────────────────────────────────────────────
onMounted(() => {
    // Tick every second to update nowMs (drives countdown computed)
    tickTimer = setInterval(() => { nowMs.value = Date.now() }, 1000)

    // Poll every 8s when not yet live
    pollTimer = setInterval(() => {
        if (currentStatus.value !== 'live' && currentStatus.value !== 'ended') {
            pollStatus()
        }
    }, 8000)

    // If user is authenticated and stream is already live, skip the name form
    if (authUser?.name && currentStatus.value === 'live') {
        joinRoom()
    }

    // Watch: auto-join when live status is detected via polling
    watch(currentStatus, (newStatus) => {
        if (newStatus === 'live' && authUser?.name && stage.value === 'form') {
            joinRoom()
        }
    })
})

onUnmounted(() => {
    clearInterval(tickTimer)
    clearInterval(pollTimer)
})

function leaveStream() {
    router.visit(`/student/live-streams/${props.stream.id}`)
}

async function joinRoom() {
    nameErr.value = ''
    if (!name.value.trim()) {
        nameErr.value = 'يرجى إدخال اسمك'
        return
    }

    if (isLiveKit.value) {
        joining.value = true
        try {
            const res = await axios.post(`/join/${props.stream.id}/livekit-token`, { name: name.value.trim() })
            livekitToken.value = res.data.token
            stage.value        = 'room'
        } catch (e) {
            nameErr.value = e.response?.data?.error || e.message || 'حدث خطأ'
        } finally {
            joining.value = false
        }
        return
    }

    stage.value = 'room'
}
</script>

<template>
    <Head :title="stream.title" />

    <div style="height:100vh;overflow:hidden;background:#0f0f1a;" class="d-flex flex-column">

        <!-- Watermark overlay (shown only when in room) -->
        <img
            v-if="watermark?.url && stage === 'room'"
            :src="watermark.url"
            :style="watermarkStyle"
            draggable="false"
            alt=""
        />

        <!-- ══════════ FORM ══════════════════════════════════════════════════ -->
        <div v-if="stage === 'form'"
            class="flex-grow-1 d-flex align-items-center justify-content-center p-3">
            <div class="card shadow-lg"
                style="max-width:440px;width:100%;border-radius:16px;background:#1a1a2e;border:1px solid #2a2a4a;">
                <div class="card-body p-4">
                    <!-- Stream info -->
                    <div class="text-center mb-4">
                        <div class="rounded-circle bg-danger bg-opacity-20 d-inline-flex align-items-center justify-content-center mb-3"
                            style="width:64px;height:64px;">
                            <i class="bi bi-broadcast text-danger fs-2"></i>
                        </div>
                        <h4 class="text-white fw-bold mb-1">{{ stream.title }}</h4>
                        <div class="text-secondary small">
                            <i class="bi bi-person me-1"></i>{{ stream.teacher_name }}
                            <span v-if="stream.subject"> · {{ stream.subject }}</span>
                        </div>
                        <div v-if="isLive"
                            class="badge bg-danger mt-2 px-3 py-1 d-inline-flex align-items-center gap-1">
                            <span class="live-dot"></span>مباشر الآن
                        </div>
                        <div v-else-if="beforeStart"
                            class="badge bg-primary mt-2 px-3 py-1">
                            مجدول · {{ stream.start_datetime }}
                        </div>
                    </div>

                    <!-- ── BEFORE SCHEDULED TIME ── countdown ──────────────── -->
                    <div v-if="beforeStart" class="text-center py-3">
                        <div class="mb-2" style="font-size:13px;color:#a0aec0;">سيبدأ البث بعد</div>
                        <div class="fw-bold mb-3"
                            style="font-size:3rem;letter-spacing:4px;color:#63b3ed;font-variant-numeric:tabular-nums;">
                            {{ countdownDisplay }}
                        </div>
                        <div class="text-secondary small">
                            <i class="bi bi-calendar-event me-1"></i>{{ stream.start_datetime }}
                        </div>
                        <div class="alert mt-4 py-2 small" style="background:#1e2a3a;border:1px solid #2a4a6a;color:#90cdf4;">
                            <i class="bi bi-info-circle me-1"></i>
                            هذه الصفحة ستسمح لك بالانضمام فور انتهاء العد التنازلي وبدء المعلم للبث.
                        </div>
                    </div>

                    <!-- ── WAITING FOR TEACHER ─────────────────────────────── -->
                    <div v-else-if="waitingForTeacher" class="text-center py-3">
                        <div class="spinner-border text-warning mb-3" style="width:2.5rem;height:2.5rem;"></div>
                        <div class="text-white fw-semibold mb-1">في انتظار المعلم...</div>
                        <div class="text-secondary small mb-3">لم يبدأ المعلم البث بعد. ستنضم تلقائياً عند البدء.</div>
                        <div class="alert py-2 small" style="background:#1e2a3a;border:1px solid #2a4a6a;color:#90cdf4;">
                            <i class="bi bi-arrow-repeat me-1"></i>
                            يتم التحقق كل몇 ثوانٍ — لا تغلق هذه الصفحة.
                        </div>
                    </div>

                    <!-- ── ENDED ───────────────────────────────────────────── -->
                    <div v-else-if="streamOver" class="alert alert-secondary text-center">
                        <i class="bi bi-clock-history me-1"></i>انتهى هذا البث.
                    </div>

                    <!-- ── LIVE: Name form ─────────────────────────────────── -->
                    <template v-else-if="isLive">
                        <div class="mb-3">
                            <label class="form-label text-white">اسمك الكامل</label>
                            <input
                                v-model="name"
                                type="text"
                                class="form-control form-control-lg"
                                :class="{ 'is-invalid': nameErr }"
                                placeholder="أدخل اسمك..."
                                style="background:#0f0f1a;color:#fff;border-color:#3a3a5a;"
                                @keyup.enter="joinRoom"
                                autofocus
                            />
                            <div v-if="nameErr" class="invalid-feedback">{{ nameErr }}</div>
                        </div>
                        <button @click="joinRoom" class="btn btn-danger btn-lg w-100 fw-bold" :disabled="joining">
                            <span v-if="joining" class="spinner-border spinner-border-sm me-2"></span>
                            <i v-else class="bi bi-broadcast me-2"></i>
                            انضم للبث المباشر
                        </button>
                        <p class="text-secondary text-center small mt-3 mb-0">
                            بالانضمام أنت توافق على الظهور بالاسم الذي أدخلته.
                        </p>
                    </template>
                </div>
            </div>
        </div>

        <!-- ══════════ ROOM ═══════════════════════════════════════════════════ -->
        <template v-else-if="stage === 'room'">
            <!-- Mini top bar -->
            <div class="d-flex align-items-center gap-2 px-2 px-sm-3 py-2 flex-shrink-0"
                style="background:#12122a;border-bottom:1px solid #2a2a4a;">
                <span class="badge bg-danger px-2 py-1 d-flex align-items-center gap-1 flex-shrink-0" style="font-size:12px;">
                    <span class="live-dot"></span>مباشر
                </span>
                <div class="text-white fw-semibold flex-grow-1 text-center text-truncate" style="font-size:14px;min-width:0;">
                    {{ stream.title }}
                </div>
                <span class="text-secondary small text-truncate d-none d-sm-block flex-shrink-0" style="max-width:100px;">{{ name }}</span>
                <button @click="leaveStream" class="btn btn-sm btn-outline-secondary flex-shrink-0" style="font-size:12px;">
                    <i class="bi bi-box-arrow-left"></i>
                    <span class="d-none d-sm-inline ms-1">خروج</span>
                </button>
            </div>

            <!-- LiveKit room -->
            <div v-if="isLiveKit && livekitToken" class="flex-grow-1 position-relative" style="overflow:hidden;">
                <LiveKitRoom
                    :wsUrl="livekitWsUrl"
                    :token="livekitToken"
                    :isTeacher="false"
                    :myName="name"
                    :teacherName="stream.teacher_name || ''"
                    :dockChat="true"
                    @leave="leaveStream"
                    style="height:100%;"
                />
                <!-- Quiz overlay (polls for active questions) -->
                <LiveStreamQuizOverlay
                    :streamId="stream.id"
                    :studentName="name"
                />
            </div>

            <!-- Jitsi iframe (fallback) -->
            <div v-else class="flex-grow-1 position-relative">
                <div v-if="!iframeReady"
                    class="position-absolute d-flex flex-column align-items-center justify-content-center"
                    style="inset:0;background:#0f0f1a;z-index:10;">
                    <div class="spinner-border text-danger mb-3" style="width:3rem;height:3rem;"></div>
                    <p class="text-white">جارٍ الانضمام...</p>
                    <p class="text-secondary small">يرجى السماح للمتصفح بالوصول للكاميرا والميكروفون</p>
                </div>

                <iframe
                    :src="jitsiSrc"
                    allow="camera *; microphone *; fullscreen *; display-capture *; autoplay *"
                    style="width:100%;height:100%;border:none;"
                    @load="iframeReady = true"
                ></iframe>
            </div>
        </template>
    </div>
</template>

<style scoped>
.live-dot {
    display: inline-block;
    width: 7px; height: 7px;
    border-radius: 50%;
    background: #fff;
    animation: pulse 1.4s ease-in-out infinite;
}
@keyframes pulse {
    0%,100% { opacity: 1; transform: scale(1); }
    50%      { opacity: .4; transform: scale(.8); }
}
</style>
