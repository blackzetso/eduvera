<script setup>
import { ref, onMounted, nextTick, watch } from 'vue'
import AppLayout from '@/Pages/Teacher/Layout/App.vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import Swal from 'sweetalert2'
import Stepper from 'bs-stepper'
import 'bs-stepper/dist/css/bs-stepper.min.css'

const page = usePage()

const props = defineProps({
    lesson: { type: Object, required: true },
})

let stepper = null

watch(() => page.props.flash, (flash) => {
    if (flash?.success) {
        Swal.fire('تم!', flash.success, 'success')
    }
    if (flash?.error && flash?.needs_activation) {
        Swal.fire({
            title: 'تفعيل المحفظة مطلوب',
            html: '<p>لرفع الفيديوهات على سيرفر المنصة، يجب تفعيل محفظة التخزين أولاً.</p>',
            icon: 'info',
            confirmButtonText: 'حسناً',
        })
    }
}, { deep: true })

const LectureForm = useForm({
    name: '',
    lesson_id: props.lesson.id,
})

const FileForm = useForm({
    name: '',
    file: null,
    type: 'free',
    description: '',
    youtube: '',
    external_link: '',
    lecture_id: null,
    _method: 'post',
})

const isEditingFile = ref(false)
const editingFileId = ref(null)
const deleteLectureForm = useForm({})
const deleteFileForm = useForm({})

onMounted(async () => {
    await nextTick()
    const el = document.querySelector('#teacher-lesson-edit-stepper')
    if (el) {
        stepper = new Stepper(el, { linear: false, animation: false })
    }
})

function goNext() { stepper?.next() }
function goPrev() { stepper?.previous() }

function saveLectureForm() {
    LectureForm.post(route('teacher.lectures.store'), {
        preserveScroll: true,
        onSuccess: () => {
            LectureForm.reset()
            LectureForm.lesson_id = props.lesson.id
            document.querySelector('#addLecture [data-bs-dismiss="modal"]')?.click()
        },
        onError: () => {
            const msg = Object.values(LectureForm.errors || {})[0] || 'حدثت مشكلة أثناء الحفظ.'
            Swal.fire('خطأ!', msg, 'error')
        },
    })
}

function confirmLectureDelete(id) {
    Swal.fire({
        title: 'هل أنت متأكد؟',
        text: 'سيتم حذف المحاضرة وملفاتها.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'نعم، احذف',
        cancelButtonText: 'إلغاء',
    }).then((result) => {
        if (result.isConfirmed) {
            deleteLectureForm.delete(route('teacher.lectures.destroy', id), { preserveScroll: true })
        }
    })
}

function openAddFile(lectureId) {
    isEditingFile.value = false
    editingFileId.value = null
    FileForm.reset()
    FileForm._method = 'post'
    FileForm.lecture_id = lectureId
}

function startEditFile(file) {
    isEditingFile.value = true
    editingFileId.value = file.id
    FileForm.name = file.name
    FileForm.lecture_id = file.lecture_id ?? null
    FileForm.file = null
    FileForm._method = 'put'
}

function handleVideoUpload(e) {
    FileForm.file = e.target.files[0] ?? null
}

function closeOffcanvas() {
    document.querySelector('#offcanvasRight .btn-close')?.click()
}

function submitDirectUpload() {
    if (!FileForm.name || !FileForm.lecture_id) {
        Swal.fire('تنبيه', 'برجاء إدخال اسم الفيديو واختيار المحاضرة', 'warning')
        return
    }
    if (!isEditingFile.value && !FileForm.file) {
        Swal.fire('تنبيه', 'برجاء اختيار ملف فيديو', 'warning')
        return
    }

    const url = isEditingFile.value
        ? route('teacher.files.update', editingFileId.value)
        : route('teacher.files.uploadToBunny')

    FileForm.post(url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            FileForm.reset()
            FileForm._method = 'post'
            isEditingFile.value = false
            editingFileId.value = null
            closeOffcanvas()
        },
        onError: (errors) => {
            Swal.fire('خطأ!', Object.values(errors || {})[0] || 'حدثت مشكلة أثناء الرفع.', 'error')
        },
    })
}

function submitYoutubeLink() {
    if (!FileForm.name || !FileForm.youtube || !FileForm.lecture_id) {
        Swal.fire('تنبيه', 'برجاء إكمال جميع الحقول', 'warning')
        return
    }
    FileForm.post(route('teacher.files.saveYoutubeLink'), {
        preserveScroll: true,
        onSuccess: () => { FileForm.reset(); closeOffcanvas() },
        onError: (errors) => Swal.fire('خطأ!', Object.values(errors || {})[0] || 'فشل الحفظ.', 'error'),
    })
}

