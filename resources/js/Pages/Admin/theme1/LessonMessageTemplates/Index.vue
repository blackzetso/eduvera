<script setup>
import { ref, watch } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import Swal from 'sweetalert2'
import { toast } from 'vue3-toastify'

const props = defineProps({
    templates: Object,
    filters: Object,
})

const search = ref(props.filters?.search ?? '')

watch(search, (val) => {
    router.get(route('admin.lesson-message-templates.index'), { search: val }, { preserveState: true, replace: true })
})

// Create modal
const showCreateModal = ref(false)
const createForm = useForm({ title: '' })

function openCreateModal() {
    createForm.reset()
    showCreateModal.value = true
}

function submitCreate() {
    createForm.post(route('admin.lesson-message-templates.store'), {
        onSuccess: () => {
            showCreateModal.value = false
            toast.success('تم إضافة الاستراتيجية بنجاح')
        },
        onError: () => {
            toast.error('حدثت مشكلة أثناء الحفظ')
        }
    })
}

// Edit modal
const showEditModal = ref(false)
const editingTemplate = ref(null)
const editForm = useForm({ title: '' })

function openEditModal(tpl) {
    editingTemplate.value = tpl
    editForm.title = tpl.title
    showEditModal.value = true
}

function submitEdit() {
    editForm.put(route('admin.lesson-message-templates.update', editingTemplate.value.id), {
        onSuccess: () => {
            showEditModal.value = false
            toast.success('تم تحديث الاستراتيجية بنجاح')
        },
        onError: () => {
            toast.error('حدثت مشكلة أثناء التحديث')
        }
    })
}

function toggleStatus(id) {
    router.patch(route('admin.lesson-message-templates.toggle-status', id), {}, {
        onSuccess: () => toast.success('تم تحديث الحالة'),
        onError:   () => toast.error('حدثت مشكلة'),
    })
}

function confirmDelete(id) {
    Swal.fire({
        title: 'هل أنت متأكد؟',
        text: 'لن تتمكن من التراجع عن هذا الإجراء!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'نعم، احذف',
        cancelButtonText: 'إلغاء',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('admin.lesson-message-templates.destroy', id), {
                onSuccess: () => toast.success('تم الحذف بنجاح'),
                onError:   () => toast.error('حدثت مشكلة أثناء الحذف'),
            })
        }
    })
}
</script>

<template>
    <Head title="استراتيجيات الدروس" />
    <AppLayout>
        <div class="page-content-wrapper border">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="mb-0">استراتيجيات الدروس</h3>
                <button class="btn btn-primary" @click="openCreateModal">
                    <i class="bi bi-plus-lg me-1"></i> إضافة استراتيجية
                </button>
            </div>

            <!-- Search -->
            <div class="mb-3">
                <input v-model="search" type="text" class="form-control w-50" placeholder="بحث باسم الاستراتيجية...">
            </div>

            <!-- Table -->
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>اسم الاستراتيجية</th>
                                    <th>الحالة</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="tpl in templates.data" :key="tpl.id">
                                    <td>{{ tpl.id }}</td>
                                    <td class="fw-semibold">{{ tpl.title }}</td>
                                    <td>
                                        <div class="form-check form-switch mb-0">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                :checked="tpl.status === 'enable'"
                                                @change="toggleStatus(tpl.id)"
                                            >
                                        </div>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary me-1" @click="openEditModal(tpl)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" @click="confirmDelete(tpl.id)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="templates.data.length === 0">
                                    <td colspan="4" class="text-center text-muted py-4">لا توجد استراتيجيات بعد</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="templates.last_page > 1" class="d-flex justify-content-center mt-3 gap-1">
                <button
                    v-for="page in templates.last_page"
                    :key="page"
                    class="btn btn-sm"
                    :class="page === templates.current_page ? 'btn-primary' : 'btn-outline-secondary'"
                    @click="router.get(route('admin.lesson-message-templates.index'), { page, search })"
                >{{ page }}</button>
            </div>
        </div>

        <!-- Create Modal -->
        <div v-if="showCreateModal" class="modal show d-block" tabindex="-1" style="background:rgba(0,0,0,.5)">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">إضافة استراتيجية جديدة</h5>
                        <button type="button" class="btn-close" @click="showCreateModal = false"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">اسم الاستراتيجية <span class="text-danger">*</span></label>
                            <input v-model="createForm.title" type="text" class="form-control" placeholder="مثال: التعليم التعاوني">
                            <div v-if="createForm.errors.title" class="text-danger small">{{ createForm.errors.title }}</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" @click="showCreateModal = false">إلغاء</button>
                        <button class="btn btn-primary" :disabled="createForm.processing" @click="submitCreate">حفظ</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <div v-if="showEditModal" class="modal show d-block" tabindex="-1" style="background:rgba(0,0,0,.5)">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">تعديل الاستراتيجية</h5>
                        <button type="button" class="btn-close" @click="showEditModal = false"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">اسم الاستراتيجية <span class="text-danger">*</span></label>
                            <input v-model="editForm.title" type="text" class="form-control">
                            <div v-if="editForm.errors.title" class="text-danger small">{{ editForm.errors.title }}</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" @click="showEditModal = false">إلغاء</button>
                        <button class="btn btn-primary" :disabled="editForm.processing" @click="submitEdit">تحديث</button>
                    </div>
                </div>
            </div>
        </div>

    </AppLayout>
</template>
