<script setup>
import { Head } from '@inertiajs/vue3'
import GuardianDashboardLayout from '@/Layouts/GuardianDashboardLayout.vue'

defineProps({
  guardian: Object,
  children: Array,
  student: Object,
  summary: Object,
  enrollments: Array,
})

const statusLabels = {
  active: 'نشط',
  expired: 'منتهي',
  cancelled: 'ملغى',
}

const statusClasses = {
  active: 'bg-success',
  expired: 'bg-secondary',
  cancelled: 'bg-danger',
}
</script>

<template>
  <Head :title="`الكورسات — ${student.name}`" />
  <GuardianDashboardLayout
    :guardian="guardian"
    :children="children"
    :student="student"
    active-menu="courses"
  >
    <div class="d-flex align-items-center p-4 bg-success bg-opacity-10 rounded-3 mb-4">
      <span class="display-6 text-success"><i class="bi bi-play-circle" /></span>
      <div class="ms-4">
        <h5 class="mb-0 fw-bold">{{ enrollments.length }} كورس مسجّل</h5>
        <span class="text-muted">التقدم في الكورسات على المنصة</span>
      </div>
    </div>

    <div v-if="!enrollments.length" class="text-center text-muted py-5">
      <i class="bi bi-collection-play display-4 d-block mb-3" />
      لم يُسجَّل في أي كورس بعد
    </div>

    <div class="row g-4" v-else>
      <div
        v-for="e in enrollments"
        :key="e.id"
        class="col-md-6"
      >
        <div class="card border h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-3">
              <h6 class="card-title mb-0 fw-bold">{{ e.lesson.name }}</h6>
              <span class="badge" :class="statusClasses[e.status] ?? 'bg-secondary'">
                {{ statusLabels[e.status] ?? e.status }}
              </span>
            </div>

            <div class="text-muted small mb-3">
              <i class="bi bi-folder2 me-1" />
              {{ e.lesson.category ?? '—' }}
            </div>

            <div class="mb-2 d-flex justify-content-between small">
              <span>التقدم في المحاضرات</span>
              <strong>{{ e.viewed_lectures }} / {{ e.total_lectures }}</strong>
            </div>
            <div class="progress mb-3" style="height: 10px;">
              <div
                class="progress-bar bg-success"
                role="progressbar"
                :style="{ width: e.progress + '%' }"
                :aria-valuenow="e.progress"
                aria-valuemin="0"
                aria-valuemax="100"
              />
            </div>

            <div class="d-flex justify-content-between text-muted small">
              <span><i class="bi bi-calendar-event me-1" />تسجيل: {{ e.enrolled_at }}</span>
              <span v-if="e.expires_at">
                <i class="bi bi-hourglass-split me-1" />ينتهي: {{ e.expires_at }}
              </span>
              <span v-else class="text-success"><i class="bi bi-infinity me-1" />مدى الحياة</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </GuardianDashboardLayout>
</template>
