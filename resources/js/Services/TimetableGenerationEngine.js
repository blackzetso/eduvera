/**
 * EDUVERA intelligent timetable generation (client-side planner).
 * Produces assignment plan; persistence is done by the wizard composable.
 */

const DIFFICULT_SUBJECT_KEYWORDS = ['رياض', 'math', 'علوم', 'science', 'فيزياء', 'physics']

function isLessonPeriod(period) {
  return Number(period.period_number) > 0
}

function timesOverlap(a, b) {
  return a.time_from < b.time_to && a.time_to > b.time_from
}

function isDifficultSubject(name) {
  const n = (name || '').toLowerCase()
  return DIFFICULT_SUBJECT_KEYWORDS.some((k) => n.includes(k.toLowerCase()))
}

function defaultTeacherAvailability(teachers, workingDays) {
  const map = {}
  for (const t of teachers ?? []) {
    map[t.id] = {
      days: [...workingDays],
      blocked_slots: [],
    }
  }
  return map
}

function teacherAvailable(availability, teacherId, period) {
  const av = availability?.[teacherId]
  if (!av) return true
  if (av.days?.length && !av.days.includes(period.day_name)) return false
  for (const block of av.blocked_slots ?? []) {
    if (block.day_name !== period.day_name) continue
    if (block.period_number && Number(block.period_number) !== Number(period.period_number)) {
      continue
    }
    if (block.time_from && block.time_to) {
      if (timesOverlap(block, period)) return false
    } else if (block.period_number) {
      return false
    }
  }
  return true
}

/** Spread weekly count across working days (balanced). */
export function spreadSubjectAcrossDays(weeklyCount, workingDays) {
  const counts = {}
  workingDays.forEach((d) => {
    counts[d] = 0
  })
  for (let i = 0; i < weeklyCount; i++) {
    let pick = workingDays[0]
    let min = counts[pick]
    for (const d of workingDays) {
      if (counts[d] < min) {
        min = counts[d]
        pick = d
      }
    }
    counts[pick] += 1
  }
  return counts
}

function buildDayLessonSlots(periods, workingDays, periodStructure = []) {
  const breakTimes = (periodStructure ?? [])
    .filter((p) => p.kind && p.kind !== 'lesson')
    .map((p) => ({ time_from: p.time_from, time_to: p.time_to }))

  return periods
    .filter((p) => workingDays.includes(p.day_name))
    .filter((p) => isLessonPeriod(p))
    .filter((p) => !breakTimes.some((br) => timesOverlap(br, p)))
    .sort((a, b) => {
      if (a.timetable_day_id !== b.timetable_day_id) return a.timetable_day_id - b.timetable_day_id
      return Number(a.period_number) - Number(b.period_number)
    })
}

function validateRequirements(
  subjectRequirements,
  lessonSlotCount,
  workingDays,
  teacherLoadDistribution = {}
) {
  const errors = []
  const totalRequired = subjectRequirements.reduce((s, r) => s + (Number(r.periods_per_week) || 0), 0)
  const capacity = lessonSlotCount

  if (totalRequired > capacity) {
    errors.push(
      `متطلبات المواد (${totalRequired} حصة) تتجاوز السعة المتاحة (${capacity} حصة دراسية).`
    )
  }

  const distribution = teacherLoadDistribution ?? {}

  for (const req of subjectRequirements) {
    if ((req.periods_per_week || 0) <= 0) continue
    const key = String(req.subject_id)
    const loads = distribution[key]
    const distTotal = Array.isArray(loads)
      ? loads.reduce((s, e) => s + (Number(e.periods) || 0), 0)
      : 0
    if (distTotal === req.periods_per_week) continue
    if (!req.teacher_id) {
      errors.push(`المادة «${req.name}» بدون معلم معيّن.`)
    }
  }

  if (!workingDays.length) {
    errors.push('لم يتم تحديد أيام عمل.')
  }

  return { errors, totalRequired, capacity }
}

