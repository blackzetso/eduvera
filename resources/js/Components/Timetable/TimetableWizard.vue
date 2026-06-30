<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AdminAppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import TeacherAppLayout from '@/Pages/Teacher/Layout/App.vue'
import CategoryOptions from '@/Pages/Admin/theme1/Categories/CategoryOptions.vue'
import CalendarView from '@/Pages/Admin/theme1/Timetables/CalendarView.vue'
import TimetableSetupWizardModal from '@/Components/Timetable/TimetableSetupWizardModal.vue'
import TeacherLoadDistributionModal from '@/Components/Timetable/TeacherLoadDistributionModal.vue'
import DailyAbsenceCoverageModal from '@/Components/Timetable/DailyAbsenceCoverageModal.vue'
import CoverageDemoEmptyState from '@/Components/Timetable/CoverageDemoEmptyState.vue'
import { useTimetableWizard } from '@/composables/useTimetableWizard'
import { toast } from 'vue3-toastify'
import Swal from 'sweetalert2'
import '../../../css/timetable-wizard.css'

const props = defineProps({
  timetable: Object,
  categories: Array,
  teachers: Array,
  subjects: Array,
  initialStep: { type: Number, default: 1 },
  dailyCoverageSummary: { type: Object, default: () => ({ absent_count: 0 }) },
  teacherAttendanceStatuses: { type: Object, default: () => ({}) },
  assignmentContext: { type: Object, default: null },
  wizardMode: { type: String, default: 'full' },
  departmentNeedsSummary: { type: Array, default: () => [] },
})

const isTeacherAssignMode = computed(() => props.wizardMode === 'teacher_assign')
const layoutComponent = computed(() =>
  isTeacherAssignMode.value ? TeacherAppLayout : AdminAppLayout
)

const showClassicGrid = ref(false)
const showAssignModal = ref(false)
const showConflictPanel = ref(false)
const showSetupModal = ref(false)
const showDistributionModal = ref(false)
const showCoverageModal = ref(false)
const coverageSummary = ref({ ...props.dailyCoverageSummary })
const setupModalRef = ref(null)
const selectedPeriod = ref(null)

const assignForm = useForm({
  timetable_period_id: null,
  teacher_id: null,
  subject_id: null,
  type: 'main',
})

const w = useTimetableWizard(() => props)

const {
  currentStep,
  processing,
  viewMode,
  wizardMeta,
  periodTemplates,
  subjectRequirements,
  teacherBySubject,
  form,
  steps,
  totalRequiredPeriods,
  remainingPeriods,
  periodsPerWeekCapacity,
  filteredPeriods,
  gridPeriods,
  isTimetableEmpty,
  timetableStatus,
  conflictWarnings,
  conflictCount,
  validationWarnings,
  aiInsights,
  teacherLoads,
  subjectColor,
  getMainAssignment,
  addPeriodTemplate,
  removePeriodTemplate,
  addSubjectRequirement,
  applyPeriodStructure,
  saveBasicInfo,
  autoGenerateTimetable,
  regenerateTimetable,
  applySetupFramework,
  syncWizardFromSetup,
  optimizeSchedule,
  resolveConflictsAuto,
  groupedConflicts,
  validationErrors,
  lastGenerationReport,
  highlightPeriodId,
  getRegenerateImpactStats,
  createBackupBeforeRegenerate,
  listLocalTimetableBackups,
  copyFromBackup,
  nextStep,
  prevStep,
  goToStep,
  getTeacherLoadDistribution,
  getDistributionPriorityRules,
  hasApprovedTeacherDistribution,
  enrichTeachersForDistribution,
  applyTeacherLoadDistribution,
  getTimetableSettings,
} = w

const showCopyModal = ref(false)
const localBackups = ref([])

const kindLabels = {
  lesson: 'حصة',
  break: 'استراحة',
  assembly: 'طابور',
  prayer: 'صلاة',
}

async function handleStep1Next() {
  try {
    await saveBasicInfo()
    toast.success('تم حفظ البيانات الأساسية')
    nextStep()
  } catch {
    toast.error('تعذر حفظ البيانات')
  }
}

async function handleStep2Next() {
  const result = await Swal.fire({
    title: 'تطبيق هيكل الحصص؟',
    text: 'سيتم إنشاء الحصص لجميع أيام الأسبوع المحددة.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'تطبيق',
    cancelButtonText: 'تخطي',
  })

  if (result.isConfirmed) {
    try {
      await applyPeriodStructure()
      toast.success('تم تطبيق هيكل الحصص')
    } catch {
      toast.error('حدث خطأ أثناء إنشاء الحصص')
    }
  }
  nextStep()
}

async function openAutoGenerateWizard() {
  if (!isTimetableEmpty.value) {
    const stats = getRegenerateImpactStats()
    const result = await Swal.fire({
      title: '⚠️ يوجد جدول حالي',
      html: `
        <p class="small text-start mb-2">سيؤدي إنشاء جدول جديد إلى:</p>
        <ul class="small text-start">
          <li>حذف ${stats.periods} حصة</li>
          <li>حذف ${stats.subjects} مادة مرتبطة</li>
          <li>حذف ${stats.teachers} تعيينات معلمين</li>
        </ul>
      `,
      icon: 'warning',
      showCancelButton: true,
      showDenyButton: true,
      confirmButtonText: 'متابعة',
      denyButtonText: 'إنشاء نسخة احتياطية أولاً',
      cancelButtonText: 'إلغاء',
    })
    if (result.isDismissed) return
    if (result.isDenied) {
      await createBackupBeforeRegenerate()
      toast.success('تم حفظ نسخة احتياطية')
    }
  }
  showSetupModal.value = true
}

function openCopyModal() {
  localBackups.value = listLocalTimetableBackups()
  showCopyModal.value = true
}

async function applyCopyBackup(backup) {
  const ok = await copyFromBackup(backup)
  showCopyModal.value = false
  if (ok) {
    toast.success('تم نسخ الجدول من النسخة الاحتياطية')
    goToStep(6)
  } else {
    toast.error('تعذر النسخ')
  }
}

function jumpToConflict(group) {
  const first = group.items[0]
  if (first?.periodIds?.[0]) {
    highlightPeriodId.value = first.periodIds[0]
    openConflictPanel()
  }
}

async function handleRegenerateTimetable() {
  const result = await Swal.fire({
    title: 'إعادة إنشاء الجدول؟',
    text: 'سيتم فتح معالج الإعداد لإعادة بناء الهيكل والجدول من الصفر.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'متابعة',
    cancelButtonText: 'إلغاء',
  })
  if (!result.isConfirmed) return
  openAutoGenerateWizard()
}

async function refreshCoverageSummary() {
  try {
    const { data } = await window.axios.get(route('admin.timetable.daily-coverage.preview'), {
      headers: { Accept: 'application/json' },
    })
    coverageSummary.value = {
      ...(data.summary ?? {}),
      date: data.date,
      day_name: data.day_name,
      absent_teachers: data.absent_teachers ?? [],
      coverage_roster: data.coverage_roster ?? [],
    }
  } catch (_) {}
}

