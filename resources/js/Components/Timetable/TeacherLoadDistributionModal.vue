<script setup>
import { ref, computed, watch } from 'vue'
import {
  DEFAULT_PRIORITY_RULES,
  DEFAULT_MAX_WEEKLY_LOAD,
  suggestAllSubjectsDistribution,
  validateDistributionState,
  distributionToSettingsPayload,
  hydrateCardsFromSettings,
  buildDistributionContext,
} from '@/Services/TeacherLoadDistributionEngine'

const props = defineProps({
  show: { type: Boolean, default: false },
  subjects: { type: Array, default: () => [] },
  teachers: { type: Array, default: () => [] },
  subjectRequirements: { type: Array, default: () => [] },
  settings: { type: Object, default: () => ({}) },
  wizardMeta: { type: Object, default: () => ({}) },
  teacherLoads: { type: Object, default: () => ({}) },
  initialDistribution: { type: Object, default: () => ({}) },
  initialRules: { type: Object, default: () => ({}) },
  processing: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'apply'])

const priorityRules = ref({ ...DEFAULT_PRIORITY_RULES, ...props.initialRules })
const cards = ref([])

const distributionContext = computed(() =>
  buildDistributionContext(props.settings, props.wizardMeta, props.teacherLoads, priorityRules.value)
)

const validation = computed(() => validateDistributionState(cards.value))
const canApply = computed(() => validation.value.isValid && cards.value.length > 0)

function rebuildSuggestions() {
  cards.value = suggestAllSubjectsDistribution(
    props.subjectRequirements,
    props.teachers,
    distributionContext.value
  )
}

function loadFromSavedOrSuggest() {
  const dist = props.initialDistribution
  if (dist && Object.keys(dist).length) {
    cards.value = hydrateCardsFromSettings(
      props.subjectRequirements,
      dist,
      props.teachers,
      distributionContext.value
    )
  } else {
    rebuildSuggestions()
  }
}

watch(
  () => props.show,
  (open) => {
    if (open) {
      priorityRules.value = { ...DEFAULT_PRIORITY_RULES, ...props.initialRules }
      loadFromSavedOrSuggest()
    }
  }
)

watch(priorityRules, () => {
  if (props.show) rebuildSuggestions()
}, { deep: true })

function updatePeriod(cardIndex, rowIndex, value) {
  const n = Math.max(0, parseInt(String(value), 10) || 0)
  const card = cards.value[cardIndex]
  if (!card?.rows?.[rowIndex]) return
  card.rows[rowIndex].periods = n
  const sum = card.rows.reduce((s, r) => s + (Number(r.periods) || 0), 0)
  card.isValid = sum === card.required
}

function cardProgress(card) {
  const assigned = (card.rows ?? []).reduce((s, r) => s + (Number(r.periods) || 0), 0)
  const req = Number(card.required) || 1
  return { assigned, req, pct: Math.min(100, Math.round((assigned / req) * 100)) }
}

function cardError(card) {
  const { assigned, req } = cardProgress(card)
  if (assigned < req) return `ما زال هناك ${req - assigned} حصة غير موزعة`
  if (assigned > req) return 'تم توزيع حصص أكثر من المطلوب'
  return ''
}

function handleApply() {
  if (!canApply.value) return
  emit('apply', {
    distribution: distributionToSettingsPayload(cards.value),
    rules: { ...priorityRules.value },
  })
}
</script>

