<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Pages/Teacher/Layout/App.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { toast } from 'vue3-toastify'

const props = defineProps({
    teamsConfigured:      Boolean,
    zoomConfigured:       Boolean,
    livekitConfigured:    Boolean,
    googleMeetConfigured: Boolean,
    hmsConfigured:        Boolean,
    maxDuration:          { type: Number, default: 60 },
    categories:           { type: Array, default: () => [] },
})

const selectedTopId = ref(null)
const selectedMidId = ref(null)
const selectedSectionId = ref(null)

const yearCategories = computed(() => {
    if (!selectedTopId.value) return []
    const top = props.categories.find(c => c.id === selectedTopId.value)
    return top?.children ?? []
})

const sectionCategories = computed(() => {
    if (!selectedMidId.value) return []
    const year = yearCategories.value.find(c => c.id === selectedMidId.value)
    return year?.children ?? []
})

const subSectionCategories = computed(() => {
    if (!selectedSectionId.value) return []
    const section = sectionCategories.value.find(c => c.id === selectedSectionId.value)
    return section?.children ?? []
})

const form = ref({
    title:               '',
    description:         '',
    learning_points:     [''],
    subject:             '',
    provider:            'teams',
    classroom_dashboard: 'livekit',
    start_datetime:      '',
    extra_session:       false,
    category_id:         null,
})

const thumbnailFile     = ref(null)
const thumbnailPreview  = ref(null)

function onThumbnailChange(e) {
    const file = e.target.files[0]
    if (!file) return
    thumbnailFile.value = file
    thumbnailPreview.value = URL.createObjectURL(file)
}

function onTopChange() {
    selectedMidId.value = null
    selectedSectionId.value = null
    form.value.category_id = selectedTopId.value
}

function onMidChange() {
    selectedSectionId.value = null
    form.value.category_id = selectedMidId.value
}

function onSectionChange() {
    form.value.category_id = selectedSectionId.value
}

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
    none:        true,
}))

const providers = [
    { id: 'livekit',     label: 'LiveKit',        desc: 'إنشاء غرفة LiveKit مباشرة',          icon: 'bi-broadcast-pin',     color: 'success' },
    { id: 'teams',       label: 'Microsoft Teams', desc: 'إنشاء اجتماع Teams تلقائياً',        icon: 'bi-camera-video',      color: 'info'    },
    { id: 'zoom',        label: 'Zoom',            desc: 'إنشاء اجتماع Zoom تلقائياً',         icon: 'bi-camera-video-fill', color: 'info'    },
    { id: 'google_meet', label: 'Google Meet',     desc: 'إنشاء اجتماع Google Meet تلقائياً', icon: 'bi-camera-video',      color: 'danger'  },
    { id: 'none',        label: 'Jitsi',           desc: 'مجاني، بدون إعداد',                  icon: 'bi-broadcast',         color: 'secondary'},
]

const dashboards = [
    { id: 'jitsi',  label: 'Jitsi',  desc: 'مجاني، بدون إعداد',               icon: 'bi-broadcast',         color: 'secondary', configKey: null     },
    { id: 'livekit',label: 'LiveKit',desc: 'أداء عالٍ، تحكم كامل',            icon: 'bi-broadcast-pin',     color: 'success',   configKey: 'livekit' },
    { id: 'hms',    label: '100ms',  desc: 'Teams-like: فيديو + شاشة + رفع يد', icon: 'bi-grid-3x3-gap-fill', color: 'primary',   configKey: 'hms', badge: '⭐ موصى به' },
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
        errors.value.start_datetime = 'يجب أن يكون تاريخ البدء في المستقبل.'
        toast.error(errors.value.start_datetime)
        saving.value = false
        return
    }

    router.post(route('teacher.live-streams.store'), {
        ...form.value,
        thumbnail: thumbnailFile.value,
    }, {
        onSuccess: () => toast.success('تم إنشاء البث بنجاح'),
        onError: (errs) => {
            errors.value = errs
            toast.error(errs.provider || errs.classroom_dashboard || 'يرجى مراجعة البيانات')
        },
        onFinish: () => { saving.value = false },
    })
}
</script>

