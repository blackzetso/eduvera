<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Pages/Teacher/Layout/App.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { toast } from 'vue3-toastify'

const props = defineProps({
    stream:               Object,
    categories:           { type: Array, default: () => [] },
    maxDuration:          { type: Number, default: 60 },
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

const copied = ref(false)
function copyJoinLink() {
    navigator.clipboard.writeText(props.stream.guest_join_url || '')
        .then(() => { copied.value = true; setTimeout(() => copied.value = false, 2000) })
}

const form = ref({
    title:           props.stream.title          || '',
    description:     props.stream.description    || '',
    learning_points: props.stream.learning_points?.length ? [...props.stream.learning_points] : [''],
    subject:         props.stream.subject        || '',
    start_datetime:  props.stream.start_datetime || '',
    video_url:       props.stream.video_url      || '',
    category_id:     props.stream.category_id    || null,
})

function initCategorySelectionsFromCurrent() {
    const currentId = Number(form.value.category_id)
    if (!currentId) return

    for (const top of props.categories) {
        if (Number(top.id) === currentId) {
            selectedTopId.value = Number(top.id)
            return
        }

        for (const year of (top.children ?? [])) {
            if (Number(year.id) === currentId) {
                selectedTopId.value = Number(top.id)
                selectedMidId.value = Number(year.id)
                return
            }

            for (const section of (year.children ?? [])) {
                if (Number(section.id) === currentId) {
                    selectedTopId.value = Number(top.id)
                    selectedMidId.value = Number(year.id)
                    selectedSectionId.value = Number(section.id)
                    return
                }

                for (const subSection of (section.children ?? [])) {
                    if (Number(subSection.id) === currentId) {
                        selectedTopId.value = Number(top.id)
                        selectedMidId.value = Number(year.id)
                        selectedSectionId.value = Number(section.id)
                        return
                    }
                }
            }
        }
    }
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

initCategorySelectionsFromCurrent()

const thumbnailFile     = ref(null)
const thumbnailPreview  = ref(props.stream.thumbnail_url || null)

function onThumbnailChange(e) {
    const file = e.target.files[0]
    if (!file) return
    thumbnailFile.value = file
    thumbnailPreview.value = URL.createObjectURL(file)
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

const providerLabels = {
    none:        'Jitsi',
    teams:       'Teams',
    zoom:        'Zoom',
    livekit:     'LiveKit',
    google_meet: 'Google Meet',
    hms:         '100ms',
}

const dashboardLabels = {
    jitsi:  'Jitsi',
    livekit: 'LiveKit',
    hms:    '100ms',
}

function submit() {
    saving.value = true
    errors.value = {}

    if (form.value.start_datetime && new Date(form.value.start_datetime) <= new Date()) {
        errors.value.start_datetime = 'يجب أن يكون تاريخ البدء في المستقبل.'
        toast.error(errors.value.start_datetime)
        saving.value = false
        return
    }

    router.put(route('teacher.live-streams.update', props.stream.id), { ...form.value, thumbnail: thumbnailFile.value }, {
        onSuccess: () => toast.success('تم تحديث البث بنجاح'),
        onError: (errs) => {
            errors.value = errs
            toast.error('يرجى مراجعة البيانات المدخلة')
        },
        onFinish: () => { saving.value = false },
    })
}
</script>

<template>
    <Head :title="`تعديل: ${stream.title}`" />
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
                            <li class="breadcrumb-item active">تعديل</li>
                        </ol>
                    </nav>
                    <h1 class="h3 mb-0">
                        <i class="bi bi-pencil me-2 text-primary"></i>
                        تعديل البث: {{ stream.title }}
                    </h1>
                </div>
            </div>

            <div class="row g-4">

                <!-- Provider + Dashboard info card (read-only) -->
                <div class="col-lg-4">
                    <div class="card shadow-sm mb-3">
                        <div class="card-header">
                            <h6 class="card-header-title mb-0">
                                <i class="bi bi-info-circle me-1"></i>إعدادات المنصة
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-2">
                                <span class="text-muted small d-block mb-1">منصة البث</span>
                                <span class="badge bg-light text-dark border">{{ providerLabels[stream.provider] ?? stream.provider }}</span>
                            </div>
                            <div>
                                <span class="text-muted small d-block mb-1">داش بورد الفصل</span>
                                <span class="badge bg-light text-dark border">{{ dashboardLabels[stream.classroom_dashboard] ?? stream.classroom_dashboard }}</span>
                            </div>
                            <p class="text-muted small mt-3 mb-0">
                                <i class="bi bi-lock-fill me-1"></i>
                                لا يمكن تغيير المنصة بعد الإنشاء.
                            </p>
                        </div>
                    </div>

                    <!-- Student join link -->
                    <div class="card shadow-sm" v-if="stream.guest_join_url">
                        <div class="card-header">
                            <h6 class="card-header-title mb-0">
                                <i class="bi bi-link-45deg me-1"></i>رابط الطلاب
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control" :value="stream.guest_join_url" readonly>
                                <button class="btn btn-outline-secondary" @click="copyJoinLink" title="نسخ الرابط">
                                    <i class="bi" :class="copied ? 'bi-check-lg text-success' : 'bi-clipboard'"></i>
                                </button>
                                <a :href="stream.guest_join_url" target="_blank" class="btn btn-outline-secondary">
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Edit form -->
                <div class="col-lg-8">
                    <div class="card shadow-sm">
                        <div class="card-header">
                            <h5 class="card-header-title mb-0">تعديل بيانات البث</h5>
                        </div>
                        <div class="card-body">
                            <form @submit.prevent="submit" class="row g-3">

                                <!-- Title -->
                                <div class="col-12">
                                    <label class="form-label fw-semibold">العنوان <span class="text-danger">*</span></label>
                                    <input v-model="form.title" type="text" class="form-control" :class="{ 'is-invalid': errors.title }">
                                    <div v-if="errors.title" class="invalid-feedback">{{ errors.title }}</div>
                                </div>

                                <!-- Subject -->
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">المادة</label>
                                    <input v-model="form.subject" type="text" class="form-control">
                                </div>

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
                                        <option :value="null">— اختر المرحلة —</option>
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

                                <!-- Start DateTime -->
                                <div class="col-md-6">
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

                                <!-- End DateTime (auto-computed) -->
                                <div class="col-md-6">
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
                                    <label class="form-label fw-semibold">الوصف</label>
                                    <textarea v-model="form.description" class="form-control" rows="2"></textarea>
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
                                        <img :src="thumbnailPreview" class="rounded-3" style="max-height:160px;object-fit:cover;border:1px solid #dee2e6;">
                                    </div>
                                </div>

                                <!-- Recording URL -->
                                <div class="col-12">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-play-circle me-1 text-success"></i>
                                        رابط تسجيل البث
                                        <span class="text-muted fw-normal small ms-1">(اختياري — YouTube أو أي رابط)</span>
                                    </label>
                                    <input
                                        v-model="form.video_url"
                                        type="url"
                                        class="form-control"
                                        :class="{ 'is-invalid': errors.video_url }"
                                        placeholder="https://www.youtube.com/watch?v=..."
                                    >
                                    <div v-if="errors.video_url" class="invalid-feedback">{{ errors.video_url }}</div>
                                    <div v-if="form.video_url" class="mt-1">
                                        <a :href="form.video_url" target="_blank" class="small text-primary">
                                            <i class="bi bi-box-arrow-up-right me-1"></i>معاينة الرابط
                                        </a>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="col-12 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary px-4" :disabled="saving">
                                        <span v-if="saving" class="spinner-border spinner-border-sm me-2"></span>
                                        <i v-else class="bi bi-check-lg me-2"></i>
                                        حفظ التعديلات
                                    </button>
                                    <Link :href="route('teacher.live-streams.index')" class="btn btn-outline-secondary">إلغاء</Link>
                                </div>

                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
