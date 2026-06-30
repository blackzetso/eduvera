/**
 * EDUVERA — weekly teaching load distribution (pre-generation).
 * Persists to timetables.settings.teacher_load_distribution
 */

import { EDUCATIONAL_STAGES, stageLabelById } from '@/composables/useTimetableSetupWizard'

export const DEFAULT_PRIORITY_RULES = {
  specializationFirst: true,
  educationalStage: true,
  gradeClass: true,
  maxWeeklyLoad: true,
  balancedLoad: true,
}

export const DEFAULT_MAX_WEEKLY_LOAD = 24

const STAGE_KEYWORDS = {
  kg: ['رياض', 'kg', 'أطفال'],
  primary: ['ابتد', 'primary'],
  middle: ['إعداد', 'متوسط', 'middle'],
  high: ['ثانو', 'secondary', 'high'],
  university: ['جامع', 'university'],
}

function normalizeText(s) {
  return (s || '').toLowerCase().trim()
}

function subjectMatchesTeacher(subject, teacher) {
  const ids = teacher.subject_ids ?? []
  if (ids.includes(subject.subject_id)) return true
  const spec = normalizeText(teacher.specialization)
  const name = normalizeText(subject.name)
  if (!spec || !name) return false
  return spec.includes(name) || name.includes(spec.split(/[،,]/)[0]?.trim() || '')
}

function inferTeacherStageLabel(teacher, selectedStageIds) {
  const blob = normalizeText(`${teacher.department || ''} ${teacher.job_title || ''}`)
  for (const id of selectedStageIds) {
    const keys = STAGE_KEYWORDS[id] ?? []
    if (keys.some((k) => blob.includes(k))) {
      return stageLabelById(id)
    }
  }
  if (selectedStageIds.length === 1) return stageLabelById(selectedStageIds[0])
  return teacher.department || 'عام'
}

function stageMatches(teacher, selectedStageIds) {
  const blob = normalizeText(`${teacher.department || ''} ${teacher.job_title || ''}`)
  return selectedStageIds.some((id) => (STAGE_KEYWORDS[id] ?? []).some((k) => blob.includes(k)))
}

function gradeMatches(teacher, categoryLabel) {
  if (!categoryLabel) return true
  const blob = normalizeText(`${teacher.department || ''} ${teacher.job_title || ''}`)
  const cat = normalizeText(categoryLabel)
  return !cat || blob.includes(cat) || cat.includes(blob)
}

function seniorityBonus(teacher) {
  const t = normalizeText(teacher.job_title || '')
  if (t.includes('رئيس') || t.includes('كبير') || t.includes('خبير')) return 10
  return 0
}

/**
 * @returns {{ score: number, reject: boolean, warnings: string[], specializationExact: boolean, stageMatch: boolean }}
 */
export function scoreTeacherForSubject(teacher, subject, context) {
  const rules = context.rules ?? DEFAULT_PRIORITY_RULES
  const warnings = []
  let score = 0
  let reject = false

  const currentLoad = context.currentLoads?.[teacher.id] ?? 0
  const maxLoad = context.maxWeeklyLoad ?? DEFAULT_MAX_WEEKLY_LOAD

  if (rules.maxWeeklyLoad && currentLoad >= maxLoad) {
    return { score: -1, reject: true, warnings: ['تجاوز الحد الأقصى للنصاب'], specializationExact: false, stageMatch: false }
  }

  const specExact = subjectMatchesTeacher(subject, teacher)
  if (rules.specializationFirst) {
    if (specExact) score += 100
    else warnings.push('تخصص غير مطابق تماماً')
  }

  const stageOk = stageMatches(teacher, context.selectedStageIds ?? [])
  if (rules.educationalStage) {
    if (stageOk) score += 70
    else if (context.selectedStageIds?.length) {
      warnings.push(`مرحلة مختلفة (${inferTeacherStageLabel(teacher, context.selectedStageIds)})`)
    }
  }

  if (rules.gradeClass && gradeMatches(teacher, context.categoryLabel)) {
    score += 50
  }

  if (rules.balancedLoad) {
    score += Math.max(0, 20 - currentLoad * 2)
  }

  score += seniorityBonus(teacher)

  if (!specExact && score < 50) {
    warnings.push('لا يوجد معلم مطابق تماماً')
  }

  if (score <= 0 && !reject) {
    score = 10
    reject = false
  }

  return {
    score,
    reject,
    warnings: [...new Set(warnings)],
    specializationExact: specExact,
    stageMatch: stageOk,
  }
}

function fixAllocationTotal(rows, required) {
  const list = rows.map((r) => ({ ...r, periods: Math.max(0, Number(r.periods) || 0) }))
  let sum = list.reduce((s, r) => s + r.periods, 0)
  if (!list.length) return list

  while (sum < required) {
    const best = list.reduce((a, b) => (a.score >= b.score ? a : b))
    best.periods += 1
    sum += 1
  }
  while (sum > required) {
    const worst = list.reduce((a, b) =>
      a.periods > 0 && (a.periods >= b.periods || b.periods === 0) ? (a.periods >= b.periods ? a : b) : b
    )
    if (worst.periods <= 0) break
    worst.periods -= 1
    sum -= 1
  }
  return list
}

/**
 * Suggest weekly period split across teachers for one subject.
 */
