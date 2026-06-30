<script setup>
import { Head } from '@inertiajs/vue3'
import GuardianDashboardLayout from '@/Layouts/GuardianDashboardLayout.vue'

defineProps({
  guardian: Object,
  children: Array,
  student: Object,
  summary: Object,
  schedule: Array,
})
</script>

<template>
  <Head :title="`جدول ${student.name}`" />
  <GuardianDashboardLayout
    :guardian="guardian"
    :children="children"
    :student="student"
    active-menu="schedule"
  >
    <div class="card border">
      <div class="card-header bg-transparent">
        <h5 class="mb-0">الجدول الدراسي — {{ student.category?.name }}</h5>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>اليوم</th>
                <th>الحصة</th>
                <th>الوقت</th>
                <th>المادة</th>
                <th>المعلم</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in schedule" :key="row.id">
                <td>{{ row.day_name }}</td>
                <td>{{ row.period_number }}</td>
                <td>{{ row.time_from }} – {{ row.time_to }}</td>
                <td>{{ row.subject ?? '—' }}</td>
                <td>
                  <span
                    v-if="row.temporary_label"
                    class="badge tt-coverage-badge--temp me-1"
                    :title="row.temporary_tooltip"
                  >{{ row.temporary_label }}</span>
                  <span v-else-if="row.is_coverage_today" class="badge bg-info-subtle text-info me-1">تغطية اليوم</span>
                  {{ row.display_teacher ?? row.teacher ?? '—' }}
                  <small v-if="row.schedule_note" class="d-block text-warning">{{ row.schedule_note }}</small>
                  <small v-else-if="row.is_coverage_today && row.teacher" class="d-block text-muted">
                    بديل عن: {{ row.teacher }}
                  </small>
                </td>
              </tr>
              <tr v-if="!schedule.length">
                <td colspan="5" class="text-center text-muted py-4">لا يوجد جدول لهذا الصف</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </GuardianDashboardLayout>
</template>
