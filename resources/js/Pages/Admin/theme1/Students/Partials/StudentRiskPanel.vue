<script setup>
import { computed } from 'vue'

const props = defineProps({
  risks: { type: Array, default: () => [] },
  formatDate: { type: Function, required: true },
})

const emit = defineEmits(['open-tab'])

const severityOrder = { high: 0, medium: 1, low: 2 }

const sortedRisks = computed(() =>
  [...props.risks].sort((a, b) =>
    (severityOrder[a.severity] ?? 9) - (severityOrder[b.severity] ?? 9),
  ),
)

const criticalCount = computed(() =>
  props.risks.filter((r) => r.severity === 'high').length,
)

const hasCritical = computed(() => criticalCount.value > 0)

const topRisks = computed(() => sortedRisks.value.slice(0, 5))

const criticalLabels = computed(() => {
  const labels = []
  if (props.risks.some((r) => r.source === 'attendance' && r.severity === 'high')) {
    labels.push('خطر حضور')
  }
  if (props.risks.some((r) => r.source === 'finance' && r.severity === 'high')) {
    labels.push('خطر مالي')
  }
  if (props.risks.some((r) => r.source === 'enrollment' && r.severity === 'high')) {
    labels.push('خطر قيد')
  }
  if (props.risks.some((r) => r.source === 'behavior' && r.severity === 'high')) {
    labels.push('خطر سلوكي')
  }
  return labels
})

function sourceTab(source) {
  return {
    attendance: 'attendance',
    finance: 'wallet',
    behavior: 'behavior',
    enrollment: 'enrollment',
  }[source] || 'overview'
}
</script>

<template>
  <div
    class="card student-command-card border-0 shadow-sm"
    :class="{ 'student-risk-panel--has-critical': hasCritical }"
  >
    <div class="card-body p-2 p-md-3">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <div class="student-section-title mb-0">
          <i class="bi bi-shield-exclamation me-1" :class="hasCritical ? 'text-danger' : 'text-warning'"></i>
          المخاطر والتنبيهات
        </div>
        <span v-if="risks.length" class="badge rounded-pill" :class="hasCritical ? 'bg-danger' : 'bg-warning text-dark'">
          {{ risks.length }}
        </span>
      </div>

      <div v-if="hasCritical" class="student-risk-hero">
        <div>
          <div class="student-risk-hero__count">{{ criticalCount }}</div>
          <div class="small fw-semibold text-danger">تنبيه حرج</div>
        </div>
        <div class="flex-grow-1">
          <div class="small fw-semibold mb-1">يتطلب انتباه فوري</div>
          <div class="d-flex flex-wrap gap-1">
            <span
              v-for="label in criticalLabels"
              :key="label"
              class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25"
            >{{ label }}</span>
          </div>
        </div>
      </div>

      <div v-if="!risks.length" class="text-center text-muted small py-2">
        <i class="bi bi-check-circle-fill text-success me-1"></i>
        لا توجد مخاطر نشطة
      </div>

      <div v-else class="vstack gap-1">
        <button
          v-for="risk in topRisks"
          :key="risk.id"
          type="button"
          class="student-risk-card d-flex gap-2 p-2 w-100 text-start border-0"
          :class="`student-risk-card--${risk.severity}`"
          @click="emit('open-tab', sourceTab(risk.source))"
        >
          <span
            class="student-risk-card__severity"
            :class="`student-risk-card__severity--${risk.severity}`"
          ></span>
          <div class="flex-grow-1 min-w-0">
            <div class="d-flex flex-wrap align-items-center gap-1 mb-0">
              <i :class="['bi', risk.icon, risk.severity === 'high' ? 'text-danger' : 'text-warning']"></i>
              <span class="fw-bold small">{{ risk.title }}</span>
              <span
                class="badge small"
                :class="risk.severity === 'high' ? 'bg-danger' : 'bg-warning text-dark'"
              >{{ risk.severity_label }}</span>
            </div>
            <p class="mb-0 small text-muted text-truncate">{{ risk.message }}</p>
          </div>
          <span v-if="risk.date" class="small text-muted text-nowrap d-none d-md-inline">
            {{ formatDate(risk.date) }}
          </span>
        </button>
      </div>
    </div>
  </div>
</template>
