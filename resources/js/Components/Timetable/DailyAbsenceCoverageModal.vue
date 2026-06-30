<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { toast } from 'vue3-toastify'
import { useDailyAbsenceCoverage, isLessonResolved } from '@/composables/useDailyAbsenceCoverage'
import TemporaryLessonSwapModal from '@/Components/Timetable/TemporaryLessonSwapModal.vue'
import CoverageDemoEmptyState from '@/Components/Timetable/CoverageDemoEmptyState.vue'
import DailyAbsenceCoverageWizard from '@/Components/Timetable/DailyAbsenceCoverageWizard.vue'
import SubstituteDistributionReportModal from '@/Components/Timetable/SubstituteDistributionReportModal.vue'

const props = defineProps({
  show: Boolean,
  teachers: { type: Array, default: () => [] },
  teacherAttendanceStatuses: { type: Object, default: () => ({}) },
  initialSummary: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['close', 'approved', 'demo-seeded'])

const {
  loading,
  preview,
  fetchPreview,
  saveCoverageDraft,
  approveCoverage,
  fetchDistributionReport,
  notifySubstituteTeacher,
  cancelLesson,
} = useDailyAbsenceCoverage()
const localLessons = ref([])
const approving = ref(false)
const savingDraft = ref(false)
const wizardRef = ref(null)
const expandedTeacherSelect = ref({})
const swapLesson = ref(null)
const showSwapModal = ref(false)
const showDistributionReport = ref(false)
const distributionReport = ref(null)
const distributionReportLoading = ref(false)
const reportOpenedAfterApprove = ref(false)
/** Blocks accidental overlay dismiss while interacting with controls inside the modal. */
const modalLocked = ref(false)
let lockTimer = null

const ALLOWED_CLOSE_REASONS = new Set(['close_button', 'outside_click', 'esc_key', 'approve_complete'])

function lockModalInteraction() {
  modalLocked.value = true
  if (lockTimer) clearTimeout(lockTimer)
  lockTimer = setTimeout(() => {
    modalLocked.value = false
    lockTimer = null
  }, 400)
}

function closeModal(reason) {
  console.log('Coverage Modal Close Reason:', reason)
  if (!ALLOWED_CLOSE_REASONS.has(reason)) {
    console.log('Coverage Modal Close Blocked: invalid_reason', reason)
    return
  }
  if (modalLocked.value && reason === 'outside_click') {
    console.log('Coverage Modal Close Blocked: modal_locked')
    return
  }
  if (showSwapModal.value && reason !== 'close_button' && reason !== 'esc_key') {
    console.log('Coverage Modal Close Blocked: swap_modal_open')
    return
  }
  emit('close')
}

function onDialogPointerDown(event) {
  event.stopPropagation()
  lockModalInteraction()
}

function onEscKey(event) {
  if (!props.show) return
  if (event.key !== 'Escape') return
  if (showSwapModal.value) return
  event.preventDefault()
  event.stopPropagation()
  closeModal('esc_key')
}

onMounted(() => {
  document.addEventListener('keydown', onEscKey, true)
})

onUnmounted(() => {
  document.removeEventListener('keydown', onEscKey, true)
  if (lockTimer) clearTimeout(lockTimer)
})

const canApprove = computed(() =>
  localLessons.value.length > 0 && localLessons.value.every((l) => isLessonResolved(l))
)

const hasSubstituteToApprove = computed(() =>
  localLessons.value.some(
    (l) => !l.cancelled && !l.adjustment?.id && l.resolution !== 'temporary_swap' && l.suggestion?.replacement_teacher_id
  )
)

watch(
  () => props.show,
  async (open) => {
    if (!open) return
    const data = await fetchPreview()
    syncLessons(data)
  }
)

function syncLessons(data) {
  localLessons.value = JSON.parse(JSON.stringify(data.affected_lessons ?? []))
  const pending = localLessons.value.find((l) => l.status === 'needs_coverage' && !l.adjustment?.id)
  if (pending) {
    expandedTeacherSelect.value = { [pending.period_id]: true }
  }
  if (data?.coverage_draft_meta && wizardRef.value?.restoreState) {
    wizardRef.value.restoreState(data.coverage_draft_meta)
  }
}

function balanceBadgeClass(balance) {
  const n = Number(balance) || 0
  if (n <= 2) return 'tt-coverage-balance--low'
  if (n <= 5) return 'tt-coverage-balance--mid'
  return 'tt-coverage-balance--high'
}

function setReplacement(lesson, teacherId, fromAvailable = null) {
  lockModalInteraction()
  const id = Number(teacherId)
  const fromList = fromAvailable || lesson.available_teachers?.find((x) => x.teacher_id === id)
  const t = props.teachers.find((x) => x.id === id)
  if (!lesson.suggestion) lesson.suggestion = {}
  lesson.suggestion.replacement_teacher_id = id || null
  lesson.suggestion.replacement_teacher_name = fromList?.name ?? t?.name ?? null
  if (fromList?.match_percent != null) {
    lesson.suggestion.match_percent = fromList.match_percent
  }
  if (fromList?.reasons?.length || fromList?.priority_reasons?.length) {
    lesson.suggestion.reasons = fromList.reasons ?? fromList.priority_reasons
  }
  lesson.resolution = 'substitute'
}

function pickAvailableTeacher(lesson, teacher) {
  lockModalInteraction()
  setReplacement(lesson, teacher.teacher_id, teacher)
  expandedTeacherSelect.value[lesson.period_id] = true
}

async function onWizardAssign(lesson, teacher) {
  const teacherId = Number(teacher?.teacher_id)
  if (!teacherId) {
    toast.warning('تعذر التعيين — لم يُحدد معلم بديل')
    return
  }
  const target = localLessons.value.find((l) => l.period_id === lesson?.period_id) ?? lesson
  if (!target) {
    toast.warning('تعذر التعيين — الحصة غير موجودة في قائمة التغطية')
    return
  }
  pickAvailableTeacher(target, teacher)
  const periodLabel = target.period_number ? `الحصة ${target.period_number}` : 'الحصة'
  const name = target.suggestion?.replacement_teacher_name ?? teacher?.name ?? 'المعلم'
  toast.success(`تم تعيين ${name} لـ${periodLabel} (مسودة — اعتماد اليوم من أسفل النافذة)`)
  wizardRef.value?.notifyAssignSuccess?.(name, target.period_id)

  if (preview.value?.date) {
    try {
      const notifyResult = await notifySubstituteTeacher(preview.value.date, target)
      if (notifyResult?.success) {
        toast.info(`تم إشعار ${name} بتعيينه للحصة`)
      }
    } catch {
      /* إشعار اختياري — لا يوقف التعيين */
    }
  }
}

async function openDistributionReport() {
  if (!preview.value?.date) return
  reportOpenedAfterApprove.value = false
  showDistributionReport.value = true
  distributionReportLoading.value = true
  distributionReport.value = null
  try {
    distributionReport.value = await fetchDistributionReport(
      preview.value.date,
      localLessons.value
    )
  } catch (e) {
    toast.error(e.response?.data?.message || 'تعذر تحميل تقرير التوزيع')
    showDistributionReport.value = false
  } finally {
    distributionReportLoading.value = false
  }
}

function onWizardLock() {
  lockModalInteraction()
}

function openSwap(lesson) {
  swapLesson.value = lesson
  showSwapModal.value = true
}

async function handleCancel(lesson) {
  await cancelLesson(preview.value.date, lesson.period_id, lesson.adjustment?.id ?? null)
  syncLessons(preview.value)
}

async function onSwapApplied() {
  syncLessons(preview.value)
}

async function handleSaveDraft() {
  if (!preview.value) return
  savingDraft.value = true
  try {
    const wizardState = wizardRef.value?.exportState?.() ?? {}
    const data = await saveCoverageDraft(preview.value.date, localLessons.value, wizardState)
    syncLessons(data.preview ?? preview.value)
    if (wizardRef.value?.restoreState && data.preview?.coverage_draft_meta) {
      wizardRef.value.restoreState(data.preview.coverage_draft_meta)
    }
  } finally {
    savingDraft.value = false
  }
}

async function handleApprove() {
  if (!canApprove.value || !preview.value) return
  approving.value = true
  try {
    let result = null
    if (hasSubstituteToApprove.value) {
      result = await approveCoverage(preview.value.date, localLessons.value)
    }
    if (result?.distribution_report) {
      distributionReport.value = result.distribution_report
      reportOpenedAfterApprove.value = true
      showDistributionReport.value = true
      if (result.notifications_queued > 0) {
        toast.success(
          `تم الاعتماد — أُرسلت إشعارات لـ ${result.notifications_queued} معلم/معلمين`
        )
      }
    }
    emit('approved')
    if (!showDistributionReport.value) {
      closeModal('approve_complete')
    }
  } finally {
    approving.value = false
  }
}

function closeDistributionReport() {
  showDistributionReport.value = false
  if (reportOpenedAfterApprove.value) {
    reportOpenedAfterApprove.value = false
    closeModal('approve_complete')
  }
}

function onDemoSeeded(data) {
  const payload = data?.preview ?? preview.value
  if (payload) {
    syncLessons(payload)
  }
  emit('demo-seeded', data)
}

const showDemoEmptyState = computed(
  () =>
    preview.value &&
    preview.value.has_school_day !== false &&
    !(preview.value.absent_teachers?.length > 0)
)

</script>

<template>
  <Teleport to="body">
    <div
      v-if="show"
      class="tt-coverage-modal-root"
      dir="rtl"
      role="dialog"
      aria-modal="true"
      aria-labelledby="coverage-modal-title"
    >
      <div class="tt-coverage-modal__overlay" @click.self="closeModal('outside_click')">
        <div
          class="modal-dialog modal-xl modal-dialog-scrollable tt-coverage-modal__dialog"
          @click.stop
          @mousedown.stop="onDialogPointerDown"
        >
          <div class="modal-content tt-coverage-modal__content shadow-lg" @click.stop>
        <div class="modal-header tt-coverage-modal__header border-0">
          <div>
            <h5 id="coverage-modal-title" class="modal-title fw-bold mb-1">
              <i class="bi bi-person-x-fill ms-2"></i>
              مركز تغطية الغياب اليومية
            </h5>
            <p v-if="preview" class="small text-white-50 mb-0">
              {{ preview.day_name }} — {{ preview.date }}
              <span class="ms-2 badge bg-white bg-opacity-25">تبديل الحصص المؤقت</span>
            </p>
          </div>
          <button type="button" class="btn-close btn-close-white" @click="closeModal('close_button')"></button>
        </div>

        <div class="modal-body">
          <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-warning"></div>
          </div>

          <template v-else-if="preview">
            <div class="alert alert-info border-0 mb-4" @mousedown.stop="lockModalInteraction" @click.stop>
              <div class="d-flex flex-wrap justify-content-between gap-2">
                <strong>كيف توزّع بدون ظلم؟</strong>
                <Link :href="route('admin.settings.coverage')" class="small" target="_blank">
                  <i class="bi bi-gear ms-1"></i>تعديل الأولويات من الإعدادات
                </Link>
              </div>
              <ol class="small mb-2 ps-3 mt-2">
                <li>راجع المعلمين <strong>الغائبين</strong> وعدد حصصهم المتأثرة.</li>
                <li>لكل حصة: اختر من <strong>المتفرغين</strong> — مرتّبين حسب أولوياتك (مادة، قسم، مرحلة…).</li>
                <li>رصيد <span class="badge tt-coverage-balance--low">+1</span> أفضل من
                  <span class="badge tt-coverage-balance--high">+7</span> (تغطيات سابقة أقل).</li>
              </ol>
              <div v-if="preview.coverage_priority?.enabled_rules?.length" class="small">
                <span class="text-muted">أولويات مفعّلة:</span>
                <span
                  v-for="r in preview.coverage_priority.enabled_rules"
                  :key="r.key"
                  class="badge bg-light text-dark border ms-1 mb-1"
                >
                  {{ r.label }} ({{ r.weight }})
                </span>
              </div>
            </div>

            <section
              v-if="preview.coverage_roster?.length"
              class="mb-4 tt-coverage-roster-panel p-3"
              @mousedown.stop="lockModalInteraction"
              @click.stop
            >
              <h6 class="fw-bold mb-2">
                <i class="bi bi-bar-chart-steps ms-1"></i>
                رصيد التغطية — كل المعلمين
              </h6>
              <p class="small text-muted mb-2">مرتب من الأقل رصيداً (أنسب لتكليف جديد) إلى الأعلى.</p>
              <div class="d-flex flex-wrap gap-2">
                <span
                  v-for="st in preview.coverage_roster"
                  :key="st.teacher_id"
                  class="tt-coverage-roster-chip"
                  :title="`هذا الأسبوع: +${st.extra_this_week} — ${st.fairness_hint}`"
                  @mousedown.stop="lockModalInteraction"
                  @click.stop="lockModalInteraction"
                >
                  {{ st.name }}
                  <span class="badge ms-1" :class="balanceBadgeClass(st.coverage_balance)">
                    {{ st.balance_label }}
                  </span>
                </span>
              </div>
            </section>

            <div v-if="!preview.has_school_day" class="alert alert-warning">
              لا يوجد يوم دراسي في الجدول يطابق «{{ preview.day_name }}».
            </div>

            <CoverageDemoEmptyState
              v-if="showDemoEmptyState"
              class="mb-4"
              @seeded="onDemoSeeded"
              @mousedown.stop="lockModalInteraction"
              @click.stop
            />

            <DailyAbsenceCoverageWizard
              v-if="!showDemoEmptyState && preview.absent_teachers?.length"
              ref="wizardRef"
              :preview="preview"
              :local-lessons="localLessons"
              class="mb-2"
              @lock="onWizardLock"
              @assign="onWizardAssign"
              @open-swap="openSwap"
              @cancel-lesson="handleCancel"
              @mousedown.stop="lockModalInteraction"
              @click.stop
            />
          </template>
        </div>

        <div class="modal-footer border-0 tt-coverage-modal__footer d-flex flex-wrap gap-2 justify-content-between">
          <button type="button" class="btn btn-secondary-soft" @click="closeModal('close_button')">إغلاق</button>
          <div class="d-flex flex-wrap gap-2">
          <button
            type="button"
            class="btn btn-outline-primary"
            :disabled="distributionReportLoading || !localLessons.length"
            @click="openDistributionReport"
          >
            <span v-if="distributionReportLoading" class="spinner-border spinner-border-sm ms-2"></span>
            <i v-else class="bi bi-clipboard-data ms-1"></i>
            تقرير توزيع الاحتياط
          </button>
          <button
            type="button"
            class="btn btn-outline-warning"
            :disabled="savingDraft || loading || !localLessons.length"
            @click="handleSaveDraft"
          >
            <span v-if="savingDraft" class="spinner-border spinner-border-sm ms-2"></span>
            <i v-else class="bi bi-file-earmark-arrow-down ms-1"></i>
            حفظ كمسودة
          </button>
          <button
            type="button"
            class="btn tt-coverage-modal__btn-approve"
            :disabled="!canApprove || approving || loading"
            @click="handleApprove"
          >
            <span v-if="approving" class="spinner-border spinner-border-sm ms-2"></span>
            <i v-else class="bi bi-check2-circle ms-1"></i>
            اعتماد التغطية اليومية
          </button>
          </div>
        </div>
          </div>
        </div>
      </div>

      <TemporaryLessonSwapModal
        :show="showSwapModal"
        :lesson="swapLesson"
        :date="preview?.date"
        @close="showSwapModal = false"
        @applied="onSwapApplied"
      />

      <SubstituteDistributionReportModal
        :show="showDistributionReport"
        :report="distributionReport"
        :loading="distributionReportLoading"
        @close="closeDistributionReport"
      />
    </div>
  </Teleport>
</template>
