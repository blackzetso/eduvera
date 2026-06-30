import { ref, reactive, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import {
  runIntelligentGeneration,
  groupConflictsByTeacher,
  buildTimetableBackupSnapshot,
  saveLocalTimetableBackup,
  listLocalTimetableBackups,
} from '@/Services/TimetableGenerationEngine'
const STORAGE_KEY = 'eduvera_timetable_wizard_v1'
const DEFAULT_DAYS = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس']
const SUBJECT_COLORS = ['#0d6efd', '#198754', '#dc3545', '#fd7e14', '#6f42c1', '#20c997', '#d63384', '#0dcaf0']

export function useTimetableWizard(getProps) {
  const props = () => getProps()
  const currentStep = ref(1)
  const processing = ref(false)
  const viewMode = ref('class')
  const lastGenerationReport = ref(null)
  const highlightPeriodId = ref(null)

  const wizardMeta = reactive(loadMeta())

  const periodTemplates = ref(loadPeriodTemplates())

  const subjectRequirements = ref(
    loadSubjectRequirements(props().subjects)
  )

  const teacherBySubject = reactive(loadTeacherMap())

  const form = reactive({
    name: props().timetable?.name ?? 'الجدول الدراسي',
    academic_year: props().timetable?.academic_year ?? defaultAcademicYear(),
    status: props().timetable?.status ?? 'active',
  })

  function defaultAcademicYear() {
    const y = new Date().getFullYear()
    return `${y}-${y + 1}`
  }

  function loadMeta() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY)
      if (raw) {
        const data = JSON.parse(raw)
        return { ...defaultMeta(), ...data.meta }
      }
    } catch (_) {}
    return defaultMeta()
  }

  function defaultMeta() {
    return {
      term: 'الفصل الأول',
      branch: 'الفرع الرئيسي',
      stage_id: null,
      grade_id: null,
      class_category_id: null,
    }
  }

  function loadPeriodTemplates() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY)
      if (raw) {
        const data = JSON.parse(raw)
        if (data.periodTemplates?.length) return data.periodTemplates
      }
    } catch (_) {}
    return [
      { id: 'p1', period_number: 1, time_from: '08:00', time_to: '08:45', kind: 'lesson', label: 'الحصة 1' },
      { id: 'p2', period_number: 2, time_from: '08:50', time_to: '09:35', kind: 'lesson', label: 'الحصة 2' },
      { id: 'p3', period_number: 3, time_from: '09:45', time_to: '10:30', kind: 'lesson', label: 'الحصة 3' },
      { id: 'br1', period_number: 0, time_from: '10:30', time_to: '10:45', kind: 'break', label: 'استراحة' },
      { id: 'p4', period_number: 4, time_from: '10:45', time_to: '11:30', kind: 'lesson', label: 'الحصة 4' },
    ]
  }

  function loadSubjectRequirements(subjects) {
    try {
      const raw = localStorage.getItem(STORAGE_KEY)
      if (raw) {
        const data = JSON.parse(raw)
        if (data.subjectRequirements?.length) return data.subjectRequirements
      }
    } catch (_) {}
    return (props().subjects ?? []).slice(0, 8).map((s, i) => ({
      subject_id: s.id,
      name: s.name,
      periods_per_week: [6, 5, 4, 3, 3, 2, 2, 1][i] ?? 2,
      color: SUBJECT_COLORS[i % SUBJECT_COLORS.length],
    }))
  }

  function loadTeacherMap() {
    const map = {}
    try {
      const raw = localStorage.getItem(STORAGE_KEY)
      if (raw) {
        const data = JSON.parse(raw)
        Object.assign(map, data.teacherBySubject ?? {})
      }
    } catch (_) {}
    return map
  }

  function persistWizard() {
    localStorage.setItem(
      STORAGE_KEY,
      JSON.stringify({
        meta: wizardMeta,
        periodTemplates: periodTemplates.value,
        subjectRequirements: subjectRequirements.value,
        teacherBySubject,
      })
    )
  }

  watch([wizardMeta, periodTemplates, subjectRequirements, teacherBySubject], persistWizard, { deep: true })

  const totalRequiredPeriods = computed(() =>
    subjectRequirements.value.reduce((s, r) => s + (Number(r.periods_per_week) || 0), 0)
  )

  const lessonPeriodTemplates = computed(() =>
    periodTemplates.value.filter((p) => p.kind === 'lesson')
  )

  const periodsPerWeekCapacity = computed(() => {
    const days = props().timetable?.days?.length || DEFAULT_DAYS.length
    return lessonPeriodTemplates.value.length * days
  })

  const remainingPeriods = computed(() =>
    Math.max(0, periodsPerWeekCapacity.value - totalRequiredPeriods.value)
  )

  const allPeriods = computed(() => {
    const list = []
    props().timetable?.days?.forEach((day) => {
      day.periods?.forEach((period) => {
        list.push({ ...period, day_name: day.day_name, timetable_day_id: day.id })
      })
    })
    return list
  })

  const filteredPeriods = computed(() => {
    if (!wizardMeta.class_category_id) return allPeriods.value
    const id = Number(wizardMeta.class_category_id)
    return allPeriods.value.filter((p) => p.category_id === id)
  })

  const displayPeriods = computed(() => {
    let list = filteredPeriods.value
    const ctx = props().assignmentContext
    if (ctx?.visible_category_ids?.length) {
      const ids = new Set(ctx.visible_category_ids.map(Number))
      list = list.filter((p) => !p.category_id || ids.has(Number(p.category_id)))
    }
    return list
  })

  const gridPeriods = computed(() =>
    props().assignmentContext?.role === 'teacher' ? displayPeriods.value : filteredPeriods.value
  )

  function subjectColor(subjectId) {
    const req = subjectRequirements.value.find((r) => r.subject_id === subjectId)
    return req?.color ?? '#6c757d'
  }

  function getMainAssignment(period) {
    return period.assignments?.find((a) => a.type === 'main')
  }

  const assignedCount = computed(() =>
    filteredPeriods.value.filter((p) => getMainAssignment(p)).length
  )

  const emptyPeriodCount = computed(() =>
    filteredPeriods.value.filter((p) => !getMainAssignment(p)).length
  )

  const isTimetableEmpty = computed(() => {
    if (!filteredPeriods.value.length) return true
    return assignedCount.value === 0
  })

  const validationWarnings = computed(() => {
    const warnings = []
    const mainAssignments = []

    filteredPeriods.value.forEach((period) => {
      const main = getMainAssignment(period)
      if (!main) {
        warnings.push({
          type: 'empty',
          message: `حصة فارغة: ${period.day_name} — ${period.time_from}`,
          periodId: period.id,
        })
      } else {
        mainAssignments.push({ period, assignment: main })
      }
    })

    const byTeacher = {}
    mainAssignments.forEach(({ period, assignment }) => {
      const tid = assignment.teacher_id
      if (!byTeacher[tid]) byTeacher[tid] = []
      byTeacher[tid].push({ period, assignment })
    })

    Object.entries(byTeacher).forEach(([teacherId, items]) => {
      const teacher = props().teachers?.find((t) => t.id === Number(teacherId))
      const name = teacher?.name ?? 'مدرس'
      const load = items.length
      const required = Object.entries(teacherBySubject)
        .filter(([, tid]) => Number(tid) === Number(teacherId))
        .reduce((s, [, sid]) => {
          const req = subjectRequirements.value.find((r) => r.subject_id === Number(sid))
          return s + (req?.periods_per_week ?? 0)
        }, 0)

      for (let i = 0; i < items.length; i++) {
        for (let j = i + 1; j < items.length; j++) {
          const a = items[i].period
          const b = items[j].period
          if (
            a.timetable_day_id === b.timetable_day_id &&
            a.time_from < b.time_to &&
            a.time_to > b.time_from
          ) {
            warnings.push({
              type: 'conflict',
              message: `تعارض للمدرس ${name} في ${a.day_name} بين ${a.time_from} و ${b.time_from}`,
              teacherId: Number(teacherId),
              assignmentIds: [items[i].assignment.id, items[j].assignment.id],
              periodIds: [a.id, b.id],
            })
          }
        }
      }
    })

    if (remainingPeriods.value > 0) {
      warnings.push({
        type: 'balance',
        message: `${remainingPeriods.value} حصة أسبوعية غير موزعة بعد`,
      })
    }

    return warnings
  })

  const conflictWarnings = computed(() =>
    validationWarnings.value.filter((w) => w.type === 'conflict')
  )

  const conflictCount = computed(() => conflictWarnings.value.length)

  const groupedConflicts = computed(() => groupConflictsByTeacher(conflictWarnings.value))

  const validationErrors = computed(() =>
    validationWarnings.value.filter((w) => w.type === 'empty' || w.type === 'conflict')
  )

  const overloadedTeacherCount = computed(() => {
    const ids = new Set()
    validationWarnings.value
      .filter((w) => w.type === 'overload')
      .forEach((w) => {
        if (w.teacherId) ids.add(w.teacherId)
      })
    return ids.size
  })

  const timetableStatus = computed(() => {
    const total = filteredPeriods.value.length
    const filled = assignedCount.value
    const empty = emptyPeriodCount.value
    const conflicts = conflictCount.value
    const overloaded = overloadedTeacherCount.value

    let quality = 100
    if (total > 0) {
      const fillRatio = filled / total
      quality = Math.round(
        fillRatio * 40 +
          (conflicts === 0 ? 30 : Math.max(0, 30 - conflicts * 8)) +
          (empty === 0 ? 20 : Math.max(0, 20 - Math.min(empty, 10) * 2)) +
          (overloaded === 0 ? 10 : Math.max(0, 10 - overloaded * 3))
      )
      quality = Math.min(100, Math.max(0, quality))
    } else {
      quality = 0
    }

    return {
      totalPeriods: total,
      filledPeriods: filled,
      emptyPeriods: empty,
      conflictCount: conflicts,
      overloadedTeachers: overloaded,
      qualityScore: quality,
    }
  })

  const aiInsights = computed(() => {
    const tips = []

    const byDaySubject = {}
    filteredPeriods.value.forEach((p) => {
      const main = getMainAssignment(p)
      if (!main) return
      const key = p.day_name
      if (!byDaySubject[key]) byDaySubject[key] = {}
      const sid = main.subject_id
      byDaySubject[key][sid] = (byDaySubject[key][sid] ?? 0) + 1
    })

    Object.entries(byDaySubject).forEach(([day, counts]) => {
      Object.entries(counts).forEach(([sid, count]) => {
        if (count >= 3) {
          const sub = props().subjects?.find((s) => s.id === Number(sid))
          tips.push({
            category: 'concentration',
            icon: 'bi-book',
            message: `${sub?.name ?? 'مادة'} مركّزة في يوم ${day} (${count} حصص).`,
          })
        }
      })
    })

    Object.entries(teacherLoads.value).forEach(([tid, load]) => {
      const required = subjectRequirements.value
        .filter((r) => Number(teacherBySubject[r.subject_id]) === Number(tid))
        .reduce((s, r) => s + (r.periods_per_week ?? 0), 0)
      if (required && load >= required - 1) {
        const teacher = props().teachers?.find((t) => t.id === Number(tid))
        tips.push({
          category: 'overload',
          icon: 'bi-person-exclamation',
          message: `المعلم ${teacher?.name ?? ''} قريب من الحد الأقصى للأعباء (${load}/${required}).`,
        })
      }
    })

    if (emptyPeriodCount.value > 0) {
      tips.push({
        category: 'empty',
        icon: 'bi-calendar-x',
        message: `يوجد ${emptyPeriodCount.value} حصة فارغة في الجدول الحالي.`,
      })
    }

    if (timetableStatus.value.qualityScore >= 75 && conflictCount.value === 0) {
      tips.push({
        category: 'distribution',
        icon: 'bi-pie-chart',
        message: 'توزيع الجدول متوازن نسبياً — يمكن تحسينه أكثر عبر «تحسين الجدول».',
      })
    } else if (timetableStatus.value.qualityScore < 50) {
      tips.push({
        category: 'distribution',
        icon: 'bi-pie-chart',
        message: 'جودة التوزيع منخفضة — يُنصح بمراجعة متطلبات المواد أو إعادة الإنشاء.',
      })
    }

    const math = subjectRequirements.value.find((r) =>
      r.name?.includes('رياض') || r.name?.toLowerCase?.().includes('math')
    )
    if (math && math.periods_per_week >= 4) {
      tips.push({
        category: 'recommendation',
        icon: 'bi-lightbulb',
        message: 'يُفضّل توزيع حصص الرياضيات على أكثر من يومين لتحسين الاستيعاب.',
      })
    }

    const science = subjectRequirements.value.find((r) =>
      r.name?.includes('علوم') || r.name?.toLowerCase?.().includes('science')
    )
    if (science && science.periods_per_week >= 3) {
      tips.push({
        category: 'recommendation',
        icon: 'bi-lightbulb',
        message: 'يفضل توزيع العلوم على ثلاثة أيام.',
      })
    }

    if (lastGenerationReport.value?.recommendations?.length) {
      lastGenerationReport.value.recommendations.forEach((msg) => {
        if (!tips.some((t) => t.message === msg)) {
          tips.push({ category: 'recommendation', icon: 'bi-lightbulb', message: msg })
        }
      })
    }

    if (remainingPeriods.value > 2) {
      tips.push({
        category: 'recommendation',
        icon: 'bi-lightbulb',
        message: 'متطلبات المواد تتجاوز سعة الجدول — راجع عدد الحصص الأسبوعية.',
      })
    }

    return tips.slice(0, 8)
  })

  /** @deprecated use aiInsights */
  const aiRecommendations = aiInsights

  const teacherLoads = computed(() => {
    const loads = {}
    filteredPeriods.value.forEach((p) => {
      const main = getMainAssignment(p)
      if (!main) return
      const tid = main.teacher_id
      loads[tid] = (loads[tid] ?? 0) + 1
    })
    return loads
  })

  function addPeriodTemplate() {
    const n = periodTemplates.value.filter((p) => p.kind === 'lesson').length + 1
    periodTemplates.value.push({
      id: `p_${Date.now()}`,
      period_number: n,
      time_from: '12:00',
      time_to: '12:45',
      kind: 'lesson',
      label: `الحصة ${n}`,
    })
  }

  function removePeriodTemplate(id) {
    periodTemplates.value = periodTemplates.value.filter((p) => p.id !== id)
  }

  function addSubjectRequirement() {
    const first = props().subjects?.find(
      (s) => !subjectRequirements.value.some((r) => r.subject_id === s.id)
    )
    if (!first) return
    subjectRequirements.value.push({
      subject_id: first.id,
      name: first.name,
      periods_per_week: 2,
      color: SUBJECT_COLORS[subjectRequirements.value.length % SUBJECT_COLORS.length],
    })
  }

  async function ensureSchoolDays() {
    let existing = props().timetable?.days ?? []
    if (existing.length >= DEFAULT_DAYS.length) return existing

    const names = new Set(existing.map((d) => d.day_name))
    let order = existing.length

    for (const dayName of DEFAULT_DAYS) {
      if (names.has(dayName)) continue
      order += 1
      await postForm(route('admin.timetable.days.add'), {
        day_name: dayName,
        day_order: order,
      })
    }

    await reloadTimetable()
    return props().timetable?.days ?? []
  }

  async function applyPeriodStructure() {
    processing.value = true
    try {
      await saveBasicInfo(false)
      const days = await ensureSchoolDays()
      const lessons = lessonPeriodTemplates.value
      const categoryId = wizardMeta.class_category_id || 'all'

      for (const day of days) {
        for (const tmpl of lessons) {
          await postForm(route('admin.timetable.periods.add'), {
            timetable_day_id: day.id,
            period_number: tmpl.period_number,
            time_from: tmpl.time_from,
            time_to: tmpl.time_to,
            category_id: categoryId,
          })
        }
      }

      await reloadTimetable()
      return true
    } finally {
      processing.value = false
    }
  }

  function reloadTimetable() {
    return new Promise((resolve) => {
      router.reload({
        only: ['timetable'],
        onFinish: () => resolve(),
      })
    })
  }

  function postForm(url, data) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content
    return window.axios.post(url, data, {
      headers: token ? { 'X-CSRF-TOKEN': token } : {},
    })
  }

  function deletePeriod(id) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content
    return window.axios.delete(route('admin.timetable.periods.delete', id), {
      headers: token ? { 'X-CSRF-TOKEN': token } : {},
    })
  }

  function deleteDay(id) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content
    return window.axios.delete(route('admin.timetable.days.delete', id), {
      headers: token ? { 'X-CSRF-TOKEN': token } : {},
    })
  }

  function saveBasicInfo(reload = true, extra = {}) {
    return new Promise((resolve, reject) => {
      router.put(
        route('admin.timetable.update'),
        {
          name: form.name,
          academic_year: form.academic_year,
          status: form.status,
          ...extra,
        },
        {
          preserveScroll: true,
          onSuccess: () => {
            if (reload) router.reload({ only: ['timetable'] })
            resolve()
          },
          onError: reject,
        }
      )
    })
  }

  function getTimetableSettings() {
    return props().timetable?.settings ?? {}
  }

  function getWorkingDaysFromSettings() {
    const s = getTimetableSettings()
    return s.working_days?.length ? s.working_days : DEFAULT_DAYS
  }

  function buildGenerationRequirements(overrideRows = null) {
    const rows = overrideRows ?? subjectRequirements.value
    return rows.map((r) => ({
      subject_id: r.subject_id,
      name: r.name,
      periods_per_week: Number(r.periods_per_week) || 0,
      teacher_id: r.teacher_id ?? teacherBySubject[r.subject_id] ?? null,
    }))
  }

  function getRegenerateImpactStats() {
    let periods = 0
    let assignments = 0
    const subjectIds = new Set()
    const teacherIds = new Set()
    filteredPeriods.value.forEach((p) => {
      periods += 1
      const main = getMainAssignment(p)
      if (main) {
        assignments += 1
        subjectIds.add(main.subject_id)
        teacherIds.add(main.teacher_id)
      }
    })
    return {
      periods,
      subjects: subjectIds.size,
      teachers: teacherIds.size,
      assignments,
    }
  }

  async function autoGenerateTimetable(options = {}) {
    const { clearExisting = true, subjectRows = null, settings = null } = options
    processing.value = true
    lastGenerationReport.value = null

    try {
      if (clearExisting) await clearAllAssignments()

      const settingsMerged = { ...getTimetableSettings(), ...(settings ?? {}) }
      const workingDays = settingsMerged.working_days ?? getWorkingDaysFromSettings()
      const reqs = buildGenerationRequirements(subjectRows)

      const deptPlan = settingsMerged.department_plan?.teacher_load_distribution ?? null
      const loadDistribution =
        deptPlan && Object.keys(deptPlan).length
          ? deptPlan
          : settingsMerged.teacher_load_distribution ?? null

      const result = runIntelligentGeneration({
        periods: filteredPeriods.value,
        subjectRequirements: reqs,
        teacherAvailability: settingsMerged.teacher_availability,
        teachers: props().teachers ?? [],
        workingDays,
        periodStructure: settingsMerged.period_structure ?? periodTemplates.value,
        teacherLoadDistribution: loadDistribution,
        departmentPlanPriority: Boolean(deptPlan && Object.keys(deptPlan).length),
      })

      lastGenerationReport.value = result.report

      if (result.report.errors?.length && !result.assignments.length) {
        throw new Error(result.report.errors[0])
      }

      for (const assignment of result.assignments) {
        await postForm(route('admin.timetable.assign-teacher'), assignment)
      }

      await reloadTimetable()
      return result
    } finally {
      processing.value = false
    }
  }

  async function copyFromBackup(backup) {
    if (!backup?.assignments?.length) return false
    processing.value = true
    try {
      await clearAllAssignments()
      for (const a of backup.assignments) {
        await postForm(route('admin.timetable.assign-teacher'), {
          timetable_period_id: a.timetable_period_id,
          teacher_id: a.teacher_id,
          subject_id: a.subject_id,
          type: a.type || 'main',
        })
      }
      await reloadTimetable()
      return true
    } finally {
      processing.value = false
    }
  }

  function createBackupBeforeRegenerate() {
    const snapshot = buildTimetableBackupSnapshot(props().timetable)
    saveLocalTimetableBackup(snapshot)
    const settings = getTimetableSettings()
    const backups = settings.timetable_backups ?? []
    backups.unshift({ ...snapshot, label: snapshot.saved_at })
    return saveBasicInfo(false, {
      settings: { ...settings, timetable_backups: backups.slice(0, 5) },
    })
  }

  function teacherHasConflictAt(teacherId, period, excludePeriodId = null) {
    return filteredPeriods.value.some((p) => {
      if (p.id === period.id || p.id === excludePeriodId) return false
      const main = getMainAssignment(p)
      if (!main || main.teacher_id !== teacherId) return false
      if (p.timetable_day_id !== period.timetable_day_id) return false
      return p.time_from < period.time_to && p.time_to > period.time_from
    })
  }

  async function clearAllAssignments() {
    const ids = []
    filteredPeriods.value.forEach((p) => {
      const main = getMainAssignment(p)
      if (main) ids.push(main.id)
    })
    for (const id of ids) {
      await deleteAssignment(id, false)
    }
    await reloadTimetable()
  }

  function deleteAssignment(id, reload = true) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content
    return window.axios
      .delete(route('admin.timetable.assignments.remove', id), {
        headers: token ? { 'X-CSRF-TOKEN': token } : {},
      })
      .then(() => (reload ? reloadTimetable() : Promise.resolve()))
  }

  async function optimizeSchedule() {
    processing.value = true
    try {
      await fillEmptySlots()

      const byDaySubject = {}
      filteredPeriods.value.forEach((p) => {
        const main = getMainAssignment(p)
        if (!main) return
        const key = `${p.day_name}:${main.subject_id}`
        if (!byDaySubject[key]) byDaySubject[key] = []
        byDaySubject[key].push({ period: p, assignment: main })
      })

      for (const [, items] of Object.entries(byDaySubject)) {
        if (items.length < 3) continue
        const { period, assignment } = items[0]
        const emptyTargets = filteredPeriods.value
          .filter((p) => !getMainAssignment(p) && p.day_name !== period.day_name)
          .sort((a, b) => a.timetable_day_id - b.timetable_day_id)

        for (const target of emptyTargets) {
          if (teacherHasConflictAt(assignment.teacher_id, target)) continue
          await deleteAssignment(assignment.id, false)
          await postForm(route('admin.timetable.assign-teacher'), {
            timetable_period_id: target.id,
            teacher_id: assignment.teacher_id,
            subject_id: assignment.subject_id,
            type: 'main',
          })
          break
        }
      }

      await reloadTimetable()
    } finally {
      processing.value = false
    }
  }

  async function fillEmptySlots() {
    const slots = filteredPeriods.value
      .filter((p) => !getMainAssignment(p))
      .sort((a, b) => {
        if (a.timetable_day_id !== b.timetable_day_id) return a.timetable_day_id - b.timetable_day_id
        return a.period_number - b.period_number
      })

    const queue = []
    subjectRequirements.value.forEach((req) => {
      for (let i = 0; i < (req.periods_per_week || 0); i++) queue.push(req)
    })

    const alreadyAssigned = {}
    filteredPeriods.value.forEach((p) => {
      const main = getMainAssignment(p)
      if (main) {
        alreadyAssigned[main.subject_id] = (alreadyAssigned[main.subject_id] ?? 0) + 1
      }
    })

    let slotIndex = 0
    for (const req of queue) {
      const need = req.periods_per_week || 0
      const have = alreadyAssigned[req.subject_id] ?? 0
      if (have >= need) continue
      if (slotIndex >= slots.length) break
      const period = slots[slotIndex++]
      const teacherId = teacherBySubject[req.subject_id]
      if (!teacherId || teacherHasConflictAt(teacherId, period)) continue

      await postForm(route('admin.timetable.assign-teacher'), {
        timetable_period_id: period.id,
        teacher_id: teacherId,
        subject_id: req.subject_id,
        type: 'main',
      })
      alreadyAssigned[req.subject_id] = (alreadyAssigned[req.subject_id] ?? 0) + 1
    }
  }

  async function regenerateTimetable() {
    await clearAllAssignments()
    await autoGenerateTimetable()
  }

  function syncWizardFromSetup(setup) {
    if (setup.workingDays?.length) {
      wizardMeta.branch = wizardMeta.branch || 'الفرع الرئيسي'
    }
    if (setup.previewSlots?.length) {
      periodTemplates.value = setup.previewSlots.map((s, i) => ({
        ...s,
        id: s.id || `setup_${i}`,
      }))
    }
    if (setup.subjectRows?.length) {
      subjectRequirements.value = setup.subjectRows.map((r, i) => ({
        subject_id: r.subject_id,
        name: r.name,
        periods_per_week: r.periods_per_week,
        color: SUBJECT_COLORS[i % SUBJECT_COLORS.length],
      }))
      setup.subjectRows.forEach((r) => {
        if (r.teacher_id) teacherBySubject[r.subject_id] = r.teacher_id
      })
    }
    persistWizard()
  }

  async function applySetupFramework(setup) {
    const settings =
      typeof setup.getSettingsPayload === 'function'
        ? setup.getSettingsPayload()
        : setup.settings ?? {}

    const workingDays = [
      ...(setup.workingDays ?? settings.working_days ?? DEFAULT_DAYS),
    ]
    const slots = JSON.parse(
      JSON.stringify(
        setup.previewSlots ?? settings.period_structure ?? periodTemplates.value
      )
    )

    const payload = {
      name: form.name,
      academic_year: form.academic_year,
      status: form.status,
      settings: JSON.parse(JSON.stringify(settings)),
      working_days: workingDays,
      period_structure: slots,
      category_id: wizardMeta.class_category_id || 'all',
    }

    processing.value = true

    try {
      await new Promise((resolve, reject) => {
        router.post(route('admin.timetable.save-framework'), payload, {
          preserveScroll: true,
          preserveState: true,
          onSuccess: () => resolve(),
          onError: (errors) => {
            const msg =
              errors?.framework ||
              (errors && typeof errors === 'object'
                ? Object.values(errors).flat().join(' ')
                : null) ||
              'تعذر حفظ الهيكل'
            reject(new Error(msg))
          },
          onFinish: () => {
            processing.value = false
          },
        })
      })

      syncWizardFromSetup({
        previewSlots: slots,
        subjectRows: setup.subjectRows,
        workingDays,
      })

      await reloadTimetable()
      return true
    } catch (e) {
      if (processing.value) processing.value = false
      throw e
    }
  }

  async function resolveConflictsAuto() {
    processing.value = true
    try {
      const conflicts = [...conflictWarnings.value]
      for (const c of conflicts) {
        const ids = c.assignmentIds ?? []
        if (ids.length < 2) continue
        const toRemove = ids[ids.length - 1]
        await deleteAssignment(toRemove, false)
      }

      const emptyList = validationWarnings.value.filter((w) => w.type === 'empty')
      await fillEmptySlots()

      for (const w of emptyList.slice(0, 5)) {
        if (!w.periodId) continue
        const period = filteredPeriods.value.find((p) => p.id === w.periodId)
        if (!period || getMainAssignment(period)) continue
        const req = subjectRequirements.value.find((r) => teacherBySubject[r.subject_id])
        if (!req) continue
        const teacherId = teacherBySubject[req.subject_id]
        if (!teacherId || teacherHasConflictAt(teacherId, period)) continue
        await postForm(route('admin.timetable.assign-teacher'), {
          timetable_period_id: period.id,
          teacher_id: teacherId,
          subject_id: req.subject_id,
          type: 'main',
        })
      }

      await reloadTimetable()
    } finally {
      processing.value = false
    }
  }

  function nextStep() {
    if (currentStep.value < 6) currentStep.value += 1
  }

  function prevStep() {
    if (currentStep.value > 1) currentStep.value -= 1
  }

  function goToStep(n) {
    currentStep.value = n
  }

  function getTeacherLoadDistribution() {
    return getTimetableSettings().teacher_load_distribution ?? {}
  }

  function getDistributionPriorityRules() {
    const s = getTimetableSettings()
    return {
      specializationFirst: true,
      educationalStage: true,
      gradeClass: true,
      maxWeeklyLoad: true,
      balancedLoad: true,
      ...(s.distribution_priority_rules ?? {}),
    }
  }

  const hasApprovedTeacherDistribution = computed(() => {
    const dist = getTeacherLoadDistribution()
    const hasRows = Object.values(dist).some((loads) => Array.isArray(loads) && loads.length > 0)
    return hasRows && !!getTimetableSettings().teacher_load_distribution_approved_at
  })

  function enrichTeachersForDistribution() {
    return (props().teachers ?? []).map((t) => ({
      ...t,
      subject_ids: (t.subjects ?? []).map((s) => s.id),
      specialization: (t.subjects ?? []).map((s) => s.name).join('، ') || t.department || '—',
    }))
  }

  async function applyTeacherLoadDistribution({ distribution, rules }) {
    processing.value = true
    try {
      const settings = {
        ...getTimetableSettings(),
        teacher_load_distribution: distribution,
        distribution_priority_rules: rules,
        teacher_load_distribution_approved_at: new Date().toISOString(),
      }
      await saveBasicInfo(false, { settings })

      for (const [subjectKey, loads] of Object.entries(distribution)) {
        if (!Array.isArray(loads) || !loads.length) continue
        const primary = [...loads].sort((a, b) => (b.periods || 0) - (a.periods || 0))[0]
        if (primary?.teacher_id) {
          teacherBySubject[subjectKey] = primary.teacher_id
          teacherBySubject[Number(subjectKey)] = primary.teacher_id
        }
      }
      persistWizard()
      return true
    } finally {
      processing.value = false
    }
  }

  const steps = [
    { n: 1, title: 'البيانات الأساسية' },
    { n: 2, title: 'هيكل الحصص' },
    { n: 3, title: 'متطلبات المواد' },
    { n: 4, title: 'تعيين المعلمين' },
    { n: 5, title: 'توليد تلقائي' },
    { n: 6, title: 'الجدول المرئي' },
  ]

  return {
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
    lessonPeriodTemplates,
    periodsPerWeekCapacity,
    remainingPeriods,
    filteredPeriods,
    displayPeriods,
    gridPeriods,
    allPeriods,
    isTimetableEmpty,
    assignedCount,
    emptyPeriodCount,
    timetableStatus,
    conflictWarnings,
    conflictCount,
    groupedConflicts,
    validationErrors,
    validationWarnings,
    lastGenerationReport,
    highlightPeriodId,
    getRegenerateImpactStats,
    createBackupBeforeRegenerate,
    listLocalTimetableBackups,
    copyFromBackup,
    aiInsights,
    aiRecommendations,
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
    nextStep,
    prevStep,
    goToStep,
    persistWizard,
    getTeacherLoadDistribution,
    getDistributionPriorityRules,
    hasApprovedTeacherDistribution,
    enrichTeachersForDistribution,
    applyTeacherLoadDistribution,
    getTimetableSettings,
  }
}
