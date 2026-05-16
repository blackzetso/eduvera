<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

const props = defineProps({
    tenant: Object,
})

const form = useForm({
    name: props.tenant.name,
    email: props.tenant.email,
    is_active: props.tenant.is_active,
    bunny_library_id: props.tenant.bunny_library_id,
    bunny_api_key: props.tenant.bunny_api_key,
})

function submit() {
    form.put(`/tenants/${props.tenant.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire('تم!', 'تم تحديث المستأجر بنجاح', 'success')
        },
        onError: (errors) => {
            const firstError = Object.values(errors)[0]
            Swal.fire('خطأ!', firstError, 'error')
        }
    })
}
</script>

<template>
<div>
    <Head title="Edit Tenant" />
    
    <div class="min-vh-100 bg-light">
        <!-- Header -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container-fluid">
                <Link href="/" class="navbar-brand">
                    <strong>جسور LMS</strong> - Central Admin
                </Link>
                <div class="ms-auto">
                    <Link href="/tenants" class="btn btn-sm btn-outline-light">
                        <i class="bi bi-arrow-left me-1"></i> عودة
                    </Link>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card border-0 shadow">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0">
                                <i class="bi bi-pencil me-2"></i>
                                تعديل المستأجر: {{ tenant.name }}
                            </h4>
                        </div>
                        <div class="card-body p-4">
                            <form @submit.prevent="submit">
                                <!-- Tenant ID (readonly) -->
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Tenant ID</label>
                                    <input 
                                        type="text" 
                                        class="form-control form-control-lg" 
                                        :value="tenant.id"
                                        readonly
                                        disabled>
                                    <small class="text-muted">لا يمكن تعديل الـ ID بعد الإنشاء</small>
                                </div>

                                <!-- Tenant Name -->
                                <div class="mb-4">
                                    <label class="form-label fw-bold">الاسم</label>
                                    <input 
                                        v-model="form.name" 
                                        type="text" 
                                        class="form-control form-control-lg" 
                                        required>
                                </div>

                                <!-- Email -->
                                <div class="mb-4">
                                    <label class="form-label fw-bold">البريد الإلكتروني</label>
                                    <input 
                                        v-model="form.email" 
                                        type="email" 
                                        class="form-control form-control-lg" 
                                        required>
                                </div>

                                <!-- Status -->
                                <div class="mb-4">
                                    <div class="form-check form-switch">
                                        <input 
                                            v-model="form.is_active" 
                                            class="form-check-input" 
                                            type="checkbox" 
                                            id="isActive">
                                        <label class="form-check-label fw-bold" for="isActive">
                                            مفعّل
                                        </label>
                                    </div>
                                    <small class="text-muted">إلغاء التفعيل سيمنع الوصول لهذا المستأجر</small>
                                </div>

                                <!-- Storage Library ID -->
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Storage Library ID</label>
                                    <input 
                                        v-model="form.bunny_library_id" 
                                        type="text" 
                                        class="form-control" 
                                        placeholder="12345">
                                    <small class="text-muted">معرف مكتبة التخزين (يتم إنشاؤه تلقائياً)</small>
                                </div>

                                <!-- Storage API Key -->
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Storage API Key</label>
                                    <input 
                                        v-model="form.bunny_api_key" 
                                        type="text" 
                                        class="form-control" 
                                        placeholder="API Key">
                                    <small class="text-muted">مفتاح API الخاص بمكتبة هذا المستأجر</small>
                                </div>

                                <!-- Buttons -->
                                <div class="d-flex gap-2 justify-content-end">
                                    <Link href="/tenants" class="btn btn-secondary btn-lg">
                                        إلغاء
                                    </Link>
                                    <button 
                                        type="submit" 
                                        :disabled="form.processing"
                                        class="btn btn-primary btn-lg">
                                        <span v-if="form.processing">
                                            <span class="spinner-border spinner-border-sm me-2"></span>
                                            جاري الحفظ...
                                        </span>
                                        <span v-else>
                                            <i class="bi bi-check-circle me-2"></i>
                                            حفظ التعديلات
                                        </span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</template>

