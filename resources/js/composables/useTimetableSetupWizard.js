import { ref, reactive, computed, watch } from 'vue'

export const SETUP_STORAGE_KEY = 'eduvera_timetable_setup_v1'

export const WEEK_DAYS = [
  { key: 'الأحد', label: 'الأحد' },
  { key: 'الإثنين', label: 'الإثنين' },
  { key: 'الثلاثاء', label: 'الثلاثاء' },
  { key: 'الأربعاء', label: 'الأربعاء' },
  { key: 'الخميس', label: 'الخميس' },
  { key: 'الجمعة', label: 'الجمعة' },
  { key: 'السبت', label: 'السبت' },
]

export const EDUCATIONAL_STAGES = [
  { id: 'kg', label: 'رياض أطفال' },
  { id: 'primary', label: 'ابتدائي' },
  { id: 'middle', label: 'إعدادي' },
  { id: 'high', label: 'ثانوي' },
  { id: 'university', label: 'جامعة' },
  { id: 'custom', label: 'مخصص' },
]

/** Legacy id aliases (e.g. older drafts used "secondary" for ثانوي). */
const STAGE_ID_ALIASES = {
  secondary: 'high',
}

const VALID_STAGE_IDS = new Set(EDUCATIONAL_STAGES.map((s) => s.id))

/**
 * Backward-compatible: selected_stages[] or legacy educational_stage string.
 * @param {object|null} settings
 * @returns {string[]}
 */
export function normalizeSelectedStagesFromSettings(settings) {
  if (!settings) return ['primary']

  if (Array.isArray(settings.selected_stages) && settings.selected_stages.length) {
    const normalized = settings.selected_stages
      .map((id) => STAGE_ID_ALIASES[id] ?? id)
      .filter((id) => VALID_STAGE_IDS.has(id))
    if (normalized.length) return [...new Set(normalized)]
  }

  const legacy = settings.educational_stage
  if (legacy) {
    const id = STAGE_ID_ALIASES[legacy] ?? legacy
    if (VALID_STAGE_IDS.has(id)) return [id]
  }

  return ['primary']
}

export function stageLabelById(stageId) {
  if (stageId === 'custom') return null
  return EDUCATIONAL_STAGES.find((s) => s.id === stageId)?.label ?? stageId
}

export const BREAK_PRESETS = ['استراحة', 'فسحة', 'صلاة', 'طابور', 'نشاط']

export const SETUP_STEPS = [
  { n: 1, title: 'أيام العمل' },
  { n: 2, title: 'المرحلة الدراسية' },
  { n: 3, title: 'هيكل الحصص' },
  { n: 4, title: 'الفسح والاستراحات' },
  { n: 5, title: 'مراجعة الجدول' },
  { n: 6, title: 'حفظ الهيكل' },
  { n: 7, title: 'تعيين المواد' },
  { n: 8, title: 'توليد الجدول' },
]

const DEFAULT_WORKING = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس']

function parseTimeHHmm(str) {
  const [h, m] = (str || '08:00').split(':').map(Number)
  return { h: h || 8, m: m || 0 }
}

function formatTimeHHmm(totalMinutes) {
  const h = Math.floor(totalMinutes / 60) % 24
  const m = totalMinutes % 60
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
}

function addMinutes(time, minutes) {
  const { h, m } = parseTimeHHmm(time)
  return formatTimeHHmm(h * 60 + m + minutes)
}

function breakKindFromName(name) {
  if (!name) return 'break'
  if (name.includes('صلاة')) return 'prayer'
  if (name.includes('طابور')) return 'assembly'
  return 'break'
}

