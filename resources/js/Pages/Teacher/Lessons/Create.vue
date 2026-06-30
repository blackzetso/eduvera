<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import AppLayout from '@/Pages/Teacher/Layout/App.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import Swal from 'sweetalert2'
import Quill from 'quill'
import Stepper from 'bs-stepper'
import ClassMultiSelect from '@/Components/Lessons/ClassMultiSelect.vue'
import 'quill/dist/quill.snow.css'
import 'bs-stepper/dist/css/bs-stepper.min.css'

const props = defineProps({
    subjects:          { type: Array,  default: () => [] },
    messageTemplates:  { type: Array,  default: () => [] },
    fromPeriod:        { type: Object, default: null },
    subjectCategories: { type: Object, default: null },
    lesson:            { type: Object, default: null },
    editingSubjectId:  { type: Number, default: null },
})

const isEditing = computed(() => !!props.lesson)

const messageTemplatesList = ref([...props.messageTemplates])

const strategySelectOptions = computed(() =>
    messageTemplatesList.value.map(t => ({ id: t.id, name: t.title }))
)

function initialStrategyIds() {
    if (props.lesson?.message_templates?.length) {
        return props.lesson.message_templates.map(t => t.id)
    }
    if (props.lesson?.lesson_message_template_id) {
        return [props.lesson.lesson_message_template_id]
    }
    return []
}

const showStrategyModal = ref(false)
const strategyCreateForm = useForm({ title: '' })

const editor = ref(null)
let quill = null
let stepper = null

// ─── Form ────────────────────────────────────────────────────────────────────
const form = useForm({
    name:                       props.lesson?.name ?? '',
    short_description:          props.lesson?.short_description ?? '',
    description:                props.lesson?.description ?? '',
    subject_id:                 props.editingSubjectId ?? props.fromPeriod?.subject_id ?? null,
    class_ids:                      props.lesson?.classes?.map(c => c.id) ?? (props.fromPeriod?.category_id ? [props.fromPeriod.category_id] : []),
    lesson_message_template_ids:    initialStrategyIds(),
    timetable_period_id:        props.fromPeriod?.id ?? null,
    expiry_period:              props.lesson?.expiry_period ?? 'lifetime',
    expire_date:                props.lesson?.expire_date ?? null,
    publish_date:               props.lesson?.publish_date ?? null,
    is_featured:                Boolean(props.lesson?.is_featured) ?? false,
    is_free:                    props.lesson != null ? Boolean(props.lesson.is_free) : true,
    price:                      props.lesson?.price ?? '',
    discount_price:             props.lesson?.discount_price ?? '',
    image:                      null,
})

const enableDiscount = ref(false)

watch(() => form.is_free, (val) => {
    if (val) {
        enableDiscount.value = false
        form.price = ''
        form.discount_price = ''
    }
})

// ─── Category cascade state ───────────────────────────────────────────────
const allStages  = ref(props.subjectCategories?.stages  ?? [])
const allGrades  = ref(props.subjectCategories?.grades  ?? [])
const allClasses = ref(props.subjectCategories?.classes ?? [])

const selectedStageId = ref(props.fromPeriod?.stage_id ?? null)
const selectedGradeId = ref(props.fromPeriod?.grade_id ?? null)

const isFromPeriod = computed(() => Boolean(props.fromPeriod?.id))

const filteredGrades = computed(() =>
    selectedStageId.value ? allGrades.value.filter(g => g.parent_id === selectedStageId.value) : []
)

const filteredClasses = computed(() =>
    selectedGradeId.value ? allClasses.value.filter(c => c.parent_id === selectedGradeId.value) : []
)

const classSelectOptions = computed(() => {
    if (filteredClasses.value.length > 0) return filteredClasses.value
    if (allClasses.value.length > 0) return allClasses.value
    return []
})

watch(() => form.subject_id, async (subjectId) => {
    if (!subjectId) {
        allStages.value = []; allGrades.value = []; allClasses.value = []
        form.class_ids = []; selectedStageId.value = null; selectedGradeId.value = null
        return
    }
    if (props.fromPeriod?.subject_id === subjectId && props.subjectCategories) return
    try {
        const res = await fetch(route('teacher.lessons.subject-categories', subjectId))
        const data = await res.json()
        allStages.value  = data.stages  ?? []
        allGrades.value  = data.grades  ?? []
        allClasses.value = data.classes ?? []
        form.class_ids = []; selectedStageId.value = null; selectedGradeId.value = null
    } catch { /* silently ignore */ }
})

