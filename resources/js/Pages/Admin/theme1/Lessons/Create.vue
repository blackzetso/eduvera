<script setup>
    import { reactive, onMounted, ref, onBeforeUnmount, watch, nextTick } from 'vue'
    import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
    import { Head, useForm ,Link } from '@inertiajs/vue3'
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

    const props = defineProps({
        categories: Array,
        teachers: Array,
        timetablePeriods: { type: Array, default: () => [] },
        fromPeriod: { type: Object, default: null },
        messageTemplates: { type: Array, default: () => [] },
        leafCategories: { type: Array, default: () => [] },
    })

    // form
    const form = useForm({
        name: '',
        short_description: '',
        description: '',
        strategies: '',
        category_id: null,
        class_ids: [],
        teacher_id: null,
        lesson_message_template_id: null,
        timetable_period_id: null,
        timetable_period_ids: [],
        semester: [],
        is_featured: false,
        expiry_period: 'lifetime',
        expire_date: null,
        publish_date: null,
        is_free: false,
        price: '',
        discount_price: '',
        image: null,
        video_url: '',
        faqs: []
    })

    // Set timetable_period_id if coming from timetable
    if (props.fromPeriod) {
        form.timetable_period_id = props.fromPeriod.id
        form.timetable_period_ids = [props.fromPeriod.id]
    }

    function onFileChange(e) {
    const file = e.target.files[0]
    form.image = file
    }

    const enableDiscount = ref(false)

    watch(() => form.is_free, (val) => {
        if (val) enableDiscount.value = false
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
        animation: true
        })
    }

    //   Quill
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


        quill.on('text-change', () => {
        form.description = quill.root.innerHTML
        })
    }

    //   Choices.js
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
        form.post(route('admin.lessons.store'), {
            onSuccess: () => {
                Swal.fire('تم الحفظ!', 'تم إنشاء الدرس بنجاح.', 'success')
            }
            ,
            onError: () => {
            let errorMsg = form.errors.message
                ? form.errors.message
                : 'حدثت مشكلة أثناء الحفظ.'
                Swal.fire('خطأ!', errorMsg, 'error')
            }
        })
    }
