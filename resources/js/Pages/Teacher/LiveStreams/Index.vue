<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import AppLayout from '@/Pages/Teacher/Layout/App.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import Swal from 'sweetalert2'
import { toast } from 'vue3-toastify'

const props = defineProps({
    streams: Array,
})

const search = ref('')

const filteredStreams = computed(() => {
    if (!search.value) return props.streams
    const q = search.value.toLowerCase()
    return props.streams.filter(s =>
        s.title.toLowerCase().includes(q) ||
        s.teacher_name.toLowerCase().includes(q) ||
        (s.subject && s.subject.toLowerCase().includes(q))
    )
})

const statusLabel = {
    scheduled: { label: 'مجدول', class: 'bg-primary' },
    live: { label: 'مباشر الآن', class: 'bg-danger' },
    ended: { label: 'انتهى', class: 'bg-secondary' },
}

const providerLabel = {
    none:        { label: 'Jitsi',       class: 'bg-light text-muted border', icon: 'bi-broadcast' },
    teams:       { label: 'Teams',       class: 'bg-primary bg-opacity-15 text-primary border border-primary border-opacity-25', icon: 'bi-camera-video' },
    zoom:        { label: 'Zoom',        class: 'bg-info bg-opacity-15 text-info border border-info border-opacity-25', icon: 'bi-camera-video-fill' },
    livekit:     { label: 'LiveKit',     class: 'bg-success bg-opacity-15 text-success border border-success border-opacity-25', icon: 'bi-broadcast-pin' },
    google_meet: { label: 'Google Meet', class: 'bg-danger bg-opacity-15 text-danger border border-danger border-opacity-25', icon: 'bi-camera-video' },
    hms:         { label: '100ms',       class: 'bg-primary bg-opacity-25 text-primary border border-primary border-opacity-50 fw-bold', icon: 'bi-grid-3x3-gap-fill' },
}

