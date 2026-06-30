<script setup>
import { reactive, onMounted, ref, onBeforeUnmount, watch, nextTick } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, useForm, Link, usePage, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import Swal from 'sweetalert2'
import Stepper from 'bs-stepper'
import Quill from 'quill'
import Choices from 'choices.js'
import CategoryOptions from './CategoryOptions.vue'
import ClassMultiSelect from '@/Components/Lessons/ClassMultiSelect.vue'

import 'bs-stepper/dist/css/bs-stepper.min.css'
import 'quill/dist/quill.snow.css'
import 'choices.js/public/assets/styles/choices.min.css'

const editor = ref(null)
let quill = null
let stepper = null

const page = usePage()

const props = defineProps({
    categories: Array,
    teachers: Array,
    lectures: Array,
    lesson: Object,
    timetablePeriods: { type: Array, default: () => [] },
    messageTemplates: { type: Array, default: () => [] },
    leafCategories: { type: Array, default: () => [] },
})

// Watch for flash messages
watch(() => page.props.flash, (flash) => {
  if (flash?.success) {
    Swal.fire('نجح!', flash.success, 'success')
  }
  if (flash?.error && !flash?.needs_activation) {
    Swal.fire({
      title: 'تفعيل المحفظة مطلوب',
      html: `
        <p>لرفع الفيديوهات على سيرفر المنصة، يجب تفعيل محفظة التخزين أولاً.</p>
        <p><strong>ستحصل على 20$ مجاناً</strong> كرصيد ترحيبي!</p>
        <p>يمكنك استخدامها لتجربة الخدمة، وعند نفاذ الرصيد يمكنك شحن المحفظة.</p>
      `,
      icon: 'info',
      showCancelButton: true,
      confirmButtonText: 'الذهاب للمحفظة',
      cancelButtonText: 'إلغاء'
    }).then((result) => {
      if (result.isConfirmed) {
        router.visit(route('admin.wallet.index'))
      }
    })
  }
//   if (flash?.error && !flash?.needs_activation) {
//     Swal.fire('تفعيل المحفظة مطلوب!', flash.info, 'info')
//     if (result.isConfirmed) {
//       router.visit(route('admin.wallet.index'))
//     }
//   }
}, { deep: true })

const form = useForm({
    id: props.lesson?.id,
    name: props.lesson?.name ?? '',
    short_description: props.lesson?.short_description ?? '',
    description: props.lesson?.description ?? '',
    strategies: props.lesson?.strategies ?? '',
    category_id: props.lesson?.category_id ?? null,
    class_ids: props.lesson?.classes?.map(c => c.id) ?? [],
    teacher_id: props.lesson?.teacher_id ?? null,
    lesson_message_template_id: props.lesson?.lesson_message_template_id ?? null,
    timetable_period_ids: props.lesson?.timetable_periods?.map(p => p.id) ?? [],
    semester: props.lesson?.semesters ?? [],
    is_featured: Boolean(props.lesson?.is_featured) ?? false,
    expiry_period: props.lesson?.expiry_period ?? 'lifetime',
    expire_date: props.lesson?.expire_date ?? null,
    publish_date: props.lesson?.publish_date ?? null,
    is_free: Boolean(props.lesson?.is_free) ?? false,
    price: props.lesson?.price ?? '',
    discount_price: props.lesson?.discount_price ?? '',
    image: null,
    video_url: props.lesson?.video_url ?? '',
    faqs: props.lesson?.faqs ?? []
})

const LectureForm = useForm({
    name: '',
    lesson_id: props.lesson?.id ?? null,
});

// const FileForm = useForm({
//     name: '',
//     url: '',
//     youtube: ''
// });

function onFileChange(e) {
  const file = e.target.files[0]
  form.image = file
}

// نفس اسم المتغير اللي عندك للخصم
const enableDiscount = ref(!!form.discount_price)

// لو الدرس مجاني اعطّل الخصم
watch(() => form.is_free, (val) => {
  if (val) {
    enableDiscount.value = false
    // سيب الأسعار زي ما هي لو مش عايز تفضيهم
    // form.price = ''
    // form.discount_price = ''
  }
})

const form_inputs = reactive({
  name: '',
  type: 'text',
  required: false,
  options: []
})

const inputsList = reactive([])

onMounted(async () => {
  await nextTick()

  // Stepper
  const stepperEl = document.querySelector('#stepper')
  if (stepperEl) {
    stepper = new Stepper(stepperEl, {
      linear: false,
      animation: false
    })
  }

  // Quill
  if (editor.value) {
    quill = new Quill(editor.value, {
      theme: 'snow',
      placeholder: 'اكتب وصف الكورس هنا...',
      modules: {
        toolbar: [
          [{ header: [1, 2, false] }],
          ['bold', 'italic', 'underline', 'strike'],
          [{ list: 'ordered' }, { list: 'bullet' }],
          ['link', 'image'],
          ['clean']
        ]
      }
    })

    // ضيف الوصف الحالي
    quill.root.innerHTML = form.description || ''

    quill.on('text-change', () => {
      form.description = quill.root.innerHTML
    })
  }

  // Choices.js
  document.querySelectorAll('.js-choice').forEach(el => {
    new Choices(el, {
      removeItemButton: true,
      searchEnabled: true,
      placeholderValue: 'Select option',
      maxItemCount: 5
    })
  })
})

onBeforeUnmount(() => {
  quill = null
  stepper = null
})

function goNext() { stepper?.next() }
function goPrev() { stepper?.previous() }

function addOption() {
  form_inputs.options.push({ value: '' })
}

function removeOption(index) {
  form_inputs.options.splice(index, 1)
}

function addInputToList() {
  if (!form_inputs.name) {
    Swal.fire('تنبيه', 'برجاء إدخال اسم الحقل', 'warning')
    return
  }
  inputsList.push(JSON.parse(JSON.stringify(form_inputs)))
  form_inputs.name = ''
  form_inputs.type = 'text'
  form_inputs.required = false
  form_inputs.options = []
}

