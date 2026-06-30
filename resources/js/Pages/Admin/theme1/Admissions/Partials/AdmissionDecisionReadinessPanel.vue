<script setup>
import { computed } from 'vue'

const props = defineProps({
  decisionReadiness: { type: Object, required: true },
})

const isReady = computed(() => !!props.decisionReadiness?.ready)
const checklist = computed(() => props.decisionReadiness?.checks || [])
const completedCount = computed(() => checklist.value.filter((item) => item.ok).length)
const totalCount = computed(() => checklist.value.length)
const progressPercent = computed(() =>
  props.decisionReadiness?.completion_percentage
  ?? (totalCount.value ? Math.round((completedCount.value / totalCount.value) * 100) : 0),
)

function checkIconClass(item) {
  if (item.ok) return 'bi-check-circle-fill text-success'
  if (!item.blocking) return 'bi-exclamation-triangle-fill text-warning'
  return 'bi-exclamation-circle-fill text-danger'
}
</script>

<template>
  <div class="card admission-command-card border-0 shadow-sm mb-4" :class="{ 'admission-readiness--ready': isReady }">
    <div class="card-body p-4">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
          <h6 class="mb-1 fw-bold">
            <i class="bi bi-clipboard2-check me-1"></i>
            جاهزية القرار
          </h6>
          <p class="text-muted small mb-0">متطلبات قبول الطلب — يجب استيفاؤها قبل تأكيد القبول</p>
        </div>
        <div class="text-end">
          <div class="fw-bold fs-5" :class="isReady ? 'text-success' : 'text-primary'">
            {{ completedCount }} / {{ totalCount }}
          </div>
          <div class="small text-muted">{{ progressPercent }}%</div>
        </div>
      </div>

      <div class="progress mb-3 rounded-pill" style="height: 8px;">
        <div
          class="progress-bar rounded-pill"
          :class="isReady ? 'bg-success' : 'bg-primary'"
          role="progressbar"
          :style="{ width: `${progressPercent}%` }"
        ></div>
      </div>

      <ul class="list-unstyled mb-0 admission-readiness-checklist">
        <li
          v-for="item in checklist"
          :key="item.id"
          class="d-flex align-items-center gap-2 py-1 small"
        >
          <i class="bi flex-shrink-0" :class="checkIconClass(item)"></i>
          <span :class="{ 'text-muted': item.ok, 'fw-semibold': !item.ok }">
            {{ item.label }}
            <span
              v-if="item.id === 'documents_complete' && item.detail && !item.ok"
              class="d-block text-danger"
            >
              {{ item.detail }}
            </span>
          </span>
        </li>
      </ul>

      <div v-if="decisionReadiness.warnings?.length" class="alert alert-info mt-3 mb-0 py-2 small">
        <strong>تحذيرات:</strong>
        <ul class="mb-0 mt-1 ps-3">
          <li v-for="(warning, i) in decisionReadiness.warnings" :key="i">{{ warning }}</li>
        </ul>
      </div>

      <div v-if="isReady" class="alert alert-success mt-3 mb-0 py-2 small mb-0">
        <i class="bi bi-check-circle me-1"></i>
        الطلب جاهز لاتخاذ قرار القبول.
      </div>

      <div v-else-if="decisionReadiness.errors?.length" class="alert alert-warning mt-3 mb-0 py-2 small">
        <strong>متطلبات غير مكتملة:</strong>
        <ul class="mb-0 mt-1 ps-3">
          <li v-for="(err, i) in decisionReadiness.errors" :key="i">{{ err }}</li>
        </ul>
      </div>
    </div>
  </div>
</template>

<style scoped>
.admission-readiness--ready {
  border-inline-start: 4px solid var(--bs-success) !important;
}
</style>
