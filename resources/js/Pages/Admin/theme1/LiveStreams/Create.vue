<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { toast } from 'vue3-toastify'

const props = defineProps({
    teamsConfigured:      Boolean,
    zoomConfigured:       Boolean,
    livekitConfigured:    Boolean,
    googleMeetConfigured: Boolean,
    hmsConfigured:        Boolean,
    authUser:             Object,
    maxDuration:          { type: Number, default: 60 },
})

const form = ref({
    title:               '',
    description:         '',
    teacher_name:        props.authUser?.name  || '',
    teacher_email:       props.authUser?.email || '',
    subject:             '',
    provider:            'livekit',
    classroom_dashboard: 'livekit',
    start_datetime:      '',
    extra_session:       false,
})

const computedEndDatetime = computed(() => {
    if (!form.value.start_datetime) return null
    const start = new Date(form.value.start_datetime)
    if (isNaN(start.getTime())) return null
    const end = new Date(start.getTime() + props.maxDuration * 60000)
    return end.toLocaleString('ar-EG', { dateStyle: 'medium', timeStyle: 'short' })
})

const durationLabel = computed(() => {
    const h = Math.floor(props.maxDuration / 60)
    const m = props.maxDuration % 60
    if (h > 0 && m > 0) return `${h} ساعة و${m} دقيقة`
    if (h > 0) return `${h} ساعة`
    return `${m} دقيقة`
})

const errors = ref({})
const saving = ref(false)

const providerStatus = computed(() => ({
    teams:       props.teamsConfigured,
    zoom:        props.zoomConfigured,
    livekit:     props.livekitConfigured,
    google_meet: props.googleMeetConfigured,
    hms:         props.hmsConfigured,
    none:        true,
}))

const providers = [
    {
        id:    'livekit',
        label: 'LiveKit',
        desc:  'إنشاء غرفة LiveKit مباشرة',
        icon:  'bi-broadcast-pin',
        color: 'success',
    },
    {
        id:    'teams',
        label: 'Microsoft Teams',
        desc:  'إنشاء اجتماع Teams تلقائياً',
        icon:  'bi-camera-video',
        color: 'info',
    },
    {
        id:    'zoom',
        label: 'Zoom',
        desc:  'إنشاء اجتماع Zoom تلقائياً',
        icon:  'bi-camera-video-fill',
        color: 'info',
    },
    {
        id:    'google_meet',
        label: 'Google Meet',
        desc:  'إنشاء اجتماع Google Meet تلقائياً',
        icon:  'bi-camera-video',
        color: 'danger',
    },
]

const dashboards = [
    {
        id:    'jitsi',
        label: 'Jitsi',
        desc:  'مجاني، بدون إعداد',
        icon:  'bi-broadcast',
        color: 'secondary',
        configKey: null,
    },
    {
        id:       'livekit',
        label:    'LiveKit',
        desc:     'أداء عالٍ، تحكم كامل',
        icon:     'bi-broadcast-pin',
        color:    'success',
        configKey: 'livekit',
    },
    {
        id:       'hms',
        label:    '100ms',
        desc:     'Teams-like: فيديو + شاشة + رفع يد',
        icon:     'bi-grid-3x3-gap-fill',
        color:    'primary',
        badge:    '⭐ موصى به',
        configKey: 'hms',
    },
]

const dashboardStatus = computed(() => ({
    jitsi:   true,
    livekit: props.livekitConfigured,
    hms:     props.hmsConfigured,
}))

function submit() {
    saving.value = true
    errors.value = {}

    if (form.value.start_datetime && new Date(form.value.start_datetime) <= new Date()) {
        errors.value.start_datetime = 'يجب أن يكون تاريخ البدء في المستقبل — لا يمكن إنشاء بث بتاريخ ماضٍ.'
        toast.error(errors.value.start_datetime)
        saving.value = false
        return
    }

    router.post(route('admin.live-streams.store'), form.value, {
        onSuccess: () => {
            toast.success('تم إنشاء البث المباشر بنجاح')
        },
        onError: (errs) => {
            errors.value = errs
            if (errs.provider) {
                toast.error(errs.provider)
            } else {
                toast.error('يرجى مراجعة البيانات المدخلة')
            }
        },
        onFinish: () => { saving.value = false },
    })
}
</script>

