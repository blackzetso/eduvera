<script setup>
import { ref, watch, computed } from 'vue'
import { useDailyAbsenceCoverage } from '@/composables/useDailyAbsenceCoverage'

const props = defineProps({
  show: Boolean,
  lesson: { type: Object, default: null },
  date: { type: String, default: '' },
})

const emit = defineEmits(['close', 'applied'])

const { fetchSwapCandidates, previewSwap, applySwap, loading } = useDailyAbsenceCoverage()

const candidatesData = ref(null)
const selectedTeacherId = ref(null)
const swapType = ref('move_lesson')
const impact = ref(null)
const applying = ref(false)
const swapError = ref(null)
const previewLoading = ref(false)

const exchangeTeacherAId = ref(null)
const exchangePeriodAId = ref(null)
const exchangeTeacherBId = ref(null)
const exchangePeriodBId = ref(null)

const selectedCandidate = computed(() =>
  candidatesData.value?.candidates?.find((c) => c.teacher_id === Number(selectedTeacherId.value))
)

const exchangePool = computed(() => candidatesData.value?.exchange_teachers ?? [])

const teacherA = computed(() =>
  exchangePool.value.find((t) => t.teacher_id === Number(exchangeTeacherAId.value))
)

const teacherBLessons = computed(() => {
  const id = Number(exchangeTeacherBId.value)
  if (!id || id === Number(exchangeTeacherAId.value)) return []
  return exchangePool.value.find((t) => t.teacher_id === id)?.lessons ?? []
})

const teacherALessons = computed(() => teacherA.value?.lessons ?? [])

const suggestedPayload = computed(() => {
  if (!props.lesson || !props.date) return null

  if (swapType.value === 'swap_lessons') {
    const aId = Number(exchangeTeacherAId.value)
    const bId = Number(exchangeTeacherBId.value)
    const periodA = Number(exchangePeriodAId.value)
    const periodB = Number(exchangePeriodBId.value)
    if (!aId || !bId || !periodA || !periodB || aId === bId) return null

    return {
      date: props.date,
      swap_type: 'swap_lessons',
      teacher_id: aId,
      source_period_id: periodA,
      secondary_teacher_id: bId,
      secondary_period_id: periodB,
      trigger_period_id: props.lesson.period_id,
      reason: 'تبديل حصتين مؤقت — تغطية غياب',
    }
  }

  const c = selectedCandidate.value
  const move = c?.suggested_move
  if (!move?.from_period_id) return null
  const targetId =
    move.to_period_id ||
    candidatesData.value?.trigger_period?.period_id ||
    props.lesson.period_id

  return {
    date: props.date,
    swap_type: 'move_lesson',
    teacher_id: c.teacher_id,
    source_period_id: move.from_period_id,
    target_period_id: targetId,
    trigger_period_id: props.lesson.period_id,
    reason: 'نقل حصة مؤقت — تغطية غياب',
  }
})

const canConfirm = computed(
  () => suggestedPayload.value && !(impact.value?.errors?.length)
)

function resetExchange() {
  exchangeTeacherAId.value = null
  exchangePeriodAId.value = null
  exchangeTeacherBId.value = null
  exchangePeriodBId.value = null
}

function initExchangeDefaults() {
  const pool = exchangePool.value
  if (pool.length < 2) return
  exchangeTeacherAId.value = pool[0].teacher_id
  exchangePeriodAId.value = pool[0].lessons?.[0]?.period_id ?? null
  exchangeTeacherBId.value = pool[1].teacher_id
  exchangePeriodBId.value = pool[1].lessons?.[0]?.period_id ?? null
}

watch(
  () => props.show,
  async (open) => {
    if (!open || !props.lesson) return
    swapError.value = null
    impact.value = null
    swapType.value = 'move_lesson'
    resetExchange()
    selectedTeacherId.value = null
    candidatesData.value = await fetchSwapCandidates(props.lesson.period_id, props.date)
    const first = candidatesData.value?.candidates?.[0]
    if (first) selectedTeacherId.value = first.teacher_id
    initExchangeDefaults()
  }
)

async function loadPreview() {
  if (!suggestedPayload.value) {
    impact.value = null
    return
  }
  previewLoading.value = true
  swapError.value = null
  try {
    impact.value = await previewSwap(suggestedPayload.value)
    swapError.value = impact.value?.errors?.length ? impact.value.errors.join(' — ') : null
  } catch (e) {
    swapError.value = e.response?.data?.message || 'تعذر معاينة التبديل'
    impact.value = null
  } finally {
    previewLoading.value = false
  }
}

watch(
  [selectedTeacherId, swapType, exchangeTeacherAId, exchangePeriodAId, exchangeTeacherBId, exchangePeriodBId],
  loadPreview
)

watch(exchangeTeacherAId, () => {
  const lessons = teacherALessons.value
  if (!lessons.some((l) => l.period_id === Number(exchangePeriodAId.value))) {
    exchangePeriodAId.value = lessons[0]?.period_id ?? null
  }
})

