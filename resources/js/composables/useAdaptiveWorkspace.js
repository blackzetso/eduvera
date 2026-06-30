import { computed } from 'vue'

export function localDateStr(date = new Date()) {
  const d = date instanceof Date ? date : new Date(date)
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

export function daysSince(dateStr) {
  if (!dateStr) return null
  const d = new Date(dateStr)
  const now = new Date()
  d.setHours(0, 0, 0, 0)
  now.setHours(0, 0, 0, 0)
  return Math.floor((now - d) / 86400000)
}

export function daysUntil(dateStr) {
  if (!dateStr) return null
  const diff = daysSince(dateStr)
  return diff === null ? null : -diff
}

export function resolveWorkspaceStage(application, isReadOnly) {
  if (isReadOnly || application?.status === 'converted' || application?.decision === 'converted') {
    return 'converted'
  }
  return application?.pipeline_stage || 'lead'
}

const CONTACT_CHECK_IDS = ['primary_contact_exists', 'contact_phone', 'contact_email']
const APPLICANT_CHECK_IDS = ['applicant_exists', 'applicant_dob', 'applicant_gender', 'target_category']

export function readinessChecksToContactStatus(readiness) {
  const checks = (readiness?.checks || []).filter((check) => CONTACT_CHECK_IDS.includes(check.id))
  if (!checks.length) {
    return { ok: false, score: 0, missing: ['لا توجد جهة اتصال'] }
  }
  const missing = checks.filter((check) => !check.ok).map((check) => check.label)
  return {
    ok: missing.length === 0,
    score: checks.filter((check) => check.ok).length,
    missing,
  }
}

export function readinessChecksToApplicantStatus(readiness) {
  const checks = (readiness?.checks || []).filter((check) => APPLICANT_CHECK_IDS.includes(check.id))
  if (!checks.length) {
    return { ok: false, missing: ['لا يوجد متقدم'] }
  }
  const missing = checks.filter((check) => !check.ok).map((check) => check.label)
  return { ok: missing.length === 0, missing }
}

export function latestVisit(visits) {
  if (!visits?.length) return null
  return [...visits].sort((a, b) => {
    const da = a.scheduled_date || ''
    const db = b.scheduled_date || ''
    return db.localeCompare(da)
  })[0]
}

export function upcomingOrLatestVisit(visits) {
  if (!visits?.length) return null
  const today = localDateStr()
  const upcoming = visits
    .filter(v => v.scheduled_date && v.scheduled_date >= today && !['cancelled', 'completed', 'no_show'].includes(v.status))
    .sort((a, b) => (a.scheduled_date || '').localeCompare(b.scheduled_date || ''))
  if (upcoming.length) return upcoming[0]
  return latestVisit(visits)
}

export function duplicateRiskLevel(duplicateAnalysis) {
  const apps = duplicateAnalysis?.possible_duplicate_applications?.length || 0
  const students = duplicateAnalysis?.possible_existing_students?.length || 0
  const guardians = duplicateAnalysis?.possible_existing_guardians?.length || 0
  const total = apps + students + guardians
  if (total >= 3) return { level: 'high', label: 'مرتفع', class: 'text-danger' }
  if (total >= 1) return { level: 'medium', label: 'متوسط', class: 'text-warning' }
  return { level: 'low', label: 'منخفض', class: 'text-success' }
}

export function useAdaptiveWorkspace(applicationRef, workspaceRef, primaryApplicantRef, primaryContactRef, isReadOnlyRef) {
  const stage = computed(() =>
    resolveWorkspaceStage(applicationRef.value, isReadOnlyRef.value)
  )

  const leadAgeDays = computed(() =>
    daysSince(applicationRef.value?.created_at?.slice?.(0, 10) || applicationRef.value?.created_at)
  )

  const applicationReadiness = computed(() => workspaceRef.value?.application_readiness || {})
  const contactStatus = computed(() => readinessChecksToContactStatus(applicationReadiness.value))
  const applicantStatus = computed(() => readinessChecksToApplicantStatus(applicationReadiness.value))

  const visit = computed(() => upcomingOrLatestVisit(workspaceRef.value?.visits || []))

  const visitDaysUntil = computed(() => daysUntil(visit.value?.scheduled_date))

  const visitPassed = computed(() => {
    const until = visitDaysUntil.value
    return until !== null && until < 0 && !['completed', 'cancelled'].includes(visit.value?.status)
  })

  const duplicateRisk = computed(() =>
    duplicateRiskLevel(workspaceRef.value?.duplicate_analysis)
  )

  const recentNotes = computed(() =>
    (workspaceRef.value?.notes || []).slice(0, 3)
  )

  const visitReadiness = computed(() => {
    const readiness = workspaceRef.value?.visit_readiness || { ok: false, checks: [] }
    return {
      ok: !!readiness.ready,
      checks: readiness.checks || [],
      completion_percentage: readiness.completion_percentage ?? 0,
    }
  })

  return {
    stage,
    leadAgeDays,
    contactStatus,
    applicantStatus,
    visit,
    visitDaysUntil,
    visitPassed,
    duplicateRisk,
    recentNotes,
    visitReadiness,
  }
}
