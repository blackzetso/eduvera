<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'

const props = defineProps({
  preview: { type: Object, required: true },
  localLessons: { type: Array, default: () => [] },
})

const emit = defineEmits([
  'lock',
  'assign',
  'open-swap',
  'cancel-lesson',
])

const assignFeedback = ref(null)
const replacementBlockRef = ref(null)
let assignFeedbackTimer = null

defineExpose({
  exportState() {
    return {
      wizard_step: wizardStep.value,
      selected_teacher_id: selectedTeacherId.value,
      selected_period_id: selectedLesson.value?.period_id ?? null,
    }
  },
  restoreState(meta) {
    restoreFromDraftMeta(meta)
  },
  notifyAssignSuccess(teacherName, periodId) {
    assignFeedback.value = `تم تعيين ${teacherName} لهذه الحصة — يمكنك متابعة باقي الحصص أو اعتماد التغطية اليومية من أسفل النافذة.`
    if (assignFeedbackTimer) clearTimeout(assignFeedbackTimer)
    assignFeedbackTimer = setTimeout(() => {
      assignFeedback.value = null
      assignFeedbackTimer = null
    }, 8000)
    if (selectedLesson.value?.period_id === periodId) {
      const fresh = lessonForPeriod(periodId)
      if (fresh) selectedLesson.value = fresh
    }
    nextTick(() => {
      replacementBlockRef.value?.scrollIntoView?.({ behavior: 'smooth', block: 'nearest' })
    })
  },
})

const wizardStep = ref('overview')
const selectedTeacherId = ref(null)
const selectedLesson = ref(null)
const candidatesListRef = ref(null)

const selectedTeacher = computed(() =>
  props.preview.absent_teachers?.find((t) => t.teacher_id === selectedTeacherId.value) ?? null
)

function teacherSchedule(teacherId) {
  const schedules = props.preview.teacher_schedules ?? {}
  return schedules[teacherId] ?? schedules[String(teacherId)] ?? { slots: [] }
}

function lessonForPeriod(periodId) {
  if (!periodId) return null
  return props.localLessons.find((l) => l.period_id === periodId) ?? null
}

function lock() {
  emit('lock')
}

function balanceBadgeClass(balance) {
  const n = Number(balance) || 0
  if (n <= 2) return 'tt-coverage-balance--low ev-badge--balance-low'
  if (n <= 5) return 'tt-coverage-balance--mid ev-badge--balance-mid'
  return 'tt-coverage-balance--high ev-badge--balance-high'
}

function statusIcon(key) {
  if (key === 'covered') return '🟢'
  if (key === 'pending') return '🟡'
  if (key === 'uncovered') return '🔴'
  return '⚪'
}

function selectAbsentTeacher(teacher) {
  lock()
  selectedTeacherId.value = teacher.teacher_id
  selectedLesson.value = null
  wizardStep.value = 'schedule'
}

function selectSlot(slot) {
  lock()
  if (slot.is_free || !slot.period_id) return
  const lesson = lessonForPeriod(slot.period_id)
  if (!lesson) return
  selectedLesson.value = lesson
  assignFeedback.value = null
  wizardStep.value = 'period'
}

function liveSlot(slot) {
  if (!slot?.period_id) return slot
  const lesson = lessonForPeriod(slot.period_id)
  if (!lesson) return slot
  if (lesson.adjustment?.id) {
    return {
      ...slot,
      coverage_status: 'covered',
      coverage_status_label: lesson.adjustment.swap_type_label ?? 'تبديل مؤقت',
    }
  }
  if (lesson.suggestion?.replacement_teacher_id) {
    const name = lesson.suggestion.replacement_teacher_name ?? 'بديل'
    return {
      ...slot,
      coverage_status: 'pending',
      coverage_status_label: `بديل: ${name}`,
    }
  }
  if (lesson.cancelled) {
    return { ...slot, coverage_status: 'covered', coverage_status_label: 'ملغاة' }
  }
  return slot
}

function goOverview() {
  lock()
  wizardStep.value = 'overview'
  selectedTeacherId.value = null
  selectedLesson.value = null
}