function saveForm() {
  form.inputs = inputsList
  // استخدام update بدل store (مع نفس أسلوبك)
  form.put(route('admin.lessons.update', form.id), {
    method: 'put',
    onSuccess: () => {
      Swal.fire('تم الحفظ!', 'تم تعديل الدرس بنجاح.', 'success')
    },
    onError: () => {
      const firstError = Object.values(form.errors || {})[0]
      const errorMsg = firstError || 'حدثت مشكلة أثناء الحفظ.'
      Swal.fire('خطأ!', errorMsg, 'error')
    }
  })
}

function saveLectureForm() {
    LectureForm.inputs = inputsList
    LectureForm.post(route('admin.lectures.store'), {
        method: 'post',
            onSuccess: () => {
            Swal.fire('تم الحفظ!', 'تم تعديل الدرس بنجاح.', 'success')
        },
        onError: () => {
        const firstError = Object.values(LectureForm.errors || {})[0]
        const errorMsg = firstError || 'حدثت مشكلة أثناء الحفظ.'
        Swal.fire('خطأ!', errorMsg, 'error')
        }
    })
}

function saveFileForm() {
    FileForm.inputs = inputsList
    FileForm.post(route('admin.files.store'), {
        method: 'post',
            onSuccess: () => {
            Swal.fire('تم الحفظ!', 'تم حفظ الفيديو بنجاح.', 'success')
        },
        onError: () => {
        const firstError = Object.values(LectureForm.errors || {})[0]
        const errorMsg = firstError || 'حدثت مشكلة أثناء الحفظ.'
        Swal.fire('خطأ!', errorMsg, 'error')
        }
    })
}
const deleteLectureForm = useForm({})

function confirmLectureDelete(id) {
  Swal.fire({
    title: 'هل أنت متأكد؟',
    text: "لن تتمكن من التراجع عن هذا الإجراء!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'نعم، احذف',
    cancelButtonText: 'إلغاء'
  }).then((result) => {
    if (result.isConfirmed) {
        deleteLectureForm.delete(route('admin.lectures.destroy', id), {
            onSuccess: () => {
            Swal.fire('تم الحذف!', 'تم حذف المحاضرة بنجاح.', 'success')
            },
            onError: () => {
            Swal.fire('خطأ!', 'حدثت مشكلة أثناء الحذف.', 'error')
            }
        })
    }
  })
}

// function confirmLectureDelete(id) {
//     Swal.fire({
//         title: 'هل أنت متأكد؟',
//         text: "لن تتمكن من التراجع عن هذا الإجراء!",
//         icon: 'warning',
//         showCancelButton: true,
//         confirmButtonColor: '#d33',
//         cancelButtonColor: '#3085d6',
//         confirmButtonText: 'نعم، احذف',
//         cancelButtonText: 'إلغاء'
//     }).then((result) => {
//         if (result.isConfirmed) {
//             router.delete(route('admin.lectures.destroy', id), {
//                 onSuccess: () => {
//                     Swal.fire('تم الحذف!', 'تم حذف القسم بنجاح.', 'success')
//                 },
//                 onError: () => {
//                     Swal.fire('خطأ!', 'حدثت مشكلة أثناء الحذف.', 'error')
//                 }
//             })
//         }
//     })
// }

const FileForm = useForm({
    name: '',
    file: null,
    type: 'free',
    description: '',
    youtube: '',
    external_link: '',
    lecture_id: null,
    _method: 'post', // will be switched to 'put' when editing
})

// State for editing existing file
const isEditing = ref(false)
const editingFileId = ref(null)

function startEditFile(file) {
    isEditing.value = true
    editingFileId.value = file.id
    FileForm.name = file.name
    FileForm.lecture_id = file.lecture_id ?? null
    FileForm.file = null // no re-upload on edit
    FileForm._method = 'put'
}

const deleteFileForm = useForm({})
function confirmDeleteFile(id) {
    Swal.fire({
        title: 'هل أنت متأكد؟',
        text: 'لن تتمكن من التراجع عن هذا الإجراء!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'نعم، احذف',
        cancelButtonText: 'إلغاء'
    }).then((result) => {
        if (result.isConfirmed) {
            deleteFileForm.delete(route('admin.files.destroy', id), {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire('تم الحذف!', 'تم حذف الملف بنجاح.', 'success')
                },
                onError: () => {
                    Swal.fire('خطأ!', 'حدثت مشكلة أثناء الحذف.', 'error')
                }
            })
        }
    })
}

function handleVideoUpload(e) {
    const file = e.target.files[0]
    if (file) {
        FileForm.file = file
    }
}

// رفع مباشر إلى السيرفر
function submitDirectUpload() {
    if (!FileForm.name) {
        Swal.fire('تنبيه', 'برجاء إدخال اسم الفيديو', 'warning')
        return
    }
    if (!isEditing.value && !FileForm.file) {
        Swal.fire('تنبيه', 'برجاء اختيار ملف فيديو', 'warning')
        return
    }

    if (!FileForm.lecture_id) {
        Swal.fire('تنبيه', 'برجاء اختيار محاضرة', 'warning')
        return
    }

    // Decide route/method based on mode
    if (isEditing.value) {
        FileForm.post(route('admin.files.update', editingFileId.value), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                // Reset form/edit state
                FileForm.reset()
                FileForm._method = 'post'
                isEditing.value = false
                editingFileId.value = null
                // Close offcanvas
                const btn = document.querySelector('#offcanvasRight .btn-close') || document.querySelector('[data-bs-dismiss="offcanvas"]')
                if (btn) btn.click()
            },
            onError: (errors) => {
                const firstError = Object.values(errors || {})[0]
                const errorMsg = firstError || 'حدثت مشكلة أثناء التعديل.'
                Swal.fire('خطأ!', errorMsg, 'error')
            }
        })
        return
    }

    // Create mode: upload to server
    FileForm.post(route('admin.files.uploadToBunny'), {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            // Wait for next tick to ensure props are updated
            setTimeout(() => {
                // Check if wallet activation is needed
                if (page.props.flash?.needs_activation) {
                    Swal.fire({
                        title: 'تفعيل المحفظة مطلوب',
                        html: `
                            <p>لرفع الفيديوهات على سيرفر المنصة، يجب تفعيل محفظة التخزين أولاً.</p>
                            <p><strong>ستحصل على 20$ مجاناً</strong> كرصيد ترحيبي!</p>
                            <p>يمكنك استخدامها لتجربة الخدمة، وعند نفاذ الرصيد يمكنك شحن المحفظة.</p>
                        `,
                        icon: 'info',
                        showCancelButton: true,
                        confirmButtonText: 'الذهاب للمحفظة',
                        cancelButtonText: 'إلغاء'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            router.visit(route('admin.wallet.index'))
                        }
                    })
                    return
                }
                
                // Only close if upload was successful
                if (page.props.flash?.success) {
                    // Reset form
                    FileForm.reset()
                    // Close offcanvas
                    const btn = document.querySelector('#offcanvasRight .btn-close') || document.querySelector('[data-bs-dismiss="offcanvas"]')
                    if (btn) btn.click()
                }
            }, 100)
            // SweetAlert will show automatically from flash message watcher for success messages
        },
        onError: (errors) => {
            const firstError = Object.values(errors || {})[0]
            const errorMsg = firstError || 'حدثت مشكلة أثناء رفع الفيديو.'
            Swal.fire('خطأ!', errorMsg, 'error')
        }
    })
}

