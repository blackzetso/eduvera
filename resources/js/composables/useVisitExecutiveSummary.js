import { computed, toValue } from 'vue'
import {
  daysSince,
  daysUntil,
  latestVisit,
  upcomingOrLatestVisit,
  localDateStr,
} from '@/composables/useAdaptiveWorkspace'

const POSITIVE_OUTCOMES = ['positive', 'interested', 'highly_interested', 'requested_application']

const STATUS_EXECUTIVE = {
  requested: { label: 'مجدولة', tone: 'scheduled', badgeClass: 'bg-primary' },
  confirmed: { label: 'مؤكدة', tone: 'scheduled', badgeClass: 'bg-primary' },
  completed: { label: 'مكتملة', tone: 'completed', badgeClass: 'bg-success' },
  cancelled: { label: 'ملغاة', tone: 'cancelled', badgeClass: 'bg-danger' },
  no_show: { label: 'لم يحضر', tone: 'overdue', badgeClass: 'bg-danger' },
}

export function useVisitExecutiveSummary(visitsSource, applicationSource, filterOptionsSource) {
  const visits = computed(() => toValue(visitsSource) || [])
  const application = computed(() => toValue(applicationSource))
  const filterOptions = computed(() => toValue(filterOptionsSource) || {})

  const activeVisit = computed(() => upcomingOrLatestVisit(visits.value))
  const lastVisitRecord = computed(() => latestVisit(visits.value))

  const visitPassed = computed(() => {
    const v = activeVisit.value
    if (!v?.scheduled_date) return false
    const until = daysUntil(v.scheduled_date)
    return until !== null && until < 0 && !['completed', 'cancelled', 'no_show'].includes(v.status)
  })

  const daysSinceVisit = computed(() => {
    const date = lastVisitRecord.value?.scheduled_date || lastVisitRecord.value?.completed_at?.slice?.(0, 10)
    return daysSince(date)
  })

  const followUpRequired = computed(() => {
    const v = activeVisit.value
    if (!v) return false
    if (!POSITIVE_OUTCOMES.includes(v.outcome)) return false
    if (v.follow_up_notes) return false
    return v.status === 'completed' || v.attendance_status === 'attended'
  })

  const statusMeta = computed(() => {
    const v = activeVisit.value
    if (!v) return null

    if (visitPassed.value) {
      return { label: 'متأخرة', tone: 'overdue', badgeClass: 'bg-danger' }
    }

    if (followUpRequired.value) {
      return { label: STATUS_EXECUTIVE[v.status]?.label || v.status, tone: 'follow_up', badgeClass: 'bg-warning text-dark' }
    }

    return STATUS_EXECUTIVE[v.status] || { label: v.status, tone: 'scheduled', badgeClass: 'bg-secondary' }
  })

  const health = computed(() => {
    if (!activeVisit.value) {
      return { key: 'none', emoji: '⚪', label: 'لا توجد زيارة', labelAr: 'لا توجد زيارة', class: 'text-muted' }
    }

    if (visitPassed.value) {
      return { key: 'overdue', emoji: '🔴', label: 'Overdue', labelAr: 'متأخرة', class: 'text-danger' }
    }

    if (followUpRequired.value) {
      return { key: 'follow_up', emoji: '🟡', label: 'Follow Up Needed', labelAr: 'متابعة مطلوبة', class: 'text-warning' }
    }

    if (activeVisit.value.status === 'cancelled') {
      return { key: 'cancelled', emoji: '🔴', label: 'Cancelled', labelAr: 'ملغاة', class: 'text-danger' }
    }

    if (activeVisit.value.status === 'completed' || activeVisit.value.attendance_status === 'attended') {
      return { key: 'excellent', emoji: '🟢', label: 'Excellent', labelAr: 'ممتازة', class: 'text-success' }
    }

    return { key: 'scheduled', emoji: '🔵', label: 'Scheduled', labelAr: 'مجدولة', class: 'text-primary' }
  })

  const nextAction = computed(() => {
    const v = activeVisit.value

    if (!v) {
      return { key: 'schedule', label: 'جدولة أول زيارة' }
    }

    if (followUpRequired.value) {
      return { key: 'follow_up', label: 'متابعة ولي الأمر' }
    }

    if (visitPassed.value && !['completed', 'cancelled', 'no_show'].includes(v.status)) {
      return { key: 'attendance', label: 'تسجيل الحضور' }
    }

    if (v.status === 'requested') {
      return { key: 'confirm', label: 'تأكيد الزيارة' }
    }

    if (v.status === 'confirmed') {
      const today = localDateStr()
      if (v.scheduled_date && v.scheduled_date <= today) {
        return { key: 'attendance', label: 'تسجيل الحضور' }
      }
      return { key: 'none', label: 'لا يوجد إجراء مطلوب' }
    }

    if (v.status === 'completed' || v.attendance_status === 'attended') {
      return { key: 'none', label: 'لا يوجد إجراء مطلوب' }
    }

    if (v.status === 'cancelled' || v.status === 'no_show') {
      return { key: 'none', label: 'لا يوجد إجراء مطلوب' }
    }

    return { key: 'none', label: 'لا يوجد إجراء مطلوب' }
  })

  const outcomeLabel = computed(() => {
    const outcome = activeVisit.value?.outcome
    if (!outcome) return '—'
    const opt = (filterOptions.value.visit_outcomes || []).find((o) => o.value === outcome)
    return opt?.label || outcome
  })

  const outcomeSuccess = computed(() => {
    const outcome = activeVisit.value?.outcome
    if (!outcome) return null
    if (POSITIVE_OUTCOMES.includes(outcome)) return 'ناجحة'
    if (outcome === 'not_interested' || outcome === 'negative') return 'غير ناجحة'
    return outcomeLabel.value
  })

  const lastVisitDateDisplay = computed(() => {
    const v = lastVisitRecord.value
    if (!v?.scheduled_date) return '—'
    return v.scheduled_date
  })

  const hasVisits = computed(() => visits.value.length > 0)

  return {
    activeVisit,
    lastVisitRecord,
    visitPassed,
    daysSinceVisit,
    followUpRequired,
    statusMeta,
    health,
    nextAction,
    outcomeLabel,
    outcomeSuccess,
    lastVisitDateDisplay,
    hasVisits,
  }
}
