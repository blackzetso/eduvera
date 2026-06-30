import { computed } from 'vue'
import { daysSince, duplicateRiskLevel, upcomingOrLatestVisit } from '@/composables/useAdaptiveWorkspace'
import { documentSummaryProgress } from '@/composables/useAdmissionDocumentPreview'

const WEBSITE_CHANNELS = ['website_visit', 'form_builder']
const POSITIVE_OUTCOMES = ['positive', 'interested', 'highly_interested', 'requested_application']

const EXECUTIVE_PIPELINE = [
  { key: 'lead', label: 'عميل محتمل' },
  { key: 'inquiry', label: 'استفسار' },
  { key: 'campus_visit', label: 'زيارة الحرم' },
  { key: 'application', label: 'طلب التقديم' },
  { key: 'decision', label: 'القرار' },
  { key: 'conversion', label: 'التحويل' },
]

const STAGE_INDEX = Object.fromEntries(EXECUTIVE_PIPELINE.map((s, i) => [s.key, i]))

function pipelineStageIndex(application) {
  if (application?.is_read_only || application?.converted_student) {
    return STAGE_INDEX.conversion
  }
  if (application?.decision && application.decision !== 'converted') {
    return STAGE_INDEX.decision
  }
  return STAGE_INDEX[application?.pipeline_stage] ?? 0
}

