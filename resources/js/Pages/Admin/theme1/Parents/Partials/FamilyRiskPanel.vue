<script setup>
const props = defineProps({
  risks: { type: Array, default: () => [] },
  formatDate: { type: Function, required: true },
})

const emit = defineEmits(['open-student', 'open-tab'])
</script>

<template>
  <div class="card family-command-card border-0 shadow-sm mb-4 rounded-4">
    <div class="card-body p-3 p-md-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0 fw-bold">
          <i class="bi bi-shield-exclamation me-1 text-warning"></i>
          مركز مخاطر العائلة
        </h6>
        <span v-if="risks.length" class="badge bg-warning text-dark">{{ risks.length }}</span>
      </div>

      <div v-if="!risks.length" class="text-center text-muted small py-3">
        <i class="bi bi-check-circle text-success d-block fs-4 mb-2"></i>
        لا توجد مخاطر نشطة للعائلة
      </div>

      <div v-else class="vstack gap-2">
        <button
          v-for="risk in risks"
          :key="risk.id"
          type="button"
          class="family-risk-card d-flex gap-3 p-3 w-100 text-start border-0 bg-transparent rounded-4"
          @click="risk.student_id ? emit('open-student', risk.student_id) : emit('open-tab', 'documents')"
        >
          <span class="family-risk-card__severity" :class="`family-risk-card__severity--${risk.severity}`"></span>
          <div class="flex-grow-1 min-w-0">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
              <i :class="['bi', risk.icon]"></i>
              <span class="fw-semibold small">{{ risk.title }}</span>
              <span class="badge bg-light text-dark border small">{{ risk.severity_label }}</span>
            </div>
            <p class="mb-1 small text-muted">{{ risk.message }}</p>
            <div class="d-flex flex-wrap gap-3 small text-muted">
              <span v-if="risk.student_name"><i class="bi bi-person me-1"></i>{{ risk.student_name }}</span>
              <span v-if="risk.date"><i class="bi bi-calendar3 me-1"></i>{{ formatDate(risk.date) }}</span>
              <span><i class="bi bi-tag me-1"></i>{{ risk.source_label }}</span>
            </div>
          </div>
        </button>
      </div>
    </div>
  </div>
</template>
