<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { toast } from 'vue3-toastify'
import Swal from 'sweetalert2'

const props = defineProps({
    stream: Object,
    attendances: Array,
    attendances_count: Number,
    quizzes: { type: Array, default: () => [] },
})

const syncing = ref(false)
const search = ref('')

// ─── Recording / YouTube URL ──────────────────────────────────────────────────
const showYoutubeForm = ref(false)
const youtubeForm = useForm({ video_url: props.stream.video_url || '' })

function submitYoutubeUrl() {
    youtubeForm.patch(route('admin.live-streams.video-url', props.stream.id), {
        onSuccess: () => {
            toast.success('تم حفظ رابط الفيديو')
            showYoutubeForm.value = false
        },
        onError: () => toast.error('تحقق من الرابط'),
    })
}

function copyWatchLink() {
    navigator.clipboard.writeText(props.stream.watch_url)
    toast.success('تم نسخ الرابط')
}

// ── Quiz results tab ──────────────────────────────────────────────────────────
const activeTab      = ref('attendance')  // 'attendance' | 'quizzes'
const expandedQuiz   = ref(null)

function toggleQuiz(id) {
    expandedQuiz.value = expandedQuiz.value === id ? null : id
}

const quizTypeLabels = {
    true_false:            '✅ صح/غلط',
    true_false_correction: '✅ صح/غلط + تصحيح',
    fill_blank:            '✏️ أكمل الفراغ',
    multiple_choice:       '🔘 اختيار متعدد',
    essay:                 '📝 مقالي',
    pdf_exam:              '📄 امتحان PDF',
}

function getQuizTypeLabel(type) {
    return quizTypeLabels[type] ?? type
}

function getAnswerDisplay(quiz, answer) {
    if (quiz.question_type === 'true_false' || quiz.question_type === 'true_false_correction') {
        return answer === 'true' ? '✅ صح' : '❌ غلط'
    }
    if (quiz.question_type === 'multiple_choice' && quiz.options) {
        try {
            const parsed = JSON.parse(answer)
            if (Array.isArray(parsed)) return parsed.map(i => quiz.options[i] ?? i).join('، ')
        } catch {}
        return quiz.options[answer] ?? answer
    }
    return answer
}

const filteredAttendances = computed(() => {
    if (!search.value) return props.attendances
    const q = search.value.toLowerCase()
    return props.attendances.filter(a =>
        a.student_name.toLowerCase().includes(q) ||
        (a.student_email && a.student_email.toLowerCase().includes(q))
    )
})

const statusConfig = {
    scheduled: { label: 'مجدول', class: 'bg-primary', icon: 'bi-calendar-event' },
    live:      { label: 'مباشر الآن', class: 'bg-danger', icon: 'bi-broadcast' },
    ended:     { label: 'انتهى', class: 'bg-secondary', icon: 'bi-check-circle' },
}

const providerConfig = {
    none:        { label: 'Jitsi مدمج',      icon: 'bi-broadcast',          color: 'secondary' },
    teams:       { label: 'Microsoft Teams',  icon: 'bi-camera-video',       color: 'primary' },
    zoom:        { label: 'Zoom',             icon: 'bi-camera-video-fill',  color: 'info' },
    livekit:     { label: 'LiveKit',          icon: 'bi-broadcast-pin',      color: 'success' },
    google_meet: { label: 'Google Meet',      icon: 'bi-camera-video',       color: 'danger' },
    hms:         { label: '100ms غرفة مدمجة', icon: 'bi-grid-3x3-gap-fill',  color: 'primary' },
}

const hasMeetingLink = computed(() =>
    (props.stream.provider === 'teams'       && props.stream.teams_meeting_id) ||
    (props.stream.provider === 'zoom'        && props.stream.zoom_meeting_id) ||
    (props.stream.provider === 'livekit'     && props.stream.livekit_room_name) ||
    (props.stream.provider === 'google_meet' && props.stream.google_meet_space_name) ||
    (props.stream.provider === 'hms'         && props.stream.hms_room_id)
)

const totalAttendanceSeconds = computed(() =>
    props.attendances.reduce((sum, a) => sum + (a.duration_seconds || 0), 0)
)

function formatDuration(seconds) {
    if (!seconds) return '—'
    const h = Math.floor(seconds / 3600)
    const m = Math.floor((seconds % 3600) / 60)
    const s = seconds % 60
    if (h > 0) return `${h}س ${m}د ${s}ث`
    if (m > 0) return `${m}د ${s}ث`
    return `${s}ث`
}

