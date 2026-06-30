<script setup>
import { pipelineBadgeClass, statusBadge, decisionBadge } from '@/Shared/admissionsBadges'

defineProps({
  application: { type: Object, required: true },
  applicantName: { type: String, default: '—' },
  targetGrade: { type: String, default: '—' },
  executiveBadges: { type: Array, default: () => [] },
  daysOpen: { type: [Number, String], default: null },
  lastActivity: { type: String, default: null },
  engagementCount: { type: Number, default: 0 },
  duplicateRisk: { type: Object, default: () => ({}) },
  formatDate: { type: Function, required: true },
  formatDateTime: { type: Function, required: true },
})
</script>

<template>
  <div class="card admissions-command-card admissions-header-card border-0 shadow-sm overflow-hidden">
    <div class="card-body">
      <div class="d-flex flex-wrap align-items-start gap-2 gap-md-3">
        <div class="admissions-header-avatar rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center flex-shrink-0">
          <i class="bi bi-person-vcard"></i>
        </div>

        <div class="flex-grow-1 min-w-0">
          <div class="d-flex flex-wrap align-items-center gap-1 gap-md-2 mb-1">
            <h5 class="mb-0 fw-bold text-truncate">{{ applicantName }}</h5>
            <code class="small text-muted mb-0">{{ application.reference_code }}</code>
          </div>

          <div class="d-flex flex-wrap align-items-center gap-2 small mb-2">
            <span class="badge rounded-pill" :class="pipelineBadgeClass(application.pipeline_stage)">
              {{ application.pipeline_stage_label }}
            </span>
            <span class="badge rounded-pill" :class="statusBadge(application.status)">
              {{ application.status_label }}
            </span>
            <span v-if="application.decision" class="badge rounded-pill" :class="decisionBadge(application.decision)">
              {{ application.decision_label }}
            </span>
          </div>

          <div class="d-flex flex-wrap gap-2 text-muted small">
            <span><i class="bi bi-bullseye me-1"></i>{{ targetGrade }}</span>
            <span><i class="bi bi-calendar3 me-1"></i>{{ application.academic_year }}</span>
            <span v-if="application.assigned_to?.name">
              <i class="bi bi-person-check me-1"></i>{{ application.assigned_to.name }}
            </span>
          </div>

          <div v-if="executiveBadges.length" class="d-flex flex-wrap gap-1 mt-2">
            <span
              v-for="badge in executiveBadges"
              :key="badge.id"
              class="badge rounded-pill admissions-exec-badge"
              :class="badge.class"
            >
              <i :class="['bi', badge.icon, 'me-1']"></i>{{ badge.label }}
            </span>
          </div>
        </div>

        <div class="d-flex flex-wrap gap-2 ms-md-auto flex-shrink-0">
          <div class="admissions-header-kpi">
            <div class="admissions-header-kpi__value">{{ daysOpen ?? '—' }}</div>
            <div class="admissions-header-kpi__label">يوم</div>
          </div>
          <div class="admissions-header-kpi">
            <div class="admissions-header-kpi__value text-truncate" style="max-width:4.5rem;font-size:0.75rem">
              {{ formatDateTime(lastActivity || application.last_activity_at).split('،')[0] }}
            </div>
            <div class="admissions-header-kpi__label">آخر نشاط</div>
          </div>
          <div class="admissions-header-kpi">
            <div class="admissions-header-kpi__value">{{ engagementCount }}</div>
            <div class="admissions-header-kpi__label">تفاعل</div>
          </div>
          <div class="admissions-header-kpi" :class="duplicateRisk.class">
            <div class="admissions-header-kpi__value" :class="duplicateRisk.class">{{ duplicateRisk.label }}</div>
            <div class="admissions-header-kpi__label">تكرار</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