function confirmDelete(stream) {
    Swal.fire({
        title: 'حذف البث؟',
        text: `سيتم حذف "${stream.title}" وجميع سجلات الحضور المرتبطة به.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'نعم، احذف',
        cancelButtonText: 'إلغاء',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('teacher.live-streams.destroy', stream.id), {
                onSuccess: () => toast.success('تم حذف البث بنجاح'),
                onError: () => toast.error('حدث خطأ أثناء الحذف'),
            })
        }
    })
}
// ─── Countdown for live streams ──────────────────────────────────────────
const nowMs    = ref(Date.now())
const copiedId = ref(null)
let tickHandle = null

onMounted(() => {
    tickHandle = setInterval(() => { nowMs.value = Date.now() }, 1000)
})

onBeforeUnmount(() => {
    if (tickHandle) clearInterval(tickHandle)
})

function copyGuestLink(stream) {
    const url = stream.guest_join_url
    if (!url) return
    navigator.clipboard.writeText(url).then(() => {
        copiedId.value = stream.id
        setTimeout(() => { copiedId.value = null }, 2000)
    })
}

function getRemaining(stream) {
    if (stream.status !== 'live' || !stream.end_datetime) return null
    const end = new Date(stream.end_datetime.replace(' ', 'T'))
    return Math.max(0, Math.round((end - nowMs.value) / 1000))
}

function formatCountdown(s) {
    if (s === null) return null
    const m = Math.floor(s / 60), sec = s % 60
    return `${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`
}
function startStream(stream) {
    router.visit(route('teacher.live-streams.room', stream.id))
}

function canStart(stream) {
    if (!stream.start_datetime) return true
    const start = new Date(stream.start_datetime.replace(' ', 'T'))
    return nowMs.value >= start.getTime()
}

function secondsUntilStart(stream) {
    if (!stream.start_datetime) return 0
    const start = new Date(stream.start_datetime.replace(' ', 'T'))
    return Math.max(0, Math.ceil((start.getTime() - nowMs.value) / 1000))
}

function formatTimeUntilStart(s) {
    if (s <= 0) return null
    const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), sec = s % 60
    const pad = n => String(n).padStart(2, '0')
    if (h > 0) return `${pad(h)}:${pad(m)}:${pad(sec)}`
    return `${pad(m)}:${pad(sec)}`
}

function endStream(stream) {
    Swal.fire({
        title: 'إنهاء البث؟',
        text: 'هل تريد إنهاء البث المباشر الآن؟',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#6c757d',
        cancelButtonColor: '#0d6efd',
        confirmButtonText: 'نعم، أنهِ البث',
        cancelButtonText: 'إلغاء',
    }).then((result) => {
        if (result.isConfirmed) {
            router.patch(route('teacher.live-streams.update-status', stream.id), { status: 'ended' }, {
                onSuccess: () => toast.success('تم إنهاء البث'),
                onError: () => toast.error('حدث خطأ'),
            })
        }
    })
}
</script>

<template>
    <Head title="البثوث المباشرة" />
    <AppLayout>
        <div class="page-content-wrapper border">
            <!-- Header -->
            <div class="row mb-3 align-items-center">
                <div class="col">
                    <h1 class="h3 mb-0">
                        <i class="bi bi-camera-video-fill me-2 text-danger"></i>
                        البثوث المباشرة
                    </h1>
                </div>
                <div class="col-auto">
                    <Link :href="route('teacher.live-streams.create')" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg me-1"></i> بث جديد
                    </Link>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-3 mb-4">
                <div class="col-sm-4">
                    <div class="card border-0 bg-primary bg-opacity-10 h-100">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="p-3 rounded-circle bg-primary bg-opacity-20">
                                <i class="bi bi-collection-play fs-4 text-primary"></i>
                            </div>
                            <div>
                                <div class="fs-3 fw-bold text-primary">{{ streams.length }}</div>
                                <div class="small text-muted">إجمالي البثوث</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="card border-0 bg-danger bg-opacity-10 h-100">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="p-3 rounded-circle bg-danger bg-opacity-20">
                                <i class="bi bi-broadcast fs-4 text-danger"></i>
                            </div>
                            <div>
                                <div class="fs-3 fw-bold text-danger">{{ streams.filter(s => s.status === 'live').length }}</div>
                                <div class="small text-muted">مباشر الآن</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="card border-0 bg-success bg-opacity-10 h-100">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="p-3 rounded-circle bg-success bg-opacity-20">
                                <i class="bi bi-people fs-4 text-success"></i>
                            </div>
                            <div>
                                <div class="fs-3 fw-bold text-success">
                                    {{ streams.reduce((sum, s) => sum + s.attendances_count, 0) }}
                                </div>
                                <div class="small text-muted">إجمالي الحضور</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search -->
            <div class="mb-3">
                <div class="input-group" style="max-width: 400px;">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input v-model="search" type="text" class="form-control" placeholder="بحث بالعنوان أو المعلم أو المادة..." />
                </div>
            </div>

            <!-- Table -->
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>#</th>
                                    <th>عنوان البث</th>
                                    <th>المعلم</th>
                                    <th>المادة</th>
                                    <th>وقت البدء</th>
                                    <th>وقت الانتهاء</th>
                                    <th>الحالة</th>
                                    <th>المنصة</th>
                                    <th>الحضور</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="filteredStreams.length === 0">
                                    <td colspan="10" class="text-center py-5 text-muted">
                                        <i class="bi bi-camera-video-off fs-1 d-block mb-2"></i>
                                        لا توجد بثوث مباشرة
                                    </td>
                                </tr>
                                <tr v-for="(stream, index) in filteredStreams" :key="stream.id">
                                    <td class="text-muted small">{{ index + 1 }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ stream.title }}</div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar avatar-sm bg-primary bg-opacity-20 rounded-circle d-flex align-items-center justify-content-center" style="width:32px;height:32px;">
                                                <i class="bi bi-person text-primary small"></i>
                                            </div>
                                            <span>{{ stream.teacher_name }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span v-if="stream.subject" class="badge bg-light text-dark border">{{ stream.subject }}</span>
                                        <span v-else class="text-muted small">—</span>
                                    </td>
                                    <td class="small">{{ stream.start_datetime }}</td>
                                    <td class="small">{{ stream.end_datetime || '—' }}</td>
                                    <td>
                                        <span class="badge" :class="statusLabel[stream.status]?.class || 'bg-secondary'">
                                            <i v-if="stream.status === 'live'" class="bi bi-broadcast me-1"></i>
                                            {{ statusLabel[stream.status]?.label || stream.status }}
                                        </span>
                                        <div
                                            v-if="stream.status === 'live' && getRemaining(stream) !== null"
                                            class="small mt-1 font-monospace d-flex align-items-center gap-1"
                                            :class="getRemaining(stream) <= 30 ? 'text-danger fw-bold' : 'text-warning'"
                                        >
                                            <i class="bi bi-hourglass-split small"></i>
                                            {{ formatCountdown(getRemaining(stream)) }}
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge" :class="providerLabel[stream.provider || 'none']?.class">
                                            <i class="bi me-1" :class="providerLabel[stream.provider || 'none']?.icon"></i>
                                            {{ providerLabel[stream.provider || 'none']?.label }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success bg-opacity-15 text-success border border-success border-opacity-25">
                                            <i class="bi bi-people me-1"></i>{{ stream.attendances_count }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1 flex-wrap">
                                            <!-- ابدأ البث -->
                                            <div v-if="stream.status === 'scheduled'" class="d-flex flex-column align-items-start gap-1">
                                                <button
                                                    @click="canStart(stream) && startStream(stream)"
                                                    :disabled="!canStart(stream)"
                                                    :class="canStart(stream) ? 'btn-success' : 'btn-danger'"
                                                    class="btn btn-sm"
                                                    :title="canStart(stream) ? 'ابدأ البث' : 'لم يحن وقت البدء بعد'"
                                                >
                                                    <i :class="canStart(stream) ? 'bi-play-fill' : 'bi-lock-fill'" class="bi me-1"></i>ابدأ
                                                </button>
                                                <span v-if="!canStart(stream)" class="text-danger font-monospace" style="font-size:11px;">
                                                    ⏳ {{ formatTimeUntilStart(secondsUntilStart(stream)) }}
                                                </span>
                                            </div>
                                            <!-- مباشر الآن: زر الانضمام + إنهاء -->
                                            <template v-else-if="stream.status === 'live'">
                                                <Link
                                                    :href="route('teacher.live-streams.room', stream.id)"
                                                    class="btn btn-sm btn-danger"
                                                    title="ادخل غرفة البث"
                                                >
                                                    <i class="bi bi-broadcast me-1"></i>ادخل
                                                </Link>
                                                <button
                                                    @click="endStream(stream)"
                                                    class="btn btn-sm btn-outline-secondary"
                                                    title="إنهاء البث"
                                                >
                                                    <i class="bi bi-stop-fill"></i>
                                                </button>
                                            </template>
                                            <!-- التفاصيل -->
                                            <Link
                                                :href="route('teacher.live-streams.show', stream.id)"
                                                class="btn btn-sm btn-outline-info"
                                                title="التفاصيل والحضور"
                                            >
                                                <i class="bi bi-eye"></i>
                                            </Link>
                                            <!-- رابط انضمام الطلاب -->
                                            <button
                                                @click="copyGuestLink(stream)"
                                                class="btn btn-sm btn-outline-secondary"
                                                :title="copiedId === stream.id ? 'تم النسخ!' : 'نسخ رابط الطلاب'"
                                            >
                                                <i class="bi" :class="copiedId === stream.id ? 'bi-check-lg text-success' : 'bi-share'"></i>
                                            </button>
                                            <Link
                                                v-if="stream.status !== 'live'"
                                                :href="route('teacher.live-streams.edit', stream.id)"
                                                class="btn btn-sm btn-outline-warning"
                                                title="تعديل"
                                            >
                                                <i class="bi bi-pencil"></i>
                                            </Link>
                                            <button
                                                v-if="stream.status !== 'live'"
                                                @click="confirmDelete(stream)"
                                                class="btn btn-sm btn-outline-danger"
                                                title="حذف"
                                            >
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
