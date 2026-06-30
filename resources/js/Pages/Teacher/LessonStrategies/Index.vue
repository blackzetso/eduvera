<script setup>
import { ref, watch } from 'vue'
import AppLayout from '@/Pages/Teacher/Layout/App.vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import Swal from 'sweetalert2'

const props = defineProps({
    strategies: { type: Array, default: () => [] },
})

const page = usePage()
const strategiesList = ref([...props.strategies])
const showCreateModal = ref(false)
const createForm = useForm({ title: '' })

watch(() => page.props.flash?.strategyCreated, (created) => {
    if (!created) return
    if (!strategiesList.value.some(s => s.id === created.id)) {
        strategiesList.value.unshift(created)
    }
})

function openCreateModal() {
    createForm.reset()
    createForm.clearErrors()
    showCreateModal.value = true
}

function submitCreate() {
    createForm.post(route('teacher.lesson-strategies.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false
            createForm.reset()
            Swal.fire('تم!', 'تم إضافة الاستراتيجية بنجاح', 'success')
        },
        onError: () => {
            Swal.fire('خطأ!', 'حدثت مشكلة أثناء الحفظ.', 'error')
        },
    })
}
</script>

<template>
    <Head title="استراتيجيات الدروس" />
    <AppLayout>
        <div class="page-content-wrapper border">

            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h3 class="mb-0">استراتيجيات الدروس</h3>
                <button class="btn btn-primary" @click="openCreateModal">
                    <i class="bi bi-plus-lg me-1"></i> إضافة استراتيجية
                </button>
            </div>

            <p class="text-muted small mb-3">
                الاستراتيجيات التي تضيفها هنا تظهر أيضاً عند إنشاء الدرس، ويمكن للإدارة إدارتها من لوحة التحكم.
            </p>

            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>اسم الاستراتيجية</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(strategy, index) in strategiesList" :key="strategy.id">
                                    <td>{{ index + 1 }}</td>
                                    <td class="fw-semibold">{{ strategy.title }}</td>
                                </tr>
                                <tr v-if="strategiesList.length === 0">
                                    <td colspan="2" class="text-center text-muted py-4">لا توجد استراتيجيات بعد</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showCreateModal" class="modal show d-block" tabindex="-1" style="background:rgba(0,0,0,.5)">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">إضافة استراتيجية تدريس</h5>
                        <button type="button" class="btn-close" @click="showCreateModal = false"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">اسم الاستراتيجية <span class="text-danger">*</span></label>
                            <input
                                v-model="createForm.title"
                                type="text"
                                class="form-control"
                                placeholder="مثال: التعليم التعاوني"
                                @keyup.enter="submitCreate"
                            >
                            <div v-if="createForm.errors.title" class="text-danger small mt-1">{{ createForm.errors.title }}</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="showCreateModal = false">إلغاء</button>
                        <button type="button" class="btn btn-primary" :disabled="createForm.processing" @click="submitCreate">
                            <span v-if="createForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                            حفظ
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