function scoreSlot({
  period,
  subjectReq,
  placed,
  subjectDayCount,
  teacherDayCount,
  teacherPeriodsOnDay,
  preferMorning,
}) {
  let score = 0
  const dayTarget = subjectDayCount[period.day_name] ?? 0
  const currentOnDay = placed.filter((p) => p.subject_id === subjectReq.subject_id && p.day_name === period.day_name).length

  if (currentOnDay < dayTarget) score += 80
  if (Number(period.period_number) <= 2 && preferMorning) score += 40
  if (currentOnDay >= 2) score -= 120
  if (currentOnDay >= 3) score -= 200

  const teacherSlots = teacherPeriodsOnDay[period.day_name] ?? []
  if (teacherSlots.some((tp) => timesOverlap(tp, period))) score -= 500

  const prevSameDay = teacherSlots.filter((tp) => tp.subject_id === subjectReq.subject_id)
  if (prevSameDay.length) {
    const last = prevSameDay[prevSameDay.length - 1]
    if (Number(last.period_number) + 1 === Number(period.period_number)) score -= 80
  }

  const load = placed.filter((p) => p.teacher_id === subjectReq.teacher_id).length
  score -= load * 3
  score -= (teacherDayCount[period.day_name] ?? 0) * 5

  return score
}

function getSubjectTeacherQuotas(req, distribution) {
  const loads = distribution?.[String(req.subject_id)]
  if (!loads?.length) return null
  return loads
    .filter((e) => (Number(e.periods) || 0) > 0)
    .map((e) => ({
      teacher_id: Number(e.teacher_id),
      quota: Number(e.periods),
      assigned: 0,
    }))
}

/**
 * @param {object} input
 * @returns {{ success: boolean, assignments: Array, report: object }}
 */
