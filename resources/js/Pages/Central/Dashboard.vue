<script setup>
import { Head, Link } from '@inertiajs/vue3'

const stats = {
    totalTenants: 1,
    activeTenants: 1,
    totalRevenue: 0,
}
</script>

<template>
<div>
    <Head title="Central Dashboard" />
    
    <div class="min-vh-100 bg-light">
        <!-- Header -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container-fluid">
                <a class="navbar-brand" href="#">
                    <strong>جسور LMS</strong> - Central Admin
                </a>
                <div class="ms-auto">
                    <span class="text-white">Super Admin</span>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="container-fluid p-4">
            <div class="row mb-4">
                <div class="col-12">
                    <h1 class="h3 mb-3">Central Dashboard</h1>
                    <p class="text-muted">إدارة المستأجرين والنظام المركزي</p>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-2">إجمالي المستأجرين</h6>
                                    <h2 class="mb-0">{{ stats.totalTenants }}</h2>
                                </div>
                                <div class="fs-1 text-primary">
                                    <i class="bi bi-building"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-2">المستأجرين النشطين</h6>
                                    <h2 class="mb-0 text-success">{{ stats.activeTenants }}</h2>
                                </div>
                                <div class="fs-1 text-success">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-2">الإيرادات</h6>
                                    <h2 class="mb-0">${{ stats.totalRevenue }}</h2>
                                </div>
                                <div class="fs-1 text-info">
                                    <i class="bi bi-currency-dollar"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-bottom">
                            <h5 class="mb-0">إجراءات سريعة</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <Link :href="'/tenants'" class="btn btn-primary btn-lg w-100">
                                        <i class="bi bi-list me-2"></i>
                                        عرض كل المستأجرين
                                    </Link>
                                </div>
                                <div class="col-md-6">
                                    <Link :href="'/tenants/create'" class="btn btn-success btn-lg w-100">
                                        <i class="bi bi-plus-circle me-2"></i>
                                        إضافة مستأجر جديد
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</template>

<style scoped>
.navbar-brand {
    font-size: 1.5rem;
}
</style>

