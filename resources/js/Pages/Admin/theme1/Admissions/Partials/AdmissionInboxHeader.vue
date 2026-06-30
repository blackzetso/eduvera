<script setup>
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
</script>

<template>
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
      <h4 class="mb-1 fw-bold">صندوق القبول</h4>
      <p class="text-muted mb-0">
        إدارة طلبات القبول والزيارات والتحويلات من مكان واحد
      </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <Link
        :href="route('admin.website.admissions')"
        class="btn btn-primary btn-sm"
      >
        <i class="bi bi-plus-lg me-1"></i>
        طلب جديد
      </Link>
      <Link
        :href="route('admin.website.admissions')"
        class="btn btn-outline-primary btn-sm"
      >
        <i class="bi bi-calendar-event me-1"></i>
        حجز زيارة
      </Link>
      <Link
        :href="route('admin.admissions.visits.index')"
        class="btn btn-outline-primary btn-sm"
      >
        <i class="bi bi-calendar-week me-1"></i>
        إدارة الزيارات
      </Link>
      <Link
        :href="route('admin.admissions.settings.documents')"
        class="btn btn-outline-secondary btn-sm"
      >
        <i class="bi bi-file-earmark-text me-1"></i>
        مستندات القبول
      </Link>
      <Link
        :href="route('admin.admissions.index', { status: 'open' })"
        class="btn btn-outline-secondary btn-sm"
      >
        <i class="bi bi-bar-chart-line me-1"></i>
        تقرير القبول
      </Link>
    </div>
  </div>
</template>