function activateWallet() {
    const activationForm = useForm({})
    activationForm.post(route('admin.wallet.activate'), {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire('تم التفعيل!', 'تم تفعيل المحفظة بنجاح! يمكنك الآن رفع الفيديوهات.', 'success')
        },
        onError: () => {
            Swal.fire('خطأ!', 'حدثت مشكلة أثناء تفعيل المحفظة.', 'error')
        }
    })
}

// حفظ لينك يوتيوب أو فيميو
function submitYoutubeLink() {
    if (!FileForm.name) {
        Swal.fire('تنبيه', 'برجاء إدخال اسم الفيديو', 'warning')
        return
    }
    if (!FileForm.youtube) {
        Swal.fire('تنبيه', 'برجاء إدخال رابط YouTube/Vimeo', 'warning')
        return
    }
    if (!FileForm.lecture_id) {
        Swal.fire('تنبيه', 'برجاء اختيار محاضرة', 'warning')
        return
    }

    FileForm.post(route('admin.files.saveYoutubeLink'), {
        preserveScroll: true,
        onSuccess: () => {
            FileForm.reset()
            const btn = document.querySelector('#offcanvasRight .btn-close') || document.querySelector('[data-bs-dismiss="offcanvas"]')
            if (btn) btn.click()
        },
        onError: () => {
            const firstError = Object.values(FileForm.errors || {})[0]
            const errorMsg = firstError || 'حدثت مشكلة أثناء حفظ الرابط.'
            Swal.fire('خطأ!', errorMsg, 'error')
        }
    })
}

// حفظ أي لينك خارجي
function submitExternalLink() {
    if (!FileForm.name) {
        Swal.fire('تنبيه', 'برجاء إدخال اسم الملف', 'warning')
        return
    }
    if (!FileForm.external_link) {
        Swal.fire('تنبيه', 'برجاء إدخال الرابط الخارجي', 'warning')
        return
    }
    if (!FileForm.lecture_id) {
        Swal.fire('تنبيه', 'برجاء اختيار محاضرة', 'warning')
        return
    }

    FileForm.post(route('admin.files.saveExternalLink'), {
        preserveScroll: true,
        onSuccess: () => {
            FileForm.reset()
            const btn = document.querySelector('#offcanvasRight .btn-close') || document.querySelector('[data-bs-dismiss="offcanvas"]')
            if (btn) btn.click()
        },
        onError: () => {
            const firstError = Object.values(FileForm.errors || {})[0]
            const errorMsg = firstError || 'حدثت مشكلة أثناء حفظ الرابط الخارجي.'
            Swal.fire('خطأ!', errorMsg, 'error')
        }
    })
}

</script>

