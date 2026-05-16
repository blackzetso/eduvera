<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { toast } from 'vue3-toastify'

const props = defineProps({
    max_duration: {
        type: Number,
        default: 60,
    },
    pending_requests: {
        type: Array,
        default: () => [],
    },
    pending_extensions: {
        type: Array,
        default: () => [],
    },
    watermark: {
        type: Object,
        default: null,
    },
})

const form = ref({
    max_duration: props.max_duration,
})

// ── Watermark form ────────────────────────────────────────────────────────────
const wmForm = useForm({
    watermark:          null,
    watermark_position: props.watermark?.position ?? 'bottom-right',
    watermark_opacity:  props.watermark?.opacity  ?? 20,
    watermark_size:     props.watermark?.size      ?? 300,
})

const wmPreview    = ref(props.watermark?.url ?? null)
const wmRemoving   = ref(false)

const POSITIONS = [
    { value: 'top-left',     label: 'أعلى يسار' },
    { value: 'top-right',    label: 'أعلى يمين' },
    { value: 'bottom-left',  label: 'أسفل يسار' },
    { value: 'bottom-right', label: 'أسفل يمين' },
    { value: 'center',       label: 'منتصف' },
]

function onWmFile(e) {
    const file = e.target.files?.[0]
    if (!file) return
    wmForm.watermark = file
    wmPreview.value  = URL.createObjectURL(file)
}

function saveWatermark() {
    wmForm.post(route('admin.live-streams.details.update'), {
        forceFormData: true,
        onSuccess: () => toast.success('تم حفظ العلامة المائية'),
        onError:   () => toast.error(Object.values(wmForm.errors)[0] || 'حدث خطأ'),
    })
}

function removeWatermark() {
    wmRemoving.value = true
    router.post(route('admin.live-streams.details.update'), { remove_watermark: true }, {
        onSuccess: () => { wmPreview.value = null; wmForm.watermark = null; toast.success('تم حذف الشعار') },
        onError:   () => toast.error('حدث خطأ'),
        onFinish:  () => { wmRemoving.value = false },
    })
}

// Live preview style for watermark demo
const wmPreviewStyle = computed(() => {
    const pos  = wmForm.watermark_position
    const opac = wmForm.watermark_opacity / 100
    const sz   = wmForm.watermark_size
    // Map 300-1000 to 5%-100% of the preview container (both width & height)
    const pct  = Math.round(((sz - 300) / 700) * 95 + 5)
    const base = { position: 'absolute', opacity: opac, width: pct + '%', height: pct + '%', objectFit: 'contain', userSelect: 'none' }
    switch (pos) {
        case 'top-left':     return { ...base, top: '8px',  left: '8px' }
        case 'top-right':    return { ...base, top: '8px',  right: '8px' }
        case 'bottom-left':  return { ...base, bottom: '8px', left: '8px' }
        case 'center':       return { ...base, top: '50%', left: '50%', transform: 'translate(-50%,-50%)' }
        default:             return { ...base, bottom: '8px', right: '8px' }
    }
})

const saving = ref(false)
const approving = ref(null)
const cancelling = ref(null)
const cancellingExt = ref(null)
const approvingExt  = ref(null)

function cancelExt(id) {
    cancellingExt.value = id
    router.patch(route('admin.live-streams.extension.cancel', id), {}, {
        onSuccess: () => toast.success('تم إلغاء التمديد وتقليص وقت البث'),
        onError:   () => toast.error('حدث خطأ'),
        onFinish:  () => { cancellingExt.value = null },
    })
}

function approveExt(id) {
    approvingExt.value = id
    router.patch(route('admin.live-streams.extension.approve', id), {}, {
        onSuccess: () => toast.success('تمت الموافقة على التمديد'),
        onError:   () => toast.error('حدث خطأ'),
        onFinish:  () => { approvingExt.value = null },
    })
}

function submit() {
    saving.value = true
    router.post(route('admin.live-streams.details.update'), form.value, {
        onSuccess: () => {
            toast.success('تم حفظ مدة البث بنجاح')
        },
        onError: (errors) => {
            toast.error(Object.values(errors)[0] || 'حدث خطأ أثناء الحفظ')
        },
        onFinish: () => { saving.value = false },
    })
}