<template>
    <Head title="بث مباشر جديد" />
    <AppLayout>
        <div class="page-content-wrapper border">

            <!-- Header -->
            <div class="row mb-4 align-items-center">
                <div class="col">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-1">
                            <li class="breadcrumb-item">
                                <Link :href="route('teacher.live-streams.index')">البثوث المباشرة</Link>
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

                        <!-- Provider -->
                        <div class="col-12">
                            <label class="form-label fw-semibold mb-2">
                                منصة البث الخارجية <span class="text-danger">*</span>
                            </label>
                            <div class="row g-3">
                                <div v-for="p in providers" :key="p.id" class="col-md-3 col-6">
                                    <div
                                        class="card h-100 border-2"
                                        :class="[
                                            form.provider === p.id ? `border-${p.color} bg-${p.color} bg-opacity-10` : 'border-light',
                                            !providerStatus[p.id] ? 'opacity-50' : '',
                                        ]"
                                        :style="providerStatus[p.id] ? 'cursor:pointer' : 'cursor:not-allowed'"
                                        @click="providerStatus[p.id] && (form.provider = p.id)"
                                    >
                                        <div class="card-body d-flex align-items-center gap-2 p-3">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" :class="`bg-${p.color} bg-opacity-20`" style="width:36px;height:36px;">
                                                <i class="bi fs-5" :class="[p.icon, `text-${p.color}`]"></i>
                                            </div>
                                            <div class="flex-grow-1 min-w-0">
                                                <div class="fw-semibold small">{{ p.label }}</div>
                                                <div class="text-muted" style="font-size:10px;">{{ p.desc }}</div>
                                            </div>
                                            <i v-if="form.provider === p.id" class="bi bi-check-circle-fill" :class="`text-${p.color}`"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-if="errors.provider" class="alert alert-danger mt-2 py-2 small">{{ errors.provider }}</div>
                        </div>

                        <!-- Dashboard -->
                        <div class="col-12">
                            <label class="form-label fw-semibold mb-2">
                                داش بورد الفصل الدراسي <span class="text-danger">*</span>
                            </label>
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
                                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" :class="`bg-${d.color} bg-opacity-20`" style="width:44px;height:44px;">
                                                <i class="bi fs-4" :class="[d.icon, `text-${d.color}`]"></i>
                                            </div>
                                            <div class="flex-grow-1 min-w-0">
                                                <div class="fw-semibold small">{{ d.label }}</div>
                                                <div class="text-muted" style="font-size:11px;">{{ d.desc }}</div>
                                                <span v-if="d.badge" class="badge bg-warning text-dark mt-1" style="font-size:9px;">{{ d.badge }}</span>
                                            </div>
                                            <i v-if="form.classroom_dashboard === d.id" class="bi bi-check-circle-fill" :class="`text-${d.color}`" style="font-size:1.2rem;"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-if="errors.classroom_dashboard" class="alert alert-danger mt-2 py-2 small">{{ errors.classroom_dashboard }}</div>
                        </div>

                        <!-- Title -->
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">عنوان البث <span class="text-danger">*</span></label>
                            <input v-model="form.title" type="text" class="form-control" :class="{ 'is-invalid': errors.title }" placeholder="مثال: درس في الرياضيات - الفصل الثالث">
                            <div v-if="errors.title" class="invalid-feedback">{{ errors.title }}</div>
                        </div>

                        <!-- Subject -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">المادة</label>
                            <input v-model="form.subject" type="text" class="form-control" placeholder="مثال: الرياضيات">
                        </div>

                        <!-- Description -->
                        <div class="col-12">
                            <label class="form-label fw-semibold">الوصف</label>
                            <textarea v-model="form.description" class="form-control" rows="2" placeholder="وصف اختياري للبث…"></textarea>
                        </div>

                        <!-- What you'll learn -->
                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-check2-square me-1 text-success"></i>
                                ما تقدمه في البث
                                <span class="text-muted fw-normal small ms-1">(اختياري)</span>
                            </label>
                            <div v-for="(point, idx) in form.learning_points" :key="idx" class="d-flex gap-2 mb-2">
                                <input
                                    v-model="form.learning_points[idx]"
                                    type="text"
                                    class="form-control form-control-sm"
                                    :placeholder="`نقطة ${idx + 1}…`"
                                />
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger px-2"
                                    @click="form.learning_points.splice(idx, 1)"
                                    :disabled="form.learning_points.length === 1"
                                    title="حذف"
                                ><i class="bi bi-x-lg"></i></button>
                            </div>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-success"
                                @click="form.learning_points.push('')"
                                :disabled="form.learning_points.length >= 20"
                            >
                                <i class="bi bi-plus-lg me-1"></i>إضافة نقطة
                            </button>
                        </div>

                        <!-- Thumbnail -->
                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-image me-1 text-primary"></i>
                                صورة البث
                                <span class="text-muted fw-normal small ms-1">(اختياري — 1280×720 أو 16:9)</span>
                            </label>
                            <input type="file" class="form-control" accept="image/*" @change="onThumbnailChange">
                            <div v-if="thumbnailPreview" class="mt-2">
                                <img :src="thumbnailPreview" class="rounded-3" style="max-height:160px;max-width:100%;object-fit:cover;">
                            </div>
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

                        <!-- Academic track / Year / Section -->
                        <div class="col-md-3" v-if="categories.length > 0">
                            <label class="form-label fw-semibold">الشعبة</label>
                            <select class="form-select" v-model="selectedTopId" @change="onTopChange">
                                <option :value="null">— عربي / إنجليزي —</option>
                                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>

                        <div class="col-md-3" v-if="selectedTopId">
                            <label class="form-label fw-semibold">المرحلة الدراسية</label>
                            <select class="form-select" v-model="selectedMidId" @change="onMidChange">
                                <option :value="null">— اختر السنة —</option>
                                <option v-for="y in yearCategories" :key="y.id" :value="y.id">{{ y.name }}</option>
                            </select>
                        </div>

                        <div class="col-md-3" v-if="selectedMidId && sectionCategories.length > 0">
                            <label class="form-label fw-semibold">الفصل الدراسي</label>
                            <select class="form-select" v-model="selectedSectionId" @change="onSectionChange">
                                <option :value="null">— اختر الفصل الدراسي —</option>
                                <option v-for="s in sectionCategories" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>

                        <div class="col-md-3" v-if="selectedSectionId && subSectionCategories.length > 0">
                            <label class="form-label fw-semibold">الفصل</label>
                            <select
                                class="form-select"
                                :class="{ 'is-invalid': errors.category_id }"
                                v-model="form.category_id"
                            >
                                <option :value="null">— اختر القسم الفرعي —</option>
                                <option v-for="sub in subSectionCategories" :key="sub.id" :value="sub.id">{{ sub.name }}</option>
                            </select>
                            <div v-if="errors.category_id" class="invalid-feedback">{{ errors.category_id }}</div>
                        </div>

                        <!-- Actions -->
                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-danger px-4" :disabled="saving">
                                <span v-if="saving" class="spinner-border spinner-border-sm me-2"></span>
                                <i v-else class="bi bi-broadcast me-2"></i>
                                إنشاء البث
                            </button>
                            <Link :href="route('teacher.live-streams.index')" class="btn btn-outline-secondary">إلغاء</Link>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