export function buildPeriodSchedule({
  dailyLessons = 8,
  startTime = '08:00',
  lessonDurationMin = 45,
  gapBetweenLessonsMin = 5,
  breaks = [],
}) {
  const sortedBreaks = [...breaks].sort((a, b) => a.afterPeriod - b.afterPeriod)
  const slots = []
  let cursor = startTime
  let lessonIndex = 0

  while (lessonIndex < dailyLessons) {
    lessonIndex += 1
    const lessonEnd = addMinutes(cursor, lessonDurationMin)
    slots.push({
      id: `lesson-${lessonIndex}`,
      period_number: lessonIndex,
      kind: 'lesson',
      label: `الحصة ${lessonIndex}`,
      time_from: cursor,
      time_to: lessonEnd,
    })
    cursor = addMinutes(lessonEnd, gapBetweenLessonsMin)

    const afterBreaks = sortedBreaks.filter((b) => Number(b.afterPeriod) === lessonIndex)
    for (const br of afterBreaks) {
      const breakEnd = addMinutes(cursor, Number(br.durationMin) || 15)
      slots.push({
        id: `break-${br.id ?? lessonIndex}-${br.afterPeriod}`,
        period_number: 0,
        kind: breakKindFromName(br.name),
        label: br.name || 'استراحة',
        time_from: cursor,
        time_to: breakEnd,
      })
      cursor = breakEnd
    }
  }

  return slots
}