async function onCoverageApproved() {
  toast.success('تم اعتماد خطة تغطية الغياب بنجاح')
  await refreshCoverageSummary()
}

function onCoverageDemoSeeded(data) {
  const p = data?.preview
  if (p) {
    coverageSummary.value = {
      ...(p.summary ?? {}),
      date: p.date,
      day_name: p.day_name,
      absent_teachers: p.absent_teachers ?? [],
      coverage_roster: p.coverage_roster ?? [],
    }
  } else {
    refreshCoverageSummary()
  }
}

function openCoverageCenter() {
  showCoverageModal.value = true
}

function balanceBadgeClass(balance) {
  const n = Number(balance) || 0
  if (n <= 2) return 'tt-coverage-balance--low'
  if (n <= 5) return 'tt-coverage-balance--mid'
  return 'tt-coverage-balance--high'
}

watch(
  () => currentStep.value,
  (step) => {
    if (step === 6) refreshCoverageSummary()
  }
)

async function onDistributionApply(payload) {
  try {
    await applyTeacherLoadDistribution(payload)
    showDistributionModal.value = false
    toast.success('تم اعتماد توزيع الحصص بنجاح')
  } catch (e) {
    toast.error(e?.message || 'تعذر حفظ التوزيع')
  }
}

async function onSetupSaveFramework(payload) {
  try {
    await applySetupFramework(payload)
    syncWizardFromSetup({
      previewSlots: payload.previewSlots,
      subjectRows: payload.subjectRows,
      workingDays: payload.workingDays,
    })
    toast.success('تم حفظ هيكل الجدول (بدون مواد)')
    setupModalRef.value?.goToStep(7)
  } catch (e) {
    console.error('[Timetable] save framework failed', e)
    toast.error(e.message || 'تعذر حفظ الهيكل')
  }
}

async function onSetupGenerate(payload) {
  try {
    const settings = { ...getTimetableSettings(), ...payload.settings }
    await saveBasicInfo(false, { settings })
    syncWizardFromSetup({
      previewSlots: settings.period_structure,
      subjectRows: payload.subjectRows,
      workingDays: settings.working_days,
      teacherAvailability: payload.teacherAvailability,
    })
    showSetupModal.value = false
    const result = await autoGenerateTimetable({
      clearExisting: true,
      subjectRows: payload.subjectRows,
      settings,
    })
    const score = result?.report?.qualityScore ?? timetableStatus.value.qualityScore
    await Swal.fire({
      title: 'تم إنشاء الجدول',
      html: `
        <p class="mb-2"><strong>جودة الجدول:</strong> ${score}%</p>
        <p class="small text-muted mb-0">تم تعيين ${result?.report?.stats?.assigned ?? 0} حصة من ${result?.report?.stats?.required ?? 0} مطلوبة.</p>
      `,
      icon: score >= 75 ? 'success' : 'info',
    })
    goToStep(6)
  } catch (e) {
    toast.error(e?.message || 'تعذر توليد الجدول — تحقق من المعلمين والحصص')
  }
}

async function handleOptimize() {
  const tipsHtml =
    aiInsights.value.length > 0
      ? `<ul class="text-start small mb-0 ps-3">${aiInsights.value
          .slice(0, 4)
          .map((t) => `<li>${t.message}</li>`)
          .join('')}</ul>`
      : '<p class="small mb-0">لا توجد ملاحظات — سيتم ملء الحصص الفارغة وتحسين التوزيع.</p>'

  const result = await Swal.fire({
    title: 'تحسين الجدول',
    html: `
      <p class="text-muted small">تحليل الجدول الحالي دون إعادة بنائه بالكامل:</p>
      ${tipsHtml}
      <p class="small mt-3 mb-0">سيتم: ملء الحصص الفارغة، تقليل تركّز المواد في يوم واحد، ومراعاة أعباء المعلمين.</p>
    `,
    icon: 'info',
    showCancelButton: true,
    confirmButtonText: 'تحسين الجدول',
    cancelButtonText: 'إلغاء',
  })
  if (!result.isConfirmed) return

  try {
    await optimizeSchedule()
    toast.success('تم تحسين الجدول')
    goToStep(6)
  } catch {
    toast.error('تعذر تحسين الجدول')
  }
}

function openConflictPanel() {
  showConflictPanel.value = true
  goToStep(6)
}

async function handleAutoFixConflicts() {
  if (!conflictCount.value && timetableStatus.value.emptyPeriods === 0) {
    toast.info('لا توجد تعارضات أو حصص فارغة لإصلاحها')
    return
  }

  const result = await Swal.fire({
    title: 'إصلاح تلقائي',
    html: `<p>تعارضات: <strong>${conflictCount.value}</strong></p>
           <p class="small text-muted">سيتم إزالة التعيينات المتعارضة ومحاولة إعادة التوزيع على حصص فارغة.</p>`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'إصلاح تلقائي',
    cancelButtonText: 'إلغاء',
  })
  if (!result.isConfirmed) return

  try {
    await resolveConflictsAuto()
    toast.success('تم تنفيذ الإصلاح')
    showConflictPanel.value = false
  } catch {
    toast.error('تعذر الإصلاح التلقائي')
  }
}

function toggleClassicGrid() {
  showClassicGrid.value = !showClassicGrid.value
  if (showClassicGrid.value) {
    goToStep(6)
  }
}

const displayQualityScore = computed(
  () => lastGenerationReport.value?.qualityScore ?? timetableStatus.value.qualityScore
)

const qualityClass = computed(() => {
  const q = displayQualityScore.value
  if (q >= 85) return 'text-success'
  if (q >= 60) return 'text-warning'
  return 'text-danger'
})

const gridDays = computed(() => props.timetable?.days ?? [])

const selectedTeacherDayId = ref(null)

const assignSubjectsList = computed(() => {
  if (props.assignmentContext?.role === 'teacher' && props.assignmentContext.subjects?.length) {
    return props.assignmentContext.subjects
  }
  return props.subjects ?? []
})

const assignTeachersList = computed(() => {
  if (props.assignmentContext?.role === 'teacher') {
    return props.assignmentContext.teachers ?? []
  }
  let list = props.teachers ?? []
  if (assignForm.subject_id) {
    const sid = Number(assignForm.subject_id)
    list = list.filter((t) => (t.subjects ?? []).some((s) => Number(s.id) === sid))
  }
  return list
})

function canSelfAssignPeriod(period) {
  if (!props.assignmentContext?.can_self_assign || props.assignmentContext.role !== 'teacher') {
    return false
  }
  if (getMainAssignment(period)) return false
  const visible = props.assignmentContext.visible_category_ids ?? []
  if (visible.length && period.category_id && !visible.includes(Number(period.category_id))) {
    return false
  }
  return (props.assignmentContext.subject_ids ?? []).length > 0
}

const teacherAssignablePeriods = computed(() =>
  isTeacherAssignMode.value
    ? gridPeriods.value.filter((p) => canSelfAssignPeriod(p))
    : []
)