function goSchedule() {
  lock()
  wizardStep.value = 'schedule'
  selectedLesson.value = null
}

function goSummary() {
  lock()
  wizardStep.value = 'summary'
}

const candidatesByTier = computed(() => {
  const lesson = selectedLesson.value
  if (!lesson?.available_teachers?.length) return []
  const tiers = [1, 2, 3, 4]
  return tiers
    .map((tier) => ({
      tier,
      label: lesson.available_teachers.find((t) => t.priority_tier === tier)?.priority_tier_label
        ?? { 1: 'نفس المادة + المرحلة', 2: 'نفس المادة', 3: 'نفس القسم', 4: 'معلم متاح' }[tier],
      teachers: lesson.available_teachers.filter((t) => (t.priority_tier ?? 4) === tier),
    }))
    .filter((g) => g.teachers.length > 0)
})

const liveReport = computed(() => {
  const lessons = props.localLessons
  const total = lessons.length
  const covered = lessons.filter((l) => {
    if (l.adjustment?.id || l.resolution === 'temporary_swap') return true
    if (l.status === 'approved') return true
    return !!l.suggestion?.replacement_teacher_id
  }).length
  const uncovered = Math.max(0, total - covered)

  const loads = {}
  for (const l of lessons) {
    const tid = l.suggestion?.replacement_teacher_id
    if (tid) loads[tid] = (loads[tid] ?? 0) + 1
  }

  const distribution = Object.entries(loads)
    .map(([teacherId, count]) => {
      const id = Number(teacherId)
      const roster = props.preview.coverage_roster?.find((r) => r.teacher_id === id)
      const fromLesson = lessons
        .flatMap((l) => l.available_teachers ?? [])
        .find((x) => x.teacher_id === id)
      const balance = roster?.coverage_balance ?? fromLesson?.coverage_balance ?? 0
      return {
        teacher_id: id,
        teacher_name: roster?.name ?? fromLesson?.name ?? '—',
        coverage_count: count,
        balance_label: roster?.balance_label ?? fromLesson?.balance_label ?? `+${balance}`,
        coverage_balance: balance,
      }
    })
    .sort((a, b) => b.coverage_count - a.coverage_count)

  const backend = props.preview.coverage_report ?? {}
  const affectedTotal = total || backend.affected_total || 0
  const coveredCount = covered || backend.covered_count || 0
  const completionPercent =
    affectedTotal > 0
      ? Math.round((coveredCount / affectedTotal) * 100)
      : backend.completion_percent ?? props.preview.summary?.completion_percent ?? 0

  return {
    affected_total: affectedTotal,
    covered_count: coveredCount,
    uncovered_count: uncovered ?? backend.uncovered_count ?? 0,
    completion_percent: completionPercent,
    distribution: distribution.length ? distribution : backend.distribution ?? [],
    most_loaded: distribution[0] ?? backend.most_loaded,
    least_loaded: distribution.length ? distribution[distribution.length - 1] : backend.least_loaded,
  }
})

const draftBannerText = computed(() => {
  const meta = props.preview.coverage_draft_meta
  if (!meta) return null
  return meta.banner_text
    ?? (meta.saved_at_label ? `مسودة محفوظة بتاريخ ${meta.saved_at_label}` : null)
})

function restoreFromDraftMeta(meta) {
  if (!meta) return
  if (meta.selected_teacher_id) {
    selectedTeacherId.value = Number(meta.selected_teacher_id)
  }
  const step = meta.wizard_step
  if (step && ['overview', 'schedule', 'period', 'summary'].includes(step)) {
    wizardStep.value = step
  }
  if (meta.selected_period_id) {
    const lesson = lessonForPeriod(Number(meta.selected_period_id))
    if (lesson) {
      selectedLesson.value = lesson
    }
  }
}

const autoRecommendation = computed(() => {
  const lesson = selectedLesson.value
  if (!lesson) return null
  return (
    lesson.suggestion?.auto_recommendation
    ?? lesson.available_teachers?.find((t) => t.is_auto_recommendation)
    ?? lesson.available_teachers?.[0]
    ?? null
  )
})