export function useTimetableSetupWizard() {
  const currentStep = ref(1)
  const processing = ref(false)

  const workingDays = ref([...DEFAULT_WORKING])
  const selectedStages = ref(['primary'])
  const customStageName = ref('')
  const dailyLessons = ref(8)
  const startTime = ref('08:00')
  const lessonDurationMin = ref(45)
  const gapBetweenLessonsMin = ref(5)
  const breaks = ref([])
  const previewSlots = ref([])
  const assignByRole = ref('admin')
  const subjectRows = ref([])
  const teacherAvailability = ref({})
  const setupSubStep = ref('subjects') // subjects | availability

  const selectedDaysCount = computed(() => workingDays.value.length)

  const selectedStagesCount = computed(() => selectedStages.value.length)

  const stageLabelsSummary = computed(() => {
    const labels = selectedStages.value.map((id) => {
      if (id === 'custom') return customStageName.value.trim() || 'مخصص'
      return stageLabelById(id) ?? id
    })
    return labels.filter(Boolean).join('، ')
  })

  /** @deprecated single-stage label; use stageLabelsSummary */
  const stageLabel = stageLabelsSummary

  const hasCustomStage = computed(() => selectedStages.value.includes('custom'))

  function loadFromStorage() {
    try {
      const raw = localStorage.getItem(SETUP_STORAGE_KEY)
      if (!raw) return
      const data = JSON.parse(raw)
      if (data.workingDays?.length) workingDays.value = data.workingDays
      if (data.selectedStages?.length) {
        selectedStages.value = data.selectedStages
      } else if (data.stageType) {
        selectedStages.value = normalizeSelectedStagesFromSettings({
          educational_stage: data.stageType,
        })
      }
      if (data.customStageName) customStageName.value = data.customStageName
      if (data.dailyLessons) dailyLessons.value = data.dailyLessons
      if (data.startTime) startTime.value = data.startTime
      if (data.lessonDurationMin) lessonDurationMin.value = data.lessonDurationMin
      if (data.gapBetweenLessonsMin) gapBetweenLessonsMin.value = data.gapBetweenLessonsMin
      if (data.breaks) breaks.value = data.breaks
      if (data.assignByRole) assignByRole.value = data.assignByRole
    } catch (_) {}
  }

  function persistToStorage() {
    localStorage.setItem(
      SETUP_STORAGE_KEY,
      JSON.stringify({
        workingDays: workingDays.value,
        selectedStages: selectedStages.value,
        customStageName: customStageName.value,
        dailyLessons: dailyLessons.value,
        startTime: startTime.value,
        lessonDurationMin: lessonDurationMin.value,
        gapBetweenLessonsMin: gapBetweenLessonsMin.value,
        breaks: breaks.value,
        assignByRole: assignByRole.value,
      })
    )
  }

  function rebuildPreview() {
    previewSlots.value = buildPeriodSchedule({
      dailyLessons: dailyLessons.value,
      startTime: startTime.value,
      lessonDurationMin: lessonDurationMin.value,
      gapBetweenLessonsMin: gapBetweenLessonsMin.value,
      breaks: breaks.value,
    })
  }

  watch(
    [dailyLessons, startTime, lessonDurationMin, gapBetweenLessonsMin, breaks],
    () => {
      if (currentStep.value >= 3) rebuildPreview()
    },
    { deep: true }
  )

  function hydrateStagesFromSettings(settings) {
    selectedStages.value = normalizeSelectedStagesFromSettings(settings)
    if (settings?.custom_stage_name) customStageName.value = settings.custom_stage_name
  }

  function toggleStage(stageId) {
    const idx = selectedStages.value.indexOf(stageId)
    if (idx >= 0) {
      if (selectedStages.value.length > 1) {
        selectedStages.value = selectedStages.value.filter((id) => id !== stageId)
      }
    } else {
      selectedStages.value = [...selectedStages.value, stageId]
    }
  }

  function isStageSelected(stageId) {
    return selectedStages.value.includes(stageId)
  }

  function toggleDay(dayKey) {
    const idx = workingDays.value.indexOf(dayKey)
    if (idx >= 0) {
      if (workingDays.value.length > 1) {
        workingDays.value = workingDays.value.filter((d) => d !== dayKey)
      }
    } else {
      workingDays.value = [...workingDays.value, dayKey]
    }
  }

  function addBreak() {
    breaks.value.push({
      id: `br_${Date.now()}`,
      name: 'فسحة',
      afterPeriod: 3,
      durationMin: 20,
    })
    rebuildPreview()
  }

  function removeBreak(id) {
    breaks.value = breaks.value.filter((b) => b.id !== id)
    rebuildPreview()
  }

  function initSubjectRows(subjects = [], teacherBySubject = {}) {
    subjectRows.value = (subjects ?? []).map((s, i) => ({
      subject_id: s.id,
      name: s.name,
      periods_per_week: [6, 5, 4, 3, 3, 2, 2, 1][i] ?? 2,
      teacher_id: teacherBySubject[s.id] ?? null,
    }))
  }

  function initTeacherAvailability(teachers = [], saved = null) {
    const map = {}
    const days = workingDays.value.length ? workingDays.value : DEFAULT_WORKING
    for (const t of teachers) {
      const prev = saved?.[t.id]
      map[t.id] = {
        days: prev?.days?.length ? [...prev.days] : [...days],
        blocked_slots: prev?.blocked_slots ? [...prev.blocked_slots] : [],
      }
    }
    teacherAvailability.value = map
  }

  function toggleTeacherDay(teacherId, dayName) {
    const av = teacherAvailability.value[teacherId]
    if (!av) return
    const idx = av.days.indexOf(dayName)
    if (idx >= 0) {
      if (av.days.length > 1) av.days = av.days.filter((d) => d !== dayName)
    } else {
      av.days.push(dayName)
    }
  }

  function toggleBlockedPeriod(teacherId, dayName, periodNumber, timeFrom, timeTo) {
    const av = teacherAvailability.value[teacherId]
    if (!av) return
    const key = `${dayName}-${periodNumber}`
    const idx = av.blocked_slots.findIndex(
      (b) => b.day_name === dayName && Number(b.period_number) === Number(periodNumber)
    )
    if (idx >= 0) {
      av.blocked_slots.splice(idx, 1)
    } else {
      av.blocked_slots.push({
        day_name: dayName,
        period_number: periodNumber,
        time_from: timeFrom,
        time_to: timeTo,
      })
    }
  }

  function isPeriodBlocked(teacherId, dayName, periodNumber) {
    const av = teacherAvailability.value[teacherId]
    if (!av) return false
    return av.blocked_slots.some(
      (b) => b.day_name === dayName && Number(b.period_number) === Number(periodNumber)
    )
  }

  function getSettingsPayload() {
    const stages = [...selectedStages.value]
    return {
      working_days: workingDays.value,
      selected_stages: stages,
      /** @deprecated legacy single stage — first selected */
      educational_stage: stages[0] ?? null,
      educational_stage_label: stageLabelsSummary.value,
      educational_stages_labels: stages.map((id) =>
        id === 'custom' ? customStageName.value.trim() || 'مخصص' : stageLabelById(id)
      ),
      custom_stage_name: customStageName.value,
      daily_lessons: dailyLessons.value,
      start_time: startTime.value,
      lesson_duration_min: lessonDurationMin.value,
      gap_between_lessons_min: gapBetweenLessonsMin.value,
      breaks: breaks.value,
      period_structure: previewSlots.value,
      assign_by_role: assignByRole.value,
      teacher_availability: teacherAvailability.value,
      subject_requirements: subjectRows.value,
    }
  }

  function validateStep(step) {
    if (step === 1 && workingDays.value.length < 1) {
      return 'اختر يوم عمل واحد على الأقل'
    }
    if (step === 2) {
      if (selectedStages.value.length < 1) return 'اختر مرحلة دراسية واحدة على الأقل'
      if (selectedStages.value.includes('custom') && !customStageName.value.trim()) {
        return 'أدخل اسم المرحلة المخصصة'
      }
    }
    if (step === 3) {
      if (dailyLessons.value < 1 || dailyLessons.value > 16) return 'عدد الحصص يجب أن يكون بين 1 و 16'
      if (lessonDurationMin.value < 20 || lessonDurationMin.value > 120) {
        return 'مدة الحصة بين 20 و 120 دقيقة'
      }
    }
    if (step === 5 && !previewSlots.value.length) {
      rebuildPreview()
      if (!previewSlots.value.length) return 'تعذر حساب هيكل الحصص'
    }
    if (step === 7) {
      const missing = subjectRows.value.filter((r) => r.periods_per_week > 0 && !r.teacher_id)
      if (missing.length) return 'عيّن معلماً لكل مادة لها حصص أسبوعية'
    }
    return null
  }

  function nextStep() {
    const err = validateStep(currentStep.value)
    if (err) return err
    if (currentStep.value === 4) rebuildPreview()
    if (currentStep.value < 8) currentStep.value += 1
    persistToStorage()
    return null
  }

  function prevStep() {
    if (currentStep.value > 1) currentStep.value -= 1
  }

  function goToStep(n) {
    if (n >= 1 && n <= 8) currentStep.value = n
    if (n >= 5) rebuildPreview()
  }

  function reset() {
    currentStep.value = 1
    workingDays.value = [...DEFAULT_WORKING]
    selectedStages.value = ['primary']
    customStageName.value = ''
    dailyLessons.value = 8
    startTime.value = '08:00'
    lessonDurationMin.value = 45
    gapBetweenLessonsMin.value = 5
    breaks.value = []
    previewSlots.value = []
    assignByRole.value = 'admin'
    subjectRows.value = []
    rebuildPreview()
  }

  function open() {
    loadFromStorage()
    rebuildPreview()
    currentStep.value = 1
  }

  return {
    currentStep,
    processing,
    workingDays,
    selectedStages,
    customStageName,
    selectedStagesCount,
    stageLabelsSummary,
    hasCustomStage,
    hydrateStagesFromSettings,
    toggleStage,
    isStageSelected,
    normalizeSelectedStagesFromSettings,
    dailyLessons,
    startTime,
    lessonDurationMin,
    gapBetweenLessonsMin,
    breaks,
    previewSlots,
    assignByRole,
    subjectRows,
    teacherAvailability,
    setupSubStep,
    initTeacherAvailability,
    toggleTeacherDay,
    toggleBlockedPeriod,
    isPeriodBlocked,
    selectedDaysCount,
    stageLabel,
    SETUP_STEPS,
    toggleDay,
    addBreak,
    removeBreak,
    rebuildPreview,
    initSubjectRows,
    getSettingsPayload,
    validateStep,
    nextStep,
    prevStep,
    goToStep,
    reset,
    open,
    persistToStorage,
  }
}
