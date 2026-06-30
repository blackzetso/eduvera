<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

defineProps({
  kpis: Object,
})
</script>

<template>
  <Head title="لوحة الحضور" />
  <AppLayout>
    <div class="container py-4">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">لوحة الحضور</h1>
        <div class="d-flex gap-2">
          <Link :href="route('admin.attendances.index')" class="btn btn-outline-primary btn-sm">السجلات</Link>
          <Link :href="route('admin.attendances.mark.form')" class="btn btn-primary btn-sm">تسجيل حضور</Link>
        </div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-md-3">
          <div class="card border-0 shadow-sm">
            <div class="card-body">
              <div class="text-muted small">تاريخ اليوم</div>
              <div class="fs-4 fw-bold">{{ kpis?.date }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card border-0 shadow-sm">
            <div class="card-body">
              <div class="text-muted small">حاضر</div>
              <div class="fs-4 fw-bold text-success">{{ kpis?.present ?? 0 }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card border-0 shadow-sm">
            <div class="card-body">
              <div class="text-muted small">غائب</div>
              <div class="fs-4 fw-bold text-danger">{{ kpis?.absent ?? 0 }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card border-0 shadow-sm">
            <div class="card-body">
              <div class="text-muted small">نسبة الحضور</div>
              <div class="fs-4 fw-bold">{{ kpis?.attendance_rate ?? 0 }}%</div>
            </div>
          </div>
        </div>
      </div>

      <div class="card border-0 shadow-sm">
        <div class="card-body d-flex flex-wrap gap-2">
          <Link :href="route('admin.attendances.thresholds')" class="btn btn-outline-secondary btn-sm">إعداد العتبات</Link>
          <Link :href="route('admin.attendances.alerts')" class="btn btn-outline-warning btn-sm">التنبيهات</Link>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