export function runIntelligentGeneration(input) {
  const {
    periods = [],
    subjectRequirements = [],
    teacherAvailability = null,
    teachers = [],
    workingDays = [],
    periodStructure = [],
    existingAssignments = [],
    teacherLoadDistribution = null,
    departmentPlanPriority = false,
  } = input

  const distribution =
    teacherLoadDistribution && Object.keys(teacherLoadDistribution).length
      ? teacherLoadDistribution
      : null

  const usingDepartmentPlan = Boolean(departmentPlanPriority && distribution)

  const availability =
    teacherAvailability && Object.keys(teacherAvailability).length
      ? teacherAvailability
      : defaultTeacherAvailability(teachers, workingDays)

  const lessonSlots = buildDayLessonSlots(periods, workingDays, periodStructure)
  const validation = validateRequirements(
    subjectRequirements,
    lessonSlots.length,
    workingDays,
    distribution ?? {}
  )

  if (validation.errors.length) {
    return {
      success: false,
      assignments: [],
      report: {
        qualityScore: 0,
        errors: validation.errors,
        recommendations: [],
        conflicts: [],
        steps: ['فشل التحقق من المتطلبات'],
      },
    }
  }

  const placed = []
  const usedPeriodIds = new Set()
  const recommendations = []
  const steps = [
    'التحقق من المتطلبات',
    'فحص السعة والأيام',
    'تطبيق توافر المعلمين',
    'توزيع المواد',
    usingDepartmentPlan
      ? 'تطبيق خطة القسم (أولوية)'
      : distribution
        ? 'تطبيق توزيع الحصص المعتمد'
        : 'تعيين المعلمين',
    'فحص التعارضات',
    'تحسين التوزيع',
  ]

  const activeReqs = subjectRequirements.filter((r) => (r.periods_per_week || 0) > 0)

  for (const req of activeReqs) {
    const dayTargets = spreadSubjectAcrossDays(req.periods_per_week, workingDays)
    const preferMorning = isDifficultSubject(req.name)
    const quotas = distribution ? getSubjectTeacherQuotas(req, distribution) : null

    for (let n = 0; n < req.periods_per_week; n++) {
      let best = null
      let bestScore = -Infinity
      let bestTeacherId = req.teacher_id

      const subjectDayCount = { ...dayTargets }

      for (const period of lessonSlots) {
        if (usedPeriodIds.has(period.id)) continue

        const teacherIds = quotas
          ? quotas
              .filter((q) => q.assigned < q.quota)
              .filter((q) => teacherAvailable(availability, q.teacher_id, period))
              .map((q) => q.teacher_id)
          : req.teacher_id
            ? [req.teacher_id]
            : []

        for (const teacherId of teacherIds) {
          if (!teacherAvailable(availability, teacherId, period)) continue

          const teacherPeriodsOnDay = {}
          const teacherDayCount = {}
          for (const p of placed) {
            if (p.teacher_id !== teacherId) continue
            teacherDayCount[p.day_name] = (teacherDayCount[p.day_name] ?? 0) + 1
            if (!teacherPeriodsOnDay[p.day_name]) teacherPeriodsOnDay[p.day_name] = []
            teacherPeriodsOnDay[p.day_name].push(p)
          }

          const sc = scoreSlot({
            period,
            subjectReq: req,
            placed,
            subjectDayCount,
            teacherDayCount,
            teacherPeriodsOnDay,
            preferMorning,
          })

          if (sc > bestScore) {
            bestScore = sc
            best = period
            bestTeacherId = teacherId
          }
        }
      }

      if (!best || !bestTeacherId) {
        recommendations.push(
          quotas
            ? `تعذر إسناد حصة من «${req.name}» ضمن التوزيع المعتمد — راجع النصاب أو التوافر.`
            : `تعذر إسناد حصة من مادة «${req.name}» — راجع توافر المعلم أو السعة.`
        )
        continue
      }

      if (quotas) {
        const q = quotas.find((x) => x.teacher_id === bestTeacherId)
        if (q) q.assigned += 1
      }

      usedPeriodIds.add(best.id)
      placed.push({
        period_id: best.id,
        teacher_id: bestTeacherId,
        subject_id: req.subject_id,
        day_name: best.day_name,
        period_number: best.period_number,
        time_from: best.time_from,
        time_to: best.time_to,
      })
    }

    const byDay = {}
    placed.filter((p) => p.subject_id === req.subject_id).forEach((p) => {
      byDay[p.day_name] = (byDay[p.day_name] ?? 0) + 1
    })
    Object.entries(byDay).forEach(([day, count]) => {
      if (count >= 3) {
        recommendations.push(`${req.name} مركّزة في يوم ${day} (${count} حصص).`)
      }
    })
  }

  const conflicts = detectPlanConflicts(placed, teachers)
  const emptyAfter = lessonSlots.length - placed.length
  const qualityScore = calculateQualityScore({
    placed,
    lessonSlots,
    conflicts,
    activeReqs,
    validation,
  })

  for (const req of activeReqs) {
    const assigned = placed.filter((p) => p.subject_id === req.subject_id).length
    if (assigned < req.periods_per_week) {
      recommendations.push(
        `مادة «${req.name}»: تم تعيين ${assigned} من ${req.periods_per_week} حصة أسبوعية.`
      )
    }
  }

  const teacherLoads = {}
  placed.forEach((p) => {
    teacherLoads[p.teacher_id] = (teacherLoads[p.teacher_id] ?? 0) + 1
  })
  Object.entries(teacherLoads).forEach(([tid, load]) => {
    const required = activeReqs
      .filter((r) => Number(r.teacher_id) === Number(tid))
      .reduce((s, r) => s + (r.periods_per_week || 0), 0)
    if (required && load >= required) {
      const teacher = teachers.find((t) => t.id === Number(tid))
      recommendations.push(`المعلم ${teacher?.name ?? ''} قريب من الحد الأقصى للأعباء (${load}/${required}).`)
    }
  })

  if (emptyAfter > 0) {
    recommendations.push(`تبقى ${emptyAfter} حصة دراسية فارغة بعد التوليد.`)
  }

  return {
    success: conflicts.length === 0 && placed.length >= validation.totalRequired * 0.9,
    assignments: placed.map((p) => ({
      timetable_period_id: p.period_id,
      teacher_id: p.teacher_id,
      subject_id: p.subject_id,
      type: 'main',
    })),
    report: {
      qualityScore,
      errors: conflicts.length ? [`تم اكتشاف ${conflicts.length} تعارض بعد التوليد.`] : [],
      recommendations: [...new Set(recommendations)].slice(0, 10),
      conflicts,
      steps,
      stats: {
        assigned: placed.length,
        required: validation.totalRequired,
        capacity: validation.capacity,
        empty: emptyAfter,
      },
    },
  }
}

