import { computed } from 'vue'

export function localDateStr(date = new Date()) {
  const d = date instanceof Date ? date : new Date(date)
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

/** Presentation-only — business rules are server-driven via visit row fields. */
export function visitColorKey(visit) {
  return visit.color_key ?? 'requested'
}

export function visitCalendarCardClass(visit) {
  return `visit-calendar-card visit-calendar-card--${visitColorKey(visit)}`
}

export function outcomeBadgeClass(outcome) {
  const map = {
    interested: 'bg-info text-dark',
    highly_interested: 'bg-primary',
    requested_application: 'bg-success',
    waitlist_candidate: 'bg-warning text-dark',
    not_interested: 'bg-secondary',
    positive: 'bg-success',
    neutral: 'bg-secondary',
    negative: 'bg-danger',
    rescheduled: 'bg-warning text-dark',
  }
  return map[outcome] || 'bg-light text-dark'
}

export function visitAlerts(visit) {
  return visit.alerts ?? []
}

export function todayBoardColumn(visit, today) {
  if (visit.scheduled_date !== today) return null
  if (visit.status === 'no_show' || visit.attendance_status === 'no_show') return 'no_show'
  if (visit.status === 'completed') return 'completed'
  if (visit.attendance_status === 'attended') return 'checked_in'
  if (['requested', 'confirmed'].includes(visit.status)) return 'scheduled'
  return 'scheduled'
}

export function formatTime(time) {
  if (!time) return '—'
  const [h, m] = time.split(':')
  const hour = parseInt(h, 10)
  const suffix = hour >= 12 ? 'م' : 'ص'
  const h12 = hour % 12 || 12
  return `${h12}:${m || '00'} ${suffix}`
}

export function useVisitCommandCenter(calendarVisitsRef, metricsRef, followUpVisitsRef) {
  const today = computed(() => localDateStr())

  const kpiCards = computed(() => {
    const m = metricsRef.value || {}
    return [
      { id: 'today', label: 'زيارات اليوم', value: m.visits_today ?? 0, icon: 'bi-calendar-day', bg: 'primary', color: 'primary' },
      { id: 'upcoming', label: 'زيارات قادمة', value: m.upcoming_visits ?? 0, icon: 'bi-calendar-plus', bg: 'info', color: 'info' },
      { id: 'attended', label: 'حضر', value: m.attended ?? 0, icon: 'bi-check-circle', bg: 'success', color: 'success' },
      { id: 'no_show', label: 'لم يحضر', value: m.no_show ?? 0, icon: 'bi-x-circle', bg: 'danger', color: 'danger' },
      { id: 'interested', label: 'عائلات مهتمة', value: m.interested_families ?? 0, icon: 'bi-heart', bg: 'warning', color: 'warning' },
      { id: 'followup', label: 'متابعة مطلوبة', value: m.follow_up_required ?? 0, icon: 'bi-bell', bg: 'secondary', color: 'secondary' },
    ]
  })

  const calendarVisits = computed(() => calendarVisitsRef.value || [])

  const todayVisits = computed(() =>
    calendarVisits.value.filter((v) => v.scheduled_date === today.value),
  )

  const todayBoard = computed(() => {
    const cols = { scheduled: [], checked_in: [], completed: [], no_show: [] }
    todayVisits.value.forEach((v) => {
      const col = todayBoardColumn(v, today.value)
      if (col) cols[col].push(v)
    })
    Object.keys(cols).forEach((k) => {
      cols[k].sort((a, b) => (a.scheduled_time || '').localeCompare(b.scheduled_time || ''))
    })
    return cols
  })

  const followUpQueue = computed(() => followUpVisitsRef?.value ?? [])

  const visitsWithMeta = computed(() => calendarVisits.value)

  return {
    today,
    kpiCards,
    todayVisits,
    todayBoard,
    followUpQueue,
    visitsWithMeta,
  }
}

export function buildMonthGrid(year, month, visits) {
  const first = new Date(year, month, 1)
  const startDay = first.getDay()
  const daysInMonth = new Date(year, month + 1, 0).getDate()
  const cells = []

  for (let i = 0; i < startDay; i++) {
    cells.push({ date: null, visits: [] })
  }

  const todayStr = localDateStr()

  for (let d = 1; d <= daysInMonth; d++) {
    const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`
    const dayVisits = visits.filter((v) => v.scheduled_date === dateStr)
    cells.push({
      date: dateStr,
      day: d,
      isToday: dateStr === todayStr,
      visits: dayVisits,
    })
  }

  return cells
}

export function buildWeekDays(anchorDate, visits) {
  const anchor = new Date(anchorDate)
  const day = anchor.getDay()
  const start = new Date(anchor)
  start.setDate(anchor.getDate() - day)

  const days = []
  const todayStr = localDateStr()

  for (let i = 0; i < 7; i++) {
    const d = new Date(start)
    d.setDate(start.getDate() + i)
    const dateStr = localDateStr(d)
    days.push({
      date: dateStr,
      label: d.toLocaleDateString('ar-EG', { weekday: 'short', day: 'numeric', month: 'short' }),
      isToday: dateStr === todayStr,
      visits: visits.filter((v) => v.scheduled_date === dateStr),
    })
  }

  return days
}
