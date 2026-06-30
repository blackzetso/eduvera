<script setup>
import { computed, toRef } from 'vue'
import { useAdaptiveWorkspace } from '@/composables/useAdaptiveWorkspace'
import LeadDashboard from './LeadDashboard.vue'
import InquiryDashboard from './InquiryDashboard.vue'
import CampusVisitDashboard from './CampusVisitDashboard.vue'
import ApplicationDashboard from './ApplicationDashboard.vue'
import ConvertedDashboard from './ConvertedDashboard.vue'

const props = defineProps({
  application: { type: Object, required: true },
  workspace: { type: Object, required: true },
  primaryApplicant: { type: Object, default: null },
  primaryContact: { type: Object, default: null },
  conversionReadiness: { type: Object, default: () => ({}) },
  decisionReadiness: { type: Object, default: () => ({}) },
  isReadOnly: { type: Boolean, default: false },
  canConvert: { type: Boolean, default: false },
  filterOptions: { type: Object, default: () => ({}) },
  formatDate: { type: Function, required: true },
  formatDateTime: { type: Function, required: true },
})

const emit = defineEmits(['convert', 'open-tab'])

const applicationRef = toRef(props, 'application')
const workspaceRef = toRef(props, 'workspace')
const applicantRef = toRef(props, 'primaryApplicant')
const contactRef = toRef(props, 'primaryContact')
const readOnlyRef = toRef(props, 'isReadOnly')

const {
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
} = useAdaptiveWorkspace(applicationRef, workspaceRef, applicantRef, contactRef, readOnlyRef)

const overview = computed(() => props.workspace.overview || {})
const documentSummary = computed(() => overview.value.document_summary || {})
</script>

<template>
  <LeadDashboard
    v-if="stage === 'lead'"
    :application="application"
    :overview="overview"
    :lead-age-days="leadAgeDays"
    :contact-status="contactStatus"
    :duplicate-risk="duplicateRisk"
    :format-date="formatDate"
    :format-date-time="formatDateTime"
    @open-tab="emit('open-tab', $event)"
  />

  <InquiryDashboard
    v-else-if="stage === 'inquiry'"
    :overview="overview"
    :recent-notes="recentNotes"
    :applicant-status="applicantStatus"
    :contact-status="contactStatus"
    :visit-readiness="visitReadiness"
    :format-date-time="formatDateTime"
    @open-tab="emit('open-tab', $event)"
  />

  <CampusVisitDashboard
    v-else-if="stage === 'campus_visit'"
    :application="application"
    :visit="visit"
    :visit-passed="visitPassed"
    :is-read-only="isReadOnly"
    @open-tab="emit('open-tab', $event)"
  />

  <ApplicationDashboard
    v-else-if="stage === 'application'"
    :conversion-readiness="conversionReadiness"
    :decision-readiness="decisionReadiness"
    :application="application"
    :primary-applicant="primaryApplicant"
    :primary-contact="primaryContact"
    :document-summary="documentSummary"
    :is-read-only="isReadOnly"
    :can-convert="canConvert"
    @convert="emit('convert')"
  />

  <ConvertedDashboard
    v-else-if="stage === 'converted'"
    :application="application"
    :overview="overview"
    :timeline="workspace.timeline || []"
    :format-date-time="formatDateTime"
  />
</template>