const departmentInsights = computed(
  () => props.preview.coverage_report?.department_insights ?? null
)

function impactRiskClass(level) {
  if (level === 'high') return 'tt-coverage-impact--high'
  if (level === 'mid') return 'tt-coverage-impact--mid'
  return 'tt-coverage-impact--low'
}

const operationalRecommendation = computed(() => autoRecommendation.value)

const isOpsRecommendationAssigned = computed(() => {
  const lesson = selectedLesson.value
  const rec = operationalRecommendation.value
  if (!lesson?.suggestion?.replacement_teacher_id || !rec?.teacher_id) return false
  return Number(lesson.suggestion.replacement_teacher_id) === Number(rec.teacher_id)
})

function focusCandidateList() {
  lock()
  candidatesListRef.value?.scrollIntoView?.({ behavior: 'smooth', block: 'start' })
}

function assignCandidate(teacher) {
  lock()
  if (!selectedLesson.value) return
  emit('assign', selectedLesson.value, teacher)
}

function applyAutoRecommendation() {
  lock()
  const auto = autoRecommendation.value
  if (!selectedLesson.value) return
  if (!auto?.teacher_id) {
    assignFeedback.value = 'لا يوجد ترشيح تلقائي لهذه الحصة — اختر معلماً من القائمة أدناه.'
    return
  }
  emit('assign', selectedLesson.value, auto)
}

function slotLabel(slot) {
  if (slot.is_free) {
    return `الحصة ${slot.period_number} — فراغ`
  }
  return `الحصة ${slot.period_number} — ${slot.subject_name} — ${slot.class_name}`
}

const stepLabels = {
  overview: 'اختيار المعلم',
  schedule: 'جدول اليوم',
  period: 'مرشحو التغطية',
  summary: 'ملخص التغطية',
}

watch(
  () => props.localLessons,
  () => {
    if (!selectedLesson.value?.period_id) return
    const fresh = lessonForPeriod(selectedLesson.value.period_id)
    if (fresh) selectedLesson.value = fresh
  },
  { deep: true }
)

watch(
  () => props.preview?.coverage_draft_meta,
  (meta) => {
    if (meta) restoreFromDraftMeta(meta)
  },
  { immediate: true }
)

onMounted(() => {
  if (props.preview?.coverage_draft_meta) {
    restoreFromDraftMeta(props.preview.coverage_draft_meta)
  }
})
</script>

