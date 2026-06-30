<script setup>
import { computed } from 'vue'
import { documentSummaryProgress } from '@/composables/useAdmissionDocumentPreview'

const props = defineProps({
  decisionReadiness: { type: Object, required: true },
  documentSummary: { type: Object, default: () => ({}) },
  blockers: { type: Array, default: () => [] },
  pipelineStage: { type: String, default: '' },
  isReadOnly: { type: Boolean, default: false },
})

const isReady = computed(() => !!props.decisionReadiness?.ready)

const showHero = computed(() =>
  !props.isReadOnly
  && (props.pipelineStage === 'application' || props.blockers.length > 0 || isReady.value),
)

const documentProgress = computed(() => documentSummaryProgress(props.documentSummary))

const documentsCheck = computed(() =>
  (props.decisionReadiness?.checks || []).find((c) => c.id === 'documents_complete') || null,
)

const showDocumentBlocker = computed(() =>
  documentsCheck.value && !documentsCheck.value.ok && documentsCheck.value.blocking !== false,
)

const displayBlockers = computed(() => {
  const blockers = props.blockers.length
    ? props.blockers
    : (props.decisionReadiness?.checks || [])
      .filter((c) => !c.ok && c.blocking !== false)
      .map((c) => c.detail || c.label)

  if (!showDocumentBlocker.value) {
    return blockers
  }

  return blockers.filter((b) => b !== documentsCheck.value?.detail && b !== documentsCheck.value?.label)
})
</script>

<template>
  <div
    v-if="showHero"
    class="admissions-readiness-hero"
    :class="isReady ? 'admissions-readiness-hero--ready' : 'admissions-readiness-hero--blocked'"
  >
    <div class="d-flex flex-wrap align-items-center gap-3">
      <div class="flex-shrink-0">
        <i
          :class="[
            'bi fs-2',
            isReady ? 'bi-check-circle-fill text-success' : 'bi-x-octagon-fill text-danger',
          ]"
        ></i>
      </div>
      <div class="flex-grow-1 min-w-0">
        <div class="admissions-readiness-hero__title" :class="isReady ? 'text-success' : 'text-danger'">
          {{ isReady ? 'جاهز لاتخاذ القرار' : 'لا يمكن اتخاذ القرار حالياً' }}
        </div>
        <div v-if="showDocumentBlocker && documentProgress.total" class="mt-2 small">
          <div class="fw-semibold text-danger">المستندات</div>
          <div class="text-danger">
            {{ documentProgress.completed }} / {{ documentProgress.total }} معتمد
          </div>
          <div v-if="documentsCheck?.detail" class="text-danger">
            {{ documentsCheck.detail }}
          </div>
        </div>
        <p v-if="!isReady && displayBlockers.length" class="mb-0 mt-2 small">
          <span class="fw-semibold text-danger">عوائق:</span>
          <span v-for="(blocker, idx) in displayBlockers" :key="idx" class="ms-1">
            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">{{ blocker }}</span>
          </span>
        </p>
        <p v-else-if="isReady" class="mb-0 mt-1 small text-muted">
          جميع متطلبات القرار مستوفاة — يمكنك المتابعة من الإجراءات السريعة.
        </p>
      </div>
      <div v-if="decisionReadiness.completion_percentage != null" class="text-end flex-shrink-0">
        <div class="fw-bold fs-4" :class="isReady ? 'text-success' : 'text-danger'">
          {{ decisionReadiness.completion_percentage }}%
        </div>
        <div class="small text-muted">جاهزية القرار</div>
      </div>
    </div>
  </div>
</template>