function syncAttendance() {
    if (!hasMeetingLink.value) {
        toast.warning('لا يوجد اجتماع مرتبط بهذا البث')
        return
    }

    Swal.fire({
        title: 'مزامنة بيانات الحضور',
        text: 'سيتم جلب بيانات الحضور من Microsoft Teams وتحديث السجلات الحالية.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'نعم، مزامنة',
        cancelButtonText: 'إلغاء',
        confirmButtonColor: '#0d6efd',
    }).then((result) => {
        if (result.isConfirmed) {
            syncing.value = true
            router.post(route('admin.live-streams.sync-attendance', props.stream.id), {}, {
                onSuccess: () => toast.success('تمت المزامنة بنجاح'),
                onError: (errors) => toast.error(errors.sync || 'فشل جلب بيانات الحضور'),
                onFinish: () => { syncing.value = false },
            })
        }
    })
}

function copyLink() {
    const url = props.stream.provider === 'livekit'
        ? (props.stream.livekit_teacher_url || props.stream.join_url)
        : props.stream.join_url
    if (url) {
        navigator.clipboard.writeText(url)
        toast.success('تم نسخ رابط الانضمام')
    }
}

const updatingStatus = ref(false)

function startStream() {
    // Navigate to the room page (controller handles status change automatically)
    router.visit(route('admin.live-streams.room', props.stream.id))
}

function endStream() {
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
            updatingStatus.value = true
            router.patch(route('admin.live-streams.update-status', props.stream.id), { status: 'ended' }, {
                onSuccess: () => toast.success('تم إنهاء البث'),
                onError: () => toast.error('حدث خطأ'),
                onFinish: () => { updatingStatus.value = false },
            })
        }
    })
}
</script>