<template>
  <div class="tt-coverage-wizard" dir="rtl">
    <div
      v-if="preview.has_saved_draft && draftBannerText"
      class="alert alert-secondary border mb-3 tt-coverage-wizard__draft-banner small mb-0"
    >
      <i class="bi bi-file-earmark-text ms-1"></i>
      {{ draftBannerText }}
    </div>

    <div class="tt-coverage-wizard__completion mb-3">
      <div class="d-flex justify-content-between align-items-center mb-1">
        <span class="small fw-bold">نسبة إكمال التغطية</span>
        <span class="fw-bold text-primary">{{ liveReport.completion_percent }}% مكتمل</span>
      </div>
      <div class="progress tt-coverage-wizard__completion-bar" style="height: 0.55rem">
        <div
          class="progress-bar bg-success"
          role="progressbar"
          :style="{ width: `${liveReport.completion_percent}%` }"
          :aria-valuenow="liveReport.completion_percent"
          aria-valuemin="0"
          aria-valuemax="100"
        ></div>
      </div>
    </div>

    <nav class="tt-coverage-wizard__steps mb-3" aria-label="خطوات التغطية">
      <button
        type="button"
        class="tt-coverage-wizard__step"
        :class="{ active: wizardStep === 'overview' }"
        @click.stop="goOverview"
      >
        ١. {{ stepLabels.overview }}
      </button>
      <button
        type="button"
        class="tt-coverage-wizard__step"
        :class="{ active: wizardStep === 'schedule', disabled: !selectedTeacherId }"
        :disabled="!selectedTeacherId"
        @click.stop="goSchedule"
      >
        ٢. {{ stepLabels.schedule }}
      </button>
      <button
        type="button"
        class="tt-coverage-wizard__step"
        :class="{ active: wizardStep === 'period', disabled: !selectedLesson }"
        :disabled="!selectedLesson"
      >
        ٣. {{ stepLabels.period }}
      </button>
      <button
        type="button"
        class="tt-coverage-wizard__step"
        :class="{ active: wizardStep === 'summary' }"
        @click.stop="goSummary"
      >
        ٤. {{ stepLabels.summary }}
      </button>
    </nav>

    <!-- Step 1: Absent teachers -->
    <section v-if="wizardStep === 'overview'" class="tt-coverage-wizard__panel">
      <h6 class="fw-bold mb-3">المدرسون الغائبون — اختر معلماً لعرض جدوله</h6>
      <div class="row g-3">
        <div
          v-for="t in preview.absent_teachers"
          :key="t.teacher_id"
          class="col-md-6"
        >
          <button
            type="button"
            class="ev-card tt-coverage-wizard__absent-btn w-100 text-end p-3"
            @click.stop="selectAbsentTeacher(t)"
          >
            <div class="fw-bold">{{ t.name }}</div>
            <div class="small text-muted">{{ t.specialization || t.department || '—' }}</div>
            <div class="mt-2">
              <span class="ev-chip ev-chip--danger">{{ t.affected_count ?? 0 }} حصة متأثرة اليوم</span>
            </div>
            <span class="ev-action-btn ev-action-btn--sm mt-2 d-inline-flex">
              <i class="bi bi-calendar3 ms-1"></i>
              عرض الجدول
            </span>
          </button>
        </div>
      </div>
      <div v-if="!preview.absent_teachers?.length" class="text-muted small">لا يوجد معلمون غائبون اليوم.</div>
    </section>

    <!-- Step 2: Teacher schedule -->
    <section v-else-if="wizardStep === 'schedule' && selectedTeacher" class="tt-coverage-wizard__panel">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" @click.stop="goOverview">
            <i class="bi bi-arrow-right ms-1"></i>
            العودة للقائمة
          </button>
          <h6 class="fw-bold mb-0 mt-1">{{ selectedTeacher.name }}</h6>
          <p class="small text-muted mb-0">{{ selectedTeacher.specialization }}</p>
        </div>
        <span class="ev-badge tt-coverage-badge--absent">غائب اليوم</span>
      </div>

      <p class="small text-muted mb-2">
        🔴 غير مغطاة · 🟡 قيد التغطية · 🟢 تمت التغطية — اضغط على حصة متأثرة لاختيار البديل
      </p>

      <div class="tt-coverage-wizard__schedule-list">
        <button
          v-for="(slot, idx) in teacherSchedule(selectedTeacher.teacher_id).slots"
          :key="`${slot.period_number}-${idx}`"
          type="button"
          class="ev-card tt-coverage-wizard__slot-row w-100 text-end mb-2 p-3"
          :class="{
            'tt-coverage-wizard__slot-row--free': slot.is_free,
            'tt-coverage-wizard__slot-row--clickable': !slot.is_free && slot.period_id,
            'tt-coverage-wizard__slot-row--active': selectedLesson?.period_id === slot.period_id,
          }"
          :disabled="slot.is_free || !slot.period_id"
          @click.stop="selectSlot(slot)"
        >
          <div class="d-flex justify-content-between align-items-start gap-2">
            <span class="tt-coverage-wizard__slot-status">{{ statusIcon(liveSlot(slot).coverage_status) }}</span>
            <div class="flex-grow-1">
              <div class="fw-semibold">{{ slotLabel(slot) }}</div>
              <div v-if="!slot.is_free" class="small text-muted">
                {{ slot.time_from }} – {{ slot.time_to }}
                <span class="ms-2">{{ liveSlot(slot).coverage_status_label }}</span>
              </div>
            </div>
          </div>
        </button>
      </div>

      <button type="button" class="btn btn-outline-secondary btn-sm mt-2" @click.stop="goSummary">
        <i class="bi bi-clipboard-data ms-1"></i>
        عرض ملخص التغطية
      </button>
    </section>

    <!-- Step 3: Candidates -->
    <section v-else-if="wizardStep === 'period' && selectedLesson" class="tt-coverage-wizard__panel">
      <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 mb-2" @click.stop="goSchedule">
        <i class="bi bi-arrow-right ms-1"></i>
        العودة لجدول {{ selectedTeacher?.name }}
      </button>

      <div class="ev-card p-3 mb-3">
        <div class="fw-bold">
          الحصة {{ selectedLesson.period_number }} — {{ selectedLesson.subject_name }}
        </div>
        <div class="small text-muted">
          {{ selectedLesson.class_name }} · {{ selectedLesson.time_from }} – {{ selectedLesson.time_to }}
        </div>
        <div class="small text-danger mt-1">الأصلي: {{ selectedLesson.absent_teacher_name }}</div>

        <div v-if="selectedLesson.adjustment" class="tt-coverage-swap-applied mt-2 p-2 small">
          <strong>تبديل مؤقت:</strong> {{ selectedLesson.adjustment.swap_type_label }}
        </div>
      </div>

      <template v-if="!selectedLesson.adjustment">
        <div
          v-if="assignFeedback"
          class="alert alert-success small mb-3 tt-coverage-wizard__assign-feedback"
          role="status"
        >
          <i class="bi bi-check-circle-fill ms-1"></i>
          {{ assignFeedback }}
        </div>

        <div
          v-if="operationalRecommendation"
          class="ev-card tt-coverage-wizard__ops-banner p-3 mb-3"
          :class="{ 'tt-coverage-wizard__ops-banner--assigned': isOpsRecommendationAssigned }"
        >
          <div class="tt-coverage-wizard__ops-banner__title mb-2">
            <span class="me-1">{{ isOpsRecommendationAssigned ? '✅' : '🏆' }}</span>
            <strong>{{ isOpsRecommendationAssigned ? 'تم تعيين الترشيح' : 'التوصية التشغيلية' }}</strong>
          </div>
          <p class="small mb-2 mb-0">
            يوصى بتعيين:
            <strong class="text-primary">{{ operationalRecommendation.name }}</strong>
          </p>
          <div class="small mb-2">
            <span class="text-muted">درجة الترشيح:</span>
            <strong class="ms-1">{{ operationalRecommendation.match_percent }}%</strong>
          </div>
          <div v-if="operationalRecommendation.recommendation_explanation?.length" class="mb-2">
            <div class="small fw-semibold text-muted mb-1">السبب:</div>
            <ul class="tt-coverage-wizard__explain list-unstyled small mb-0">
              <li
                v-for="(line, oi) in operationalRecommendation.recommendation_explanation.filter((l) => l.ok)"
                :key="oi"
                class="text-success"
              >
                {{ line.text }}
              </li>
              <li
                v-if="operationalRecommendation.from_department_plan"
                class="text-success"
              >
                ✓ من خطة القسم
              </li>
            </ul>
          </div>
          <div
            v-if="operationalRecommendation.coverage_impact"
            class="small mb-2"
          >
            <span class="text-muted">مستوى المخاطرة:</span>
            <span
              class="badge ms-1"
              :class="impactRiskClass(operationalRecommendation.coverage_impact.risk_level)"
            >
              {{ operationalRecommendation.coverage_impact.risk_label }}
            </span>
          </div>
          <div
            v-if="operationalRecommendation.coverage_impact"
            class="tt-coverage-wizard__ops-impact small p-2 mb-3"
            :class="impactRiskClass(operationalRecommendation.coverage_impact.risk_level)"
          >
            <div class="fw-semibold mb-1">معاينة أثر التغطية</div>
            <div v-if="operationalRecommendation.coverage_impact.workload_before_label">
              الحمل الحالي: {{ operationalRecommendation.coverage_impact.workload_before_label }}
            </div>
            <div v-if="operationalRecommendation.coverage_impact.workload_after_label">
              بعد التغطية: {{ operationalRecommendation.coverage_impact.workload_after_label }}
            </div>
            <div v-if="operationalRecommendation.coverage_impact.balance_transition">
              رصيد التغطية: {{ operationalRecommendation.coverage_impact.balance_transition }}
            </div>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <button
              v-if="!isOpsRecommendationAssigned"
              type="button"
              class="ev-action-btn ev-action-btn--sm"
              @click.stop="applyAutoRecommendation"
            >
              <i class="bi bi-person-check ms-1"></i>
              تعيين الترشيح
            </button>
            <span
              v-else
              class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2"
            >
              <i class="bi bi-check-lg ms-1"></i>
              مُعيَّن لهذه الحصة
            </span>
            <button
              type="button"
              class="btn btn-sm btn-outline-secondary"
              @click.stop="focusCandidateList"
            >
              اختيار مدرس آخر
            </button>
          </div>
          <p class="small text-muted mb-0 mt-2">
            التعيين هنا يحفظ الاختيار في المسودة فقط — الاعتماد النهائي لليوم من زر «اعتماد التغطية اليومية» في أسفل النافذة.
          </p>
        </div>

        <div v-if="candidatesByTier.length" ref="candidatesListRef" class="mb-3">
          <div v-for="group in candidatesByTier" :key="group.tier" class="mb-3">
            <div class="small fw-bold text-muted mb-2">
              أولوية {{ group.tier }} — {{ group.label }}
            </div>
            <div
              v-for="t in group.teachers"
              :key="t.teacher_id"
              class="ev-card tt-coverage-wizard__candidate p-3 mb-2"
              :class="{ 'tt-coverage-wizard__candidate--selected': selectedLesson.suggestion?.replacement_teacher_id === t.teacher_id }"
            >
              <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                <div>
                  <div class="fw-bold">
                    {{ t.name }}
                    <span
                      v-if="t.is_auto_recommendation"
                      class="ev-badge tt-coverage-badge--auto ms-1"
                    >
                      الترشيح التلقائي
                    </span>
                  </div>
                  <div class="small text-muted">{{ t.priority_tier_label }}</div>
                  <span
                    v-if="t.from_department_plan"
                    class="ev-badge ev-badge--dept-plan mt-1"
                  >
                    مرشح من خطة القسم
                  </span>
                  <div v-if="t.priority_reasons?.length" class="small text-success mt-1">
                    {{ t.priority_reasons.slice(0, 3).join(' · ') }}
                  </div>
                </div>
                <div class="text-end">
                  <div class="small fw-semibold text-dark">{{ t.workload_label || '—' }}</div>
                  <div class="small text-muted">{{ t.coverage_balance_label || `رصيد التغطية ${t.balance_label}` }}</div>
                  <span class="badge mt-1" :class="balanceBadgeClass(t.coverage_balance)">
                    {{ t.balance_label }}
                  </span>
                  <div class="small text-muted mt-1">{{ t.match_percent }}% تطابق</div>
                </div>
              </div>
              <ul
                v-if="t.recommendation_explanation?.length"
                class="tt-coverage-wizard__explain list-unstyled small mb-2 mt-2"
              >
                <li
                  v-for="(line, ei) in t.recommendation_explanation"
                  :key="ei"
                  :class="line.ok ? 'text-success' : 'text-muted'"
                >
                  {{ line.text }}
                </li>
              </ul>
              <div
                v-if="t.coverage_impact"
                class="tt-coverage-wizard__impact small p-2 mb-2"
                :class="impactRiskClass(t.coverage_impact.risk_level)"
              >
                <div class="fw-semibold mb-1">
                  <i class="bi bi-graph-up-arrow ms-1"></i>
                  أثر التغطية
                  <span v-if="t.coverage_impact.risk_label" class="badge ms-1">
                    {{ t.coverage_impact.risk_label }}
                  </span>
                </div>
                <div v-if="t.coverage_impact.workload_before_label">
                  الحمل الحالي: {{ t.coverage_impact.workload_before_label }}
                </div>
                <div v-if="t.coverage_impact.workload_after_label">
                  بعد التغطية: {{ t.coverage_impact.workload_after_label }}
                </div>
                <div v-if="t.coverage_impact.balance_transition">
                  رصيد التغطية: {{ t.coverage_impact.balance_transition }}
                </div>
              </div>
              <div
                v-if="t.coverage_history_summary || t.coverage_history?.length"
                class="tt-coverage-wizard__history small text-muted mb-2"
              >
                <div class="fw-semibold text-dark mb-1">
                  <i class="bi bi-clock-history ms-1"></i>
                  سجل التغطية
                </div>
                <div
                  v-if="t.coverage_history_summary"
                  class="tt-coverage-wizard__history-summary mb-2 p-2"
                >
                  <div v-if="t.coverage_history_summary.week_count != null">
                    التغطيات هذا الأسبوع:
                    <strong>{{ t.coverage_history_summary.week_count }}</strong>
                  </div>
                  <div v-if="t.coverage_history_summary.month_count != null">
                    التغطيات هذا الشهر:
                    <strong>{{ t.coverage_history_summary.month_count }}</strong>
                  </div>
                  <div v-if="t.coverage_history_summary.last_coverage_day_label">
                    آخر تغطية:
                    <strong>{{ t.coverage_history_summary.last_coverage_day_label }}</strong>
                  </div>
                </div>
                <div v-for="(h, hi) in t.coverage_history" :key="hi">
                  {{ h.line_label || `${h.day_label || h.date} – ${h.subject_name || '—'} – ${h.match_score}%` }}
                </div>
              </div>
              <button
                type="button"
                class="ev-action-btn ev-action-btn--sm mt-2"
                @click.stop="assignCandidate(t)"
              >
                <i class="bi bi-person-check ms-1"></i>
                تعيين للتغطية
              </button>
            </div>
          </div>
        </div>
        <div v-else class="alert alert-warning small">
          لا يوجد معلم متفرغ في هذه الحصة وفق قواعد التعارض الحالية.
        </div>

        <div v-if="selectedLesson.busy_teachers?.length" class="small text-muted mb-3">
          مشغولون:
          <span v-for="(b, bi) in selectedLesson.busy_teachers" :key="b.teacher_id">
            {{ b.name }}
            <span class="badge tt-coverage-balance--mid">{{ b.balance_label }}</span>{{ bi < selectedLesson.busy_teachers.length - 1 ? '، ' : '' }}
          </span>
        </div>

        <div class="d-flex flex-wrap gap-2">
          <button
            type="button"
            class="btn btn-outline-warning btn-sm"
            :disabled="!!selectedLesson.adjustment"
            @click.stop="emit('open-swap', selectedLesson)"
          >
            <i class="bi bi-arrow-left-right ms-1"></i>
            تبديل حصة
          </button>
          <button
            type="button"
            class="btn btn-outline-secondary btn-sm"
            @click.stop="emit('cancel-lesson', selectedLesson)"
          >
            إلغاء الحصة
          </button>
        </div>

        <div
          v-if="selectedLesson.suggestion?.replacement_teacher_id"
          ref="replacementBlockRef"
          class="tt-coverage-replacement mt-3 p-3 rounded"
        >
          <span class="ev-badge tt-coverage-badge--replace mb-1">البديل المختار</span>
          <div class="fw-bold">{{ selectedLesson.suggestion.replacement_teacher_name }}</div>
          <div class="small text-success">التطابق: {{ selectedLesson.suggestion.match_percent ?? 0 }}%</div>
        </div>
      </template>
    </section>

    <!-- Step 4: Summary -->
    <section v-else-if="wizardStep === 'summary'" class="tt-coverage-wizard__panel">
      <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 mb-3" @click.stop="goOverview">
        <i class="bi bi-arrow-right ms-1"></i>
        العودة لبداية المعالج
      </button>

      <div class="ev-card tt-coverage-wizard__completion-card p-3 mb-4 text-center">
        <div class="display-5 fw-bold text-primary mb-0">{{ liveReport.completion_percent }}%</div>
        <div class="small text-muted">مكتمل — {{ liveReport.covered_count }} من {{ liveReport.affected_total }} حصة</div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <div class="ev-card tt-coverage-stat-card p-3 text-center">
            <div class="display-6 fw-bold text-danger">{{ liveReport.affected_total }}</div>
            <div class="small text-muted">حصص متأثرة</div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="ev-card tt-coverage-stat-card p-3 text-center">
            <div class="display-6 fw-bold text-success">{{ liveReport.covered_count }}</div>
            <div class="small text-muted">حصص مغطاة</div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="ev-card tt-coverage-stat-card p-3 text-center">
            <div class="display-6 fw-bold text-warning">{{ liveReport.uncovered_count }}</div>
            <div class="small text-muted">حصص غير مغطاة</div>
          </div>
        </div>
      </div>

      <h6 class="fw-bold mb-2">
        <i class="bi bi-clipboard-data ms-1"></i>
        تقرير توزيع الاحتياط لليوم
      </h6>
      <p class="small text-muted mb-2">
        يعرض عدد حصص الاحتياط لكل معلم بديل. يمكن فتح نسخة مفصّلة من زر «تقرير توزيع الاحتياط» أسفل النافذة.
      </p>
      <div v-if="liveReport.distribution?.length" class="table-responsive mb-3">
        <table class="table table-sm tt-coverage-plan-table">
          <thead>
            <tr>
              <th>المعلم</th>
              <th>عدد التغطيات</th>
              <th>الرصيد</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in liveReport.distribution" :key="row.teacher_id">
              <td>{{ row.teacher_name }}</td>
              <td class="fw-semibold">{{ row.coverage_count }}</td>
              <td>
                <span class="badge" :class="balanceBadgeClass(row.coverage_balance)">
                  {{ row.balance_label }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else class="small text-muted">لم يُعيَّن بدلاء بعد.</p>

      <section v-if="departmentInsights?.insights?.length" class="mb-4">
        <h6 class="fw-bold mb-2">
          <i class="bi bi-diagram-3 ms-1"></i>
          رؤى خطة القسم
        </h6>
        <div
          v-for="(item, di) in departmentInsights.insights"
          :key="di"
          class="ev-card p-2 mb-2 small d-flex align-items-start gap-2"
        >
          <i :class="[item.icon, 'text-primary']"></i>
          <span>{{ item.text }}</span>
        </div>
      </section>

      <div v-if="liveReport.most_loaded" class="row g-2 small">
        <div class="col-md-6">
          <div class="ev-card p-2">
            <span class="text-muted">الأكثر تحميلاً:</span>
            <strong>{{ liveReport.most_loaded.teacher_name }}</strong>
            ({{ liveReport.most_loaded.coverage_count }} حصة)
          </div>
        </div>
        <div v-if="liveReport.least_loaded && liveReport.distribution?.length > 1" class="col-md-6">
          <div class="ev-card p-2">
            <span class="text-muted">الأقل تحميلاً:</span>
            <strong>{{ liveReport.least_loaded.teacher_name }}</strong>
            ({{ liveReport.least_loaded.coverage_count }} حصة)
          </div>
        </div>
      </div>

      <section v-if="preview.coverage_plan?.length" class="mt-4">
        <h6 class="fw-bold mb-2">جدول التغطية المؤقت (اليوم فقط)</h6>
        <div class="table-responsive">
          <table class="table table-sm tt-coverage-plan-table">
            <thead>
              <tr>
                <th>الحصة</th>
                <th>الوقت</th>
                <th>المادة</th>
                <th>الأصلي</th>
                <th>البديل</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, i) in preview.coverage_plan" :key="i">
                <td>{{ row.period_number }}</td>
                <td>{{ row.time }}</td>
                <td>{{ row.subject }}</td>
                <td class="text-danger">{{ row.original_teacher }}</td>
                <td class="text-primary fw-semibold">{{ row.replacement_teacher }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </section>
  </div>
</template>
