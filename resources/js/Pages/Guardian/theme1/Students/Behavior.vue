<script setup>
import { Head } from '@inertiajs/vue3'
import GuardianDashboardLayout from '@/Layouts/GuardianDashboardLayout.vue'

defineProps({
  guardian: Object,
  children: Array,
  student: Object,
  summary: Object,
  records: Array,
})

const severityLabels = {
  positive: 'إيجابي',
  neutral: 'محايد',
  negative: 'سلبي',
}
</script>

<template>
  <Head :title="`سلوك ${student.name}`" />
  <GuardianDashboardLayout
    :guardian="guardian"
    :children="children"
    :student="student"
    active-menu="behavior"
  >
    <div class="row g-3 mb-4">
      <div class="col-4">
        <div class="p-3 border rounded text-center border-success">
          <div class="fw-bold text-success fs-4">{{ summary.behavior.positive }}</div>
          <small>إيجابي</small>
        </div>
      </div>
      <div class="col-4">
        <div class="p-3 border rounded text-center">
          <div class="fw-bold fs-4">{{ summary.behavior.neutral }}</div>
          <small>محايد</small>
        </div>
      </div>
      <div class="col-4">
        <div class="p-3 border rounded text-center border-danger">
          <div class="fw-bold text-danger fs-4">{{ summary.behavior.negative }}</div>
          <small>سلبي</small>
        </div>
      </div>
    </div>

    <div class="vstack gap-3">
      <div v-for="r in records" :key="r.id" class="card border">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <span
                class="badge me-2"
                :class="{
                  'bg-success': r.severity === 'positive',
                  'bg-secondary': r.severity === 'neutral',
                  'bg-danger': r.severity === 'negative',
                }"
              >
                {{ severityLabels[r.severity] }}
              </span>
              <span class="badge bg-light text-dark">{{ r.category }}</span>
              <h6 class="mt-2 mb-1">{{ r.title }}</h6>
              <p v-if="r.description" class="small text-muted mb-0">{{ r.description }}</p>
            </div>
            <small class="text-muted">{{ r.occurred_at }}</small>
          </div>
        </div>
      </div>
      <p v-if="!records.length" class="text-muted">لا توجد ملاحظات سلوكية مسجلة.</p>
    </div>
  </GuardianDashboardLayout>
</template>