<template>
    <Head title="إنشاء بث مباشر" />
    <AppLayout>
        <div class="page-content-wrapper border">
            <!-- Header -->
            <div class="row mb-4 align-items-center">
                <div class="col">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-1">
                            <li class="breadcrumb-item">
                                <Link :href="route('admin.live-streams.index')">البثوث المباشرة</Link>
                            </li>
                            <li class="breadcrumb-item active">بث جديد</li>
                        </ol>
                    </nav>
                    <h1 class="h3 mb-0">
                        <i class="bi bi-broadcast me-2 text-danger"></i>
                        إنشاء بث مباشر جديد
                    </h1>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header border-bottom">
                    <h5 class="card-header-title mb-0">بيانات البث</h5>
                </div>
                <div class="card-body">
                    <form @submit.prevent="submit" class="row g-4">

                        <!-- ── منصة البث (external platform) ─────────────────── -->
                        <div class="col-12">
                            <label class="form-label fw-semibold mb-2">
                                منصة البث الخارجية <span class="text-danger">*</span>
                            </label>
                            <p class="text-muted small mb-2">رابط الاجتماع الخارجي (Zoom / Teams / Meet) — اختر "بدون منصة" إذا لم تحتج رابطاً خارجياً.</p>
                            <div class="row g-3">
                                <div v-for="p in providers" :key="p.id" class="col-md-3">
                                    <div
                                        class="card h-100 border-2"
                                        :class="[
                                            form.provider === p.id ? `border-${p.color} bg-${p.color} bg-opacity-10` : 'border-light',
                                            !providerStatus[p.id] ? 'opacity-50' : '',
                                        ]"
                                        :style="providerStatus[p.id] ? 'cursor:pointer' : 'cursor:not-allowed'"
                                        @click="providerStatus[p.id] && (form.provider = p.id)"
                                    >
                                        <div class="card-body d-flex align-items-center gap-3 p-3">
                                            <div
                                                class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                                :class="`bg-${p.color} bg-opacity-20`"
                                                style="width:40px;height:40px;"
                                            >
                                                <i class="bi fs-5" :class="[p.icon, `text-${p.color}`]"></i>
                                            </div>
                                            <div class="flex-grow-1 min-w-0">
                                                <div class="fw-semibold small">{{ p.label }}</div>
                                                <div class="text-muted" style="font-size:11px;">{{ p.desc }}</div>
                                                <span
                                                    v-if="p.id !== 'none'"
                                                    class="badge mt-1"
                                                    :class="providerStatus[p.id] ? 'bg-success' : 'bg-danger'"
                                                    style="font-size:10px;"
                                                >
                                                    {{ providerStatus[p.id] ? 'مُعدَّد' : 'غير مُعدَّد' }}
                                                </span>
                                            </div>
                                            <i
                                                v-if="form.provider === p.id"
                                                class="bi bi-check-circle-fill flex-shrink-0"
                                                :class="`text-${p.color}`"
                                            ></i>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="form.provider === 'livekit' && !livekitConfigured" class="alert alert-warning mt-2 py-2 small">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                LiveKit غير مُعدَّد.
                                <Link :href="route('admin.settings.livekit')" class="alert-link">إعداد LiveKit</Link>
                            </div>
                            <div v-if="form.provider === 'teams' && !teamsConfigured" class="alert alert-warning mt-2 py-2 small">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                Teams غير مُعدَّد.
                                <Link :href="route('admin.settings.teams')" class="alert-link">إعداد Teams</Link>
                            </div>
                            <div v-if="form.provider === 'zoom' && !zoomConfigured" class="alert alert-warning mt-2 py-2 small">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                Zoom غير مُعدَّد.
                                <Link :href="route('admin.settings.zoom')" class="alert-link">إعداد Zoom</Link>
                            </div>
                            <div v-if="form.provider === 'google_meet' && !googleMeetConfigured" class="alert alert-warning mt-2 py-2 small">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                Google Meet غير مُعدَّد.
                                <Link :href="route('admin.settings.google-meet')" class="alert-link">إعداد Google Meet</Link>
                            </div>
                            <div v-if="errors.provider" class="alert alert-danger mt-2 py-2 small">
                                <i class="bi bi-exclamation-circle me-1"></i>{{ errors.provider }}
                            </div>
                        </div>

                        <!-- ── داش بورد الفصل الدراسي ────────────────────────── -->
                        <div class="col-12">
                            <label class="form-label fw-semibold mb-2">
                                داش بورد الفصل الدراسي <span class="text-danger">*</span>
                            </label>
                            <p class="text-muted small mb-2">الغرفة التفاعلية التي سيستخدمها المدرس والطلاب (فيديو، ميكروفون، شات، شير سكرين).</p>
                            <div class="row g-3">
                                <div v-for="d in dashboards" :key="d.id" class="col-md-4">
                                    <div
                                        class="card h-100 border-2"
                                        :class="[
                                            form.classroom_dashboard === d.id ? `border-${d.color} bg-${d.color} bg-opacity-10` : 'border-light',
                                            !dashboardStatus[d.id] ? 'opacity-50' : '',
                                        ]"
                                        :style="dashboardStatus[d.id] ? 'cursor:pointer' : 'cursor:not-allowed'"
                                        @click="dashboardStatus[d.id] && (form.classroom_dashboard = d.id)"
                                    >
                                        <div class="card-body d-flex align-items-center gap-3 p-3">
                                            <div
                                                class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                                :class="`bg-${d.color} bg-opacity-20`"
                                                style="width:44px;height:44px;"
                                            >
                                                <i class="bi fs-4" :class="[d.icon, `text-${d.color}`]"></i>
                                            </div>
                                            <div class="flex-grow-1 min-w-0">
                                                <div class="fw-semibold small">{{ d.label }}</div>
                                                <div class="text-muted" style="font-size:11px;">{{ d.desc }}</div>
                                                <span v-if="d.badge" class="badge bg-warning text-dark mt-1" style="font-size:9px;">{{ d.badge }}</span>
                                                <span
                                                    v-if="d.configKey"
                                                    class="badge mt-1"
                                                    :class="dashboardStatus[d.id] ? 'bg-success' : 'bg-danger'"
                                                    style="font-size:10px;"
                                                >
                                                    {{ dashboardStatus[d.id] ? 'مُعدَّد' : 'غير مُعدَّد' }}
                                                </span>
                                                <span v-else class="badge bg-secondary mt-1" style="font-size:10px;">مجاني دائماً</span>
                                            </div>
                                            <i
                                                v-if="form.classroom_dashboard === d.id"
                                                class="bi bi-check-circle-fill flex-shrink-0"
                                                :class="`text-${d.color}`"
                                                style="font-size:1.2rem;"
                                            ></i>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="form.classroom_dashboard === 'livekit' && !livekitConfigured" class="alert alert-warning mt-2 py-2 small">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                LiveKit غير مُعدَّد.
                                <Link :href="route('admin.settings.livekit')" class="alert-link">إعداد LiveKit</Link>
                            </div>
                            <div v-if="form.classroom_dashboard === 'hms' && !hmsConfigured" class="alert alert-warning mt-2 py-2 small">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                100ms غير مُعدَّد.
                                <Link :href="route('admin.settings.hms')" class="alert-link">إعداد 100ms</Link>
                            </div>
                            <div v-if="form.classroom_dashboard === 'livekit' && livekitConfigured" class="alert alert-success mt-2 py-2 small">
                                <i class="bi bi-check-circle me-1"></i>
                                سيتم إنشاء غرفة LiveKit مخصصة مع تحكم كامل (كتم الجميع، شير سكرين، شات).
                            </div>
                            <div v-if="form.classroom_dashboard === 'hms' && hmsConfigured" class="alert alert-primary mt-2 py-2 small">
                                <i class="bi bi-info-circle me-1"></i>
                                سيتم إنشاء غرفة 100ms كاملة (فيديو + شاشة + رفع يد) داخل المنصة مباشرةً.
                            </div>
                            <div v-if="errors.classroom_dashboard" class="alert alert-danger mt-2 py-2 small">
                                <i class="bi bi-exclamation-circle me-1"></i>{{ errors.classroom_dashboard }}
                            </div>
                        </div>

                        <!-- Title -->
                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                عنوان البث <span class="text-danger">*</span>
                            </label>
                            <input
                                v-model="form.title"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': errors.title }"
                                placeholder="مثال: حصة الرياضيات - الصف الثالث"
                            />
                            <div v-if="errors.title" class="invalid-feedback">{{ errors.title }}</div>
                        </div>

                        <!-- Teacher Info (auto from auth user) -->
                        <div class="col-12">
                            <div class="d-flex align-items-center gap-3 p-3 rounded-3 border bg-light">
                                <div
                                    class="rounded-circle bg-primary d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0"
                                    style="width:44px;height:44px;font-size:16px;"
                                >
                                    {{ form.teacher_name?.charAt(0)?.toUpperCase() }}
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold">{{ form.teacher_name }}</div>
                                    <div class="text-muted small">{{ form.teacher_email }}</div>
                                </div>
                                <span class="badge bg-primary bg-opacity-15 text-primary border border-primary border-opacity-25">
                                    <i class="bi bi-person-check me-1"></i>المعلم المسجل
                                </span>
                            </div>
                        </div>

                        <!-- Subject -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">المادة الدراسية</label>
                            <input
                                v-model="form.subject"
                                type="text"
                                class="form-control"
                                placeholder="مثال: الرياضيات، العلوم، اللغة العربية..."
                            />
                        </div>

                        <!-- Start DateTime -->
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">
                                تاريخ ووقت البدء <span class="text-danger">*</span>
                            </label>
                            <input
                                v-model="form.start_datetime"
                                type="datetime-local"
                                class="form-control"
                                :class="{ 'is-invalid': errors.start_datetime }"
                            />
                            <div v-if="errors.start_datetime" class="invalid-feedback">{{ errors.start_datetime }}</div>
                        </div>

                        <!-- End DateTime (auto-computed from admin max duration) -->
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">تاريخ ووقت الانتهاء</label>
                            <div class="form-control bg-light text-muted d-flex align-items-center gap-2" style="min-height:38px;">
                                <i class="bi bi-clock-history text-danger"></i>
                                <span v-if="computedEndDatetime">{{ computedEndDatetime }}</span>
                                <span v-else class="fst-italic">يُحسب بعد تحديد وقت البدء</span>
                            </div>
                            <div class="form-text">
                                <i class="bi bi-info-circle me-1"></i>
                                مدة البث المحددة: <strong class="text-danger">{{ durationLabel }}</strong>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="col-12">
                            <label class="form-label fw-semibold">وصف البث</label>
                            <textarea
                                v-model="form.description"
                                class="form-control"
                                rows="3"
                                placeholder="وصف موضوع الحصة أو البث..."
                            ></textarea>
                        </div>

                        <!-- Extra Session Request -->
                        <div class="col-12">
                            <div
                                class="p-3 rounded-3 border d-flex align-items-center gap-3"
                                :class="form.extra_session ? 'border-warning bg-warning bg-opacity-10' : 'border-light bg-light'"
                                style="cursor:pointer"
                                @click="form.extra_session = !form.extra_session"
                            >
                                <div
                                    class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                    :class="form.extra_session ? 'bg-warning' : 'bg-secondary bg-opacity-25'"
                                    style="width:44px;height:44px;"
                                >
                                    <i class="bi bi-plus-circle-fill fs-5" :class="form.extra_session ? 'text-white' : 'text-secondary'"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold" :class="form.extra_session ? 'text-warning' : ''">
                                        <i class="bi bi-clock-history me-1"></i>
                                        طلب حصة إضافية
                                    </div>
                                    <div class="text-muted small">
                                        إذا كنت تحتاج لوقت أكثر من المدة المحددة ({{ durationLabel }})، سيُرسل طلبك للأدمن للموافقة عليه.
                                        عند الموافقة ستحصل على ضعف المدة.
                                    </div>
                                </div>
                                <div class="form-check form-switch mb-0 flex-shrink-0" @click.stop>
                                    <input
                                        v-model="form.extra_session"
                                        class="form-check-input"
                                        type="checkbox"
                                        role="switch"
                                        style="width:2.5rem;height:1.3rem;"
                                    />
                                </div>
                            </div>
                            <div v-if="form.extra_session" class="alert alert-warning py-2 small mt-2 mb-0 d-flex gap-2">
                                <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
                                سيُنشأ البث فوراً بضعف المدة ({{ durationLabel }} ×٢). لو رفض الأدمن، سيرجع البث للمدة الأصلية ({{ durationLabel }}) فقط.
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="col-12 d-flex justify-content-end gap-2 pt-2 border-top">
                            <Link :href="route('admin.live-streams.index')" class="btn btn-outline-secondary">
                                إلغاء
                            </Link>
                            <button
                                type="submit"
                                class="btn btn-primary"
                                :disabled="saving
                                    || (form.provider === 'livekit'     && !livekitConfigured)
                                    || (form.provider === 'teams'       && !teamsConfigured)
                                    || (form.provider === 'zoom'        && !zoomConfigured)
                                    || (form.provider === 'google_meet' && !googleMeetConfigured)
                                    || (form.classroom_dashboard === 'livekit' && !livekitConfigured)
                                    || (form.classroom_dashboard === 'hms'     && !hmsConfigured)"
                            >
                                <span v-if="saving" class="spinner-border spinner-border-sm me-2"></span>
                                <i v-else class="bi bi-broadcast me-2"></i>
                                إنشاء البث
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