<template>
  <Head title="Edit Lesson" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h3 mb-0">Edit Lesson</h1>
        <a
          v-if="lesson?.id"
          :href="route('lesson.show', lesson.id)"
          target="_blank"
          rel="noopener noreferrer"
          class="btn btn-sm btn-primary"
        >
          <i class="bi bi-eye me-1"></i> معاينة كطالب
        </a>
      </div>

        <!-- Card START -->
        <div class="card border rounded-3 mb-5">
            <div id="stepper" class="bs-stepper stepper-outline">
            <!-- Card header -->
            <div class="card-header bg-light border-bottom px-lg-5">
                <!-- Step Buttons START -->
                <div class="bs-stepper-header" role="tablist">
                <!-- Step 1 -->
                <div class="step" data-target="#step-1">
                    <div class="d-grid text-center align-items-center">
                        <button type="button" class="btn btn-link step-trigger mb-0" role="tab" id="steppertrigger1" aria-controls="step-1">
                            <span class="bs-stepper-circle">1</span>
                        </button>
                        <h6 class="bs-stepper-label d-none d-md-block">Curriculum</h6>
                    </div>
                </div>
                <div class="line"></div>

                <!-- Step 2 -->
                <div class="step" data-target="#step-2">
                    <div class="d-grid text-center align-items-center">
                        <button type="button" class="btn btn-link step-trigger mb-0" role="tab" id="steppertrigger2" aria-controls="step-2">
                            <span class="bs-stepper-circle">2</span>
                        </button>
                        <h6 class="bs-stepper-label d-none d-md-block">Course details</h6>
                    </div>
                </div>
                <div class="line"></div>

                <!-- Step 3 -->
                <div class="step" data-target="#step-3">
                    <div class="d-grid text-center align-items-center">
                        <button type="button" class="btn btn-link step-trigger mb-0" role="tab" id="steppertrigger3" aria-controls="step-3">
                            <span class="bs-stepper-circle">3</span>
                        </button>
                        <h6 class="bs-stepper-label d-none d-md-block">Course media</h6>
                    </div>
                </div>
                <div class="line"></div>

                <!-- Step 4 -->
                <div class="step" data-target="#step-4">
                    <div class="d-grid text-center align-items-center">
                        <button type="button" class="btn btn-link step-trigger mb-0" role="tab" id="steppertrigger4" aria-controls="step-4">
                            <span class="bs-stepper-circle">4</span>
                        </button>
                        <h6 class="bs-stepper-label d-none d-md-block">Lesson Price</h6>
                    </div>
                </div>
                </div>
                <!-- Step Buttons END -->
            </div>

            <!-- Card body START -->
            <div class="card-body px-1 px-sm-4">
                <!-- Step content START -->
                <div class="bs-stepper-content">
                <form @submit.prevent="saveForm">
                    <!-- Step 1 content START (Curriculum) -->
                    <div id="step-1" role="tabpanel" class="content fade" aria-labelledby="steppertrigger1">
                        <!-- Title -->
                        <h4>Curriculum</h4>
                        <hr>
                        <div class="row">
                            <!-- Add lecture Modal button -->
                            <div class="d-sm-flex justify-content-sm-between align-items-center mb-3">
                                <h5 class="mb-2 mb-sm-0">Upload Lecture</h5>
                                <a href="#" class="btn btn-sm btn-primary-soft mb-0" data-bs-toggle="modal" data-bs-target="#addLecture">
                                    <i class="bi bi-plus-circle me-2"></i>Add Lecture
                                </a>
                            </div>
                            <hr>
                            <!-- Edit lecture START -->
                            <div class="accordion accordion-icon accordion-bg-light" id="accordionExample2">
                                <div v-for="(lecture,index) in lesson.lectures" :key="lecture.id" class="accordion-item mb-3">
                                    <h6 class="accordion-header font-base">
                                        <button class="accordion-button fw-bold rounded d-inline-block d-block pe-5" type="button" data-bs-toggle="collapse" :data-bs-target="'#collapse-' + lecture.id" :aria-controls="'collapse-' + lecture.id" :aria-expanded="index === 0">
                                            {{ lecture.name }}
                                        </button>
                                    </h6>

                                    <div :id="'collapse-' + lecture.id" class="accordion-collapse collapse" :class="{ show: index === 0 }" data-bs-parent="#accordionExample2">

                                    <!-- Accordion body -->
                                    <div class="accordion-body mt-3">

                                        <!-- Loop على الملفات -->
                                        <div v-if="lecture.files && lecture.files.length">
                                            <div v-for="file in lecture.files" :key="file.id"
                                                class="d-flex justify-content-between align-items-center mb-2">
                                                <div class="position-relative">
                                                    <a :href="file.path" target="_blank" class="btn btn-danger-soft btn-round btn-sm mb-0 stretched-link position-static">
                                                        <i class="fas fa-play"></i>
                                                    </a>
                                                    <span class="ms-2 mb-0 h6 fw-light">{{ file.name }}</span>
                                                </div>
                                                <div>
                                                    <a href="#" class="btn btn-sm btn-success-soft btn-round me-1 mb-1 mb-md-0"
                                                       data-bs-toggle="offcanvas" data-bs-target="#offcanvasRight"
                                                       @click.prevent="startEditFile(file)">
                                                        <i class="far fa-fw fa-edit"></i>
                                                    </a>
                                                    <button class="btn btn-sm btn-danger-soft btn-round mb-0"
                                                            @click.prevent="confirmDeleteFile(file.id)">
                                                        <i class="fas fa-fw fa-times"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- لو مفيش ملفات -->
                                        <div v-else>
                                            <small class="text-muted">No files for this lecture</small>
                                        </div>

                                        <hr>
                                        <!-- Add topic & delete lecture -->
                                        <a href="#" class="btn btn-sm btn-dark mb-0"  data-bs-toggle="offcanvas" data-bs-target="#offcanvasRight" aria-controls="offcanvasRight">
                                            <i class="bi bi-plus-circle me-2"></i>Add Lesson file
                                        </a>
                                        <a href="#" @click="confirmLectureDelete(lecture.id)" class="btn btn-sm btn-danger-soft mb-0 mt-1 mt-sm-0" style="margin-left: 5px;">
                                            Delete this Lecture
                                        </a>

                                    </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Edit lecture END -->



                            <!-- Step 3 button -->
                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-secondary prev-btn mb-0" disabled>Previous</button>
                                <button type="button" class="btn btn-primary next-btn mb-0" @click="goNext">Next</button>
                            </div>
                        </div>
                    </div>
                    <!-- Step 1 content END -->

                    <!-- Step 2 content START -->
                    <div id="step-2" role="tabpanel" class="content fade" aria-labelledby="steppertrigger2">
                    <!-- Title -->
                        <h4>Course details</h4>

                        <hr> <!-- Divider -->

                    <!-- Basic information START -->
                        <div class="row g-4">
                            <!-- Course title -->
                            <div class="col-12">
                                <label class="form-label">Course title</label>
                                <input class="form-control" type="text" placeholder="Enter course title" v-model="form.name">
                            </div>

                            <!-- Short description -->
                            <div class="col-12">
                                <label class="form-label">Short description</label>
                                <textarea class="form-control" rows="2" placeholder="Enter keywords" v-model="form.short_description"></textarea>
                            </div>

                            <!-- Course category -->
                            <div class="col-md-6">
                                <label class="form-label">Course category</label>
                                <select v-model="form.category_id" class="form-select js-choice border-0 z-index-9 bg-transparent" aria-label=".form-select-sm" data-search-enabled="true">
                                    <option value="">Select category</option>
                                    <CategoryOptions :categories="props.categories" :prefix="''"/>
                                </select>
                            </div>

                            <!-- Teacher -->
                            <div class="col-md-6">
                                <label class="form-label">Teacher</label>
                                <select v-model="form.teacher_id" class="form-select js-choice border-0 z-index-9 bg-transparent" aria-label=".form-select-sm" data-search-enabled="false" data-remove-item-button="true">
                                    <option value="">Select Teacher</option>
                                    <option v-for="t in props.teachers" :key="t.id" :value="t.id">{{ t.name }}</option>
                                </select>
                            </div>

                            <!-- Timetable Periods -->
                            <div class="col-12" v-if="props.timetablePeriods && props.timetablePeriods.length > 0">
                                <label class="form-label">الحصص المرتبطة من الجدول</label>
                                <select 
                                    v-model="form.timetable_period_ids" 
                                    class="form-select" 
                                    multiple
                                    style="min-height: 150px;"
                                >
                                    <option 
                                        v-for="period in props.timetablePeriods" 
                                        :key="period.id" 
                                        :value="period.id"
                                    >
                                        {{ period.day?.day_name }} - {{ period.time_from }} - {{ period.time_to }}
                                        <span v-if="period.category"> ({{ period.category.name }})</span>
                                    </option>
                                </select>
                                <small class="text-muted">يمكنك اختيار أكثر من حصة. اضغط Ctrl (أو Cmd على Mac) للاختيار المتعدد.</small>
                                
                                <!-- Display linked periods -->
                                <div v-if="props.lesson?.timetable_periods && props.lesson.timetable_periods.length > 0" class="mt-3">
                                    <h6>الحصص المرتبطة حالياً:</h6>
                                    <ul class="list-group">
                                        <li 
                                            v-for="period in props.lesson.timetable_periods" 
                                            :key="period.id"
                                            class="list-group-item d-flex justify-content-between align-items-center"
                                        >
                                            <span>
                                                {{ period.day?.day_name }} - {{ period.time_from }} - {{ period.time_to }}
                                                <span v-if="period.category"> ({{ period.category.name }})</span>
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <!-- semester -->
                            <div class="col-md-6">
                            <label class="form-label">semester</label>
                            <select class="form-select js-choice border-0 z-index-9 bg-transparent"
                                    v-model="form.semester"
                                    multiple
                                    aria-label=".form-select-sm"
                                    data-max-item-count="3"
                                    data-remove-item-button="true">
                                <option value="first">First Semester</option>
                                <option value="second">Second semester</option>
                            </select>
                            </div>

                            <!-- featured -->
                            <div class="col-md-6 d-flex align-items-center justify-content-start mt-5">
                            <div class="form-check form-switch form-check-md">
                                <input class="form-check-input" type="checkbox" id="checkPrivacy1" v-model="form.is_featured">
                                <label class="form-check-label" for="checkPrivacy1">Check this for featured course</label>
                            </div>
                            </div>

                            <!-- Expiry period -->
                            <div class="col-md-6">
                                <label class="form-label">Expiry period</label>
                                <select v-model="form.expiry_period" class="form-select" aria-label=".form-select-sm">
                                    <option value="lifetime">Lifetime</option>
                                    <option value="limited">Limited time</option>
                                </select>
                            </div>

                            <!-- Expire date -->
                            <div class="col-md-6">
                                <label class="form-label">Expire Date</label>
                                <input class="form-control" type="date" v-model="form.expire_date" :disabled="form.expiry_period !== 'limited'">
                            </div>

                            <!-- Publish Date -->
                            <div class="col-md-6">
                                <label class="form-label">Publish Date <small class="text-muted">(اختياري)</small></label>
                                <input class="form-control" type="datetime-local" v-model="form.publish_date">
                            </div>

                            <!-- Message Template -->
                            <div class="col-md-6">
                                <label class="form-label">استراتيجيات التدريس <small class="text-muted">(اختياري)</small></label>
                                <select v-model="form.lesson_message_template_id" class="form-select">
                                    <option :value="null">-- بدون استراتيجية --</option>
                                    <option v-for="tpl in props.messageTemplates" :key="tpl.id" :value="tpl.id">
                                        {{ tpl.title }}
                                    </option>
                                </select>
                            </div>

                            <!-- Multi-class assignment -->
                            <div class="col-12">
                                <label class="form-label">تعيين الدرس لفصول متعددة <small class="text-muted">(اختياري)</small></label>
                                <ClassMultiSelect
                                    v-model="form.class_ids"
                                    :options="props.leafCategories"
                                    empty-text="لا توجد فصول دراسية متاحة."
                                />
                            </div>

                            <!-- Strategies -->
                            <div class="col-12">
                                <label class="form-label">استراتيجيات التدريس <small class="text-muted">(اختياري)</small></label>
                                <textarea class="form-control" rows="3" v-model="form.strategies" placeholder="اكتب استراتيجيات التدريس المقترحة..."></textarea>
                            </div>

                            <div class="col-md-12">
                            <label>is Free Lesson</label>
                            <div class="form-check form-switch d-flex form-check-md">
                                <input class="form-check-input" v-model="form.is_free" type="checkbox" />
                            </div>
                            </div>

                            <!-- Course price -->
                            <div class="col-md-6">
                            <label class="form-label">Course price</label>
                            <input type="text" class="form-control mb-2" v-model="form.price" :disabled="form.is_free" placeholder="Enter course price" >
                            </div>

                            <!-- Course discount -->
                            <div class="col-md-6">
                            <label class="form-label">Discount price</label>
                            <input class="form-control" type="text" v-model="form.discount_price" :disabled="!enableDiscount || form.is_free" placeholder="Enter discount">
                            <div class="col-12 mt-1 mb-0">
                                <div class="form-check small mb-0">
                                <input class="form-check-input" v-model="enableDiscount"  type="checkbox" id="checkBox1">
                                <label class="form-check-label" for="checkBox1">
                                    Enable this Discount
                                </label>
                                </div>
                            </div>
                            </div>

                            <!-- Course description -->
                            <div class="col-12">
                            <label class="form-label">Add description</label>
                            <!-- محرر Quill -->
                            <div ref="editor" class="bg-body border rounded h-400px"></div>
                            </div>

                            <!-- Step 1 button -->
                            <div class="d-flex justify-content-end mt-3">
                            <button type="button" class="btn btn-primary next-btn mb-0" @click="goNext">Next</button>
                            </div>
                        </div>
                    <!-- Basic information END -->
                    </div>
                    <!-- Step 2 content END -->

                    <!-- Step 3 content START -->
                    <div id="step-3" role="tabpanel" class="content fade" aria-labelledby="steppertrigger3">
                    <!-- Title -->
                    <h4>Course media</h4>

                    <hr> <!-- Divider -->

                    <div class="row">
                        <!-- Upload image START -->
                        <div class="col-12">
                        <div class="text-center justify-content-center align-items-center p-4 p-sm-5 border border-2 border-dashed position-relative rounded-3">
                            <!-- Image -->
                            <img src="assets/images/element/gallery.svg" class="h-50px" alt="">
                            <div>
                            <h6 class="my-2">Upload course image here, or<a href="#!" class="text-primary"> Browse</a></h6>
                            <label style="cursor:pointer;">
                                <span>
                                <input class="form-control stretched-link" type="file" name="my-image" id="image" accept="image/gif, image/jpeg, image/png" @change="onFileChange" />
                                </span>
                            </label>
                            <p class="small mb-0 mt-2"><b>Note:</b> Only JPG, JPEG and PNG. Our suggested dimensions are 600px * 450px. Larger image will be cropped to 4:3 to fit our thumbnails/previews.</p>
                            </div>
                        </div>
                        </div>
                        <!-- Upload image END -->

                        <!-- Upload video START -->
                        <div class="col-12 mt-3">
                        <h5>Upload video</h5>
                        <!-- Input -->
                        <div class="col-12 mt-4 mb-5">
                            <label class="form-label">Video URL</label>
                            <input class="form-control" type="text" placeholder="Enter video url" v-model="form.video_url">
                        </div>

                        </div>
                        <!-- Upload video END -->

                        <!-- Step 2 button -->
                        <div class="d-flex justify-content-between mt-3">
                        <button type="button" class="btn btn-secondary prev-btn mb-0" @click="goPrev">Previous</button>
                        <button type="button" class="btn btn-primary next-btn mb-0" @click="goNext">Next</button>
                        </div>
                    </div>
                    </div>
                    <!-- Step 3 content END -->

                    <div id="step-4" role="tabpanel" class="content fade" aria-labelledby="steppertrigger4">
                    <!-- Title -->
                    <h4>Lesson Price</h4>

                    <hr> <!-- Divider -->

                    <div class="row g-4">

                        <!-- Edit faq START -->
                        <div class="col-12">
                        <div class="row" >
                            <div class="col-md-12" >
                            <label>is Free Lesson</label>
                            <div class="form-check form-switch d-flex form-check-md">
                                <input class="form-check-input" v-model="form.is_free" type="checkbox" />
                            </div>
                            </div>
                            <!-- Lesson price -->
                            <div class="col-md-6">
                            <label class="form-label">Lesson price</label>
                            <input type="number" class="form-control mb-2" v-model="form.price" :disabled="form.is_free" placeholder="Enter Lesson price" >
                            </div>

                            <!-- Lesson discount -->
                            <div class="col-md-6">
                            <label class="form-label">Discount price</label>
                            <input class="form-control" type="number" v-model="form.discount_price" :disabled="!enableDiscount || form.is_free" placeholder="Enter discount" >
                            <div class="col-12 mt-1 mb-0">
                                <div class="form-check small mb-0">
                                <input class="form-check-input" v-model="enableDiscount"  type="checkbox" id="checkBox2">
                                <label class="form-check-label" for="checkBox2">
                                    Enable this Discount
                                </label>
                                </div>
                            </div>
                            </div>
                        </div>
                        </div>
                        <!-- Edit faq END -->

                        <!-- Step 4 button -->
                        <div class="d-md-flex justify-content-between align-items-start mt-4">
                        <button type="button" class="btn btn-secondary prev-btn mb-2 mb-md-0" @click="goPrev">Previous</button>
                        <div class="text-md-end">
                            <button type="button" :disabled="form.processing" @click="saveForm" class="btn btn-success mb-2 mb-sm-0">Submit a Lesson</button>
                        </div>
                        </div>
                    </div>
                    </div>

                </form>
                </div>
            </div>
            <!-- Card body END -->
            </div>
        </div>
        <!-- Card END -->
    </div>
  </AppLayout>

  <!-- Popup modal for add lecture START -->
  <div class="modal fade" id="addLecture" tabindex="-1" aria-labelledby="addLectureLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header bg-dark">
          <h5 class="modal-title text-white" id="addLectureLabel">Add Lecture</h5>
          <button type="button" class="btn btn-sm btn-light mb-0 ms-auto" data-bs-dismiss="modal" aria-label="Close"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
          <form id="addLectureForm" onsubmit="return false" class="row text-start g-3">
            <!-- Course name -->
            <div class="col-12">
              <label class="form-label">Course name<span class="text-danger">*</span></label>
              <input type="text" v-model="LectureForm.name" class="form-control" placeholder="Enter course name">
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-danger-soft my-0" data-bs-dismiss="modal">Close</button>
          <button form="addLectureForm" :disabled="LectureForm.processing" @click="saveLectureForm" type="button" class="btn btn-success my-0">Save Lecture</button>
        </div>
      </div>
    </div>
  </div>
  <!-- Popup modal for add lecture END -->