export function useAdmissionCommandCenter(workspaceRef, applicationRef, primaryApplicantRef, primaryContactRef) {
  const overview = computed(() => workspaceRef.value?.overview || {})
  const documentSummary = computed(() => overview.value.document_summary || {})
  const duplicateAnalysis = computed(() => workspaceRef.value?.duplicate_analysis || {})
  const engagementTimeline = computed(() => workspaceRef.value?.engagement_timeline || [])
  const visits = computed(() => workspaceRef.value?.visits || [])
  const decisionReadiness = computed(() => workspaceRef.value?.decision_readiness || {})
  const applicationReadiness = computed(() => workspaceRef.value?.application_readiness || {})

  const daysOpen = computed(() =>
    daysSince(applicationRef.value?.created_at?.slice?.(0, 10) || applicationRef.value?.created_at),
  )

  const engagementCount = computed(() => engagementTimeline.value.length)

  const duplicateRisk = computed(() => duplicateRiskLevel(duplicateAnalysis.value))

  const duplicateCandidatesCount = computed(() => {
    const d = duplicateAnalysis.value
    return (d.possible_existing_students?.length || 0)
      + (d.possible_existing_guardians?.length || 0)
      + (d.possible_existing_families?.length || 0)
      + (d.possible_duplicate_applications?.length || 0)
  })

  const visit = computed(() => upcomingOrLatestVisit(visits.value))

  const visitsScheduled = computed(() =>
    visits.value.filter((v) => ['requested', 'confirmed'].includes(v.status)).length,
  )

  const visitsCompleted = computed(() =>
    visits.value.filter((v) => v.status === 'completed' || v.attendance_status === 'attended').length,
  )

  const visitFollowUpRequired = computed(() => {
    const v = visit.value
    if (!v) return false
    if (!POSITIVE_OUTCOMES.includes(v.outcome)) return false
    if (v.follow_up_notes) return false
    return v.status === 'completed' || v.attendance_status === 'attended'
  })

  const documentProgress = computed(() => documentSummaryProgress(documentSummary.value))

  const documentSubmittedPercent = computed(() => documentProgress.value.percent)

  const documentsMissing = computed(() => documentProgress.value.blocking)

  const decisionReadinessPercent = computed(() =>
    decisionReadiness.value.completion_percentage
    ?? (decisionReadiness.value.checks?.length
      ? Math.round(
        (decisionReadiness.value.checks.filter((c) => c.ok).length / decisionReadiness.value.checks.length) * 100,
      )
      : 0),
  )

  const executiveBadges = computed(() => {
    const app = applicationRef.value
    const badges = []

    if (WEBSITE_CHANNELS.includes(app?.source_channel)) {
      badges.push({ id: 'website', label: 'Website Lead', icon: 'bi-globe2', class: 'bg-info text-dark' })
    }

    if (app?.pipeline_stage === 'campus_visit' || visits.value.length > 0) {
      badges.push({ id: 'visit', label: 'Campus Visit', icon: 'bi-building', class: 'bg-primary' })
    }

    if (applicationReadiness.value.ready) {
      badges.push({ id: 'app_complete', label: 'Application Complete', icon: 'bi-file-earmark-check', class: 'bg-success' })
    }

    if (decisionReadiness.value.ready) {
      badges.push({ id: 'decision_ready', label: 'Decision Ready', icon: 'bi-clipboard2-check', class: 'bg-success' })
    }

    if (app?.is_read_only || app?.converted_student) {
      badges.push({ id: 'converted', label: 'Converted', icon: 'bi-person-check-fill', class: 'bg-dark' })
    }

    return badges
  })

  const pipelineSteps = computed(() => {
    const app = applicationRef.value
    const currentIndex = pipelineStageIndex(app)
    const blockedAtDecision = app?.pipeline_stage === 'application'
      && !decisionReadiness.value.ready
      && !app?.decision
      && !app?.is_read_only

    return EXECUTIVE_PIPELINE.map((step, index) => {
      let state = 'future'
      if (index < currentIndex) state = 'completed'
      else if (index === currentIndex) state = 'active'
      if (step.key === 'decision' && blockedAtDecision && index === currentIndex) {
        state = 'blocked'
      }

      return { ...step, state }
    })
  })

  const readinessBlockers = computed(() => {
    const checks = decisionReadiness.value.checks || []
    return checks
      .filter((c) => !c.ok && c.blocking !== false)
      .map((c) => c.detail || c.label)
  })

  const documentsReadinessCheck = computed(() =>
    (decisionReadiness.value.checks || []).find((c) => c.id === 'documents_complete') || null,
  )

  const matchedFamily = computed(() => {
    const contact = primaryContactRef.value
    const app = applicationRef.value
    const guardian = contact?.matched_guardian
      || app?.converted_guardian
      || overview.value?.decision?.converted_guardian

    if (!guardian?.id) return null

    const families = duplicateAnalysis.value.possible_existing_families || []
    const family = families.find((f) => f.guardian_id === guardian.id)

    return {
      guardian,
      childrenCount: family?.children?.length || 0,
      children: family?.children || [],
      profileUrl: guardian.profile_url || family?.guardian_profile_url,
    }
  })

  const hasDuplicateMatches = computed(() => duplicateCandidatesCount.value > 0)

  const kpiCards = computed(() => [
    {
      id: 'days_open',
      label: 'أيام في المسار',
      value: daysOpen.value ?? '—',
      color: 'primary',
      icon: 'bi-hourglass-split',
    },
    {
      id: 'engagements',
      label: 'التفاعلات',
      value: engagementCount.value,
      color: 'info',
      icon: 'bi-chat-dots-fill',
    },
    {
      id: 'visits',
      label: 'زيارات مجدولة / مكتملة',
      value: `${visitsScheduled.value} / ${visitsCompleted.value}`,
      color: 'primary',
      icon: 'bi-calendar-event',
    },
    {
      id: 'documents',
      label: 'المستندات',
      value: `${documentSubmittedPercent.value}%`,
      sub: documentProgress.value.total
        ? `${documentProgress.value.completed} / ${documentProgress.value.total} معتمد`
        : null,
      color: documentsMissing.value ? 'warning' : 'success',
      icon: 'bi-folder-check',
    },
    {
      id: 'readiness',
      label: 'جاهزية القرار',
      value: `${decisionReadinessPercent.value}%`,
      color: decisionReadiness.value.ready ? 'success' : 'warning',
      icon: 'bi-clipboard2-check',
    },
    {
      id: 'duplicates',
      label: 'تطابقات محتملة',
      value: duplicateCandidatesCount.value,
      color: duplicateCandidatesCount.value > 0 ? 'danger' : 'success',
      icon: 'bi-shield-exclamation',
    },
  ])

  const applicantName = computed(() => {
    const a = primaryApplicantRef.value
    return a?.display_name
      || [a?.first_name, a?.father_name].filter(Boolean).join(' ')
      || '—'
  })

  const targetGrade = computed(() =>
    applicationRef.value?.target_grade
    || applicationRef.value?.target_category?.name
    || '—',
  )

  return {
    overview,
    daysOpen,
    engagementCount,
    duplicateRisk,
    duplicateCandidatesCount,
    duplicateAnalysis,
    engagementTimeline,
    visit,
    visitsScheduled,
    visitsCompleted,
    visitFollowUpRequired,
    documentProgress,
    documentSubmittedPercent,
    documentsMissing,
    documentsReadinessCheck,
    decisionReadinessPercent,
    executiveBadges,
    pipelineSteps,
    readinessBlockers,
    matchedFamily,
    hasDuplicateMatches,
    kpiCards,
    applicantName,
    targetGrade,
    decisionReadiness,
  }
}