watch(exchangeTeacherBId, () => {
  const lessons = teacherBLessons.value
  if (!lessons.some((l) => l.period_id === Number(exchangePeriodBId.value))) {
    exchangePeriodBId.value = lessons[0]?.period_id ?? null
  }
})

async function confirmSwap() {
  if (!canConfirm.value) return
  applying.value = true
  try {
    await applySwap(suggestedPayload.value)
    emit('applied')
    emit('close')
  } catch (e) {
    swapError.value = e.response?.data?.message || e.response?.data?.errors?.swap?.[0] || 'فشل تطبيق التبديل'
  } finally {
    applying.value = false
  }
}

function impactRowLabel(row) {
  return row.label || `${row.subject_name || '—'} — ${row.teacher_name || '—'}`
}
</script>

<template>
  <div v-if="show" class="modal fade show d-block tt-swap-modal" tabindex="-1" dir="rtl" style="z-index: 1065">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content shadow-lg">
        <div class="modal-header tt-swap-modal__header border-0">
          <div>
            <h5 class="modal-title fw-bold mb-1">
              <i class="bi bi-arrow-left-right ms-2"></i>
              تبديل الحصة مؤقتاً
            </h5>
            <p class="small text-white-50 mb-0">تعديل يومي — لا يؤثر على الجدول الأساسي</p>
          </div>
          <button type="button" class="btn-close btn-close-white" @click="emit('close')"></button>
        </div>

        <div class="modal-body">
          <div v-if="loading && !candidatesData" class="text-center py-4">
            <div class="spinner-border text-primary"></div>
          </div>

          <template v-else-if="candidatesData">
            <div v-if="candidatesData.trigger_period" class="alert alert-light border mb-3">
              <strong>الحصة المتأثرة:</strong>
              الحصة {{ candidatesData.trigger_period.period_number }} —
              {{ candidatesData.trigger_period.subject_name || '—' }}
              <span class="text-muted">({{ candidatesData.trigger_period.class_name }})</span>
              <span class="d-block small text-danger mt-1">
                غائب: {{ candidatesData.trigger_period.absent_teacher_name }}
              </span>
            </div>

            <ul class="nav nav-pills tt-swap-tabs mb-4">
              <li class="nav-item">
                <button
                  type="button"
                  class="nav-link"
                  :class="{ active: swapType === 'move_lesson' }"
                  @click="swapType = 'move_lesson'"
                >
                  <i class="bi bi-arrow-up-circle ms-1"></i>
                  نقل حصة
                </button>
              </li>
              <li class="nav-item">
                <button
                  type="button"
                  class="nav-link"
                  :class="{ active: swapType === 'swap_lessons' }"
                  @click="swapType = 'swap_lessons'"
                >
                  <i class="bi bi-arrow-left-right ms-1"></i>
                  تبديل بين معلمين
                </button>
              </li>
            </ul>

            <!-- Move lesson -->
            <template v-if="swapType === 'move_lesson'">
              <div v-if="!candidatesData.candidates?.length" class="alert alert-warning">
                لا يوجد معلمون يمكن نقل حصصهم (يحتاج حصة فارغة عند وقت الغياب).
              </div>
              <template v-else>
                <div class="mb-3">
                  <label class="form-label fw-semibold">المعلم</label>
                  <select v-model="selectedTeacherId" class="form-select">
                    <option v-for="c in candidatesData.candidates" :key="c.teacher_id" :value="c.teacher_id">
                      {{ c.name }} — تطابق {{ c.match_score }}%
                    </option>
                  </select>
                </div>

                <div v-if="selectedCandidate" class="mb-3">
                  <h6 class="fw-bold small text-muted mb-2">جدول المعلم اليوم</h6>
                  <div class="row g-2">
                    <div
                      v-for="slot in selectedCandidate.schedule"
                      :key="slot.period_number"
                      class="col-6 col-md-4"
                    >
                      <div
                        class="tt-swap-slot p-2 rounded"
                        :class="{
                          'tt-swap-slot--free': slot.is_free,
                          'tt-swap-slot--busy': slot.has_lesson,
                        }"
                      >
                        <div class="small fw-bold">الحصة {{ slot.period_number }}</div>
                        <div class="small">{{ slot.label }}</div>
                      </div>
                    </div>
                  </div>
                </div>

                <div v-if="selectedCandidate?.suggested_move" class="alert alert-info border-0">
                  <strong>إجراء مقترح:</strong>
                  نقل الحصة {{ selectedCandidate.suggested_move.from_period_number }} →
                  {{ selectedCandidate.suggested_move.to_period_number }}
                </div>
              </template>
            </template>

            <!-- Exchange between teachers -->
            <template v-else>
              <div v-if="exchangePool.length < 2" class="alert alert-warning">
                يجب وجود معلمين على الأقل لديهما حصص اليوم لتفعيل التبديل.
              </div>
              <template v-else>
                <div class="row g-3 mb-3">
                  <div class="col-md-6">
                    <label class="form-label fw-semibold">المعلم الأول</label>
                    <select v-model="exchangeTeacherAId" class="form-select">
                      <option v-for="t in exchangePool" :key="t.teacher_id" :value="t.teacher_id">
                        {{ t.name }}
                      </option>
                    </select>
                    <label class="form-label small mt-2">حصته</label>
                    <select v-model="exchangePeriodAId" class="form-select form-select-sm">
                      <option v-for="l in teacherALessons" :key="l.period_id" :value="l.period_id">
                        الحصة {{ l.period_number }} — {{ l.label }}
                      </option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-semibold">المعلم الثاني</label>
                    <select v-model="exchangeTeacherBId" class="form-select">
                      <option
                        v-for="t in exchangePool.filter((x) => x.teacher_id !== Number(exchangeTeacherAId))"
                        :key="t.teacher_id"
                        :value="t.teacher_id"
                      >
                        {{ t.name }}
                      </option>
                    </select>
                    <label class="form-label small mt-2">حصته</label>
                    <select v-model="exchangePeriodBId" class="form-select form-select-sm">
                      <option v-for="l in teacherBLessons" :key="l.period_id" :value="l.period_id">
                        الحصة {{ l.period_number }} — {{ l.label }}
                      </option>
                    </select>
                  </div>
                </div>

                <div
                  v-if="exchangePeriodAId && exchangePeriodBId"
                  class="tt-swap-exchange-diagram alert alert-secondary border-0 text-center"
                >
                  <div class="d-flex align-items-center justify-content-center gap-3 flex-wrap">
                    <div class="tt-swap-exchange-box">
                      <div class="small text-muted">المعلم ١ / الحصة</div>
                      <strong>{{ teacherA?.name }}</strong>
                      <div class="small">
                        {{
                          teacherALessons.find((l) => l.period_id === Number(exchangePeriodAId))?.label
                        }}
                      </div>
                    </div>
                    <i class="bi bi-arrow-left-right fs-3 text-primary"></i>
                    <div class="tt-swap-exchange-box">
                      <div class="small text-muted">المعلم ٢ / الحصة</div>
                      <strong>{{
                        exchangePool.find((t) => t.teacher_id === Number(exchangeTeacherBId))?.name
                      }}</strong>
                      <div class="small">
                        {{
                          teacherBLessons.find((l) => l.period_id === Number(exchangePeriodBId))?.label
                        }}
                      </div>
                    </div>
                  </div>
                </div>
              </template>
            </template>

            <div v-if="previewLoading" class="text-center py-3">
              <div class="spinner-border spinner-border-sm text-primary"></div>
              <span class="small text-muted ms-2">جاري المعاينة...</span>
            </div>

            <div
              v-else-if="impact && (impact.before?.length || impact.after?.length)"
              class="tt-swap-preview row g-3 mt-1"
            >
              <div class="col-md-6">
                <h6 class="fw-bold text-danger">قبل التعديل</h6>
                <ul class="list-unstyled small mb-0">
                  <li v-for="row in impact.before" :key="'b' + (row.period_id || row.period_number)">
                    <template v-if="swapType === 'swap_lessons'">
                      الحصة {{ row.period_number }} ({{ row.class_name }}) → {{ impactRowLabel(row) }}
                    </template>
                    <template v-else> الحصة {{ row.period_number }} → {{ impactRowLabel(row) }} </template>
                  </li>
                </ul>
              </div>
              <div class="col-md-6">
                <h6 class="fw-bold text-success">بعد التعديل</h6>
                <ul class="list-unstyled small mb-0">
                  <li v-for="row in impact.after" :key="'a' + (row.period_id || row.period_number)">
                    <template v-if="swapType === 'swap_lessons'">
                      الحصة {{ row.period_number }} ({{ row.class_name }}) → {{ impactRowLabel(row) }}
                    </template>
                    <template v-else> الحصة {{ row.period_number }} → {{ impactRowLabel(row) }} </template>
                  </li>
                </ul>
              </div>
            </div>

            <div v-if="swapError" class="alert alert-danger mt-3 mb-0">{{ swapError }}</div>
          </template>
        </div>

        <div class="modal-footer border-0">
          <button type="button" class="btn btn-secondary" @click="emit('close')">إلغاء</button>
          <button
            type="button"
            class="btn btn-primary"
            :disabled="applying || !canConfirm || previewLoading"
            @click="confirmSwap"
          >
            <span v-if="applying" class="spinner-border spinner-border-sm ms-2"></span>
            تأكيد التبديل المؤقت
          </button>
        </div>
      </div>
    </div>
    <div class="modal-backdrop fade show" style="z-index: 1060" @click="emit('close')"></div>
  </div>
</template>