</script>
<template>
  <Head title="Create Lesson" />
  <AppLayout>
        <div class="page-content-wrapper border">

		<h1 class="h3 mb-3">Create Lesson</h1>

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
								<h6 class="bs-stepper-label d-none d-md-block">Lesson details</h6>
							</div>
						</div>
						<div class="line"></div>

						<!-- Step 2 -->
						<div class="step" data-target="#step-2">
							<div class="d-grid text-center align-items-center">
								<button type="button" class="btn btn-link step-trigger mb-0" role="tab" id="steppertrigger2" aria-controls="step-2">
									<span class="bs-stepper-circle">2</span>
								</button>
								<h6 class="bs-stepper-label d-none d-md-block">Lesson media</h6>
							</div>
						</div>
						<div class="line"></div>

						<!-- Step 4 -->
						<div class="step" data-target="#step-4">
							<div class="d-grid text-center align-items-center">
								<button type="button" class="btn btn-link step-trigger mb-0" role="tab" id="steppertrigger4" aria-controls="step-4">
									<span class="bs-stepper-circle">3</span>
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
						<form onsubmit="return false">

							<!-- Step 1 content START -->
							<div id="step-1" role="tabpanel" class="content fade" aria-labelledby="steppertrigger1">
								<!-- Title -->
								<h4>Lesson details</h4>

								<hr> <!-- Divider -->

								<!-- Basic information START -->
								<div class="row g-4">
									<!-- Lesson title -->
									<div class="col-12">
										<label class="form-label">Lesson title</label>
										<input class="form-control" v-model="form.name" type="text" placeholder="Enter Lesson title" >
									</div>

									<!-- Short description -->
									<div class="col-12">
										<label class="form-label">Short description</label>
										<textarea class="form-control" rows="2" v-model="form.short_description" placeholder="Enter Short Descriptions"></textarea>
									</div>

									<!-- Lesson category -->
									<div class="col-md-6">
										<label class="form-label">Lesson category</label>
                                        <select v-model="form.category_id"
                                                class="form-select"
                                                style="height:50px !important;"
                                                aria-label=".form-select-sm"
                                                data-search-enabled="true">
                                            <option :value="null">قسم رئيسي</option>
                                            <CategoryOptions :categories="props.categories" />
                                        </select>
									</div>

									<!-- Lesson level -->
									<div class="col-md-6">
										<label class="form-label">Teacher</label>
										<select v-model="form.teacher_id" class="form-select js-choice border-0 z-index-9 bg-transparent" aria-label=".form-select-sm" data-search-enabled="false" data-remove-item-button="true">
											<option :value="null" >Select teacher</option>
                                            <option v-for="teacher in props.teachers" :value="teacher.id" :key="teacher.id">{{ teacher.name }}</option>
										</select>
									</div>

									<!-- Timetable Periods -->
									<div class="col-12" v-if="props.timetablePeriods && props.timetablePeriods.length > 0">
										<label class="form-label">الحصص من الجدول (اختياري - يمكن اختيار أكثر من حصة)</label>
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
										<small class="text-muted d-block mt-1">
											<i class="bi bi-info-circle"></i> يمكنك اختيار أكثر من حصة. اضغط Ctrl (أو Cmd على Mac) للاختيار المتعدد.
										</small>
										<small v-if="props.fromPeriod" class="text-success d-block mt-1">
											<i class="bi bi-check-circle"></i> تم اختيار الحصة تلقائياً من الجدول
										</small>
									</div>

								<!-- Language -->
								<div class="col-md-6">
									<label class="form-label">Semester</label>
									<select v-model="form.semester" class="form-select js-choice border-0 z-index-9 bg-transparent" multiple="multiple" aria-label=".form-select-sm" data-max-item-count="3" data-remove-item-button="true">
										<option value="first">First Semester</option>
										<option value="second">Second Semester</option>
									</select>
								</div>

								<!-- Switch -->
								<div class="col-md-6 d-flex align-items-center justify-content-start mt-5">
									<div class="form-check form-switch form-check-md">
										<input v-model="form.is_featured" class="form-check-input" type="checkbox" id="checkPrivacy1">
										<label class="form-check-label" for="checkPrivacy1">Check this for featured Lesson</label>
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

								<!-- Expire Date -->
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
									<label class="form-label">تعيين الدرس لفصول متعددة <small class="text-muted">(اختياري - يمكن اختيار أكثر من فصل)</small></label>
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

								<!-- Lesson description -->
								<div class="col-12">
									<label class="form-label">Add description</label>
									<div ref="editor" class="bg-body border rounded h-400px"></div>
								</div>

									<!-- Step 1 button -->
									<div class="d-flex justify-content-end mt-3">
										<button type="button" class="btn btn-primary next-btn mb-0" @click="goNext">Next</button>
									</div>
								</div>
								<!-- Basic information START -->
							</div>
							<!-- Step 1 content END -->

							<!-- Step 2 content START -->
							<div id="step-2" role="tabpanel" class="content fade" aria-labelledby="steppertrigger2">
								<!-- Title -->
								<h4>Lesson media</h4>

								<hr> <!-- Divider -->

								<div class="row">
									<!-- Upload image START -->
									<div class="col-12">
										<div class="text-center justify-content-center align-items-center p-4 p-sm-5 border border-2 border-dashed position-relative rounded-3">
											<!-- Image -->
											<img src="assets/images/element/gallery.svg" class="h-50px" alt="">
											<div>
												<h6 class="my-2">Upload Lesson image here, or<a href="#!" class="text-primary"> Browse</a></h6>
												<label style="cursor:pointer;">
													<span>
														<input class="form-control stretched-link" @change="onFileChange" type="file" id="image" accept="image/gif, image/jpeg, image/png" />
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
											<input class="form-control" type="text" v-model="form.video_url" placeholder="Enter video url" value="https://www.youtube.com/embed/tXHviS-4ygo">
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
							<!-- Step 2 content END -->

							<!-- Step 4 content START -->
							<div id="step-4" role="tabpanel" class="content fade" aria-labelledby="steppertrigger4">
								<!-- Title -->
								<h4>Lesson Price</h4>

								<hr> <!-- Divider -->

								<div class="row g-4">

									<div class="col-12">
                                        <div class="row">
                                            <div class="col-md-12">
                                                <label>Is Free Lesson</label>
                                                <div class="form-check form-switch d-flex form-check-md">
                                                    <input class="form-check-input" v-model="form.is_free" type="checkbox" />
                                                </div>
                                            </div>
                                            <div class="col-md-6 mt-2">
                                                <label class="form-label">Lesson price</label>
                                                <input type="number" class="form-control mb-2" v-model="form.price" :disabled="form.is_free" placeholder="Enter Lesson price">
                                            </div>
                                            <div class="col-md-6 mt-2">
                                                <label class="form-label">Discount price</label>
                                                <input class="form-control" type="number" v-model="form.discount_price" :disabled="!enableDiscount" placeholder="Enter discount">
                                                <div class="col-12 mt-1 mb-0">
                                                    <div class="form-check small mb-0">
                                                        <input class="form-check-input" v-model="enableDiscount" type="checkbox" id="checkBox1">
                                                        <label class="form-check-label" for="checkBox1">Enable this Discount</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
									</div>

									<!-- Tags START -->
									<!-- <div class="col-12">
										<div class="bg-light border rounded p-4">
											<h5 class="mb-0">Tags</h5>
											<div class="mt-3">
												<input type="text" class="form-control js-choice mb-0" value="java, javascript, finance" data-placeholder="true" data-placeholder-Val="Enter tags" data-max-item-count="14" data-remove-item-button="true">
												<span class="small">Maximum of 14 keywords. Keywords should all be in lowercase and separated by commas. e.g. javascript, react, marketing</span>
											</div>
										</div>
									</div> -->
									<!-- Tags START -->

									<!-- Reviewer START -->
                                    <!-- Only On Instructor Account -->
									<!-- <div class="col-12">
										<div class="bg-light border rounded p-4">
											<h5 class="mb-0">Message to a reviewer</h5>
											<div class="mt-3">
												<textarea class="form-control" rows="4" placeholder="Write a message" spellcheck="false">Perceived end knowledge certainly day sweetness why cordially. Ask a quick six seven offer see among. Handsome met debating sir dwelling age material. As style lived he worse dried. Offered related so visitors we private removed. Moderate do subjects to distance.
												</textarea>
												<div class="form-check mb-0 mt-2">
													<input type="checkbox" class="form-check-input" id="exampleCheck1">
													<label class="form-check-label" for="exampleCheck1">
														Any images, sounds, or other assets that are not my own work, have been appropriately licensed for use in the file preview or main Lesson. Other than these items, this work is entirely my own and I have full rights to sell it here.
													</label>
												</div>
											</div>
										</div>
									</div> -->
									<!-- Reviewer START -->

									<!-- Step 4 button -->
									<div class="d-md-flex justify-content-between align-items-start mt-4">
										<button type="button" class="btn btn-secondary prev-btn mb-2 mb-md-0" @click="goPrev">Previous</button>
										<!-- <button class="btn btn-light me-auto ms-md-2 mb-2 mb-md-0">Preview Lesson</button> -->
										<div class="text-md-end">
											<button type="button" :disabled="form.processing" @click="saveForm" class="btn btn-success mb-2 mb-sm-0">Submit a Lesson</button>
                                            <!-- Only On Instructor List -->
											<!-- <p class="mb-0 small mt-1">Once you click "Submit a Lesson", your Lesson will be uploaded and marked as pending for review.</p> -->
										</div>
									</div>
								</div>
							</div>
							<!-- Step 4 content END -->

						</form>
					</div>
				</div>
				<!-- Card body END -->
			</div>
		</div>
		<!-- Card END -->
	</div>
  </AppLayout>

</template>