<div class="offcanvas offcanvas-end" tabindex="-1" style="width: 510px;" id="offcanvasRight" aria-labelledby="offcanvasRightLabel">
    <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="offcanvasRightLabel">{{ isEditing ? 'Edit Lesson File' : 'Add Lesson File' }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <ul class="nav nav-pills mb-4 justify-content-center"  id="pills-tab" role="tablist">
                    <!-- 🟩 رفع مباشر -->
                    <li class="nav-item" role="presentation">
                        <button
                            class="nav-link active d-flex align-items-center gap-2 "
                            id="pills-upload-tab"
                            data-bs-toggle="pill"
                            data-bs-target="#pills-upload"
                            type="button"
                            role="tab"
                            aria-controls="pills-upload"
                            aria-selected="true">
                            <i class="bi bi-cloud-upload fs-5"></i>
                            <span>Upload file</span>
                        </button>
                    </li>

                    <!-- 🟦 يوتيوب / فيميو -->
                    <li class="nav-item" role="presentation">
                        <button class="nav-link d-flex align-items-center gap-2 " id="pills-youtube-tab"
                            data-bs-toggle="pill"
                            data-bs-target="#pills-youtube"
                            type="button"
                            role="tab"
                            aria-controls="pills-youtube"
                            aria-selected="false">
                            <i class="bi bi-youtube fs-5 text-danger"></i>
                            <span>YouTube Link</span>
                        </button>
                    </li>

                    <!-- 🟨 رابط خارجي -->
                    <li class="nav-item" role="presentation">
                        <button
                            class="nav-link d-flex align-items-center gap-2"
                            id="pills-external-tab"
                            data-bs-toggle="pill"
                            data-bs-target="#pills-external"
                            type="button"
                            role="tab"
                            aria-controls="pills-external"
                            aria-selected="false">
                            <i class="bi bi-link-45deg fs-5"></i>
                            <span>External Link</span>
                        </button>
                    </li>
                </ul>
                <hr>
                <div class="tab-content" id="pills-tabContent" >
                    <!-- 🟩 الحالة الأولى: رفع فيديو مباشر -->
                    <div class="tab-pane fade show active" id="pills-upload" role="tabpanel" aria-labelledby="pills-upload-tab">
                        <!-- Description -->
                        <div class="alert alert-info mb-3">
                            <h6 class="alert-heading">الرفع المباشر على سيرفر المنصة</h6>
                            <p class="mb-2"><strong>المميزات:</strong></p>
                            <ul class="mb-2">
                                <li>جودات متعددة للفيديو تلقائياً (360p, 720p, 1080p)</li>
                                <li>سرعة تشغيل فائقة مع CDN عالمي</li>
                                <li>حماية كاملة من التحميل</li>
                                <li>تحكم كامل في الفيديو والإعدادات</li>
                                <li>إحصائيات مشاهدة تفصيلية</li>
                            </ul>
                            <p class="mb-0"><strong>التكلفة:</strong> يتم الخصم من رصيد المحفظة حسب حجم الملف والاستهلاك</p>
                        </div>
                        <form @submit.prevent="submitDirectUpload" class="row text-start g-3">
                            <!-- اسم الفيديو -->
                            <div class="col-md-12">
                                <label class="form-label">Video name</label>
                                <input class="form-control" v-model="FileForm.name" type="text" placeholder="Enter video name">
                            </div>

                            <!-- اختيار المحاضرة -->
                            <div class="col-md-12">
                                <label class="form-label">Select Lecture</label>
                                <select class="form-select" v-model="FileForm.lecture_id" required>
                                    <option value="">-- Choose a lecture --</option>
                                    <option v-for="lecture in lectures" :key="lecture.id" :value="lecture.id">
                                        {{ lecture.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- رفع الملف (مخفي أثناء التعديل) -->
                            <div class="col-md-12" v-if="!isEditing">
                                <label class="form-label">Upload video file</label>
                                <input class="form-control" accept="video/*" @change="handleVideoUpload" type="file">
                                <small class="text-muted">Allowed formats: MP4, MOV, AVI, MKV</small>
                            </div>
                            <div class="col-md-12" v-else>
                                <div class="alert alert-info py-2 mb-0">
                                    تعديل الملف لا يشمل رفع فيديو جديد. يمكنك فقط تعديل الاسم وربط المحاضرة.
                                </div>
                            </div>

                            <!-- الوصف -->
                            <div class="col-12 mt-3">
                                <label class="form-label">Video description</label>
                                <textarea class="form-control" v-model="FileForm.description" rows="4" spellcheck="false"></textarea>
                            </div>

                            <!-- نوع الفيديو -->
                            <div class="col-6 mt-3">
                                <div class="btn-group" role="group" aria-label="Basic radio toggle button group">
                                    <input type="radio" class="btn-check" v-model="FileForm.type" value="free" id="uploadFree" checked>
                                    <label class="btn btn-sm btn-light btn-primary-soft-check border-0 m-0" for="uploadFree">Free</label>

                                    <input type="radio" class="btn-check" v-model="FileForm.type" value="premium" id="uploadPremium">
                                    <label class="btn btn-sm btn-light btn-primary-soft-check border-0 m-0" for="uploadPremium">Premium</label>
                                </div>
                            </div>

                            <!-- submit -->
                            <div class="col-12 mt-3 text-end">
                                <button type="submit" :disabled="FileForm.processing" class="btn btn-success">
                                    {{ isEditing ? 'Save changes' : 'Upload ' }}
                                </button>
                                <button v-if="isEditing" type="button" class="btn btn-secondary ms-2"
                                        @click="isEditing=false; editingFileId=null; FileForm.reset(); FileForm._method='post'">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- 🟦 الحالة الثانية: YouTube / Vimeo -->
                    <div class="tab-pane fade" id="pills-youtube" role="tabpanel" aria-labelledby="pills-youtube-tab">
                        <!-- Description -->
                        <div class="alert alert-success mb-3">
                            <h6 class="alert-heading">رابط YouTube أو Vimeo</h6>
                            <p class="mb-2"><strong>المميزات:</strong></p>
                            <ul class="mb-2">
                                <li>مجاني تماماً - لا يستهلك من رصيد المحفظة</li>
                                <li>حماية من التحميل (حسب إعدادات YouTube)</li>
                                <li>عدم استهلاك مساحة تخزين</li>
                            </ul>
                            <p class="mb-2"><strong>العيوب:</strong></p>
                            <ul class="mb-0">
                                <li>لا تحكم في جودة الفيديو</li>
                                <li>يعتمد على استمرارية الفيديو على YouTube</li>
                                <li>قد تظهر إعلانات YouTube</li>
                            </ul>
                        </div>
                        <form @submit.prevent="submitYoutubeLink" class="row text-start g-3">
                            <!-- اسم الفيديو -->
                            <div class="col-md-12">
                                <label class="form-label">Video name</label>
                                <input class="form-control" v-model="FileForm.name" type="text" placeholder="Enter video name">
                            </div>

                            <!-- اختيار المحاضرة -->
                            <div class="col-md-12">
                                <label class="form-label">Select Lecture</label>
                                <select class="form-select" v-model="FileForm.lecture_id" required>
                                    <option value="">-- Choose a lecture --</option>
                                    <option v-for="lecture in lectures" :key="lecture.id" :value="lecture.id">
                                        {{ lecture.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- رابط يوتيوب أو فيميو -->
                            <div class="col-md-12">
                                <label class="form-label">YouTube / Vimeo link</label>
                                <input class="form-control" v-model="FileForm.youtube" type="url" placeholder="https://youtube.com/... or https://vimeo.com/...">
                                <small class="text-muted">Paste the full video link</small>
                            </div>

                            <!-- الوصف -->
                            <div class="col-12 mt-3">
                                <label class="form-label">Video description</label>
                                <textarea class="form-control" v-model="FileForm.description" rows="4" spellcheck="false"></textarea>
                            </div>

                            <!-- نوع الفيديو -->
                            <div class="col-6 mt-3">
                                <div class="btn-group" role="group">
                                    <input type="radio" class="btn-check" v-model="FileForm.type" value="free" id="youtubeFree" checked>
                                    <label class="btn btn-sm btn-light btn-primary-soft-check border-0 m-0" for="youtubeFree">Free</label>

                                    <input type="radio" class="btn-check" v-model="FileForm.type" value="premium" id="youtubePremium">
                                    <label class="btn btn-sm btn-light btn-primary-soft-check border-0 m-0" for="youtubePremium">Premium</label>
                                </div>
                            </div>

                            <!-- submit -->
                            <div class="col-12 mt-3 text-end">
                                <button type="submit" :disabled="FileForm.processing" class="btn btn-success">Save YouTube/Vimeo Link</button>
                            </div>
                        </form>
                    </div>

                    <!-- 🟨 الحالة الثالثة: رابط خارجي عام -->
                    <div class="tab-pane fade" id="pills-external" role="tabpanel" aria-labelledby="pills-external-tab">
                        <!-- Description -->
                        <div class="alert alert-warning mb-3">
                            <h6 class="alert-heading">رابط خارجي (External Link)</h6>
                            <p class="mb-2"><strong>الاستخدام:</strong></p>
                            <ul class="mb-2">
                                <li>تضمين أي نوع ملف (فيديو، PDF، PowerPoint، Word، Excel)</li>
                                <li>للملفات المستضافة على مواقع خارجية (Google Drive، Dropbox، إلخ)</li>
                                <li>مجاني - لا يستهلك من رصيد المحفظة</li>
                            </ul>
                            <p class="mb-0"><strong>ملاحظة:</strong> تأكد من أن الرابط عام ويسمح بالتضمين (Embedding)</p>
                        </div>
                        <form @submit.prevent="submitExternalLink" class="row text-start g-3">
                            <!-- اسم الفيديو -->
                            <div class="col-md-12">
                                <label class="form-label">Video name</label>
                                <input class="form-control" v-model="FileForm.name" type="text" placeholder="Enter video name">
                            </div>

                            <!-- اختيار المحاضرة -->
                            <div class="col-md-12">
                                <label class="form-label">Select Lecture</label>
                                <select class="form-select" v-model="FileForm.lecture_id" required>
                                    <option value="">-- Choose a lecture --</option>
                                    <option v-for="lecture in lectures" :key="lecture.id" :value="lecture.id">
                                        {{ lecture.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- رابط خارجي -->
                            <div class="col-md-12">
                                <label class="form-label">External link</label>
                                <input class="form-control" v-model="FileForm.external_link" type="url" placeholder="https://example.com/file.pdf">
                                <small class="text-muted">Enter a valid public link (video, PDF, PowerPoint, etc.)</small>
                            </div>

                            <!-- الوصف -->
                            <div class="col-12 mt-3">
                                <label class="form-label">Video description</label>
                                <textarea class="form-control" v-model="FileForm.description" rows="4" spellcheck="false"></textarea>
                            </div>

                            <!-- نوع الفيديو -->
                            <div class="col-6 mt-3">
                                <div class="btn-group" role="group">
                                    <input type="radio" class="btn-check" v-model="FileForm.type" value="free" id="externalFree" checked>
                                    <label class="btn btn-sm btn-light btn-primary-soft-check border-0 m-0" for="externalFree">Free</label>

                                    <input type="radio" class="btn-check" v-model="FileForm.type" value="premium" id="externalPremium">
                                    <label class="btn btn-sm btn-light btn-primary-soft-check border-0 m-0" for="externalPremium">Premium</label>
                                </div>
                            </div>

                            <!-- submit -->
                            <div class="col-12 mt-3 text-end">
                                <button type="submit" :disabled="FileForm.processing" class="btn btn-success">Save External Link</button>
                            </div>
                        </form>
                    </div>
                </div>
    </div>
</div>

  <!-- Popup modal for add topic START -->
  <div class="modal fade" id="addTopic" tabindex="-1" aria-labelledby="addTopicLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header bg-dark">
          <h5 class="modal-title text-white" id="addTopicLabel">Add Video</h5>
          <button type="button" class="btn btn-sm btn-light mb-0 ms-auto" data-bs-dismiss="modal" aria-label="Close"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <div class="d-flex align-items-start">

            </div>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-danger-soft my-0" data-bs-dismiss="modal">Close</button>
          <button type="submut" form="addVideoForm" @click="saveFileForm" class="btn btn-success my-0">Save Video</button>
        </div>
      </div>
    </div>
  </div>
  <!-- Popup modal for add topic END -->

  <!-- Popup modal for add faq START -->
  <div class="modal fade" id="addQuestion" tabindex="-1" aria-labelledby="addQuestionLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header bg-dark">
          <h5 class="modal-title text-white" id="addQuestionLabel">Add FAQ</h5>
          <button type="button" class="btn btn-sm btn-light mb-0 ms-auto" data-bs-dismiss="modal" aria-label="Close"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
          <form class="row text-start g-3">
            <!-- Question -->
            <div class="col-12">
              <label class="form-label">Question</label>
              <input class="form-control" type="text" placeholder="Write a question">
            </div>
            <!-- Answer -->
            <div class="col-12 mt-3">
              <label class="form-label">Answer</label>
              <textarea class="form-control" rows="4" placeholder="Write a answer" spellcheck="false"></textarea>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-danger-soft my-0" data-bs-dismiss="modal">Close</button>
          <button type="button" class="btn btn-success my-0">Save Video</button>
        </div>
      </div>
    </div>
  </div>
  <!-- Popup modal for add faq END -->
</template>