const teacherDaysWithPeriods = computed(() =>
  (gridDays.value ?? []).filter((day) =>
    teacherAssignablePeriods.value.some((p) => p.timetable_day_id === day.id)
  )
)

const selectedTeacherDay = computed(() =>
  teacherDaysWithPeriods.value.find((d) => d.id === selectedTeacherDayId.value) ?? null
)

const teacherDayTimeSlots = computed(() => {
  const dayId = selectedTeacherDayId.value
  if (!dayId) return []
  const map = new Map()
  teacherAssignablePeriods.value
    .filter((p) => p.timetable_day_id === dayId)
    .forEach((p) => {
      const key = `${p.time_from}-${p.time_to}`
      if (!map.has(key)) {
        map.set(key, { time_from: p.time_from, time_to: p.time_to, period_number: p.period_number })
      }
    })
  return [...map.values()].sort((a, b) => a.time_from.localeCompare(b.time_from))
})

watch(teacherDaysWithPeriods, (days) => {
  if (!days.length) {
    selectedTeacherDayId.value = null
    return
  }
  if (!selectedTeacherDayId.value || !days.some((d) => d.id === selectedTeacherDayId.value)) {
    selectedTeacherDayId.value = days[0].id
  }
}, { immediate: true })

function selectTeacherDay(dayId) {
  selectedTeacherDayId.value = dayId
}

function cellAssignablePeriods(dayId, slot) {
  return teacherAssignablePeriods.value.filter(
    (p) =>
      p.timetable_day_id === dayId &&
      p.time_from === slot.time_from &&
      p.time_to === slot.time_to
  )
}

function quickSelfAssign(period) {
  const subjects = assignSubjectsList.value
  if (subjects.length === 1) {
    assignForm.timetable_period_id = period.id
    assignForm.subject_id = subjects[0].id
    assignSelfToPeriod()
    return
  }
  openAssign(period)
}

function assignSelfToPeriod() {
  assignForm.post(route('teacher.timetables.assign-self'), {
    preserveScroll: true,
    onSuccess: () => {
      showAssignModal.value = false
      toast.success('تم التعيين بنجاح')
      router.reload({ only: ['timetable'] })
    },
    onError: (errors) => {
      const msg =
        errors.timetable_period_id || errors.subject_id || errors.teacher_id
      if (msg) toast.error(Array.isArray(msg) ? msg[0] : msg)
    },
  })
}

const gridTimeSlots = computed(() => {
  const map = new Map()
  gridPeriods.value.forEach((p) => {
    const key = `${p.time_from}-${p.time_to}`
    if (!map.has(key)) map.set(key, { time_from: p.time_from, time_to: p.time_to, period_number: p.period_number })
  })
  return [...map.values()].sort((a, b) => a.time_from.localeCompare(b.time_from))
})

function cellPeriod(dayId, slot) {
  return gridPeriods.value.find(
    (p) =>
      p.timetable_day_id === dayId &&
      p.time_from === slot.time_from &&
      p.time_to === slot.time_to
  )
}

function cellPeriods(dayId, slot) {
  return gridPeriods.value.filter(
    (p) =>
      p.timetable_day_id === dayId &&
      p.time_from === slot.time_from &&
      p.time_to === slot.time_to
  )
}

function periodClassLabel(period) {
  return period?.category?.name ?? '—'
}

function openAssign(period) {
  if (isTeacherAssignMode.value) {
    if (!canSelfAssignPeriod(period)) return
    selectedPeriod.value = period
    assignForm.timetable_period_id = period.id
    assignForm.teacher_id = props.assignmentContext?.teacher_id ?? null
    assignForm.subject_id = null
    assignForm.type = 'main'
    showAssignModal.value = true
    return
  }

  selectedPeriod.value = period
  assignForm.timetable_period_id = period.id
  assignForm.teacher_id =
    getMainAssignment(period)?.teacher_id ??
    (props.assignmentContext?.teacher_id ?? null)
  assignForm.subject_id = getMainAssignment(period)?.subject_id ?? null
  assignForm.type = 'main'
  showAssignModal.value = true
}

const teacherViewRows = computed(() => {
  const rows = {}
  gridPeriods.value.forEach((p) => {
    const main = getMainAssignment(p)
    if (!main) return
    const tid = main.teacher_id
    if (!rows[tid]) {
      rows[tid] = {
        teacher: props.teachers?.find((t) => t.id === tid),
        slots: [],
      }
    }
    rows[tid].slots.push({ period: p, assignment: main })
  })
  return Object.values(rows)
})

function assignTeacher() {
  if (isTeacherAssignMode.value) {
    assignSelfToPeriod()
    return
  }
  assignForm.post(route('admin.timetable.assign-teacher'), {
    preserveScroll: true,
    onSuccess: () => {
      showAssignModal.value = false
      toast.success('تم تعيين المدرس بنجاح')
      router.reload({ only: ['timetable'] })
    },
    onError: (errors) => {
      const msg = errors.teacher_id || errors.subject_id || errors.timetable_period_id
      if (msg) toast.error(Array.isArray(msg) ? msg[0] : msg)
    },
  })
}

function removeAssignment(id) {
  Swal.fire({
    title: 'هل أنت متأكد؟',
    text: 'سيتم إزالة تعيين المدرس من هذه الحصة',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'نعم، احذف',
    cancelButtonText: 'إلغاء',
  }).then((result) => {
    if (!result.isConfirmed) return
    router.delete(route('admin.timetable.assignments.remove', id), {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('تم إزالة التعيين')
        router.reload({ only: ['timetable'] })
      },
    })
  })
}

onMounted(() => {
  if (isTeacherAssignMode.value) {
    goToStep(6)
    return
  }
  const params = new URLSearchParams(window.location.search)
  const step = Number(params.get('step') || props.initialStep || 1)
  if (step >= 1 && step <= 6) {
    goToStep(step)
  }
})
</script>

