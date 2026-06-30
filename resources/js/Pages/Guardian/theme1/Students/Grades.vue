<script setup>
import { Head } from '@inertiajs/vue3'
import GuardianDashboardLayout from '@/Layouts/GuardianDashboardLayout.vue'

defineProps({
  guardian: Object,
  children: Array,
  student: Object,
  summary: Object,
  grades: Array,
})

const typeLabels = {
  exam: 'امتحان',
  quiz: 'اختبار قصير',
  assignment: 'واجب',
  monthly: 'شهري',
}
</script>

<template>
  <Head :title="`درجات ${student.name}`" />
  <GuardianDashboardLayout
    :guardian="guardian"
    :children="children"
    :student="student"
    active-menu="grades"
  >
    <div class="d-flex align-items-center p-4 bg-primary bg-opacity-10 rounded-3 mb-4">
      <span class="display-6 text-primary"><i class="bi bi-graph-up" /></span>
      <div class="ms-4">
        <h5 class="mb-0 fw-bold">
          {{ summary.grades_average != null ? summary.grades_average + '%' : '—' }}
        </h5>
        <span class="text-muted">متوسط الدرجات — {{ grades.length }} تقييم</span>
      </div>
    </div>

    <div class="card border">
      <div class="card-header bg-transparent">
        <h5 class="mb-0">تفاصيل الدرجات</h5>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>المادة</th>
                <th>التقييم</th>
                <th>النوع</th>
                <th>الدرجة</th>
                <th>التاريخ</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="g in grades" :key="g.id">
                <td>{{ g.subject ?? '—' }}</td>
                <td>{{ g.title }}</td>
                <td><span class="badge bg-light text-dark">{{ typeLabels[g.assessment_type] ?? g.assessment_type }}</span></td>
                <td>
                  <strong>{{ g.score }}</strong> / {{ g.max_score }}
                  <span class="text-muted small">({{ g.percentage }}%)</span>
                </td>
                <td>{{ g.assessed_at }}</td>
              </tr>
              <tr v-if="!grades.length">
                <td colspan="5" class="text-center text-muted py-4">لا توجد درجات مسجلة</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </GuardianDashboardLayout>
</template>
