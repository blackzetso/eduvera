<script setup>
import AppLayout from '@/Pages/Student/Theme1/Layout/App.vue'
import { Head, Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { computed } from 'vue'

const props = defineProps({
    streams: { type: Array, default: () => [] },
})

// Generate a distinct gradient per stream based on its id
const GRADIENTS = [
    ['#1a1a2e', '#0f3460'],
    ['#0d2137', '#1b6ca8'],
    ['#1a0533', '#6a0dad'],
    ['#0c2340', '#1e5f74'],
    ['#1e0a00', '#b34700'],
    ['#001a12', '#00704a'],
    ['#1a0020', '#8b0049'],
    ['#00151f', '#005f87'],
]

function gradientFor(id) {
    const [from, to] = GRADIENTS[id % GRADIENTS.length]
    return `linear-gradient(135deg, ${from} 0%, ${to} 100%)`
}

const liveStreams      = computed(() => props.streams.filter(s => s.status === 'live'))
const scheduledStreams = computed(() => props.streams.filter(s => s.status !== 'live'))

function formatDate(dt) {
    if (!dt) return ''
    const d = new Date(dt.replace(' ', 'T'))
    return d.toLocaleString('ar-EG', { weekday: 'short', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
    <Head title="Live Streams" />
    <AppLayout>
        <!-- Page header -->
        <section class="py-4 py-lg-5" style="background: linear-gradient(135deg, #1a1a2e 0%, #0f3460 100%);">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-12">
                        <div class="d-flex align-items-center gap-3">
                            <span class="live-badge-hero d-flex align-items-center gap-2 px-3 py-1 rounded-pill"
                                style="background:rgba(220,53,69,.15);border:1px solid rgba(220,53,69,.4);">
                                <span class="live-dot-hero"></span>
                                <span class="text-danger fw-bold fs-6">LIVE</span>
                            </span>
                            <div>
                                <h1 class="text-white mb-0 h3">Live Streams</h1>
                                <p class="text-white-50 mb-0 small">Join live classes and upcoming sessions</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Content -->
        <section class="py-5">
            <div class="container">

                <!-- Empty state -->
                <div v-if="streams.length === 0" class="text-center py-5">
                    <div class="mb-4" style="font-size:4rem;">📡</div>
                    <h4 class="mb-2">No streams available right now</h4>
                    <p class="text-muted">Check back later for live and upcoming sessions.</p>
                </div>

                <!-- Live now section -->
                <template v-if="liveStreams.length > 0">
                    <h5 class="mb-4 d-flex align-items-center gap-2">
                        <span class="live-dot-sm"></span>
                        <span>Live Now</span>
                        <span class="badge bg-danger ms-1">{{ liveStreams.length }}</span>
                    </h5>
                    <div class="row g-4 mb-5">
                        <div v-for="stream in liveStreams" :key="stream.id" class="col-sm-6 col-lg-4 col-xl-3">
                            <div class="card stream-card h-100 shadow-sm border-0 overflow-hidden">
                                <!-- Cover image -->
                                <div class="card-img-top position-relative"
                                    :style="stream.thumbnail_url ? {} : { background: gradientFor(stream.id) }"
                                    style="height:160px;overflow:hidden;">
                                    <img v-if="stream.thumbnail_url" :src="stream.thumbnail_url"
                                        style="width:100%;height:160px;object-fit:cover;display:block;">
                                    <!-- Live badge -->
                                    <span class="badge bg-danger position-absolute top-0 start-0 m-2 d-flex align-items-center gap-1 px-2 py-1"
                                        style="font-size:11px;letter-spacing:.5px;">
                                        <span class="live-dot-card"></span>LIVE
                                    </span>
                                    <!-- Subject overlay -->
                                    <div class="position-absolute bottom-0 start-0 w-100 px-3 py-2"
                                        style="background:linear-gradient(transparent,rgba(0,0,0,.7));">
                                        <span class="text-white fw-semibold" style="font-size:13px;">
                                            {{ stream.subject || 'General' }}
                                        </span>
                                    </div>
                                    <!-- Play icon center (only when no thumbnail) -->
                                    <div v-if="!stream.thumbnail_url" class="position-absolute top-50 start-50 translate-middle">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                                            style="width:48px;height:48px;background:rgba(255,255,255,.15);backdrop-filter:blur(4px);">
                                            <i class="bi bi-play-fill text-white fs-4"></i>
                                        </div>
                                    </div>
                                </div>
                                <!-- Card body -->
                                <div class="card-body d-flex flex-column p-3">
                                    <h6 class="card-title mb-1 lh-sm" style="font-size:14px;">{{ stream.title }}</h6>
                                    <p class="text-muted mb-3 small d-flex align-items-center gap-1">
                                        <i class="bi bi-person-circle"></i>{{ stream.teacher_name }}
                                    </p>
                                    <a :href="`/student/live-streams/${stream.id}`"
                                        class="btn btn-danger btn-sm mt-auto w-100 d-flex align-items-center justify-content-center gap-1">
                                        <i class="bi bi-info-circle"></i>
                                        Live Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Upcoming section -->
                <template v-if="scheduledStreams.length > 0">
                    <h5 class="mb-4 d-flex align-items-center gap-2">
                        <i class="bi bi-calendar-event text-primary"></i>
                        <span>Upcoming Sessions</span>
                        <span class="badge bg-primary ms-1">{{ scheduledStreams.length }}</span>
                    </h5>
                    <div class="row g-4">
                        <div v-for="stream in scheduledStreams" :key="stream.id" class="col-sm-6 col-lg-4 col-xl-3">
                            <div class="card stream-card h-100 shadow-sm border-0 overflow-hidden">
                                <!-- Cover image -->
                                <div class="card-img-top position-relative"
                                    :style="stream.thumbnail_url ? {} : { background: gradientFor(stream.id) }"
                                    style="height:160px;overflow:hidden;">
                                    <img v-if="stream.thumbnail_url" :src="stream.thumbnail_url"
                                        style="width:100%;height:160px;object-fit:cover;display:block;">
                                    <!-- Scheduled badge -->
                                    <span class="badge bg-warning text-dark position-absolute top-0 start-0 m-2"
                                        style="font-size:11px;">
                                        Scheduled
                                    </span>
                                    <!-- Subject overlay -->
                                    <div class="position-absolute bottom-0 start-0 w-100 px-3 py-2"
                                        style="background:linear-gradient(transparent,rgba(0,0,0,.7));">
                                        <span class="text-white fw-semibold" style="font-size:13px;">
                                            {{ stream.subject || 'General' }}
                                        </span>
                                    </div>
                                    <!-- Clock icon center (only when no thumbnail) -->
                                    <div v-if="!stream.thumbnail_url" class="position-absolute top-50 start-50 translate-middle">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                                            style="width:48px;height:48px;background:rgba(255,255,255,.15);backdrop-filter:blur(4px);">
                                            <i class="bi bi-clock text-white fs-4"></i>
                                        </div>
                                    </div>
                                </div>
                                <!-- Card body -->
                                <div class="card-body d-flex flex-column p-3">
                                    <h6 class="card-title mb-1 lh-sm" style="font-size:14px;">{{ stream.title }}</h6>
                                    <p class="text-muted mb-1 small d-flex align-items-center gap-1">
                                        <i class="bi bi-person-circle"></i>{{ stream.teacher_name }}
                                    </p>
                                    <p class="text-primary mb-3 small d-flex align-items-center gap-1">
                                        <i class="bi bi-clock"></i>{{ formatDate(stream.start_datetime) }}
                                    </p>
                                    <a :href="`/student/live-streams/${stream.id}`"
                                        class="btn btn-outline-primary btn-sm mt-auto w-100 d-flex align-items-center justify-content-center gap-1">
                                        <i class="bi bi-info-circle"></i>
                                        Live Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

            </div>
        </section>
    </AppLayout>
</template>

<style scoped>
/* Pulsing live dot for hero */
.live-dot-hero {
    width: 10px;
    height: 10px;
    background: #dc3545;
    border-radius: 50%;
    animation: pulse-hero 1.2s ease-in-out infinite;
}
@keyframes pulse-hero {
    0%, 100% { opacity: 1; transform: scale(1); box-shadow: 0 0 0 0 rgba(220,53,69,.6); }
    50%       { opacity: .8; transform: scale(1.1); box-shadow: 0 0 0 6px rgba(220,53,69,0); }
}

/* Small live dot for section label */
.live-dot-sm {
    width: 8px;
    height: 8px;
    background: #dc3545;
    border-radius: 50%;
    animation: pulse-hero 1.2s ease-in-out infinite;
    display: inline-block;
}

/* Tiny live dot inside card badge */
.live-dot-card {
    width: 6px;
    height: 6px;
    background: #fff;
    border-radius: 50%;
    animation: pulse-hero 1.2s ease-in-out infinite;
    display: inline-block;
}

.stream-card {
    transition: transform .2s ease, box-shadow .2s ease;
    border-radius: 12px !important;
}
.stream-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,.15) !important;
}
</style>
