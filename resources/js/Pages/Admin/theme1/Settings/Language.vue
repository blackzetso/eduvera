<script setup>
    import { ref, watch } from 'vue'
    import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
    import Sidebar from '@/Pages/Admin/theme1/Settings/Partials/Sidebar.vue'
    import { Head, Link, router } from '@inertiajs/vue3'
    import { route } from 'ziggy-js'
    import Swal from 'sweetalert2'
    import { toast } from 'vue3-toastify'

    const props = defineProps({
        languages: Array
    })

    // ✅ حذف قسم
    function confirmDelete(id) {
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
            router.delete(route('admin.language.destroy', id), {
                onSuccess: () => {
                Swal.fire('تم الحذف!', 'تم حذف القسم بنجاح.', 'success')
                },
                onError: () => {
                Swal.fire('خطأ!', 'حدثت مشكلة أثناء الحذف.', 'error')
                }
            })
            }
        })
    }

    function toggleStatus(id) {
        router.patch(route('admin.language.status', id), {}, {
            onSuccess: () => {
            toast.success("تم تعديل حالة اللغه", {
                position: "top-right",
                autoClose: 3000,
            })
            },
            onError: () => {
            toast.error("حدثت مشكلة أثناء التحديث", {
                position: "top-right",
                autoClose: 3000,
            })
            }
        })
    }

    const search = ref(props.filters?.search ?? '')
        watch(search, (value) => {
        if (value) {
            router.get(route('admin.language.search', value), {}, {
            preserveState: true,
            replace: true,
            })
        } else {
            router.get(route('admin.language.index'), {}, {
            preserveState: true,
            replace: true,
            })
        }
    })

</script>
<template>

    <Head title="Lessons" />
    <AppLayout>
            <!-- Page main content START -->
            <div class="page-content-wrapper border">

                <!-- Title -->
                <div class="row">
                    <div class="col-12 mb-3">
                        <h1 class="h3 mb-2 mb-sm-0"> Language </h1>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Left side START -->
                    <div class="col-xl-3">
                        <!-- Tab START -->
                        <Sidebar/>
                        <!-- Tab END -->
                    </div>
                    <!-- Left side END -->

                    <!-- Right side START -->
                    <div class="col-xl-9">
                         <!-- Tab Content START -->
                        <div class="tab-content">
                            <div class="row mb-3">

                                <div class="col-10">
                                <input class="form-control" v-model="search" name="search" placeholder="ابحث عن لغه..." />
                                </div>
                                <div class="col-2 text-center">
                                <Link :href="route('admin.language.create')" class="btn btn-success-soft btn-round">
                                    <i class="bi bi-plus"></i>
                                </Link>
                                </div>
                            </div>
                            <!-- Personal Information content START -->
                            <div class="tab-pane show active" id="tab-1">
                                <div class="card shadow">
                                    <div class="table-responsive border-0">
                                        <table class="table table-dark-gray align-middle p-4 mb-0 table-hover">
                                        <thead>
                                            <tr class="text-center">
                                            <th>#</th>
                                            <th>Language Name</th>
                                            <th> is Default </th>
                                            <th>Enable/Disable</th>
                                            <th>Action</th>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            <tr v-for="(language, index) in props.languages.data" :key="language.id" class="text-center" >
                                                <td>{{ index + 1 }}</td>
                                                <td>
                                                    <h6 class="mb-0">{{ language.name }}</h6>
                                                </td>
                                                <td>

                                                </td>
                                                <td>
                                                    <div class="form-check form-switch d-flex justify-content-center">
                                                    <input class="form-check-input" type="checkbox" :checked="language.status === 'enabled'" @change="toggleStatus(language.id)" />
                                                    </div>
                                                </td>
                                                <td>
                                                    <Link :href="route('admin.language.edit', language.id)" class="btn btn-success-soft btn-round me-1">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </Link>
                                                    <button class="btn btn-danger-soft btn-round"  v-if="!language.is_default"   @click="confirmDelete(language.id)" >
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>

                                            <!-- لما مفيش بيانات -->
                                            <!-- <tr v-if="!props.language.data.length" class="text-center">
                                                <td colspan="5" class="text-center py-4">
                                                    <i class="bi bi-inbox text-muted fs-4 d-block mb-2"></i>
                                                    <span class="text-muted">لا توجد بيانات</span>
                                                </td>
                                            </tr> -->
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
