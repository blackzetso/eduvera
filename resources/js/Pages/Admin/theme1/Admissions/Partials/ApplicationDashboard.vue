<script setup>
import { computed } from 'vue'
import AdmissionReadinessPanel from './AdmissionReadinessPanel.vue'
import AdmissionDecisionReadinessPanel from './AdmissionDecisionReadinessPanel.vue'

const props = defineProps({
  conversionReadiness: { type: Object, required: true },
  decisionReadiness: { type: Object, default: () => ({ ready: false, checks: [] }) },
  application: { type: Object, required: true },
  primaryApplicant: { type: Object, default: null },
  primaryContact: { type: Object, default: null },
  documentSummary: { type: Object, default: () => ({}) },
  isReadOnly: { type: Boolean, default: false },
  canConvert: { type: Boolean, default: false },
})

const emit = defineEmits(['convert'])

const checklist = computed(() => props.conversionReadiness?.checks || [])
const showDecisionPanel = computed(() =>
  !props.isReadOnly && props.application.decision !== 'accepted'
)
</script>

<template>
  <div class="admission-adaptive-dashboard mb-4">
    <AdmissionDecisionReadinessPanel
      v-if="showDecisionPanel"
      :decision-readiness="decisionReadiness"
    />
    <div class="mb-3">
      <h6 class="fw-bold mb-1">
        <i class="bi bi-file-earmark-text me-1 text-warning"></i>
        لوحة طلب التقديم
      </h6>
      <p class="text-muted small mb-0">التحقق من الجاهزية والتحويل إلى طالب مسجل</p>
    </div>

    <div class="row g-2 mb-3">
      <div
        v-for="item in checklist"
        :key="item.id"
        class="col-6 col-md-4 col-lg-2"
      >
        <div class="admission-adaptive-card p-2 p-md-3 h-100 text-center">
          <i
            class="bi d-block mb-1"
            :class="item.ok ? 'bi-check-circle-fill text-success' : (item.blocking ? 'bi-circle text-muted' : 'bi-exclamation-triangle text-warning')"
          ></i>
          <div class="small" :class="item.ok ? 'text-success fw-semibold' : 'text-muted'">{{ item.label }}</div>
        </div>
      </div>
    </div>

    <AdmissionReadinessPanel
      :conversion-readiness="conversionReadiness"
      :application="application"
      :primary-applicant="primaryApplicant"
      :primary-contact="primaryContact"
      :document-summary="documentSummary"
      :is-read-only="isReadOnly"
      :can-convert="canConvert"
      :converted-student="null"
      @convert="emit('convert')"
    />
  </div>
</template>