watch(selectedStageId, () => {
    if (isFromPeriod.value) return
    selectedGradeId.value = null
    form.class_ids = []
})
watch(selectedGradeId, () => {
    if (isFromPeriod.value) return
    form.class_ids = []
})

// ─── Lifecycle ───────────────────────────────────────────────────────────────
onMounted(async () => {
    await nextTick()

    const stepperEl = document.querySelector('#teacher-lesson-stepper')
    if (stepperEl) {
        stepper = new Stepper(stepperEl, { linear: false, animation: true })
    }

    if (editor.value) {
        quill = new Quill(editor.value, {
            theme: 'snow',
            placeholder: 'اكتب وصف الدرس هنا...',
            modules: {
                toolbar: [
                    [{ header: [1, 2, false] }],
                    ['bold', 'italic', 'underline'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['link'],
                    ['clean'],
                ],
            },
        })
        if (form.description) quill.root.innerHTML = form.description
        quill.on('text-change', () => { form.description = quill.root.innerHTML })
    }

    if ((isEditing.value || isFromPeriod.value) && props.subjectCategories) {
        allStages.value  = props.subjectCategories.stages  ?? []
        allGrades.value  = props.subjectCategories.grades  ?? []
        allClasses.value = props.subjectCategories.classes ?? []
    }
})

onBeforeUnmount(() => { quill = null; stepper = null })

function goNext() { stepper?.next() }
function goPrev() { stepper?.previous() }

function onFileChange(e) { form.image = e.target.files[0] }

function openStrategyModal() {
    strategyCreateForm.reset()
    strategyCreateForm.clearErrors()
    showStrategyModal.value = true
}

function submitStrategy() {
    if (!strategyCreateForm.title.trim()) {
        strategyCreateForm.setError('title', 'يرجى إدخال اسم الاستراتيجية')
        return
    }

    strategyCreateForm.post(route('teacher.lesson-strategies.store'), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            const tpl = page.props.flash?.strategyCreated
            if (tpl) {
                if (!messageTemplatesList.value.some(t => t.id === tpl.id)) {
                    messageTemplatesList.value.unshift(tpl)
                }
                if (!form.lesson_message_template_ids.includes(tpl.id)) {
                    form.lesson_message_template_ids = [...form.lesson_message_template_ids, tpl.id]
                }
            }
            showStrategyModal.value = false
            strategyCreateForm.reset()
            Swal.fire('تم!', 'تم إضافة الاستراتيجية بنجاح', 'success')
        },
        onError: () => {
            Swal.fire('خطأ!', strategyCreateForm.errors.title || 'حدثت مشكلة أثناء الحفظ.', 'error')
        },
    })
}

function submit() {
    const url = isEditing.value
        ? route('teacher.lessons.update', props.lesson.id)
        : route('teacher.lessons.store')
    const method = isEditing.value ? form.put : form.post
    method.call(form, url, {
        onError: () => {
            const firstError = Object.values(form.errors || {})[0]
            Swal.fire('خطأ!', firstError || 'حدثت مشكلة أثناء الحفظ.', 'error')
        },
    })
}
</script>

