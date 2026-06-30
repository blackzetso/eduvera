import { computed } from 'vue'

const KPI_CARD_DEFS = [
  { id: 'today', label: 'طلبات اليوم', key: 'applications_today', icon: 'bi-inbox-fill', color: 'primary', bg: 'primary' },
  { id: 'visits_today', label: 'زيارات اليوم', key: 'visits_today', icon: 'bi-calendar-event-fill', color: 'info', bg: 'info' },
  { id: 'open', label: 'طلبات مفتوحة', key: 'open_applications', icon: 'bi-folder2-open', color: 'success', bg: 'success' },
  { id: 'ready', label: 'جاهزة للتحويل', key: 'ready_for_conversion', icon: 'bi-person-plus-fill', color: 'success', bg: 'success' },
  { id: 'missing', label: 'بيانات ناقصة', key: 'missing_target_grade', icon: 'bi-exclamation-triangle-fill', color: 'warning', bg: 'warning' },
  { id: 'attention', label: 'تحتاج متابعة', key: 'needs_attention', icon: 'bi-bell-fill', color: 'danger', bg: 'danger' },
]

/**
 * Server-driven inbox metrics — no client-side business rules.
 */
export function useAdmissionInboxMetrics(metrics) {
  const metricsData = computed(() => metrics.value ?? metrics ?? {})

  const pipelineFunnel = computed(() => metricsData.value.pipeline_funnel ?? [])

  const bottleneck = computed(() => metricsData.value.bottleneck ?? null)

  const priorityQueue = computed(() => metricsData.value.priority_queue ?? [])

  const kpiCards = computed(() =>
    KPI_CARD_DEFS.map((def) => ({
      id: def.id,
      label: def.label,
      value: metricsData.value[def.key] ?? 0,
      icon: def.icon,
      color: def.color,
      bg: def.bg,
    })),
  )

  const engagementMetrics = computed(() => metricsData.value.engagement_metrics ?? {})

  const engagementCards = computed(() => [
    { id: 'total_engagements', label: 'إجمالي التفاعلات', key: 'total_engagements', icon: 'bi-chat-dots-fill', color: 'info' },
    { id: 'completed_engagements', label: 'تفاعلات مكتملة', key: 'completed_engagements', icon: 'bi-check-circle-fill', color: 'success' },
    { id: 'pending_followups', label: 'متابعات معلّقة', key: 'pending_followups', icon: 'bi-arrow-repeat', color: 'warning' },
    { id: 'visits_completed', label: 'زيارات مكتملة', key: 'visits_completed', icon: 'bi-building', color: 'primary' },
  ].map((def) => ({
    ...def,
    value: engagementMetrics.value[def.key] ?? 0,
  })))

  return {
    pipelineFunnel,
    bottleneck,
    priorityQueue,
    kpiCards,
    engagementMetrics,
    engagementCards,
  }
}

export function formatVisitRelative(row) {
  if (!row.visit_date) return { relative: '—', detail: '' }

  const today = new Date().toISOString().slice(0, 10)
  const tomorrow = new Date()
  tomorrow.setDate(tomorrow.getDate() + 1)
  const tomorrowIso = tomorrow.toISOString().slice(0, 10)

  let relative = ''
  if (row.visit_date === today) relative = 'اليوم'
  else if (row.visit_date === tomorrowIso) relative = 'غداً'
  else relative = new Date(row.visit_date).toLocaleDateString('ar-EG')

  const detail = row.visit_time || ''

  return { relative, detail }
}