<template>
  <div
    v-if="show"
    class="modal fade show d-block tt-dist-modal"
    tabindex="-1"
    role="dialog"
    aria-modal="true"
    dir="rtl"
  >
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content tt-dist-modal__content shadow-lg">
        <div class="modal-header tt-dist-modal__header border-0">
          <div>
            <h5 class="modal-title fw-bold mb-1">
              <i class="bi bi-diagram-3 ms-2"></i>
              توزيع الحصص على المعلمين
            </h5>
            <p class="small text-white-50 mb-0">
              وزّع الحصص الأسبوعية على المعلمين قبل توليد الجدول
            </p>
          </div>
          <button type="button" class="btn-close btn-close-white" aria-label="إغلاق" @click="emit('close')"></button>
        </div>

        <div class="modal-body">
          <!-- Section 1 -->
          <section class="tt-dist-modal__section mb-4">
            <h6 class="fw-bold mb-2">
              <i class="bi bi-sliders2 text-primary ms-1"></i>
              قواعد التوزيع
            </h6>
            <p class="small text-muted mb-3">
              سيتم اقتراح توزيع الحصص بناءً على أولوية التخصص والمرحلة مع مراعاة نصاب المعلمين.
            </p>
            <div class="row g-2">
              <div class="col-md-6 col-lg-4">
                <label class="tt-dist-modal__rule">
                  <input v-model="priorityRules.specializationFirst" type="checkbox" />
                  <span>التخصص أولاً</span>
                </label>
              </div>
              <div class="col-md-6 col-lg-4">
                <label class="tt-dist-modal__rule">
                  <input v-model="priorityRules.educationalStage" type="checkbox" />
                  <span>المرحلة الدراسية</span>
                </label>
              </div>
              <div class="col-md-6 col-lg-4">
                <label class="tt-dist-modal__rule">
                  <input v-model="priorityRules.gradeClass" type="checkbox" />
                  <span>الصف الدراسي</span>
                </label>
              </div>
              <div class="col-md-6 col-lg-4">
                <label class="tt-dist-modal__rule">
                  <input v-model="priorityRules.maxWeeklyLoad" type="checkbox" />
                  <span>الحد الأقصى للنصاب ({{ DEFAULT_MAX_WEEKLY_LOAD }})</span>
                </label>
              </div>
              <div class="col-md-6 col-lg-4">
                <label class="tt-dist-modal__rule">
                  <input v-model="priorityRules.balancedLoad" type="checkbox" />
                  <span>توازن الأحمال</span>
                </label>
              </div>
            </div>
            <button type="button" class="btn btn-sm tt-dist-modal__btn-outline mt-3" @click="rebuildSuggestions">
              <i class="bi bi-arrow-repeat ms-1"></i>
              إعادة الاقتراح التلقائي
            </button>
          </section>

          <!-- Section 2 -->
          <section class="tt-dist-modal__section">
            <h6 class="fw-bold mb-3">
              <i class="bi bi-person-lines-fill text-primary ms-1"></i>
              توزيع المواد
            </h6>

            <div v-if="!cards.length" class="alert alert-light border text-center">
              أضف متطلبات مواد (حصص أسبوعية) من الخطوة 3 أو معالج الإعداد أولاً.
            </div>

            <div v-for="(card, cIdx) in cards" :key="card.subject_id" class="tt-dist-card mb-3">
              <div class="tt-dist-card__head">
                <div class="d-flex align-items-center gap-2">
                  <span
                    class="tt-wizard__subject-dot"
                    :style="{ background: card.color || '#6f42c1' }"
                  ></span>
                  <div>
                    <strong>{{ card.name }}</strong>
                    <div class="small text-muted">{{ card.required }} حصة مطلوبة أسبوعياً</div>
                  </div>
                </div>
                <div class="tt-dist-card__progress-wrap">
                  <div class="small fw-semibold mb-1">
                    {{ cardProgress(card).assigned }} / {{ cardProgress(card).req }} حصة
                  </div>
                  <div class="progress tt-dist-card__progress" style="height: 8px">
                    <div
                      class="progress-bar"
                      :class="card.isValid ? 'bg-success' : 'bg-warning'"
                      :style="{ width: cardProgress(card).pct + '%' }"
                    ></div>
                  </div>
                </div>
              </div>

              <div v-if="card.warnings?.length" class="tt-dist-card__warnings px-3 pt-2">
                <span
                  v-for="(w, wi) in [...new Set(card.warnings)].slice(0, 2)"
                  :key="wi"
                  class="badge tt-dist-chip tt-dist-chip--warn"
                >
                  <i class="bi bi-exclamation-triangle ms-1"></i>{{ w }}
                </span>
              </div>

              <p v-if="cardError(card)" class="small text-danger mb-0 px-3 pt-2">
                {{ cardError(card) }}
              </p>

              <div class="tt-dist-card__body">
                <div
                  v-for="(row, rIdx) in card.rows"
                  :key="`${card.subject_id}-${row.teacher_id}`"
                  class="tt-dist-teacher-row"
                >
                  <div class="tt-dist-teacher-row__info">
                    <span class="tt-dist-chip tt-dist-chip--teacher">{{ row.teacher?.name || 'معلم' }}</span>
                    <div class="small text-muted mt-1">
                      <span>تخصص: {{ row.specialization }}</span>
                      <span class="mx-2">|</span>
                      <span>مرحلة: {{ row.stageLabel }}</span>
                      <span class="mx-2">|</span>
                      <span>نصاب حالي: {{ row.currentLoad }}</span>
                    </div>
                    <div class="small text-primary mt-1">
                      مقترح: {{ row.periods }} حصة
                      <span v-if="row.specializationExact" class="badge bg-success-subtle text-success ms-1"
                        >تخصص مطابق</span
                      >
                    </div>
                  </div>
                  <div class="tt-dist-teacher-row__input">
                    <label class="small text-muted d-block mb-1">حصص</label>
                    <input
                      type="number"
                      class="form-control form-control-sm text-center"
                      min="0"
                      :max="card.required"
                      :value="row.periods"
                      @input="updatePeriod(cIdx, rIdx, $event.target.value)"
                    />
                  </div>
                </div>
              </div>
            </div>
          </section>
        </div>

        <div class="modal-footer border-0 tt-dist-modal__footer">
          <button type="button" class="btn btn-secondary-soft" @click="emit('close')">إلغاء</button>
          <button
            type="button"
            class="btn tt-dist-modal__btn-apply"
            :disabled="!canApply || processing"
            @click="handleApply"
          >
            <span v-if="processing" class="spinner-border spinner-border-sm ms-2"></span>
            <i v-else class="bi bi-check2-circle ms-1"></i>
            اعتماد توزيع الحصص
          </button>
        </div>
      </div>
    </div>
    <div class="modal-backdrop fade show" @click="emit('close')"></div>
  </div>
</template>
