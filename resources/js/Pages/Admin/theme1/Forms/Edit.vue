<script setup>
    import { onMounted, nextTick  } from 'vue'
    import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
    import { Head, useForm } from '@inertiajs/vue3'
    import VueApexCharts from 'vue3-apexcharts'
    import Stepper from 'bs-stepper'
    import 'bs-stepper/dist/css/bs-stepper.min.css'
    import { Link } from '@inertiajs/vue3'
    import { route } from 'ziggy-js'
    import { getCurrentInstance } from 'vue'
    import { usePage } from '@inertiajs/vue3'
    import Swal from 'sweetalert2'

    const page = usePage()

    onMounted(() => {
        if (page.props.flash?.success) {
            proxy.$swal({
            title: 'تم الحفظ!',
            text: page.props.flash.success,
            icon: 'success',
            confirmButtonText: 'تمام'
            })
        }

        if (page.props.flash?.error) {
            proxy.$swal({
            title: 'خطأ',
            text: page.props.flash.error,
            icon: 'error',
            confirmButtonText: 'موافق'
            })
        }
    })
    const { proxy } = getCurrentInstance()

    let stepper = null

    onMounted(() => {
    const el = document.querySelector('.bs-stepper')
        if (el) {
            stepper = new Stepper(el, {
            linear: false,
            animation: true,
            })
        }
    })



    const props = defineProps({
        form: Object
    })
    // form handler

    const form = useForm({
        name: props.form.name ?? ''
    })

    function submit() {
        form.put(route('admin.forms.update', props.form.id), {
            onSuccess: () => {
                    Swal.fire({
                    title: 'تم الحفظ!',
                    text: 'تم حفظ البيانات بنجاح',
                    icon: 'success',
                    confirmButtonText: 'تمام'
                })
            },
            onError: () => {
                window.Swal.fire({
                    title: 'خطأ!',
                    text: 'حدثت مشكلة أثناء الحفظ',
                    icon: 'error',
                    confirmButtonText: 'موافق'
                })
            }
        })
    }
</script>
<template>
    <Head title="Dashboard"/>
    <AppLayout>
        <div class="page-content-wrapper border">

		<!-- Card START -->
		<!-- Card body START -->
        <div class="card-body px-1 px-sm-4">
            <!-- Step content START -->
            <form @submit.prevent="submit" >
                <!-- Step 1 content START -->
                <div  role="tabpanel" class="content " aria-labelledby="steppertrigger1">
                    <!-- Title -->
                    <h4>Edit Form Name</h4>
                    <Link  :href="route('admin.forms.index')" >
                        <i class="fas fa-arrow-left" ></i>
                        back
                    </Link>
                    <hr> <!-- Divider -->

                    <!-- Basic information START -->
                    <div class="row g-4">
                        <!-- Course title -->
                        <div class="col-12">
                            <label class="form-label">Form title</label>
                            <input class="form-control" v-model="form.name" type="text" placeholder="Enter course title" value="">

                             <div v-if="form.errors.name" class="text-danger">
                                {{ form.errors.name }}
                            </div>
                        </div>
                        <!-- Step 1 button -->
                        <div class="d-flex justify-content-end mt-3">
                            <button type="submit" class="btn btn-primary next-btn mb-0" :disabled="form.processing">Save</button>
                        </div>
                    </div>
                    <!-- Basic information START -->
                </div>
                <!-- Step 1 content END -->
            </form>
        </div>
        <!-- Card body END -->
	</div>
    </AppLayout>
</template>