function detectPlanConflicts(placed, teachers) {
  const conflicts = []
  const byTeacherDay = {}

  for (const p of placed) {
    const key = `${p.teacher_id}:${p.day_name}`
    if (!byTeacherDay[key]) byTeacherDay[key] = []
    byTeacherDay[key].push(p)
  }

  Object.entries(byTeacherDay).forEach(([key, items]) => {
    const [teacherId] = key.split(':')
    const teacher = teachers.find((t) => t.id === Number(teacherId))
    const name = teacher?.name ?? 'مدرس'

    for (let i = 0; i < items.length; i++) {
      for (let j = i + 1; j < items.length; j++) {
        if (timesOverlap(items[i], items[j])) {
          conflicts.push({
            type: 'conflict',
            teacherId: Number(teacherId),
            teacherName: name,
            message: `تعارض للمدرس ${name} في ${items[i].day_name} بين ${items[i].time_from} و ${items[j].time_from}`,
            periodIds: [items[i].period_id, items[j].period_id],
          })
        }
      }
    }
  })

  return conflicts
}

function calculateQualityScore({ placed, lessonSlots, conflicts, activeReqs, validation }) {
  const total = lessonSlots.length || 1
  const fillRatio = placed.length / total
  let score = Math.round(
    fillRatio * 35 +
      (conflicts.length === 0 ? 30 : Math.max(0, 30 - conflicts.length * 10)) +
      (validation.totalRequired > 0
        ? Math.min(25, (placed.length / validation.totalRequired) * 25)
        : 10)
  )

  let balancePenalty = 0
  for (const req of activeReqs) {
    const byDay = {}
    placed.filter((p) => p.subject_id === req.subject_id).forEach((p) => {
      byDay[p.day_name] = (byDay[p.day_name] ?? 0) + 1
    })
    if (Object.values(byDay).some((c) => c >= 3)) balancePenalty += 5
  }
  score = Math.min(100, Math.max(0, score - balancePenalty + 10))
  return score
}

export function groupConflictsByTeacher(conflictList) {
  const groups = {}
  for (const c of conflictList) {
    const id = c.teacherId ?? 0
    const nameMatch = c.message?.match(/للمدرس\s+(.+?)\s+في/)
    if (!groups[id]) {
      groups[id] = {
        teacherId: id,
        teacherName: c.teacherName ?? nameMatch?.[1] ?? 'مدرس',
        count: 0,
        items: [],
      }
    }
    groups[id].items.push(c)
    groups[id].count += 1
  }
  return Object.values(groups)
}

export function buildTimetableBackupSnapshot(timetable) {
  const periods = []
  const assignments = []
  timetable?.days?.forEach((day) => {
    day.periods?.forEach((period) => {
      periods.push({ ...period, day_name: day.day_name })
      period.assignments?.forEach((a) => {
        if (a.type === 'main') assignments.push(a)
      })
    })
  })
  const subjectIds = new Set(assignments.map((a) => a.subject_id))
  const teacherIds = new Set(assignments.map((a) => a.teacher_id))

  return {
    saved_at: new Date().toISOString(),
    name: timetable?.name,
    academic_year: timetable?.academic_year,
    settings: timetable?.settings,
    days: timetable?.days,
    stats: {
      periods: periods.length,
      subjects: subjectIds.size,
      teachers: teacherIds.size,
      assignments: assignments.length,
    },
    assignments: assignments.map((a) => ({
      timetable_period_id: a.timetable_period_id,
      teacher_id: a.teacher_id,
      subject_id: a.subject_id,
      type: a.type,
    })),
  }
}

const BACKUP_STORAGE_KEY = 'eduvera_timetable_backups'

export function saveLocalTimetableBackup(snapshot) {
  const list = JSON.parse(localStorage.getItem(BACKUP_STORAGE_KEY) || '[]')
  list.unshift(snapshot)
  localStorage.setItem(BACKUP_STORAGE_KEY, JSON.stringify(list.slice(0, 10)))
  return snapshot
}

export function listLocalTimetableBackups() {
  try {
    return JSON.parse(localStorage.getItem(BACKUP_STORAGE_KEY) || '[]')
  } catch {
    return []
  }
}
