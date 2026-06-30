/** Centralized badge classes for Admissions module */

export const PIPELINE_STAGES = ['lead', 'inquiry', 'campus_visit', 'application']

export const stageBadgeClass = {
  lead: 'bg-secondary',
  inquiry: 'bg-info',
  campus_visit: 'bg-primary',
  application: 'bg-warning text-dark',
}

export const statusBadgeClass = {
  open: 'bg-success',
  converted: 'bg-dark',
  rejected: 'bg-danger',
  withdrawn: 'bg-secondary',
  waitlisted: 'bg-warning text-dark',
}

export const decisionBadgeClass = {
  accepted: 'bg-success',
  rejected: 'bg-danger',
  waitlisted: 'bg-warning text-dark',
  withdrawn: 'bg-secondary',
  converted: 'bg-dark',
}

export const visitBadgeClass = {
  requested: 'bg-secondary',
  confirmed: 'bg-primary',
  completed: 'bg-success',
  no_show: 'bg-danger',
  cancelled: 'bg-dark',
}

export const documentBadgeClass = {
  needs_upload: 'bg-secondary',
  review_pending: 'bg-warning text-dark',
  submitted: 'bg-primary',
  pending: 'bg-warning text-dark',
  approved: 'bg-success',
  reupload_required: 'text-bg-warning border border-warning',
  rejected: 'bg-danger',
}

export function pipelineBadgeClass(stage) {
  return stageBadgeClass[stage] || 'bg-light text-dark'
}

export function statusBadge(stage) {
  return statusBadgeClass[stage] || 'bg-light text-dark'
}

export function decisionBadge(decision) {
  return decisionBadgeClass[decision] || 'bg-light text-dark'
}

export function visitBadge(status) {
  return visitBadgeClass[status] || 'bg-light text-dark'
}

export function documentBadge(status) {
  return documentBadgeClass[status] || 'bg-light text-dark'
}

export const visitOutcomeBadgeClass = {
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

export function visitOutcomeBadge(outcome) {
  return visitOutcomeBadgeClass[outcome] || 'bg-light text-dark'
}

export const engagementStatusBadgeClass = {
  pending: 'bg-secondary',
  scheduled: 'bg-primary',
  completed: 'bg-success',
  cancelled: 'bg-dark',
  failed: 'bg-danger',
}

export const engagementChannelBadgeClass = {
  website: 'bg-info text-dark',
  phone: 'bg-primary',
  whatsapp: 'bg-success',
  email: 'bg-warning text-dark',
  visit: 'bg-primary',
  internal: 'bg-secondary',
}

export const engagementTypeEmoji = {
  website_form: '🌐',
  phone_call: '📞',
  whatsapp: '💬',
  email: '📧',
  follow_up: '📝',
  campus_visit: '🏫',
  meeting: '👥',
  note: '📝',
  task: '✅',
}

export function engagementStatusBadge(status) {
  return engagementStatusBadgeClass[status] || 'bg-light text-dark'
}

export function engagementChannelBadge(channel) {
  return engagementChannelBadgeClass[channel] || 'bg-light text-dark'
}

export const timelineEventColors = {
  engagement: 'text-info',
  stage_change: 'text-primary',
  visit: 'text-info',
  visit_scheduled: 'text-primary',
  visit_attended: 'text-success',
  visit_rescheduled: 'text-warning',
  visit_cancelled: 'text-danger',
  visit_outcome: 'text-info',
  assignment: 'text-secondary',
  note: 'text-muted',
  document: 'text-warning',
  decision_change: 'text-success',
  conversion: 'text-primary',
  student_created: 'text-success',
  enrollment_created: 'text-success',
  guardian_match: 'text-info',
}

export const timelineEventIcons = {
  engagement: 'bi-chat-dots-fill',
  stage_change: 'bi-arrow-right-circle',
  visit: 'bi-calendar-event',
  visit_scheduled: 'bi-calendar-plus',
  visit_attended: 'bi-check-circle-fill',
  visit_rescheduled: 'bi-arrow-repeat',
  visit_cancelled: 'bi-x-circle',
  visit_outcome: 'bi-clipboard-check',
  assignment: 'bi-person-check',
  note: 'bi-chat-left-text',
  document: 'bi-folder-check',
  decision_change: 'bi-check2-circle',
  conversion: 'bi-person-plus',
  student_created: 'bi-person-badge',
  enrollment_created: 'bi-journal-check',
  guardian_match: 'bi-people',
}

export function timelineEventColor(type) {
  return timelineEventColors[type] || 'text-primary'
}