<template>
    <Head :title="`بث: ${stream.title}`" />
    <AppLayout>
        <div class="page-content-wrapper border">
            <!-- Header -->
            <div class="row mb-4 align-items-start">
                <div class="col">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-1">
                            <li class="breadcrumb-item">
                                <Link :href="route('admin.live-streams.index')">البثوث المباشرة</Link>
                            </li>
                            <li class="breadcrumb-item active">{{ stream.title }}</li>
                        </ol>
                    </nav>
                    <h1 class="h3 mb-1">{{ stream.title }}</h1>
                    <span class="badge fs-6" :class="statusConfig[stream.status]?.class">
                        <i class="bi me-1" :class="statusConfig[stream.status]?.icon"></i>
                        {{ statusConfig[stream.status]?.label }}
                    </span>
                </div>
                <div class="col-auto d-flex gap-2 flex-wrap justify-content-end">
                    <Link
                        :href="route('admin.live-streams.index')"
                        class="btn btn-outline-secondary btn-sm"
                    >
                        <i class="bi bi-arrow-right me-1"></i>رجوع للبثوث
                    </Link>
                </div>
            </div>

            <div class="row g-4">
                <!-- Stream Info Card -->
                <div class="col-lg-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header border-bottom">
                            <h6 class="card-header-title mb-0">
                                <i class="bi bi-info-circle me-2"></i>تفاصيل البث
                            </h6>
                        </div>
                        <div class="card-body">
                            <dl class="row g-2 mb-0">
                                <dt class="col-5 text-muted small">المعلم</dt>
                                <dd class="col-7 mb-0">
                                    <span class="fw-semibold">{{ stream.teacher_name }}</span>
                                    <div v-if="stream.teacher_email" class="small text-muted">{{ stream.teacher_email }}</div>
                                </dd>

                                <dt class="col-5 text-muted small">المادة</dt>
                                <dd class="col-7 mb-0">
                                    <span v-if="stream.subject" class="badge bg-light text-dark border">{{ stream.subject }}</span>
                                    <span v-else class="text-muted">—</span>
                                </dd>

                                <dt class="col-5 text-muted small">وقت البدء</dt>
                                <dd class="col-7 mb-0 small">{{ stream.start_datetime }}</dd>

                                <dt class="col-5 text-muted small">وقت الانتهاء</dt>
                                <dd class="col-7 mb-0 small">{{ stream.end_datetime || '—' }}</dd>

                                <dt class="col-5 text-muted small">المنصة</dt>
                                <dd class="col-7 mb-0">
                                    <span class="badge" :class="stream.provider === 'teams' ? 'bg-primary' : stream.provider === 'zoom' ? 'bg-info' : 'bg-secondary'">
                                        <i class="bi me-1" :class="providerConfig[stream.provider || 'none']?.icon"></i>
                                        {{ providerConfig[stream.provider || 'none']?.label }}
                                    </span>
                                </dd>

                                <dt class="col-5 text-muted small">الوصف</dt>
                                <dd class="col-7 mb-0 small">{{ stream.description || '—' }}</dd>
                            </dl>


                        </div>
                    </div>
                </div>

                <!-- ── Recording Card ──────────────────────────────── -->
                <div v-if="stream.status === 'ended'" class="col-lg-8">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header border-bottom">
                            <h6 class="card-header-title mb-0">
                                <i class="bi bi-camera-video me-2 text-danger"></i>الفيديو المسجّل
                            </h6>
                        </div>
                        <div class="card-body">

                            <!-- Server recording ready -->
                            <template v-if="stream.recording_type === 'server' && stream.recording_status === 'ready'">
                                <div class="d-flex align-items-center gap-3 flex-wrap">
                                    <span class="badge bg-success fs-6">
                                        <i class="bi bi-check-circle me-1"></i>
                                        مسجّل على السيرفر
                                        <span v-if="stream.recording_size_mb"> · {{ stream.recording_size_mb.toFixed(1) }} MB</span>
                                    </span>
                                    <a :href="stream.watch_url" target="_blank" class="btn btn-sm btn-primary">
                                        <i class="bi bi-play-circle me-1"></i>مشاهدة
                                    </a>
                                    <button @click="copyWatchLink" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-link-45deg me-1"></i>نسخ رابط الطلاب
                                    </button>
                                </div>
                            </template>

                            <!-- YouTube URL saved -->
                            <template v-else-if="stream.recording_type === 'local' && stream.video_url">
                                <div class="d-flex align-items-center gap-3 flex-wrap mb-3">
                                    <span class="badge bg-danger fs-6">
                                        <i class="bi bi-youtube me-1"></i>YouTube
                                    </span>
                                    <a :href="stream.watch_url" target="_blank" class="btn btn-sm btn-primary">
                                        <i class="bi bi-play-circle me-1"></i>مشاهدة
                                    </a>
                                    <button @click="copyWatchLink" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-link-45deg me-1"></i>نسخ رابط الطلاب
                                    </button>
                                    <button @click="showYoutubeForm = !showYoutubeForm" class="btn btn-sm btn-outline-warning">
                                        <i class="bi bi-pencil me-1"></i>تعديل الرابط
                                    </button>
                                </div>
                                <div v-if="showYoutubeForm" class="mt-2">
                                    <div class="input-group" style="max-width:480px;">
                                        <input v-model="youtubeForm.video_url" type="url"
                                            class="form-control form-control-sm"
                                            :class="{ 'is-invalid': youtubeForm.errors.video_url }"
                                            placeholder="https://www.youtube.com/watch?v=..." dir="ltr" />
                                        <button @click="submitYoutubeUrl" :disabled="youtubeForm.processing"
                                            class="btn btn-sm btn-success">
                                            <span v-if="youtubeForm.processing" class="spinner-border spinner-border-sm"></span>
                                            <span v-else>حفظ</span>
                                        </button>
                                    </div>
                                    <div v-if="youtubeForm.errors.video_url" class="invalid-feedback d-block small">{{ youtubeForm.errors.video_url }}</div>
                                </div>
                            </template>

                            <!-- No recording -->
                            <template v-else>
                                <p class="text-muted small mb-3">
                                    لا يوجد تسجيل لهذا البث بعد. يمكن للمدرس إضافة رابط YouTube من صفحته الخاصة.
                                </p>
                                <div v-if="!showYoutubeForm">
                                    <button @click="showYoutubeForm = true" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-youtube me-1"></i>إضافة رابط YouTube (كأدمن)
                                    </button>
                                </div>
                                <div v-else class="d-flex gap-2 align-items-start flex-wrap" style="max-width:480px;">
                                    <div class="flex-grow-1">
                                        <input v-model="youtubeForm.video_url" type="url"
                                            class="form-control form-control-sm"
                                            :class="{ 'is-invalid': youtubeForm.errors.video_url }"
                                            placeholder="https://www.youtube.com/watch?v=..." dir="ltr" />
                                        <div v-if="youtubeForm.errors.video_url" class="invalid-feedback">{{ youtubeForm.errors.video_url }}</div>
                                    </div>
                                    <button @click="submitYoutubeUrl" :disabled="youtubeForm.processing"
                                        class="btn btn-sm btn-success">
                                        <span v-if="youtubeForm.processing" class="spinner-border spinner-border-sm"></span>
                                        <span v-else>حفظ الرابط</span>
                                    </button>
                                    <button @click="showYoutubeForm = false" class="btn btn-sm btn-outline-secondary">إلغاء</button>
                                </div>
                            </template>

                        </div>
                    </div>
                </div>

                <!-- Attendance Section -->
                <div class="col-lg-8">
                    <!-- Stats -->
                    <div class="row g-3 mb-3">
                        <div class="col-sm-4">
                            <div class="card border-0 bg-success bg-opacity-10 text-center py-3">
                                <div class="fs-2 fw-bold text-success">{{ attendances_count }}</div>
                                <div class="small text-muted">إجمالي الحضور</div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="card border-0 bg-info bg-opacity-10 text-center py-3">
                                <div class="fs-2 fw-bold text-info">
                                    {{ attendances.filter(a => a.duration_seconds >= 300).length }}
                                </div>
                                <div class="small text-muted">حضروا +5 دقائق</div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="card border-0 bg-warning bg-opacity-10 text-center py-3">
                                <div class="fs-5 fw-bold text-warning">
                                    {{ formatDuration(attendances_count > 0 ? Math.round(totalAttendanceSeconds / attendances_count) : 0) }}
                                </div>
                                <div class="small text-muted">متوسط مدة الحضور</div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabs -->
                    <ul class="nav nav-tabs mb-3">
                        <li class="nav-item">
                            <button
                                @click="activeTab = 'attendance'"
                                class="nav-link"
                                :class="{ active: activeTab === 'attendance' }"
                            ><i class="bi bi-people me-1"></i>سجل الحضور</button>
                        </li>
                        <li class="nav-item">
                            <button
                                @click="activeTab = 'quizzes'"
                                class="nav-link d-flex align-items-center gap-1"
                                :class="{ active: activeTab === 'quizzes' }"
                            >
                                <i class="bi bi-patch-question"></i>
                                نتائج الأسئلة
                                <span v-if="quizzes.length" class="badge bg-primary" style="font-size:10px;">{{ quizzes.length }}</span>
                            </button>
                        </li>
                    </ul>

                    <!-- Attendance Table -->
                    <div v-if="activeTab === 'attendance'" class="card shadow-sm">
                        <div class="card-header border-bottom d-flex align-items-center justify-content-between">
                            <h6 class="card-header-title mb-0">
                                <i class="bi bi-people me-2"></i>
                                سجل حضور الطلاب
                            </h6>
                            <div class="input-group" style="max-width: 250px;">
                                <span class="input-group-text py-1 px-2 small"><i class="bi bi-search"></i></span>
                                <input v-model="search" type="text" class="form-control form-control-sm" placeholder="بحث باسم الطالب..." />
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>اسم الطالب</th>
                                            <th>البريد الإلكتروني</th>
                                            <th>وقت الدخول</th>
                                            <th>وقت الخروج</th>
                                            <th>مدة الحضور</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="filteredAttendances.length === 0">
                                            <td colspan="6" class="text-center py-5 text-muted">
                                                <i class="bi bi-person-x fs-2 d-block mb-2"></i>
                                                <span v-if="!hasMeetingLink">
                                                    هذا البث غير مرتبط بمنصة. لا يمكن مزامنة الحضور تلقائياً.
                                                </span>
                                                <span v-else>
                                                    لا توجد بيانات حضور بعد.
                                                    <button @click="syncAttendance" class="btn btn-link btn-sm p-0 ms-1">مزامنة الآن</button>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr v-for="(attendance, index) in filteredAttendances" :key="attendance.id">
                                            <td class="text-muted small">{{ index + 1 }}</td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="rounded-circle bg-primary bg-opacity-15 d-flex align-items-center justify-content-center text-primary fw-bold" style="width:34px;height:34px;font-size:13px;">
                                                        {{ attendance.student_name.charAt(0).toUpperCase() }}
                                                    </div>
                                                    <span class="fw-semibold">{{ attendance.student_name }}</span>
                                                </div>
                                            </td>
                                            <td class="small text-muted">{{ attendance.student_email || '—' }}</td>
                                            <td>
                                                <span v-if="attendance.join_time" class="badge bg-success bg-opacity-15 text-success">
                                                    <i class="bi bi-box-arrow-in-right me-1"></i>{{ attendance.join_time }}
                                                </span>
                                                <span v-else class="text-muted">—</span>
                                            </td>
                                            <td>
                                                <span v-if="attendance.leave_time" class="badge bg-danger bg-opacity-15 text-danger">
                                                    <i class="bi bi-box-arrow-right me-1"></i>{{ attendance.leave_time }}
                                                </span>
                                                <span v-else class="text-muted">—</span>
                                            </td>
                                            <td>
                                                <span
                                                    class="badge"
                                                    :class="attendance.duration_seconds >= 300 ? 'bg-success' : (attendance.duration_seconds > 0 ? 'bg-warning text-dark' : 'bg-secondary')"
                                                >
                                                    <i class="bi bi-clock me-1"></i>
                                                    {{ formatDuration(attendance.duration_seconds) }}
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Quiz Results Tab -->
                    <div v-if="activeTab === 'quizzes'">
                        <div v-if="!quizzes.length" class="card shadow-sm">
                            <div class="card-body text-center py-5 text-muted">
                                <i class="bi bi-patch-question fs-2 d-block mb-2"></i>
                                لا توجد أسئلة لهذا البث بعد
                            </div>
                        </div>

                        <div v-for="(quiz, qi) in quizzes" :key="quiz.id" class="card shadow-sm mb-3">
                            <div
                                class="card-header d-flex align-items-center justify-content-between"
                                style="cursor:pointer;"
                                @click="toggleQuiz(quiz.id)"
                            >
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-secondary bg-opacity-75" style="font-size:11px;">{{ qi + 1 }}</span>
                                    <span class="badge bg-light text-dark border" style="font-size:11px;">{{ getQuizTypeLabel(quiz.question_type) }}</span>
                                    <span class="fw-semibold small">{{ quiz.question_text }}</span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-primary bg-opacity-15 text-primary" style="font-size:11px;">
                                        {{ quiz.answers.length }} إجابة
                                    </span>
                                    <span v-if="quiz.correct_answer !== null && quiz.answers.length" class="badge bg-success bg-opacity-15 text-success" style="font-size:11px;">
                                        {{ quiz.answers.filter(a => a.is_correct).length }} صحيح
                                    </span>
                                    <i class="bi" :class="expandedQuiz === quiz.id ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                                </div>
                            </div>

                            <div v-if="expandedQuiz === quiz.id" class="card-body p-0">
                                <!-- PDF attachment link -->
                                <div v-if="quiz.question_type === 'pdf_exam' && quiz.attachment_url" class="px-3 pt-3">
                                    <a :href="quiz.attachment_url" target="_blank" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-file-earmark-pdf me-1"></i>عرض الامتحان PDF
                                    </a>
                                </div>

                                <div v-if="!quiz.answers.length" class="text-center py-4 text-muted small">
                                    <i class="bi bi-inbox d-block mb-1 fs-4"></i>لا توجد إجابات
                                </div>

                                <div v-else class="table-responsive">
                                    <table class="table table-sm table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>اسم الطالب</th>
                                                <th>الإجابة</th>
                                                <th v-if="quiz.question_type === 'true_false_correction'">التصحيح</th>
                                                <th v-if="quiz.correct_answer !== null">النتيجة</th>
                                                <th>وقت الإجابة</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(ans, ai) in quiz.answers" :key="ans.id">
                                                <td class="text-muted small">{{ ai + 1 }}</td>
                                                <td class="fw-semibold small">{{ ans.student_name }}</td>
                                                <td class="small">{{ getAnswerDisplay(quiz, ans.answer) }}</td>
                                                <td v-if="quiz.question_type === 'true_false_correction'" class="small text-muted">{{ ans.correction || '—' }}</td>
                                                <td v-if="quiz.correct_answer !== null">
                                                    <span v-if="ans.is_correct === true" class="badge bg-success">صحيح ✓</span>
                                                    <span v-else-if="ans.is_correct === false" class="badge bg-danger">خطأ ✗</span>
                                                    <span v-else class="text-muted">—</span>
                                                </td>
                                                <td class="small text-muted">{{ ans.submitted_at }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
