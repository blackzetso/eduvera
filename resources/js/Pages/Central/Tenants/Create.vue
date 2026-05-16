<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

const form = useForm({
    id: '',
    name: '',
    email: '',
    domain: '',
})

function generateId() {
    if (form.name) {
        form.id = form.name.toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/(^-|-$)/g, '')
    }
}

function generateDomain() {
    if (form.id) {
        form.domain = form.id + '.localhost'
    }
}

function submit() {
    if (!form.id || !form.name || !form.email || !form.domain) {
        Swal.fire('خطأ', 'جميع الحقول مطلوبة', 'error')
        return
    }

    form.post('/tenants', {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                title: 'تم الإنشاء!',
                html: `
                    <p>تم إنشاء المستأجر بنجاح!</p>
                    <p><strong>Domain:</strong> ${form.domain}</p>
                    <p>جاري إنشاء قاعدة البيانات ومكتبة التخزين...</p>
                `,
                icon: 'success',
            })
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
    <Head title="Create Tenant" />
    
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
                                <i class="bi bi-plus-circle me-2"></i>
                                إنشاء مستأجر جديد
                            </h4>
                        </div>
                        <div class="card-body p-4">
                            <form @submit.prevent="submit">
                                <!-- Tenant Name -->
                                <div class="mb-4">
                                    <label class="form-label fw-bold">
                                        اسم المستأجر <span class="text-danger">*</span>
                                    </label>
                                    <input 
                                        v-model="form.name" 
                                        @blur="generateId(); generateDomain()"
                                        type="text" 
                                        class="form-control form-control-lg" 
                                        placeholder="مثال: المدرسة الثانوية الأولى"
                                        required>
                                    <small class="text-muted">الاسم الذي سيظهر في النظام</small>
                                </div>

                                <!-- Tenant ID -->
                                <div class="mb-4">
                                    <label class="form-label fw-bold">
                                        Tenant ID <span class="text-danger">*</span>
                                    </label>
                                    <input 
                                        v-model="form.id" 
                                        @blur="generateDomain"
                                        type="text" 
                                        class="form-control form-control-lg" 
                                        placeholder="مثال: school1"
                                        required>
                                    <small class="text-muted">معرف فريد (حروف إنجليزية وأرقام وشرطات فقط)</small>
                                </div>

                                <!-- Email -->
                                <div class="mb-4">
                                    <label class="form-label fw-bold">
                                        البريد الإلكتروني <span class="text-danger">*</span>
                                    </label>
                                    <input 
                                        v-model="form.email" 
                                        type="email" 
                                        class="form-control form-control-lg" 
                                        placeholder="admin@example.com"
                                        required>
                                    <small class="text-muted">البريد الإلكتروني للمسؤول الرئيسي</small>
                                </div>

                                <!-- Domain -->
                                <div class="mb-4">
                                    <label class="form-label fw-bold">
                                        Domain/Subdomain <span class="text-danger">*</span>
                                    </label>
                                    <input 
                                        v-model="form.domain" 
                                        type="text" 
                                        class="form-control form-control-lg" 
                                        placeholder="tenant.localhost أو tenant.yoursite.com"
                                        required>
                                    <div class="alert alert-info mt-2 mb-0">
                                        <small>
                                            <i class="bi bi-info-circle me-1"></i>
                                            <strong>في التطوير:</strong> استخدم <code>.localhost</code> (مثال: school1.localhost)<br>
                                            <strong>في الإنتاج:</strong> استخدم subdomain حقيقي (مثال: school1.yoursite.com)
                                        </small>
                                    </div>
                                </div>

                                <!-- Info Box -->
                                <div class="alert alert-light border">
                                    <h6 class="alert-heading">
                                        <i class="bi bi-lightning me-1"></i>
                                        ما سيحدث عند الإنشاء:
                                    </h6>
                                    <ul class="mb-0">
                                        <li>إنشاء سجل Tenant في قاعدة البيانات المركزية</li>
                                        <li>إنشاء Domain مرتبط بالـ Tenant</li>
                                        <li>إنشاء قاعدة بيانات منفصلة: <code>tenant_{{ form.id || '{id}' }}</code></li>
                                        <li>إنشاء مكتبة تخزين منفصلة</li>
                                        <li>تشغيل Migrations في قاعدة بيانات الـ Tenant</li>
                                    </ul>
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
                                            جاري الإنشاء...
                                        </span>
                                        <span v-else>
                                            <i class="bi bi-check-circle me-2"></i>
                                            إنشاء المستأجر
                                        </span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Help Card -->
                    <div class="card border-0 shadow-sm mt-4">
                        <div class="card-body">
                            <h6 class="mb-3">
                                <i class="bi bi-question-circle me-2"></i>
                                بعد إنشاء المستأجر:
                            </h6>
                            <ol class="mb-0">
                                <li class="mb-2">انتظر حتى تكتمل عملية الإنشاء (قد تستغرق 10-30 ثانية)</li>
                                <li class="mb-2">استخدم سكريبت <code>create_tenant.php</code> لإنشاء Admin User</li>
                                <li class="mb-2">أو أنشئ Admin User يدوياً من tinker</li>
                                <li>الوصول للـ tenant من: <code>http://[domain]:8000</code></li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</template>