<template>
    <Head :title="isEditing ? 'تعديل الدرس' : 'إنشاء درس جديد'" />
    <AppLayout>
        <div class="page-content-wrapper border">

            <div class="d-flex align-items-center gap-2 mb-3">
                <Link :href="route('teacher.lessons.index')" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-right me-1"></i> دروسي
                </Link>
                <h3 class="mb-0">{{ isEditing ? 'تعديل الدرس' : 'إنشاء درس جديد' }}</h3>
            </div>

            <div v-if="isEditing" class="alert alert-light border d-flex justify-content-between align-items-center mb-3">
                <span><i class="bi bi-collection-play me-2"></i> لإضافة فيديوهات ومحاضرات، انتقل إلى خطوة المحتوى.</span>
                <Link :href="route('teacher.lessons.edit', lesson.id)" class="btn btn-sm btn-primary">المحتوى</Link>
            </div>
            <div v-if="fromPeriod" class="alert alert-info d-flex align-items-center gap-2 mb-3">
                <i class="bi bi-calendar-check fs-5"></i>
                <div>
                    <strong>إنشاء درس من الجدول الدراسي</strong><br>
                    <small>
                        الحصة: {{ fromPeriod.day_name }} {{ fromPeriod.time_from }}–{{ fromPeriod.time_to }} |
                        المادة: {{ fromPeriod.subject_name ?? '—' }} |
                        الفصل: {{ fromPeriod.category_name ?? '—' }}
                    </small>
                </div>
            </div>

            <!-- Card START -->
            <div class="card border rounded-3 mb-5">
                <div id="teacher-lesson-stepper" class="bs-stepper stepper-outline">

                    <!-- Stepper Header -->
                    <div class="card-header bg-light border-bottom px-lg-5">
                        <div class="bs-stepper-header" role="tablist">

                            <!-- Step 1 -->
                            <div class="step" data-target="#tl-step-1">
                                <div class="d-grid text-center align-items-center">
                                    <button type="button" class="btn btn-link step-trigger mb-0" role="tab" id="tl-trigger1" aria-controls="tl-step-1">
                                        <span class="bs-stepper-circle">1</span>
                                    </button>
                                    <h6 class="bs-stepper-label d-none d-md-block">تفاصيل الدرس</h6>
                                </div>
                            </div>
                            <div class="line"></div>

                            <!-- Step 2 -->
                            <div class="step" data-target="#tl-step-2">
                                <div class="d-grid text-center align-items-center">
                                    <button type="button" class="btn btn-link step-trigger mb-0" role="tab" id="tl-trigger2" aria-controls="tl-step-2">
                                        <span class="bs-stepper-circle">2</span>
                                    </button>
                                    <h6 class="bs-stepper-label d-none d-md-block">المادة والفصل</h6>
                                </div>
                            </div>
                            <div class="line"></div>

                            <!-- Step 3 -->
                            <div class="step" data-target="#tl-step-3">
                                <div class="d-grid text-center align-items-center">
                                    <button type="button" class="btn btn-link step-trigger mb-0" role="tab" id="tl-trigger3" aria-controls="tl-step-3">
                                        <span class="bs-stepper-circle">3</span>
                                    </button>
                                    <h6 class="bs-stepper-label d-none d-md-block">الوسائط والسعر</h6>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- Stepper Body -->
                    <div class="card-body px-1 px-sm-4">
                        <div class="bs-stepper-content">
                            <form onsubmit="return false">

                                <!-- ═══ STEP 1: Lesson Details ═══ -->
                                <div id="tl-step-1" role="tabpanel" class="content fade" aria-labelledby="tl-trigger1">
                                    <h4>تفاصيل الدرس</h4>
                                    <hr>
                                    <div class="row g-4">

                                        <!-- Title -->
                                        <div class="col-12">
                                            <label class="form-label">عنوان الدرس <span class="text-danger">*</span></label>
                                            <input class="form-control" v-model="form.name" type="text" placeholder="أدخل عنوان الدرس">
                                            <div v-if="form.errors.name" class="text-danger small mt-1">{{ form.errors.name }}</div>
                                        </div>

                                        <!-- Short description -->
                                        <div class="col-12">
                                            <label class="form-label">وصف مختصر</label>
                                            <textarea class="form-control" rows="2" v-model="form.short_description" placeholder="وصف مختصر للدرس"></textarea>
                                        </div>

                                        <!-- Description (Quill) -->
                                        <div class="col-12">
                                            <label class="form-label">وصف تفصيلي</label>
                                            <div ref="editor" class="bg-body border rounded h-200px"></div>
                                        </div>

                                        <!-- Publish Date -->
                                        <div class="col-md-6">
                                            <label class="form-label">تاريخ النشر <small class="text-muted">(اختياري)</small></label>
                                            <input class="form-control" type="datetime-local" v-model="form.publish_date">
                                        </div>

                                        <!-- Expiry Period -->
                                        <div class="col-md-6">
                                            <label class="form-label">مدة الصلاحية</label>
                                            <select v-model="form.expiry_period" class="form-select">
                                                <option value="lifetime">دائم</option>
                                                <option value="limited">محدد بتاريخ</option>
                                            </select>
                                        </div>

                                        <!-- Expire Date -->
                                        <div class="col-md-6" v-if="form.expiry_period === 'limited'">
                                            <label class="form-label">تاريخ الانتهاء</label>
                                            <input class="form-control" type="date" v-model="form.expire_date">
                                        </div>

                                        <!-- Teaching Strategies (message template) -->
                                        <div class="col-12">
                                            <label class="form-label d-inline-flex align-items-center gap-2 flex-wrap">
                                                <span>استراتيجيات التدريس <small class="text-muted">(اختياري)</small></span>
                                                <button
                                                    type="button"
                                                    class="btn btn-primary btn-sm flex-shrink-0 px-2"
                                                    title="إضافة استراتيجية جديدة"
                                                    @click="openStrategyModal"
                                                >
                                                    <i class="bi bi-plus-lg"></i>
                                                </button>
                                            </label>
                                            <ClassMultiSelect
                                                v-model="form.lesson_message_template_ids"
                                                :options="strategySelectOptions"
                                                empty-text="لا توجد استراتيجيات متاحة."
                                                search-placeholder="ابحث عن استراتيجية..."
                                            />
                                        </div>

                                        <!-- Featured -->
                                        <div class="col-md-6 d-flex align-items-center">
                                            <div class="form-check form-switch form-check-md">
                                                <input v-model="form.is_featured" class="form-check-input" type="checkbox" id="featuredSwitch">
                                                <label class="form-check-label" for="featuredSwitch">درس مميز</label>
                                            </div>
                                        </div>

                                        <!-- Next button -->
                                        <div class="col-12 d-flex justify-content-end mt-2">
                                            <button type="button" class="btn btn-primary next-btn mb-0" @click="goNext">التالي</button>
                                        </div>
                                    </div>
                                </div>
                                <!-- Step 1 END -->

                                <!-- ═══ STEP 2: Subject & Class ═══ -->
                                <div id="tl-step-2" role="tabpanel" class="content fade" aria-labelledby="tl-trigger2">
                                    <h4>المادة والفصل الدراسي</h4>
                                    <hr>
                                    <div class="row g-4">

                                        <!-- Subject -->
                                        <div class="col-md-6">
                                            <label class="form-label">المادة الدراسية <span class="text-danger">*</span></label>
                                            <div v-if="fromPeriod && fromPeriod.subject_id">
                                                <input type="text" class="form-control bg-light" :value="fromPeriod.subject_name" readonly>
                                                <small class="text-muted">تم التعيين تلقائياً من الجدول</small>
                                            </div>
                                            <select v-else v-model="form.subject_id" class="form-select">
                                                <option :value="null">-- اختر المادة --</option>
                                                <option v-for="subject in subjects" :key="subject.id" :value="subject.id">
                                                    {{ subject.name }}
                                                </option>
                                            </select>
                                            <div v-if="form.errors.subject_id" class="text-danger small mt-1">{{ form.errors.subject_id }}</div>
                                        </div>

                                        <!-- Stage cascade -->
                                        <div class="col-md-6" v-if="fromPeriod?.stage_id || allStages.length > 0">
                                            <label class="form-label">المرحلة</label>
                                            <div v-if="fromPeriod?.stage_id">
                                                <input type="text" class="form-control bg-light" :value="fromPeriod.stage_name" readonly>
                                                <small class="text-muted">تم التعيين تلقائياً من الجدول</small>
                                            </div>
                                            <select v-else v-model="selectedStageId" class="form-select">
                                                <option :value="null">-- اختر المرحلة --</option>
                                                <option v-for="stage in allStages" :key="stage.id" :value="stage.id">{{ stage.name }}</option>
                                            </select>
                                        </div>

                                        <!-- Grade cascade -->
                                        <div class="col-md-6" v-if="fromPeriod?.grade_id || filteredGrades.length > 0">
                                            <label class="form-label">الصف الدراسي</label>
                                            <div v-if="fromPeriod?.grade_id">
                                                <input type="text" class="form-control bg-light" :value="fromPeriod.grade_name" readonly>
                                                <small class="text-muted">تم التعيين تلقائياً من الجدول</small>
                                            </div>
                                            <select v-else v-model="selectedGradeId" class="form-select">
                                                <option :value="null">-- اختر الصف --</option>
                                                <option v-for="grade in filteredGrades" :key="grade.id" :value="grade.id">{{ grade.name }}</option>
                                            </select>
                                        </div>

                                        <!-- Class multi-select -->
                                        <div class="col-12">
                                            <label class="form-label">الفصول الدراسية</label>
                                            <div v-if="fromPeriod && fromPeriod.category_id">
                                                <input type="text" class="form-control bg-light" :value="fromPeriod.category_name" readonly>
                                                <small class="text-muted">تم التعيين تلقائياً من الجدول</small>
                                            </div>
                                            <ClassMultiSelect
                                                v-else
                                                v-model="form.class_ids"
                                                :options="classSelectOptions"
                                                :empty-text="form.subject_id ? 'اختر المرحلة والصف لعرض الفصول المتاحة.' : 'اختر المادة أولاً لعرض الفصول المتاحة.'"
                                            />
                                            <div v-if="form.errors.class_ids" class="text-danger small mt-1">{{ form.errors.class_ids }}</div>
                                        </div>

                                        <!-- Buttons -->
                                        <div class="col-12 d-flex justify-content-between mt-2">
                                            <button type="button" class="btn btn-secondary prev-btn mb-0" @click="goPrev">السابق</button>
                                            <button type="button" class="btn btn-primary next-btn mb-0" @click="goNext">التالي</button>
                                        </div>
                                    </div>
                                </div>
                                <!-- Step 2 END -->

                                <!-- ═══ STEP 3: Media & Price ═══ -->
                                <div id="tl-step-3" role="tabpanel" class="content fade" aria-labelledby="tl-trigger3">
                                    <h4>الوسائط والسعر</h4>
                                    <hr>
                                    <div class="row g-4">

                                        <!-- Image upload -->
                                        <div class="col-12">
                                            <label class="form-label">صورة الدرس</label>
                                            <div class="text-center justify-content-center align-items-center p-4 p-sm-5 border border-2 border-dashed position-relative rounded-3">
                                                <i class="bi bi-image fs-1 text-muted"></i>
                                                <div class="mt-2">
                                                    <h6 class="my-2">ارفع صورة الدرس أو <span class="text-primary">تصفح</span></h6>
                                                    <input class="form-control" @change="onFileChange" type="file" accept="image/gif,image/jpeg,image/png">
                                                    <p class="small mb-0 mt-2 text-muted">JPG, JPEG, PNG — الحجم الموصى به 600×450</p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Price section -->
                                        <div class="col-12">
                                            <div class="row g-3">
                                                <div class="col-12">
                                                    <div class="form-check form-switch form-check-md">
                                                        <input class="form-check-input" v-model="form.is_free" type="checkbox" id="isFreeSwitch">
                                                        <label class="form-check-label" for="isFreeSwitch">درس مجاني</label>
                                                    </div>
                                                </div>
                                                <template v-if="!form.is_free">
                                                    <div class="col-md-6">
                                                        <label class="form-label">سعر الدرس</label>
                                                        <input type="number" class="form-control" v-model="form.price" placeholder="أدخل سعر الدرس">
                                                    </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">سعر بعد الخصم</label>
                                                    <input class="form-control" type="number" v-model="form.discount_price" :disabled="!enableDiscount" placeholder="أدخل سعر الخصم">
                                                    <div class="form-check small mt-1">
                                                        <input class="form-check-input" v-model="enableDiscount" type="checkbox" id="discountCheck">
                                                        <label class="form-check-label" for="discountCheck">تفعيل الخصم</label>
                                                    </div>
                                                </div>
                                                </template>
                                            </div>
                                        </div>

                                        <!-- Buttons -->
                                        <div class="col-12 d-md-flex justify-content-between align-items-start mt-2">
                                            <button type="button" class="btn btn-secondary prev-btn mb-2 mb-md-0" @click="goPrev">السابق</button>
                                            <button
                                                type="button"
                                                :disabled="form.processing"
                                                @click="submit"
                                                class="btn btn-success mb-0"
                                            >
                                                <i class="bi bi-check-lg me-1"></i>
                                                {{ isEditing ? 'حفظ التعديلات' : 'إنشاء الدرس' }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <!-- Step 3 END -->

                            </form>
                        </div>
                    </div>
                    <!-- Stepper Body END -->

                </div>
            </div>
            <!-- Card END -->

        </div>
    </AppLayout>

    <!-- Add Strategy Modal -->
    <div v-if="showStrategyModal" class="modal show d-block" tabindex="-1" style="background:rgba(0,0,0,.5)">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة استراتيجية تدريس</h5>
                    <button type="button" class="btn-close" @click="showStrategyModal = false"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">اسم الاستراتيجية <span class="text-danger">*</span></label>
                        <input
                            v-model="strategyCreateForm.title"
                            type="text"
                            class="form-control"
                            placeholder="مثال: التعلم التعاوني"
                            @keyup.enter="submitStrategy"
                        >
                        <div v-if="strategyCreateForm.errors.title" class="text-danger small mt-1">{{ strategyCreateForm.errors.title }}</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" @click="showStrategyModal = false">إلغاء</button>
                    <button type="button" class="btn btn-primary" :disabled="strategyCreateForm.processing" @click="submitStrategy">
                        <span v-if="strategyCreateForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                        حفظ
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
