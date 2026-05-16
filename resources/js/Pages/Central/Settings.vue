<script setup>
import { Head, Link } from '@inertiajs/vue3'
</script>

<template>
<div>
    <Head title="System Settings" />
    
    <div class="min-vh-100 bg-light">
        <!-- Header -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container-fluid">
                <Link href="/" class="navbar-brand">
                    <strong>جسور LMS</strong> - Central Admin
                </Link>
                <div class="ms-auto">
                    <Link href="/" class="btn btn-sm btn-outline-light">
                        <i class="bi bi-arrow-left me-1"></i> Dashboard
                    </Link>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="container-fluid p-4">
            <h1 class="h3 mb-4">إعدادات النظام</h1>
            
            <div class="card border-0 shadow-sm">
                <div class="card-body p-5 text-center">
                    <i class="bi bi-gear fs-1 text-muted mb-3"></i>
                    <h5>إعدادات النظام المركزي</h5>
                    <p class="text-muted">سيتم إضافة الإعدادات لاحقاً</p>
                </div>
            </div>
        </div>
    </div>
</div>
</template>