export function suggestSubjectDistribution(subject, teachers, context) {
  const required = Number(subject.periods_per_week) || 0
  if (required <= 0) {
    return { required: 0, rows: [], warnings: [], isValid: true }
  }

  const scored = (teachers ?? [])
    .map((teacher) => {
      const { score, reject, warnings, specializationExact, stageMatch } = scoreTeacherForSubject(
        teacher,
        subject,
        context
      )
      return {
        teacher_id: teacher.id,
        teacher,
        score: reject ? -1 : score,
        periods: 0,
        warnings,
        specializationExact,
        stageMatch,
        stageLabel: inferTeacherStageLabel(teacher, context.selectedStageIds ?? []),
        specialization: teacher.specialization || '—',
        currentLoad: context.currentLoads?.[teacher.id] ?? 0,
      }
    })
    .filter((r) => r.score >= 0)
    .sort((a, b) => b.score - a.score)

  const warnings = []
  if (!scored.length) {
    return {
      required,
      rows: [],
      warnings: ['لا يوجد معلم متاح لهذه المادة'],
      isValid: false,
    }
  }

  const top = scored.filter((s) => s.score >= 30).length ? scored.filter((s) => s.score >= 30) : scored.slice(0, 3)
  const totalScore = top.reduce((s, t) => s + t.score, 0) || 1

  const rows = top.map((t) => {
    let periods = Math.max(1, Math.round((t.score / totalScore) * required))
    if (context.rules?.maxWeeklyLoad) {
      const room = (context.maxWeeklyLoad ?? DEFAULT_MAX_WEEKLY_LOAD) - t.currentLoad
      periods = Math.min(periods, Math.max(0, room))
    }
    return { ...t, periods }
  })

  const fixed = fixAllocationTotal(rows.filter((r) => r.periods > 0).length ? rows : [{ ...top[0], periods: required }], required)

  if (!fixed.some((r) => r.specializationExact)) {
    warnings.push('لا يوجد معلم مطابق تماماً')
  }
  const stageMismatch = fixed.some((r) => !r.stageMatch && context.selectedStageIds?.length)
  if (stageMismatch) {
    warnings.push('تم اختيار معلم من مرحلة مختلفة')
  }

  const sum = fixed.reduce((s, r) => s + r.periods, 0)
  return {
    required,
    rows: fixed,
    warnings: [...new Set([...warnings, ...fixed.flatMap((r) => r.warnings)])],
    isValid: sum === required,
  }
}

export function suggestAllSubjectsDistribution(subjectRequirements, teachers, context) {
  return (subjectRequirements ?? [])
    .filter((s) => (Number(s.periods_per_week) || 0) > 0)
    .map((subject) => ({
      subject_id: subject.subject_id,
      name: subject.name,
      color: subject.color,
      ...suggestSubjectDistribution(subject, teachers, context),
    }))
}

export function validateDistributionState(cards) {
  const errors = []
  for (const card of cards) {
    const sum = (card.rows ?? []).reduce((s, r) => s + (Number(r.periods) || 0), 0)
    const req = Number(card.required) || 0
    if (sum < req) {
      errors.push({ subject_id: card.subject_id, type: 'under', diff: req - sum, message: `ما زال هناك ${req - sum} حصة غير موزعة` })
    } else if (sum > req) {
      errors.push({ subject_id: card.subject_id, type: 'over', diff: sum - req, message: 'تم توزيع حصص أكثر من المطلوب' })
    }
  }
  return {
    isValid: errors.length === 0,
    errors,
  }
}

export function distributionToSettingsPayload(cards) {
  const out = {}
  for (const card of cards) {
    const key = String(card.subject_id)
    out[key] = (card.rows ?? [])
      .filter((r) => (Number(r.periods) || 0) > 0)
      .map((r) => ({
        teacher_id: Number(r.teacher_id),
        periods: Number(r.periods),
      }))
  }
  return out
}

export function hydrateCardsFromSettings(subjectRequirements, distribution, teachers, context) {
  return (subjectRequirements ?? [])
    .filter((s) => (Number(s.periods_per_week) || 0) > 0)
    .map((subject) => {
      const key = String(subject.subject_id)
      const saved = distribution?.[key] ?? []
      if (!saved.length) {
        return {
          subject_id: subject.subject_id,
          name: subject.name,
          color: subject.color,
          ...suggestSubjectDistribution(subject, teachers, context),
        }
      }

      const rows = saved.map((entry) => {
        const teacher = teachers.find((t) => t.id === Number(entry.teacher_id))
        const { score, warnings, specializationExact, stageMatch } = teacher
          ? scoreTeacherForSubject(teacher, subject, context)
          : { score: 0, warnings: ['معلم غير موجود'], specializationExact: false, stageMatch: false }
        return {
          teacher_id: Number(entry.teacher_id),
          teacher,
          periods: Number(entry.periods) || 0,
          score,
          warnings,
          specializationExact,
          stageMatch,
          stageLabel: teacher ? inferTeacherStageLabel(teacher, context.selectedStageIds ?? []) : '—',
          specialization: teacher?.specialization || '—',
          currentLoad: context.currentLoads?.[entry.teacher_id] ?? 0,
        }
      })

      const required = Number(subject.periods_per_week) || 0
      const sum = rows.reduce((s, r) => s + r.periods, 0)
      return {
        subject_id: subject.subject_id,
        name: subject.name,
        color: subject.color,
        required,
        rows,
        warnings: rows.flatMap((r) => r.warnings),
        isValid: sum === required,
      }
    })
}

export function buildDistributionContext(settings, wizardMeta, teacherLoads, rules) {
  const selectedStageIds = settings?.selected_stages?.length
    ? settings.selected_stages
    : settings?.educational_stage
      ? [settings.educational_stage]
      : ['primary']

  return {
    rules: { ...DEFAULT_PRIORITY_RULES, ...rules },
    selectedStageIds,
    categoryLabel: wizardMeta?.grade_id ? String(wizardMeta.grade_id) : null,
    currentLoads: teacherLoads ?? {},
    maxWeeklyLoad: DEFAULT_MAX_WEEKLY_LOAD,
  }
}

export { inferTeacherStageLabel, EDUCATIONAL_STAGES }
