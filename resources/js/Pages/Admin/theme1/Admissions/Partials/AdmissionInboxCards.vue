<script setup>
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { pipelineBadgeClass, statusBadge, decisionBadge } from '@/Shared/admissionsBadges'
import { formatVisitRelative } from '@/composables/useAdmissionInboxMetrics'

defineProps({
  applications: { type: Array, required: true },
  formatDateTime: { type: Function, required: true },
})

</script>

<template>
  <div class="admission-inbox-cards-wrap d-flex flex-column gap-3 mb-4">
    <div class="d-flex justify-content-between align-items-center px-1">
      <h6 class="mb-0 fw-bold">جميع الطلبات</h6>
      <span class="badge bg-light text-dark rounded-pill">{{ applications.length }}</span>
    </div>

    <div
      v-for="row in applications"
      :key="row.id"
      class="card admission-inbox-row-card"
    >
      <div class="card-body p-3">
        <div class="d-flex align-items-start gap-3 mb-3">
          <span class="admission-inbox-avatar">
            <i class="bi bi-person-fill"></i>
          </span>
          <div class="flex-grow-1 min-w-0">
            <Link
              :href="route('admin.admissions.show', row.id)"
              class="fw-bold text-decoration-none d-block"
            >
              {{ row.student_name || '—' }}
            </Link>
            <code class="small text-muted">{{ row.reference_code }}</code>
          </div>
          <Link
            :href="route('admin.admissions.show', row.id)"
            class="btn btn-sm btn-primary"
            title="فتح مساحة العمل"
          >
            <i class="bi bi-box-arrow-up-left"></i>
          </Link>
        </div>

        <div class="row g-2 small mb-3">
          <div class="col-6">
            <span class="text-muted d-block">ولي الأمر</span>
            <span class="fw-semibold">{{ row.parent_name || '—' }}</span>
          </div>
          <div class="col-6">
            <span class="text-muted d-block">الزيارة</span>
            <span class="fw-semibold">{{ formatVisitRelative(row).relative }}</span>
            <span v-if="formatVisitRelative(row).detail" class="text-muted d-block">
              {{ formatVisitRelative(row).detail }}
            </span>
          </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
          <span
            v-if="row.target_grade"
            class="badge rounded-pill admission-category-badge"
          >
            {{ row.target_grade }}
          </span>
          <span class="badge rounded-pill" :class="pipelineBadgeClass(row.pipeline_stage)">
            {{ row.pipeline_stage_label }}
          </span>
          <span class="badge rounded-pill" :class="statusBadge(row.status)">
            {{ row.status_label }}
          </span>
          <span
            v-if="row.decision"
            class="badge rounded-pill"
            :class="decisionBadge(row.decision)"
          >
            {{ row.decision_label }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>