function approve(id) {
    approving.value = id
    router.patch(route('admin.live-streams.extra-session.approve', id), {}, {
        onSuccess: () => toast.success('تمت الموافقة على الحصة الإضافية'),
        onError: ()  => toast.error('حدث خطأ'),
        onFinish: () => { approving.value = null },
    })
}

function cancel(id) {
    cancelling.value = id
    router.patch(route('admin.live-streams.extra-session.cancel', id), {}, {
        onSuccess: () => toast.success('تم رفض طلب الحصة الإضافية'),
        onError: ()  => toast.error('حدث خطأ'),
        onFinish: () => { cancelling.value = null },
    })
}
</script>

<template>
    <Head title="تفاصيل البث" />
    <AppLayout>
        <div class="page-content-wrapper border">
            <div class="row mb-3">
                <div class="col-12">
                    <h1 class="h3 mb-0">تفاصيل البث المباشر</h1>
                    <nav aria-label="breadcrumb" class="mt-1">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a :href="route('admin.dashboard.index')">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a :href="route('admin.live-streams.index')">البثوث المباشرة</a></li>
                            <li class="breadcrumb-item active">تفاصيل البث</li>
                        </ol>
                    </nav>
                </div>
            </div>

            <!-- Pending Live Extension Requests -->
            <div v-if="pending_extensions.length > 0" class="row justify-content-center mb-4">
                <div class="col-xl-6 col-lg-8">
                    <div class="card shadow border-info">
                        <div class="card-header border-bottom bg-info bg-opacity-10 d-flex align-items-center gap-2">
                            <i class="bi bi-alarm-fill text-info fs-5"></i>
                            <h5 class="card-header-title mb-0">طلبات تمديد البث المباشر</h5>
                            <span class="badge bg-info text-dark ms-auto">{{ pending_extensions.length }}</span>
                        </div>
                        <div class="card-body p-0">
                            <div
                                v-for="req in pending_extensions"
                                :key="req.id"
                                class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center gap-3 p-3 border-bottom"
                            >
                                <div class="rounded-circle bg-info bg-opacity-15 d-flex align-items-center justify-content-center flex-shrink-0 text-info fw-bold" style="width:42px;height:42px;font-size:15px;">
                                    {{ req.teacher_name?.charAt(0)?.toUpperCase() }}
                                </div>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-semibold text-truncate">بث: {{ req.title }}</div>
                                    <div class="small text-muted">
                                        <i class="bi bi-person me-1"></i>{{ req.teacher_name }}
                                        <span v-if="req.subject" class="ms-2"><i class="bi bi-book me-1"></i>{{ req.subject }}</span>
                                    </div>
                                    <div class="small text-muted">
                                        <i class="bi bi-calendar-event me-1"></i>{{ req.start_datetime }}
                                    </div>
                                    <div class="small text-info fw-semibold mt-1">
                                        <i class="bi bi-clock-history me-1"></i>
                                        يطلب تمديد البث بـ <strong>{{ req.extension_minutes }} دقيقة</strong> — لو ألغيت، سينتهي مبكراً.
                                    </div>
                                </div>
                                <div class="d-flex gap-2 flex-shrink-0">
                                    <button
                                        class="btn btn-success btn-sm px-3"
                                        :disabled="approvingExt === req.id"
                                        @click="approveExt(req.id)"
                                    >
                                        <span v-if="approvingExt === req.id" class="spinner-border spinner-border-sm me-1"></span>
                                        <i v-else class="bi bi-check-circle me-1"></i>
                                        موافقة
                                    </button>
                                    <button
                                        class="btn btn-outline-danger btn-sm px-3"
                                        :disabled="cancellingExt === req.id"
                                        @click="cancelExt(req.id)"
                                    >
                                        <span v-if="cancellingExt === req.id" class="spinner-border spinner-border-sm me-1"></span>
                                        <i v-else class="bi bi-x-circle me-1"></i>
                                        إلغاء
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Extra Session Requests -->
            <div v-if="pending_requests.length > 0" class="row justify-content-center mb-4">
                <div class="col-xl-6 col-lg-8">
                    <div class="card shadow border-warning">
                        <div class="card-header border-bottom bg-warning bg-opacity-10 d-flex align-items-center gap-2">
                            <i class="bi bi-bell-fill text-warning fs-5"></i>
                            <h5 class="card-header-title mb-0">طلبات الحصص الإضافية</h5>
                            <span class="badge bg-warning text-dark ms-auto">{{ pending_requests.length }}</span>
                        </div>
                        <div class="card-body p-0">
                            <div
                                v-for="req in pending_requests"
                                :key="req.id"
                                class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center gap-3 p-3 border-bottom"
                            >
                                <div class="rounded-circle bg-primary bg-opacity-15 d-flex align-items-center justify-content-center flex-shrink-0 text-primary fw-bold" style="width:42px;height:42px;font-size:15px;">
                                    {{ req.teacher_name?.charAt(0)?.toUpperCase() }}
                                </div>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-semibold text-truncate">{{ req.title }}</div>
                                    <div class="small text-muted">
                                        <i class="bi bi-person me-1"></i>{{ req.teacher_name }}
                                        <span v-if="req.subject" class="ms-2"><i class="bi bi-book me-1"></i>{{ req.subject }}</span>
                                    </div>
                                    <div class="small text-muted">
                                        <i class="bi bi-calendar-event me-1"></i>{{ req.start_datetime }}
                                    </div>
                                    <div class="small text-warning fw-semibold mt-1">
                                        <i class="bi bi-clock-history me-1"></i>
                                        بث بضعف المدة ({{ max_duration * 2 }} دقيقة) — بانتظار موافقتك. لو رفضت، سيرجع لـ {{ max_duration }} دقيقة.
                                    </div>
                                </div>
                                <div class="d-flex gap-2 flex-shrink-0">
                                    <button
                                        class="btn btn-success btn-sm px-3"
                                        :disabled="approving === req.id"
                                        @click="approve(req.id)"
                                    >
                                        <span v-if="approving === req.id" class="spinner-border spinner-border-sm me-1"></span>
                                        <i v-else class="bi bi-check-circle me-1"></i>
                                        موافقة
                                    </button>
                                    <button
                                        class="btn btn-outline-secondary btn-sm px-3"
                                        :disabled="cancelling === req.id"
                                        @click="cancel(req.id)"
                                    >
                                        <span v-if="cancelling === req.id" class="spinner-border spinner-border-sm me-1"></span>
                                        <i v-else class="bi bi-x-circle me-1"></i>
                                        إلغاء
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row justify-content-center">
                <div class="col-xl-6 col-lg-8">
                    <div class="card shadow">
                        <div class="card-header border-bottom d-flex align-items-center gap-2">
                            <i class="bi bi-broadcast text-danger fs-5"></i>
                            <h5 class="card-header-title mb-0">إعدادات مدة البث للمدرسين</h5>
                        </div>

                        <div class="card-body">
                            <div class="alert alert-info d-flex gap-2 mb-4">
                                <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
                                <div>
                                    تحدد هذه القيمة الحد الأقصى لمدة البث المباشر المسموح بها للمدرسين بالدقيقة.
                                    عند إنشاء بث جديد لن يتمكن المدرس من تجاوز هذه المدة.
                                </div>
                            </div>

                            <form @submit.prevent="submit" class="row g-4">
                                <div class="col-12">
                                    <label for="max_duration" class="form-label fw-semibold">
                                        الحد الأقصى لمدة البث (بالدقائق)
                                    </label>
                                    <div class="input-group">
                                        <input
                                            id="max_duration"
                                            v-model.number="form.max_duration"
                                            type="number"
                                            min="1"
                                            max="1440"
                                            class="form-control"
                                            placeholder="مثال: 60"
                                            required
                                        />
                                        <span class="input-group-text">دقيقة</span>
                                    </div>
                                    <div class="form-text text-muted">
                                        القيمة الحالية: <strong>{{ form.max_duration }} دقيقة</strong>
                                        (أي {{ Math.floor(form.max_duration / 60) }} ساعة و {{ form.max_duration % 60 }} دقيقة)
                                    </div>
                                </div>

                                <div class="col-12 pt-2">
                                    <button type="submit" class="btn btn-danger px-4" :disabled="saving">
                                        <span v-if="saving" class="spinner-border spinner-border-sm me-2" role="status"></span>
                                        <i v-else class="bi bi-save me-2"></i>
                                        حفظ الإعدادات
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Watermark / Logo Card -->
            <div class="row justify-content-center mt-4">
                <div class="col-xl-6 col-lg-8">
                    <div class="card shadow">
                        <div class="card-header border-bottom d-flex align-items-center gap-2">
                            <i class="bi bi-badge-wc text-primary fs-5"></i>
                            <h5 class="card-header-title mb-0">العلامة المائية (شعار البث)</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info d-flex gap-2 mb-4">
                                <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
                                <div>
                                    تُعرض هذه الصورة كعلامة مائية شفافة فوق شاشة البث لدى المعلم والطالب.
                                    يُنصح باستخدام صورة <strong>PNG بخلفية شفافة</strong> للحصول على أفضل نتيجة.
                                </div>
                            </div>

                            <!-- Live demo box -->
                            <div class="mb-4">
                                <div class="form-label fw-semibold">معاينة الموضع والشفافية</div>
                                <div class="position-relative rounded overflow-hidden d-flex align-items-center justify-content-center"
                                    style="height:260px;background:linear-gradient(135deg,#0f0f2a 0%,#1a1a40 100%);border:1px solid #2a2a5a;">
                                    <span class="text-secondary" style="font-size:12px;">شاشة البث</span>
                                    <img v-if="wmPreview"
                                        :src="wmPreview"
                                        draggable="false"
                                        :style="wmPreviewStyle"
                                        alt="watermark preview"
                                    />
                                    <div v-else class="position-absolute d-flex flex-column align-items-center gap-1 text-secondary" style="bottom:8px;right:8px;font-size:11px;">
                                        <i class="bi bi-image fs-4"></i>
                                        <span>لا توجد علامة</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Upload -->
                            <div class="mb-3">
                                <label class="form-label fw-semibold">رفع صورة الشعار</label>
                                <input
                                    type="file"
                                    class="form-control"
                                    accept="image/png,image/jpeg,image/gif,image/webp"
                                    @change="onWmFile"
                                />
                                <div class="form-text text-muted">PNG (شفاف) · JPG · GIF · WEBP — حد أقصى 2MB</div>
                            </div>

                            <!-- Position -->
                            <div class="mb-3">
                                <label class="form-label fw-semibold">موضع الشعار</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <label
                                        v-for="p in POSITIONS" :key="p.value"
                                        class="btn btn-sm"
                                        :class="wmForm.watermark_position === p.value ? 'btn-primary' : 'btn-outline-secondary'"
                                        style="cursor:pointer;"
                                    >
                                        <input type="radio" class="d-none" :value="p.value" v-model="wmForm.watermark_position" />
                                        {{ p.label }}
                                    </label>
                                </div>
                            </div>

                            <!-- Opacity -->
                            <div class="mb-4">
                                <label class="form-label fw-semibold">
                                    درجة الشفافية —
                                    <span class="text-primary">{{ wmForm.watermark_opacity }}%</span>
                                </label>
                                <input
                                    type="range"
                                    class="form-range"
                                    min="5"
                                    max="50"
                                    step="5"
                                    v-model.number="wmForm.watermark_opacity"
                                />
                                <div class="d-flex justify-content-between form-text text-muted">
                                    <span>5% (خفي جداً)</span>
                                    <span>50% (واضح)</span>
                                </div>
                            </div>

                            <!-- Size -->
                            <div class="mb-4">
                                <label class="form-label fw-semibold">
                                    حجم العلامة المائية —
                                    <span class="text-primary">{{ wmForm.watermark_size }}px</span>
                                </label>
                                <input
                                    type="range"
                                    class="form-range"
                                    min="300"
                                    max="1000"
                                    step="10"
                                    v-model.number="wmForm.watermark_size"
                                />
                                <div class="d-flex justify-content-between form-text text-muted">
                                    <span>300px (صغير)</span>
                                    <span>1000px (كبير)</span>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="d-flex gap-2 flex-wrap">
                                <button
                                    type="button"
                                    class="btn btn-danger px-4"
                                    :disabled="wmForm.processing"
                                    @click="saveWatermark"
                                >
                                    <span v-if="wmForm.processing" class="spinner-border spinner-border-sm me-2"></span>
                                    <i v-else class="bi bi-save me-2"></i>
                                    حفظ الشعار
                                </button>
                                <button
                                    v-if="wmPreview && !wmForm.watermark"
                                    type="button"
                                    class="btn btn-outline-secondary px-3"
                                    :disabled="wmRemoving"
                                    @click="removeWatermark"
                                >
                                    <span v-if="wmRemoving" class="spinner-border spinner-border-sm me-2"></span>
                                    <i v-else class="bi bi-trash me-2"></i>
                                    حذف الشعار
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
