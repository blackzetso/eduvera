<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

const props = defineProps({
    tenants: Object,
})

const deleteForm = useForm({})

function confirmDelete(tenant) {
    Swal.fire({
        title: 'هل أنت متأكد؟',
        html: `سيتم حذف <strong>${tenant.name}</strong> وكل بياناته نهائياً!<br>هذا الإجراء لا يمكن التراجع عنه.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'نعم، احذف',
        cancelButtonText: 'إلغاء'
    }).then((result) => {
        if (result.isConfirmed) {
            deleteForm.delete(`/tenants/${tenant.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire('تم الحذف!', 'تم حذف المستأجر بنجاح', 'success')
                },
                onError: () => {
                    Swal.fire('خطأ!', 'حدثت مشكلة أثناء الحذف', 'error')
                }
            })
        }
    })
}
</script>

<template>
<div>
    <Head title="Manage Tenants" />
    
    <div class="min-vh-100 bg-light">
        <!-- Header -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container-fluid">
                <Link href="/" class="navbar-brand">
                    <strong>جسور LMS</strong> - Central Admin
                </Link>
                <div class="ms-auto">
                    <Link href="/" class="btn btn-sm btn-outline-light me-2">
                        <i class="bi bi-house"></i> Dashboard
                    </Link>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="container-fluid p-4">
            <div class="row mb-4">
                <div class="col-12 d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-1">إدارة المستأجرين</h1>
                        <p class="text-muted mb-0">عرض وإدارة جميع المستأجرين في النظام</p>
                    </div>
                    <Link href="/tenants/create" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>
                        إضافة مستأجر جديد
                    </Link>
                </div>
            </div>

            <!-- Tenants Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>الاسم</th>
                                    <th>البريد الإلكتروني</th>
                                    <th>Domain</th>
                                    <th>Storage Library</th>
                                    <th>الحالة</th>
                                    <th>تاريخ الإنشاء</th>
                                    <th>إجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="tenant in tenants.data" :key="tenant.id">
                                    <td><code>{{ tenant.id }}</code></td>
                                    <td>
                                        <strong>{{ tenant.name }}</strong>
                                    </td>
                                    <td>{{ tenant.email }}</td>
                                    <td>
                                        <a v-if="tenant.domains && tenant.domains[0]" 
                                           :href="`http://${tenant.domains[0].domain}:8000`" 
                                           target="_blank"
                                           class="text-primary">
                                            {{ tenant.domains[0].domain }}
                                            <i class="bi bi-box-arrow-up-right ms-1"></i>
                                        </a>
                                        <span v-else class="text-muted">-</span>
                                    </td>
                                    <td>
                                        <span v-if="tenant.bunny_library_id" class="badge bg-success">
                                            <i class="bi bi-check-circle me-1"></i>
                                            {{ tenant.bunny_library_id }}
                                        </span>
                                        <span v-else class="badge bg-warning">
                                            <i class="bi bi-exclamation-triangle me-1"></i>
                                            Not Set
                                        </span>
                                    </td>
                                    <td>
                                        <span v-if="tenant.is_active" class="badge bg-success">نشط</span>
                                        <span v-else class="badge bg-secondary">معطل</span>
                                    </td>
                                    <td>{{ new Date(tenant.created_at).toLocaleDateString('ar-EG') }}</td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <Link :href="`/tenants/${tenant.id}/edit`" class="btn btn-outline-primary">
                                                <i class="bi bi-pencil"></i>
                                            </Link>
                                            <button @click="confirmDelete(tenant)" class="btn btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="!tenants.data || tenants.data.length === 0" class="text-center py-5">
                <i class="bi bi-inbox fs-1 text-muted"></i>
                <p class="text-muted mt-3">لا يوجد مستأجرين بعد</p>
                <Link href="/tenants/create" class="btn btn-primary">
                    إضافة أول مستأجر
                </Link>
            </div>
        </div>
    </div>
</div>
</template>