function submitExternalLink() {
    if (!FileForm.name || !FileForm.external_link || !FileForm.lecture_id) {
        Swal.fire('تنبيه', 'برجاء إكمال جميع الحقول', 'warning')
        return
    }
    FileForm.post(route('teacher.files.saveExternalLink'), {
        preserveScroll: true,
        onSuccess: () => { FileForm.reset(); closeOffcanvas() },
        onError: (errors) => Swal.fire('خطأ!', Object.values(errors || {})[0] || 'فشل الحفظ.', 'error'),
    })
}

function confirmDeleteFile(id) {
    Swal.fire({
        title: 'هل أنت متأكد؟',
        text: 'سيتم حذف الملف نهائياً.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'نعم، احذف',
        cancelButtonText: 'إلغاء',
    }).then((result) => {
        if (result.isConfirmed) {
            deleteFileForm.delete(route('teacher.files.destroy', id), { preserveScroll: true })
        }
    })
}
</script>

<template>
    <Head title="تعديل درس" />
    <AppLayout>
        <div class="page-content-wrapper border">

            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <Link :href="route('teacher.lessons.index')" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-right me-1"></i> دروسي
                    </Link>
                    <h3 class="mb-0">تعديل درس</h3>
                </div>
                <a
                    :href="route('lesson.show', lesson.id)"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="btn btn-sm btn-primary"
                >
                    <i class="bi bi-eye me-1"></i> معاينة كطالب
                </a>
            </div>

            <div class="card border rounded-3 mb-5">
                <div id="teacher-lesson-edit-stepper" class="bs-stepper stepper-outline">

                    <div class="card-header bg-light border-bottom px-lg-5">
                        <div class="bs-stepper-header" role="tablist">
                            <div class="step" data-target="#te-step-1">
                                <div class="d-grid text-center align-items-center">
                                    <button type="button" class="btn btn-link step-trigger mb-0" role="tab" id="te-step-1-trigger" aria-controls="te-step-1">
                                        <span class="bs-stepper-circle">1</span>
                                    </button>
                                    <h6 class="bs-stepper-label d-none d-md-block">المحتوى</h6>
                                </div>
                            </div>
                            <div class="line"></div>
                            <div class="step" data-target="#te-step-2">
                                <div class="d-grid text-center align-items-center">
                                    <button type="button" class="btn btn-link step-trigger mb-0" role="tab" aria-controls="te-step-2">
                                        <span class="bs-stepper-circle">2</span>
                                    </button>
                                    <h6 class="bs-stepper-label d-none d-md-block">تفاصيل الدرس</h6>
                                </div>
                            </div>
                            <div class="line"></div>
                            <div class="step" data-target="#te-step-3">
                                <div class="d-grid text-center align-items-center">
                                    <button type="button" class="btn btn-link step-trigger mb-0" role="tab" aria-controls="te-step-3">
                                        <span class="bs-stepper-circle">3</span>
                                    </button>
                                    <h6 class="bs-stepper-label d-none d-md-block">وسائط الدرس</h6>
                                </div>
                            </div>
                            <div class="line"></div>
                            <div class="step" data-target="#te-step-4">
                                <div class="d-grid text-center align-items-center">
                                    <button type="button" class="btn btn-link step-trigger mb-0" role="tab" aria-controls="te-step-4">
                                        <span class="bs-stepper-circle">4</span>
                                    </button>
                                    <h6 class="bs-stepper-label d-none d-md-block">سعر الدرس</h6>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-body px-1 px-sm-4">
                        <div class="bs-stepper-content">

                            <!-- Step 1: المحتوى -->
                            <div id="te-step-1" role="tabpanel" class="content fade" aria-labelledby="te-step-1-trigger">
                                <h4>المحتوى</h4>
                                <hr>
                                <div class="d-sm-flex justify-content-sm-between align-items-center mb-3">
                                    <h5 class="mb-2 mb-sm-0">رفع محاضرة</h5>
                                    <button type="button" class="btn btn-sm btn-primary-soft mb-0" data-bs-toggle="modal" data-bs-target="#addLecture">
                                        <i class="bi bi-plus-circle me-2"></i>إضافة محاضرة
                                    </button>
                                </div>
                                <hr>

                                <div class="accordion accordion-icon accordion-bg-light" id="teacherLecturesAccordion">
                                    <div v-for="(lecture, index) in (lesson.lectures ?? [])" :key="lecture.id" class="accordion-item mb-3">
                                        <h6 class="accordion-header font-base">
                                            <button
                                                class="accordion-button fw-bold rounded d-block pe-5"
                                                type="button"
                                                data-bs-toggle="collapse"
                                                :data-bs-target="'#collapse-' + lecture.id"
                                                :aria-expanded="index === 0"
                                            >
                                                {{ lecture.name }}
                                            </button>
                                        </h6>
                                        <div :id="'collapse-' + lecture.id" class="accordion-collapse collapse" :class="{ show: index === 0 }">
                                            <div class="accordion-body mt-3">
                                                <div v-if="lecture.files?.length">
                                                    <div v-for="file in lecture.files" :key="file.id" class="d-flex justify-content-between align-items-center mb-2">
                                                        <div class="position-relative">
                                                            <a :href="file.path || file.url" target="_blank" class="btn btn-danger-soft btn-round btn-sm mb-0 stretched-link position-static">
                                                                <i class="fas fa-play"></i>
                                                            </a>
                                                            <span class="ms-2 mb-0 h6 fw-light">{{ file.name }}</span>
                                                        </div>
                                                        <div>
                                                            <button type="button" class="btn btn-sm btn-success-soft btn-round me-1 mb-0"
                                                                data-bs-toggle="offcanvas" data-bs-target="#offcanvasRight"
                                                                @click="startEditFile(file)">
                                                                <i class="far fa-fw fa-edit"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-danger-soft btn-round mb-0"
                                                                @click="confirmDeleteFile(file.id)">
                                                                <i class="fas fa-fw fa-times"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div v-else>
                                                    <small class="text-muted">لا توجد ملفات لهذه المحاضرة</small>
                                                </div>
                                                <hr>
                                                <button type="button" class="btn btn-sm btn-dark mb-0"
                                                    data-bs-toggle="offcanvas" data-bs-target="#offcanvasRight"
                                                    @click="openAddFile(lecture.id)">
                                                    <i class="bi bi-plus-circle me-2"></i>إضافة ملف للدرس
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger-soft mb-0 ms-2"
                                                    @click="confirmLectureDelete(lecture.id)">
                                                    حذف المحاضرة
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="!lesson.lectures?.length" class="text-center text-muted py-4">
                                    <i class="bi bi-collection-play fs-1 d-block mb-2 opacity-25"></i>
                                    لا توجد محاضرات بعد — اضغط «إضافة محاضرة» للبدء
                                </div>

                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-secondary mb-0" disabled>السابق</button>
                                    <button type="button" class="btn btn-primary mb-0" @click="goNext">التالي</button>
                                </div>
                            </div>

                            <!-- Steps 2–4: redirect to details editor -->
                            <div id="te-step-2" role="tabpanel" class="content fade">
                                <h4>تفاصيل الدرس</h4>
                                <p class="text-muted">عدّل اسم الدرس، الوصف، المادة، والفصول من صفحة التفاصيل.</p>
                                <Link :href="route('teacher.lessons.details', lesson.id)" class="btn btn-primary">
                                    <i class="bi bi-pencil me-1"></i> تعديل التفاصيل
                                </Link>
                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-secondary mb-0" @click="goPrev">السابق</button>
                                    <button type="button" class="btn btn-primary mb-0" @click="goNext">التالي</button>
                                </div>
                            </div>

                            <div id="te-step-3" role="tabpanel" class="content fade">
                                <h4>وسائط الدرس</h4>
                                <p class="text-muted">ارفع صورة الدرس من صفحة التفاصيل. الفيديوهات تُضاف هنا في خطوة «المحتوى».</p>
                                <Link :href="route('teacher.lessons.details', lesson.id)" class="btn btn-primary">
                                    <i class="bi bi-image me-1"></i> تعديل صورة الدرس
                                </Link>
                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-secondary mb-0" @click="goPrev">السابق</button>
                                    <button type="button" class="btn btn-primary mb-0" @click="goNext">التالي</button>
                                </div>
                            </div>

                            <div id="te-step-4" role="tabpanel" class="content fade">
                                <h4>سعر الدرس</h4>
                                <p class="text-muted">عدّل السعر والخصم من صفحة التفاصيل.</p>
                                <Link :href="route('teacher.lessons.details', lesson.id)" class="btn btn-primary">
                                    <i class="bi bi-tag me-1"></i> تعديل السعر
                                </Link>
                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-secondary mb-0" @click="goPrev">السابق</button>
                                    <Link :href="route('teacher.lessons.index')" class="btn btn-success mb-0">إنهاء</Link>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>

    <!-- Add lecture modal -->
    <div class="modal fade" id="addLecture" tabindex="-1" aria-labelledby="addLectureLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-dark">
                    <h5 class="modal-title text-white" id="addLectureLabel">إضافة محاضرة</h5>
                    <button type="button" class="btn btn-sm btn-light mb-0 ms-auto" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">اسم الدرس <span class="text-danger">*</span></label>
                            <input type="text" v-model="LectureForm.name" class="form-control" placeholder="أدخل اسم الدرس">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger-soft my-0" data-bs-dismiss="modal">إغلاق</button>
                    <button type="button" :disabled="LectureForm.processing" @click="saveLectureForm" class="btn btn-success my-0">حفظ المحاضرة</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add file offcanvas -->
    <div class="offcanvas offcanvas-end" tabindex="-1" style="width: 510px;" id="offcanvasRight">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title">{{ isEditingFile ? 'تعديل ملف' : 'إضافة ملف للدرس' }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <ul class="nav nav-pills mb-4 justify-content-center" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#pills-upload" type="button">رفع ملف</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#pills-youtube" type="button">YouTube / Vimeo</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#pills-external" type="button">رابط خارجي</button>
                </li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="pills-upload">
                    <form @submit.prevent="submitDirectUpload" class="row g-3">
                        <div class="col-12">
                            <label class="form-label">اسم الفيديو</label>
                            <input class="form-control" v-model="FileForm.name" type="text" placeholder="أدخل اسم الفيديو">
                        </div>
                        <div class="col-12">
                            <label class="form-label">المحاضرة</label>
                            <select class="form-select" v-model="FileForm.lecture_id" required>
                                <option :value="null">-- اختر محاضرة --</option>
                                <option v-for="lecture in lesson.lectures" :key="lecture.id" :value="lecture.id">{{ lecture.name }}</option>
                            </select>
                        </div>
                        <div class="col-12" v-if="!isEditingFile">
                            <label class="form-label">رفع ملف فيديو</label>
                            <input class="form-control" accept="video/*" @change="handleVideoUpload" type="file">
                        </div>
                        <div class="col-12">
                            <label class="form-label">الوصف</label>
                            <textarea class="form-control" v-model="FileForm.description" rows="3"></textarea>
                        </div>
                        <div class="col-12 text-end">
                            <button type="submit" :disabled="FileForm.processing" class="btn btn-success">{{ isEditingFile ? 'حفظ' : 'رفع' }}</button>
                        </div>
                    </form>
                </div>
                <div class="tab-pane fade" id="pills-youtube">
                    <form @submit.prevent="submitYoutubeLink" class="row g-3">
                        <div class="col-12">
                            <label class="form-label">اسم الفيديو</label>
                            <input class="form-control" v-model="FileForm.name" type="text">
                        </div>
                        <div class="col-12">
                            <label class="form-label">المحاضرة</label>
                            <select class="form-select" v-model="FileForm.lecture_id" required>
                                <option :value="null">-- اختر محاضرة --</option>
                                <option v-for="lecture in lesson.lectures" :key="lecture.id" :value="lecture.id">{{ lecture.name }}</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">رابط YouTube / Vimeo</label>
                            <input class="form-control" v-model="FileForm.youtube" type="url" placeholder="https://youtube.com/...">
                        </div>
                        <div class="col-12 text-end">
                            <button type="submit" :disabled="FileForm.processing" class="btn btn-success">حفظ</button>
                        </div>
                    </form>
                </div>
                <div class="tab-pane fade" id="pills-external">
                    <form @submit.prevent="submitExternalLink" class="row g-3">
                        <div class="col-12">
                            <label class="form-label">اسم الملف</label>
                            <input class="form-control" v-model="FileForm.name" type="text">
                        </div>
                        <div class="col-12">
                            <label class="form-label">المحاضرة</label>
                            <select class="form-select" v-model="FileForm.lecture_id" required>
                                <option :value="null">-- اختر محاضرة --</option>
                                <option v-for="lecture in lesson.lectures" :key="lecture.id" :value="lecture.id">{{ lecture.name }}</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">رابط خارجي</label>
                            <input class="form-control" v-model="FileForm.external_link" type="url" placeholder="https://...">
                        </div>
                        <div class="col-12 text-end">
                            <button type="submit" :disabled="FileForm.processing" class="btn btn-success">حفظ</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
