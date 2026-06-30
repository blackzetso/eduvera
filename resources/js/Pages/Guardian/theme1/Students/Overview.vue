<script setup>
import { Head } from '@inertiajs/vue3'
import GuardianDashboardLayout from '@/Layouts/GuardianDashboardLayout.vue'

defineProps({
  guardian: Object,
  children: Array,
  student: Object,
  summary: Object,
})
</script>

<template>
  <Head :title="`نظرة عامة - ${student.name}`" />
  <GuardianDashboardLayout
    :guardian="guardian"
    :children="children"
    :student="student"
    active-menu="overview"
  >
    <div class="row g-4">
      <div class="col-sm-6 col-lg-3">
        <div class="d-flex align-items-center p-4 bg-success bg-opacity-10 rounded-3">
          <span class="display-6 text-success"><i class="bi bi-check-circle" /></span>
          <div class="ms-3">
            <h5 class="mb-0 fw-bold">{{ summary.attendance.present }}</h5>
            <span class="small">حضور</span>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-3">
        <div class="d-flex align-items-center p-4 bg-danger bg-opacity-10 rounded-3">
          <span class="display-6 text-danger"><i class="bi bi-x-circle" /></span>
          <div class="ms-3">
            <h5 class="mb-0 fw-bold">{{ summary.attendance.absent }}</h5>
            <span class="small">غياب</span>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-3">
        <div class="d-flex align-items-center p-4 bg-primary bg-opacity-10 rounded-3">
          <span class="display-6 text-primary"><i class="bi bi-percent" /></span>
          <div class="ms-3">
            <h5 class="mb-0 fw-bold">{{ summary.grades_average ?? '—' }}<template v-if="summary.grades_average != null">%</template></h5>
            <span class="small">متوسط الدرجات</span>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-3">
        <div class="d-flex align-items-center p-4 bg-warning bg-opacity-15 rounded-3">
          <span class="display-6 text-warning"><i class="bi bi-emoji-frown" /></span>
          <div class="ms-3">
            <h5 class="mb-0 fw-bold">{{ summary.behavior.negative }}</h5>
            <span class="small">ملاحظات سلبية</span>
          </div>
        </div>
      </div>
    </div>

    <div v-if="summary.attendance_alert" class="alert alert-warning mt-4">
      <strong>تنبيه حضور ({{ summary.attendance_alert.level }})</strong>
      — عدد الغيابات: {{ summary.attendance_alert.absences_count }}
      <span v-if="summary.attendance_alert.triggered_at"> — {{ summary.attendance_alert.triggered_at }}</span>
    </div>

    <div class="card border mt-4">
      <div class="card-header bg-transparent">
        <h5 class="mb-0">بيانات الطالب</h5>
      </div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-3">الاسم</dt>
          <dd class="col-sm-9">{{ student.name }}</dd>
          <dt class="col-sm-3">الصف</dt>
          <dd class="col-sm-9">{{ student.category?.name ?? '—' }}</dd>
          <dt class="col-sm-3">كود الطالب</dt>
          <dd class="col-sm-9">{{ student.student_code ?? '—' }}</dd>
        </dl>
      </div>
    </div>
  </GuardianDashboardLayout>
</template>
