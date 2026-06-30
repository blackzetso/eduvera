<script setup>
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
</script>

<template>
  <div class="admission-inbox-empty admission-dashboard-card mb-4">
    <div class="display-6 text-muted mb-3">
      <i class="bi bi-inbox"></i>
    </div>
    <h5 class="fw-bold mb-2">لا توجد طلبات قبول حتى الآن</h5>
    <p class="text-muted small mb-4 admission-inbox-empty__text">
      ستظهر هنا طلبات القبول الواردة من الموقع أو مساحة العمل. يمكنك ضبط نموذج الزيارة من إعدادات الموقع.
    </p>
    <div class="d-flex flex-wrap justify-content-center gap-2">
      <Link :href="route('admin.website.admissions')" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>
        إعداد استقبال الطلبات
      </Link>
    </div>
  </div>
</template>