<template>
  <Head :title="isTeacherAssignMode ? 'تعيين الحصص' : 'منشئ الجدول الدراسي'" />
  <component :is="layoutComponent">
    <div class="page-content-wrapper border tt-wizard" dir="rtl">
      <div class="card-body px-1 px-sm-4">
        <div
          v-if="!isTeacherAssignMode && departmentNeedsSummary?.length"
          class="alert alert-light border mb-3"
        >
          <h6 class="fw-bold mb-2">احتياجات الأقسام</h6>
          <div class="d-flex flex-wrap gap-2">
            <span
              v-for="(d, i) in departmentNeedsSummary"
              :key="i"
              class="badge"
              :class="{
                'bg-danger': d.status === 'shortage',
                'bg-success': d.status === 'complete',
                'bg-warning text-dark': d.status === 'surplus',
              }"
            >
              {{ d.department }} — {{ d.label }}
            </span>
          </div>
          <Link :href="route('department-plan.index')" class="small">خطة القسم</Link>
        </div>

        <div
          v-if="!isTeacherAssignMode && (coverageSummary.absent_count ?? 0) > 0"
          class="tt-wizard__coverage-banner d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"
        >
          <span>
            <i class="bi bi-exclamation-triangle-fill text-warning ms-1"></i>
            يوجد {{ coverageSummary.absent_count }} مدرس/مدرسين غائبين اليوم
            <span v-if="coverageSummary.affected_count" class="text-muted small">
              ({{ coverageSummary.affected_count }} حصة متأثرة)
            </span>
          </span>
          <button type="button" class="btn btn-sm tt-wizard__btn-coverage" @click="openCoverageCenter">
            فتح مركز التغطية
          </button>
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <div>
            <h4 class="mb-1">{{ isTeacherAssignMode ? 'تعيين الحصص' : 'منشئ الجدول الدراسي' }}</h4>
            <p v-if="isTeacherAssignMode" class="text-muted small mb-0">
              فصول مرحلتك — انقر على حصة فارغة لتعيين نفسك
            </p>
            <button v-if="!isTeacherAssignMode" type="button" class="btn btn-link btn-sm p-0" @click="goToStep(6)">
              <i class="bi bi-eye ms-1"></i> عرض الجدول (الخطوة 6)
            </button>
          </div>
          <ul v-if="isTeacherAssignMode && teacherDaysWithPeriods.length" class="nav nav-pills mb-0">
            <li v-for="day in teacherDaysWithPeriods" :key="day.id" class="nav-item">
              <button
                type="button"
                class="nav-link"
                :class="{ active: selectedTeacherDayId === day.id }"
                @click="selectTeacherDay(day.id)"
              >
                {{ day.day_name }}
              </button>
            </li>
          </ul>
          <div v-if="!isTeacherAssignMode" class="tt-wizard__quick-actions d-flex flex-wrap gap-2 align-items-center">
            <template v-if="isTimetableEmpty">
              <button
                type="button"
                class="btn btn-primary btn-sm"
                :disabled="processing"
                @click="openAutoGenerateWizard"
              >
                <i class="bi bi-magic ms-1"></i> توليد تلقائي
              </button>
              <button
                type="button"
                class="btn btn-sm tt-wizard__btn-distribute"
                :disabled="processing"
                @click="showDistributionModal = true"
              >
                <i class="bi bi-diagram-3 ms-1"></i> توزيع الحصص
              </button>
              <button
                type="button"
                class="btn btn-sm tt-wizard__btn-coverage"
                :disabled="processing"
                @click="openCoverageCenter"
              >
                <i class="bi bi-person-x-fill ms-1"></i> تغطية الغياب اليومية
              </button>
            </template>
            <template v-else>
              <button
                type="button"
                class="btn btn-primary btn-sm"
                :disabled="processing"
                @click="openAutoGenerateWizard"
              >
                <i class="bi bi-magic ms-1"></i> إنشاء الجدول
              </button>
              <button
                type="button"
                class="btn btn-sm tt-wizard__btn-distribute"
                :disabled="processing"
                @click="showDistributionModal = true"
              >
                <i class="bi bi-diagram-3 ms-1"></i> توزيع الحصص
              </button>
              <button
                type="button"
                class="btn btn-sm tt-wizard__btn-coverage"
                :disabled="processing"
                @click="openCoverageCenter"
              >
                <i class="bi bi-person-x-fill ms-1"></i> تغطية الغياب اليومية
              </button>
              <button type="button" class="btn btn-secondary-soft btn-sm" @click="openCopyModal">
                <i class="bi bi-copy ms-1"></i> نسخ من فصل سابق
              </button>
              <button
                type="button"
                class="btn btn-success-soft btn-sm"
                :disabled="processing"
                @click="handleOptimize"
              >
                <i class="bi bi-sliders ms-1"></i> تحسين الجدول
              </button>
              <button
                type="button"
                class="btn btn-warning-soft btn-sm"
                :class="{ active: showConflictPanel }"
                @click="openConflictPanel"
              >
                <i class="bi bi-exclamation-triangle ms-1"></i> حل التعارضات
                <span v-if="conflictCount" class="badge bg-danger ms-1">{{ conflictCount }}</span>
              </button>
              <button
                type="button"
                class="btn btn-outline-secondary btn-sm"
                :class="{ active: showClassicGrid }"
                @click="toggleClassicGrid"
              >
                <i class="bi bi-grid-3x3 ms-1"></i>
                {{ showClassicGrid ? 'الجدول المرئي' : 'الشبكة الكلاسيكية' }}
              </button>
              <button
                type="button"
                class="btn btn-link btn-sm text-muted"
                :disabled="processing"
                @click="handleRegenerateTimetable"
              >
                إعادة إنشاء الجدول
              </button>
            </template>
          </div>
        </div>

        <div v-if="!isTeacherAssignMode" class="tt-wizard__stepper">
          <button
            v-for="step in steps"
            :key="step.n"
            type="button"
            class="tt-wizard__step"
            :class="{ 'is-active': currentStep === step.n, 'is-done': currentStep > step.n }"
            @click="goToStep(step.n)"
          >
            <span class="d-block small opacity-75">{{ step.n }}</span>
            {{ step.title }}
          </button>
        </div>

        <!-- Step 1 -->
        <div v-show="!isTeacherAssignMode && currentStep === 1" class="tt-wizard__panel">
          <h5 class="fw-bold mb-4">البيانات الأساسية</h5>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">السنة الدراسية</label>
              <input v-model="form.academic_year" class="form-control" placeholder="2025-2026" />
            </div>
            <div class="col-md-6">
              <label class="form-label">الفصل الدراسي</label>
              <select v-model="wizardMeta.term" class="form-select">
                <option>الفصل الأول</option>
                <option>الفصل الثاني</option>
                <option>الفصل الصيفي</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">فرع المدرسة</label>
              <input v-model="wizardMeta.branch" class="form-control" placeholder="الفرع الرئيسي" />
            </div>
            <div class="col-md-6">
              <label class="form-label">اسم الجدول</label>
              <input v-model="form.name" class="form-control" />
            </div>
            <div class="col-md-6">
              <label class="form-label">المرحلة / الصف / الفصل</label>
              <select v-model="wizardMeta.class_category_id" class="form-select">
                <option :value="null">جميع الفصول (لاحقاً اختر فصلاً محدداً)</option>
                <CategoryOptions :categories="categories" :prefix="''" />
              </select>
              <small class="text-muted">يُستخدم لتصفية الجدول والتوليد التلقائي</small>
            </div>
            <div class="col-md-6">
              <label class="form-label">الحالة</label>
              <select v-model="form.status" class="form-select">
                <option value="active">نشط</option>
                <option value="inactive">غير نشط</option>
              </select>
            </div>
          </div>
          <div class="d-flex justify-content-end mt-4">
            <button type="button" class="btn btn-primary px-4" :disabled="processing" @click="handleStep1Next">
              التالي
            </button>
          </div>
        </div>

        <!-- Step 2 -->
        <div v-show="!isTeacherAssignMode && currentStep === 2" class="tt-wizard__panel">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0">هيكل الحصص اليومي</h5>
            <button type="button" class="btn btn-primary-soft btn-sm" @click="addPeriodTemplate">
              <i class="bi bi-plus-lg ms-1"></i> إضافة فترة
            </button>
          </div>
          <p class="text-muted small">عرّف الحصص مرة واحدة — تُطبَّق على كل أيام الأسبوع عند الانتقال للخطوة التالية.</p>

          <div class="tt-wizard__period-row fw-semibold small text-muted border-bottom pb-2 mb-2">
            <span>الوصف</span>
            <span>من</span>
            <span>إلى</span>
            <span>النوع</span>
            <span></span>
          </div>

          <div v-for="p in periodTemplates" :key="p.id" class="tt-wizard__period-row">
            <input v-model="p.label" class="form-control form-control-sm" />
            <input v-model="p.time_from" type="time" class="form-control form-control-sm" />
            <input v-model="p.time_to" type="time" class="form-control form-control-sm" />
            <select v-model="p.kind" class="form-select form-select-sm">
              <option value="lesson">حصة دراسية</option>
              <option value="break">استراحة</option>
              <option value="assembly">طابور</option>
              <option value="prayer">صلاة</option>
            </select>
            <button type="button" class="btn btn-danger-soft btn-sm" @click="removePeriodTemplate(p.id)">
              <i class="bi bi-trash"></i>
            </button>
          </div>

          <div class="d-flex justify-content-between mt-4">
            <button type="button" class="btn btn-secondary-soft" @click="prevStep">السابق</button>
            <button type="button" class="btn btn-primary px-4" :disabled="processing" @click="handleStep2Next">
              التالي
            </button>
          </div>
        </div>

        <!-- Step 3 -->
        <div v-show="!isTeacherAssignMode && currentStep === 3" class="tt-wizard__panel">
          <div class="row g-4">
            <div class="col-lg-8">
              <div class="d-flex justify-content-between mb-3">
                <h5 class="fw-bold mb-0">متطلبات المواد (حصص/أسبوع)</h5>
                <button type="button" class="btn btn-primary-soft btn-sm" @click="addSubjectRequirement">+ مادة</button>
              </div>
              <div v-for="req in subjectRequirements" :key="req.subject_id" class="d-flex align-items-center gap-2 mb-2">
                <span class="tt-wizard__subject-dot" :style="{ background: req.color }"></span>
                <span class="flex-grow-1 fw-semibold">{{ req.name }}</span>
                <input
                  v-model.number="req.periods_per_week"
                  type="number"
                  min="0"
                  max="20"
                  class="form-control form-control-sm"
                  style="width: 90px"
                />
                <span class="small text-muted">حصة/أسبوع</span>
              </div>
            </div>
            <div class="col-lg-4">
              <div class="tt-wizard__counter text-center">
                <div class="display-6 fw-bold text-primary">{{ totalRequiredPeriods }}</div>
                <div class="text-muted">إجمالي الحصص المطلوبة</div>
                <hr />
                <div class="h4 mb-0">{{ periodsPerWeekCapacity }}</div>
                <div class="small text-muted">سعة الجدول الأسبوعية</div>
                <hr />
                <div class="h4 mb-0" :class="remainingPeriods > 0 ? 'text-warning' : 'text-success'">
                  {{ remainingPeriods }}
                </div>
                <div class="small">حصص متبقية غير موزعة</div>
              </div>
            </div>
          </div>
          <div class="d-flex justify-content-between mt-4">
            <button type="button" class="btn btn-secondary-soft" @click="prevStep">السابق</button>
            <button type="button" class="btn btn-primary px-4" @click="nextStep">التالي</button>
          </div>
        </div>

        <!-- Step 4 -->
        <div v-show="!isTeacherAssignMode && currentStep === 4" class="tt-wizard__panel">
          <h5 class="fw-bold mb-3">تعيين المعلمين للمواد</h5>
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead>
                <tr>
                  <th>المادة</th>
                  <th>حصص/أسبوع</th>
                  <th>المعلم</th>
                  <th>الحمل الحالي</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="req in subjectRequirements" :key="req.subject_id">
                  <td>
                    <span class="tt-wizard__subject-dot" :style="{ background: req.color }"></span>
                    {{ req.name }}
                  </td>
                  <td>{{ req.periods_per_week }}</td>
                  <td>
                    <select v-model="teacherBySubject[req.subject_id]" class="form-select form-select-sm">
                      <option :value="null">— اختر —</option>
                      <option v-for="t in teachers" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </select>
                  </td>
                  <td>
                    <span v-if="teacherBySubject[req.subject_id]" class="badge bg-light border">
                      {{ teacherLoads[teacherBySubject[req.subject_id]] ?? 0 }} حصة مجدولة
                    </span>
                    <span v-else class="text-muted">—</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="d-flex justify-content-between mt-4">
            <button type="button" class="btn btn-secondary-soft" @click="prevStep">السابق</button>
            <button type="button" class="btn btn-primary px-4" @click="nextStep">التالي</button>
          </div>
        </div>

        <!-- Step 5 -->
        <div v-show="!isTeacherAssignMode && currentStep === 5" class="tt-wizard__panel">
          <h5 class="fw-bold mb-2">توليد تلقائي</h5>
          <p class="text-muted small mb-4">
            يفتح معالج إعداد من 8 خطوات: أيام العمل، المرحلة، هيكل الحصص، الفسح، ثم تعيين المواد وتوليد الجدول.
          </p>
          <div class="row g-3">
            <div class="col-md-4">
              <div class="tt-wizard__action-card">
                <i class="bi bi-plus-circle text-primary"></i>
                <h6 class="fw-bold mt-2">إنشاء الجدول</h6>
                <p class="small text-muted">توليد كامل للحصص الفارغة وفق الإعدادات الحالية.</p>
                <button
                  type="button"
                  class="btn btn-primary btn-sm w-100"
                  :disabled="processing"
                  @click="openAutoGenerateWizard"
                >
                  <i class="bi bi-magic ms-1"></i> توليد تلقائي
                </button>
                <p v-if="!isTimetableEmpty" class="small text-muted mt-2 mb-0">
                  الجدول موجود — استخدم «إعادة إنشاء» أو «تحسين» من الشريط العلوي.
                </p>
              </div>
            </div>
            <div class="col-md-4">
              <div class="tt-wizard__action-card">
                <i class="bi bi-sliders text-success"></i>
                <h6 class="fw-bold mt-2">تحسين الجدول</h6>
                <p class="small text-muted">تحسين التوزيع دون مسح الجدول بالكامل.</p>
                <button
                  type="button"
                  class="btn btn-success-soft btn-sm w-100"
                  :disabled="processing || isTimetableEmpty"
                  @click="handleOptimize"
                >
                  تحسين الجدول
                </button>
              </div>
            </div>
            <div class="col-md-4">
              <div class="tt-wizard__action-card">
                <i class="bi bi-shield-check text-warning"></i>
                <h6 class="fw-bold mt-2">حل التعارضات</h6>
                <p class="small text-muted">إصلاح التعارضات والحصص الفارغة فقط.</p>
                <button
                  type="button"
                  class="btn btn-warning-soft btn-sm w-100"
                  :disabled="isTimetableEmpty"
                  @click="openConflictPanel"
                >
                  عرض التعارضات
                </button>
              </div>
            </div>
          </div>
          <div v-if="!isTimetableEmpty" class="mt-3 text-center">
            <button type="button" class="btn btn-link btn-sm text-danger" :disabled="processing" @click="handleRegenerateTimetable">
              إعادة إنشاء الجدول من الصفر
            </button>
          </div>
          <div class="d-flex justify-content-between mt-4">
            <button type="button" class="btn btn-secondary-soft" @click="prevStep">السابق</button>
            <button type="button" class="btn btn-outline-primary" @click="goToStep(6)">عرض الجدول المرئي</button>
          </div>
        </div>

        <!-- Step 6 -->
        <div v-show="currentStep === 6">
          <!-- Teacher grid (جدولي) -->
          <div v-if="isTeacherAssignMode">
            <div v-if="!timetable" class="text-center text-muted py-5">
              <i class="bi bi-calendar-x fs-1 d-block mb-2 opacity-25"></i>
              لا يوجد جدول دراسي نشط حالياً
            </div>
            <div v-else-if="!(assignmentContext?.subject_ids?.length)" class="text-center text-muted py-5">
              <i class="bi bi-journal-x fs-1 d-block mb-2 opacity-25"></i>
              لا توجد مواد مسجلة لتدريسها — تواصل مع الإدارة
            </div>
            <div v-else-if="!teacherAssignablePeriods.length" class="text-center text-muted py-5">
              <i class="bi bi-calendar2-x fs-1 d-block mb-2 opacity-25"></i>
              لا توجد حصص فارغة يمكنك التعيين عليها حالياً
            </div>
            <div v-else class="tt-wizard__panel">
              <h6 v-if="selectedTeacherDay" class="mb-3 text-muted">{{ selectedTeacherDay.day_name }}</h6>
              <div v-if="!teacherDayTimeSlots.length" class="text-center text-muted py-4">
                لا توجد حصص فارغة في هذا اليوم
              </div>
              <div v-else class="table-responsive">
                <table :key="selectedTeacherDayId" class="table table-bordered tt-wizard__grid-table tt-wizard__grid-table--single-day text-center">
                  <thead>
                    <tr>
                      <th style="width: 140px">الوقت</th>
                      <th>الحصص</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="slot in teacherDayTimeSlots" :key="slot.time_from">
                      <td class="small text-muted">{{ slot.time_from }} – {{ slot.time_to }}</td>
                      <td class="p-2 text-start tt-wizard__day-periods-col">
                        <div class="tt-wizard__cell-stack tt-wizard__cell-stack--horizontal">
                          <div
                            v-for="period in cellAssignablePeriods(selectedTeacherDayId, slot)"
                            :key="period.id"
                            class="tt-wizard__cell tt-wizard__cell--mini"
                          >
                            <div class="small fw-semibold text-truncate" :title="periodClassLabel(period)">
                              {{ periodClassLabel(period) }}
                            </div>
                            <button
                              type="button"
                              class="btn btn-sm btn-success mt-1 py-0 px-2"
                              style="font-size: 0.7rem"
                              @click.stop="quickSelfAssign(period)"
                            >
                              تعيين نفسي
                            </button>
                          </div>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <template v-else>
          <div
            v-if="(coverageSummary.absent_count ?? 0) > 0"
            class="tt-wizard__coverage-step-panel card border-warning mb-3"
          >
            <div class="card-body">
              <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                <div>
                  <h6 class="fw-bold mb-1 text-danger">
                    <i class="bi bi-person-x-fill ms-1"></i>
                    غياب اليوم — {{ coverageSummary.day_name }} ({{ coverageSummary.date }})
                  </h6>
                  <p class="small text-muted mb-0">
                    {{ coverageSummary.absent_count }} غائب —
                    {{ coverageSummary.affected_count ?? 0 }} حصة متأثرة —
                    {{ coverageSummary.pending_count ?? 0 }} بانتظار التوزيع
                  </p>
                </div>
                <button type="button" class="btn btn-warning fw-bold" @click="openCoverageCenter">
                  <i class="bi bi-play-circle ms-1"></i>
                  ابدأ توزيع التغطية
                </button>
              </div>

              <div v-if="coverageSummary.absent_teachers?.length" class="mb-3">
                <div class="small fw-semibold mb-1">المدرسون الغائبون</div>
                <div class="d-flex flex-wrap gap-2">
                  <button
                    v-for="t in coverageSummary.absent_teachers"
                    :key="t.teacher_id"
                    type="button"
                    class="badge bg-danger-subtle text-danger border tt-coverage-wizard__step-chip"
                    @click="openCoverageCenter"
                  >
                    {{ t.name }} ({{ t.affected_count ?? 0 }} حصة)
                  </button>
                </div>
              </div>

              <div class="mb-2">
                <a :href="route('admin.settings.coverage')" class="small" target="_blank">
                  <i class="bi bi-sliders ms-1"></i>
                  ضبط أولويات الترتيب (مادة، قسم، مرحلة…) من الإعدادات
                </a>
              </div>
              <div v-if="coverageSummary.coverage_roster?.length">
                <div class="small fw-semibold mb-1">
                  رصيد التغطية — اختر معلمين برصيد أقل (+1 أفضل من +7)
                </div>
                <div class="d-flex flex-wrap gap-1">
                  <span
                    v-for="st in coverageSummary.coverage_roster.slice(0, 12)"
                    :key="st.teacher_id"
                    class="tt-coverage-roster-chip small"
                  >
                    {{ st.name }}
                    <span class="badge" :class="balanceBadgeClass(st.coverage_balance)">
                      {{ st.balance_label }}
                    </span>
                  </span>
                </div>
              </div>
            </div>
          </div>
          <CoverageDemoEmptyState
            v-else
            class="mb-3"
            compact
            @seeded="onCoverageDemoSeeded"
          />

          <div class="tt-wizard__status-bar mb-3">
            <div class="tt-wizard__status-item">
              <span class="tt-wizard__status-label">إجمالي الحصص</span>
              <span class="tt-wizard__status-value">{{ timetableStatus.totalPeriods }}</span>
            </div>
            <div class="tt-wizard__status-item">
              <span class="tt-wizard__status-label">التعارضات</span>
              <span class="tt-wizard__status-value" :class="timetableStatus.conflictCount ? 'text-danger' : 'text-success'">
                {{ timetableStatus.conflictCount }}
              </span>
            </div>
            <div class="tt-wizard__status-item">
              <span class="tt-wizard__status-label">الحصص الفارغة</span>
              <span class="tt-wizard__status-value" :class="timetableStatus.emptyPeriods ? 'text-warning' : ''">
                {{ timetableStatus.emptyPeriods }}
              </span>
            </div>
            <div class="tt-wizard__status-item">
              <span class="tt-wizard__status-label">المعلمون المثقلون</span>
              <span class="tt-wizard__status-value">{{ timetableStatus.overloadedTeachers }}</span>
            </div>
            <div class="tt-wizard__status-item tt-wizard__status-item--quality">
              <span class="tt-wizard__status-label">جودة الجدول</span>
              <span class="tt-wizard__status-value fw-bold" :class="qualityClass">{{ displayQualityScore }}%</span>
            </div>
          </div>

          <div v-if="showConflictPanel" class="tt-wizard__panel mb-3 border-warning">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
              <h6 class="fw-bold mb-0">
                <i class="bi bi-exclamation-triangle text-warning ms-1"></i>
                حل التعارضات
              </h6>
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-warning btn-sm" :disabled="processing" @click="handleAutoFixConflicts">
                  إصلاح تلقائي
                </button>
                <button type="button" class="btn btn-link btn-sm" @click="showConflictPanel = false">إغلاق</button>
              </div>
            </div>
            <p class="small text-muted mb-2">
              يعالج فقط: تعارض المعلمين، الحجز المزدوج، والحصص الفارغة — دون إعادة بناء الجدول بالكامل.
            </p>
            <div v-if="groupedConflicts.length" class="mb-2">
              <div class="small fw-semibold text-danger mb-2">مركز التعارضات</div>
              <div
                v-for="group in groupedConflicts"
                :key="group.teacherId"
                class="tt-setup__card mb-2 d-flex flex-wrap justify-content-between align-items-center gap-2"
              >
                <div>
                  <span class="text-danger">🔴</span>
                  <strong>{{ group.teacherName }}</strong>
                  <span class="badge bg-danger ms-1">{{ group.count }} تعارضات</span>
                </div>
                <div class="d-flex gap-1">
                  <button type="button" class="btn btn-sm btn-outline-primary" @click="jumpToConflict(group)">
                    الانتقال للمشكلة
                  </button>
                </div>
              </div>
              <button type="button" class="btn btn-warning btn-sm w-100" :disabled="processing" @click="handleAutoFixConflicts">
                إصلاح تلقائي للكل
              </button>
            </div>
            <div v-if="validationWarnings.filter((w) => w.type === 'empty').length">
              <div class="small fw-semibold mb-1">حصص فارغة</div>
              <div
                v-for="(w, i) in validationWarnings.filter((w) => w.type === 'empty').slice(0, 5)"
                :key="'e-' + i"
                class="tt-wizard__warning small"
              >
                {{ w.message }}
              </div>
            </div>
            <div v-if="!conflictCount && !timetableStatus.emptyPeriods" class="text-success small">
              <i class="bi bi-check-circle ms-1"></i> لا توجد تعارضات أو حصص فارغة
            </div>
          </div>

          <div class="row g-3">
            <div class="col-lg-9">
              <div class="tt-wizard__panel">
                <p v-if="showClassicGrid" class="small text-muted mb-2">
                  <i class="bi bi-pencil-square ms-1"></i>
                  وضع التحرير اليدوي — انقر على الحصص لتعديل التعيينات أو اسحب في الشبكة الكلاسيكية.
                </p>
                <ul v-if="!showClassicGrid" class="nav nav-tabs mb-3">
                  <li class="nav-item">
                    <button type="button" class="nav-link" :class="{ active: viewMode === 'class' }" @click="viewMode = 'class'">عرض الفصل</button>
                  </li>
                  <li class="nav-item">
                    <button type="button" class="nav-link" :class="{ active: viewMode === 'teacher' }" @click="viewMode = 'teacher'">عرض المعلم</button>
                  </li>
                  <li class="nav-item">
                    <button type="button" class="nav-link" :class="{ active: viewMode === 'room' }" @click="viewMode = 'room'">عرض الغرف</button>
                  </li>
                  <li class="nav-item">
                    <button type="button" class="nav-link" :class="{ active: viewMode === 'student' }" @click="viewMode = 'student'">عرض الطالب</button>
                  </li>
                </ul>

                <div v-if="viewMode === 'class' && !showClassicGrid" class="table-responsive">
                  <table class="table table-bordered tt-wizard__grid-table text-center">
                    <thead>
                      <tr>
                        <th>الوقت</th>
                        <th v-for="day in gridDays" :key="day.id">{{ day.day_name }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="slot in gridTimeSlots" :key="slot.time_from">
                        <td class="small text-muted">{{ slot.time_from }} – {{ slot.time_to }}</td>
                        <td v-for="day in gridDays" :key="day.id">
                          <div
                            v-if="cellPeriod(day.id, slot)"
                            class="tt-wizard__cell"
                            :class="{
                              'has-conflict': highlightPeriodId === cellPeriod(day.id, slot)?.id,
                            }"
                            :style="{ borderRight: `4px solid ${subjectColor(getMainAssignment(cellPeriod(day.id, slot))?.subject_id)}` }"
                            @click="openAssign(cellPeriod(day.id, slot))"
                          >
                            <template v-if="getMainAssignment(cellPeriod(day.id, slot))">
                              <div class="fw-bold small">
                                {{ getMainAssignment(cellPeriod(day.id, slot)).subject?.name }}
                              </div>
                              <div class="text-muted" style="font-size: 0.72rem">
                                {{ getMainAssignment(cellPeriod(day.id, slot)).teacher?.name }}
                              </div>
                            </template>
                            <template v-else-if="canSelfAssignPeriod(cellPeriod(day.id, slot))">
                              <div class="small text-success fw-semibold">حصة متاحة للتعيين</div>
                              <button
                                type="button"
                                class="btn btn-sm btn-success mt-1"
                                @click.stop="quickSelfAssign(cellPeriod(day.id, slot))"
                              >
                                تعيين نفسي
                              </button>
                            </template>
                            <span v-else class="text-muted small">+ تعيين</span>
                          </div>
                          <div v-else class="tt-wizard__cell is-empty">—</div>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <div v-else-if="viewMode === 'teacher'">
                  <div v-for="row in teacherViewRows" :key="row.teacher?.id" class="mb-3 border rounded p-3">
                    <h6 class="fw-bold">{{ row.teacher?.name }}</h6>
                    <div class="d-flex flex-wrap gap-2">
                      <span
                        v-for="(item, i) in row.slots"
                        :key="i"
                        class="badge bg-light border text-dark"
                      >
                        {{ item.period.day_name }} {{ item.period.time_from }} — {{ item.assignment.subject?.name }}
                      </span>
                    </div>
                  </div>
                </div>

                <div v-else-if="viewMode === 'room'">
                  <p class="text-muted">عرض الغرف — يُفعّل عند ربط الغرف في قاعدة البيانات. حالياً: غرفة افتراضية لكل حصة.</p>
                  <div class="table-responsive">
                    <table class="table table-sm">
                      <tr v-for="p in filteredPeriods" :key="p.id">
                        <td>{{ p.day_name }}</td>
                        <td>{{ p.time_from }}</td>
                        <td>{{ getMainAssignment(p)?.subject?.name ?? '—' }}</td>
                        <td>غرفة {{ (p.id % 8) + 101 }}</td>
                      </tr>
                    </table>
                  </div>
                </div>

                <div v-else>
                  <p class="alert alert-info">عرض الطالب يعرض نفس جدول الفصل المحدد — من منظور التلميذ.</p>
                </div>

                <div v-if="showClassicGrid && timetable?.days?.length" class="mt-3">
                  <CalendarView
                    :days="timetable.days"
                    :periods="filteredPeriods"
                    :show-assignments="true"
                    :readonly="false"
                  />
                </div>
              </div>
            </div>

            <div class="col-lg-3">
              <div class="tt-wizard__panel mb-3">
                <h6 class="fw-bold mb-2"><i class="bi bi-stars ms-1"></i> تحليلات EDUVERA الذكية</h6>
                <div v-for="(tip, i) in aiInsights" :key="i" class="tt-wizard__ai-card small">
                  <i :class="[tip.icon, 'ms-1 opacity-75']"></i>
                  {{ tip.message }}
                </div>
                <div v-if="!aiInsights.length" class="text-muted small">لا توجد تحليلات حالياً</div>
              </div>
              <div class="tt-wizard__panel">
                <h6 class="fw-bold mb-2"><i class="bi bi-x-octagon ms-1"></i> أخطاء يجب إصلاحها</h6>
                <div
                  v-for="(w, i) in validationErrors.slice(0, 6)"
                  :key="i"
                  class="tt-wizard__warning"
                  :class="{ 'tt-wizard__conflict': w.type === 'conflict' }"
                >
                  {{ w.message }}
                </div>
                <div v-if="validationErrors.length > 6" class="small text-muted">
                  + {{ validationErrors.length - 6 }} أخطاء أخرى
                </div>
                <div v-if="!validationErrors.length" class="text-success small">
                  <i class="bi bi-check-circle ms-1"></i> لا توجد أخطاء حرجة
                </div>
              </div>
            </div>
          </div>
          <div class="d-flex justify-content-between mt-3">
            <button type="button" class="btn btn-secondary-soft" @click="prevStep">السابق</button>
            <button type="button" class="btn btn-success px-4" @click="goToStep(6)">عرض الجدول النهائي</button>
          </div>
          </template>
        </div>
      </div>
    </div>

    <div v-if="showCopyModal" class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, 0.5)">
      <div class="modal-dialog">
        <div class="modal-content" dir="rtl">
          <div class="modal-header">
            <h5 class="modal-title">نسخ من فصل سابق</h5>
            <button type="button" class="btn-close" @click="showCopyModal = false"></button>
          </div>
          <div class="modal-body">
            <p class="small text-muted">اختر نسخة احتياطية محلية أو من إعدادات الجدول.</p>
            <div v-if="!localBackups.length" class="alert alert-info small">لا توجد نسخ احتياطية بعد — أنشئ نسخة قبل إعادة التوليد.</div>
            <button
              v-for="(b, i) in localBackups"
              :key="i"
              type="button"
              class="btn btn-outline-secondary w-100 mb-2 text-start"
              @click="applyCopyBackup(b)"
            >
              <div class="fw-semibold">{{ b.name ?? 'جدول' }} — {{ b.academic_year ?? '' }}</div>
              <div class="small text-muted">
                {{ b.stats?.assignments ?? 0 }} تعيين · {{ new Date(b.saved_at).toLocaleString('ar-EG') }}
              </div>
            </button>
          </div>
        </div>
      </div>
    </div>

    <DailyAbsenceCoverageModal
      :show="showCoverageModal"
      :teachers="teachers"
      :teacher-attendance-statuses="teacherAttendanceStatuses"
      :initial-summary="coverageSummary"
      @close="showCoverageModal = false"
      @approved="onCoverageApproved"
      @demo-seeded="onCoverageDemoSeeded"
    />

    <TeacherLoadDistributionModal
      :show="showDistributionModal"
      :teachers="enrichTeachersForDistribution()"
      :subjects="subjects"
      :subject-requirements="subjectRequirements"
      :settings="getTimetableSettings()"
      :wizard-meta="wizardMeta"
      :teacher-loads="teacherLoads"
      :initial-distribution="getTeacherLoadDistribution()"
      :initial-rules="getDistributionPriorityRules()"
      :processing="processing"
      @close="showDistributionModal = false"
      @apply="onDistributionApply"
    />

    <TimetableSetupWizardModal
      ref="setupModalRef"
      :show="showSetupModal"
      :teachers="teachers"
      :subjects="subjects"
      :teacher-by-subject="teacherBySubject"
      :timetable-settings="timetable?.settings"
      :processing="processing"
      :has-approved-distribution="hasApprovedTeacherDistribution"
      @close="showSetupModal = false"
      @save-framework="onSetupSaveFramework"
      @generate="onSetupGenerate"
    />

    <!-- تعيين مدرس (نفس منطق صفحة العرض السابقة) -->
    <div v-if="showAssignModal" class="modal show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5)">
      <div class="modal-dialog">
        <div class="modal-content" dir="rtl">
          <div class="modal-header">
            <h5 class="modal-title">
              {{ isTeacherAssignMode ? 'تعيين نفسي للحصة' : 'تعيين مدرس للحصة' }}
            </h5>
            <button type="button" class="btn-close" @click="showAssignModal = false"></button>
          </div>
          <div class="modal-body">
            <div v-if="selectedPeriod" class="mb-3 small">
              <p class="mb-1"><strong>اليوم:</strong> {{ selectedPeriod.day_name }}</p>
              <p class="mb-1"><strong>الوقت:</strong> {{ selectedPeriod.time_from }} – {{ selectedPeriod.time_to }}</p>
              <p v-if="selectedPeriod.category" class="mb-1"><strong>الفصل:</strong> {{ selectedPeriod.category.name }}</p>
              <div v-if="!isTeacherAssignMode && getMainAssignment(selectedPeriod)" class="mt-2">
                <button
                  type="button"
                  class="btn btn-sm btn-danger-soft"
                  @click="removeAssignment(getMainAssignment(selectedPeriod).id)"
                >
                  إزالة التعيين الحالي
                </button>
              </div>
            </div>
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">المادة</label>
                <select v-model="assignForm.subject_id" class="form-select">
                  <option :value="null">اختر المادة</option>
                  <option v-for="s in assignSubjectsList" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
              </div>
              <div v-if="!isTeacherAssignMode" class="col-12">
                <label class="form-label">المدرس</label>
                <select v-model="assignForm.teacher_id" class="form-select">
                  <option :value="null">اختر المدرس</option>
                  <option v-for="t in assignTeachersList" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary-soft" @click="showAssignModal = false">إلغاء</button>
            <button type="button" class="btn btn-primary" :disabled="assignForm.processing" @click="assignTeacher">
              {{ isTeacherAssignMode ? 'تعيين نفسي' : 'تعيين' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </component>
</template>
