<script setup>
import { ref } from 'vue'
import { pipelineBadgeClass, statusBadge, decisionBadge } from '@/Shared/admissionsBadges'

const props = defineProps({
  application: { type: Object, required: true },
  primaryApplicant: { type: Object, default: null },
  formatDate: { type: Function, required: true },
})

const showDetails = ref(false)

const applicantName = () =>
  props.primaryApplicant?.display_name
  || [props.primaryApplicant?.first_name, props.primaryApplicant?.father_name].filter(Boolean).join(' ')
  || '—'

const currentGrade = () =>
  props.application.current_grade_label
  || props.primaryApplicant?.current_grade_label
  || '—'

const targetCategory = () =>
  props.application.target_grade
  || props.application.target_category?.name
  || '—'
</script>

<template>
  <div class="card admission-command-card admission-header-compact border-0 shadow-sm mb-3 overflow-hidden">
    <div class="card-body p-3">
      <div class="d-flex flex-wrap align-items-center gap-2 gap-md-3">
        <div class="admission-header-avatar rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center flex-shrink-0">
          <i class="bi bi-person-vcard"></i>
        </div>

        <div class="flex-grow-1 min-w-0">
          <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
            <h4 class="mb-0 fw-bold text-truncate">{{ applicantName() }}</h4>
            <code class="small text-muted">{{ application.reference_code }}</code>
          </div>
          <div class="d-flex flex-wrap align-items-center gap-2 small">
            <span class="badge rounded-pill" :class="pipelineBadgeClass(application.pipeline_stage)">
              {{ application.pipeline_stage_label }}
            </span>
            <span
              v-if="application.decision"
              class="badge rounded-pill"
              :class="decisionBadge(application.decision)"
            >
              {{ application.decision_label }}
            </span>
            <span class="text-muted">
              <i class="bi bi-mortarboard me-1"></i>{{ currentGrade() }}
            </span>
            <span class="text-muted">
              <i class="bi bi-bullseye me-1"></i>
              <span :class="{ 'text-warning fw-semibold': targetCategory() === '—' }">{{ targetCategory() }}</span>
            </span>
          </div>
        </div>

        <span class="badge rounded-pill align-self-start" :class="statusBadge(application.status)">
          {{ application.status_label }}
        </span>
      </div>

      <button
        type="button"
        class="btn btn-link btn-sm text-decoration-none px-0 mt-2 admission-header-compact__toggle"
        @click="showDetails = !showDetails"
      >
        <i :class="['bi me-1', showDetails ? 'bi-chevron-up' : 'bi-chevron-down']"></i>
        {{ showDetails ? 'إخفاء التفاصيل' : 'تفاصيل إضافية' }}
      </button>

      <div v-show="showDetails" class="row g-2 g-md-3 text-muted small mt-2 pt-2 border-top">
        <div class="col-6 col-md-4">
          <i class="bi bi-person-check me-1"></i>
          <strong>مسؤول القبول:</strong>
          {{ application.assigned_to?.name || '—' }}
        </div>
        <div class="col-6 col-md-4">
          <i class="bi bi-calendar-plus me-1"></i>
          <strong>تاريخ الإنشاء:</strong>
          {{ formatDate(application.created_at) }}
        </div>
        <div class="col-6 col-md-4">
          <i class="bi bi-calendar3 me-1"></i>
          <strong>السنة الدراسية:</strong>
          {{ application.academic_year }}
        </div>
        <div class="col-6 col-md-4">
          <i class="bi bi-check2-square me-1"></i>
          <strong>القرار:</strong>
          {{ application.decision_label || '—' }}
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.admission-command-card {
  border-radius: 0.75rem;
}

.admission-header-compact .admission-header-avatar {
  width: 48px;
  height: 48px;
  font-size: 1.25rem;
}

.admission-header-compact__toggle {
  font-size: 0.8rem;
}
</style>
