<script setup>
import { computed, ref, watch, onMounted, toRef } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { toast } from 'vue3-toastify'
import AdmissionWorkspaceModals from './Partials/AdmissionWorkspaceModals.vue'
import AdmissionsHeaderCard from './Partials/AdmissionsHeaderCard.vue'
import AdmissionsPipelineProgress from './Partials/AdmissionsPipelineProgress.vue'
import AdmissionsExecutiveKPIs from './Partials/AdmissionsExecutiveKPIs.vue'
import DecisionReadinessHero from './Partials/DecisionReadinessHero.vue'
import EngagementTimelineWidget from './Partials/EngagementTimelineWidget.vue'
import VisitCommandCenterWidget from './Partials/VisitCommandCenterWidget.vue'
import DuplicateDetectionHero from './Partials/DuplicateDetectionHero.vue'
import FamilyMatchHero from './Partials/FamilyMatchHero.vue'
import AdaptiveStageDashboard from './Partials/AdaptiveStageDashboard.vue'
import AdmissionConversionModal from './Partials/AdmissionConversionModal.vue'
import OverviewTab from './Partials/Tabs/OverviewTab.vue'
import ApplicantTab from './Partials/Tabs/ApplicantTab.vue'
import ContactsTab from './Partials/Tabs/ContactsTab.vue'
import VisitsTab from './Partials/Tabs/VisitsTab.vue'
import DocumentsTab from './Partials/Tabs/DocumentsTab.vue'
import TimelineTab from './Partials/Tabs/TimelineTab.vue'
import { useAdmissionWorkspaceForms } from '@/composables/useAdmissionWorkspaceForms'
import { useAdmissionCommandCenter } from '@/composables/useAdmissionCommandCenter'

const props = defineProps({
  workspace: { type: Object, required: true },
  filterOptions: { type: Object, default: () => ({}) },
  categories: { type: Array, default: () => [] },
})

const modals = ref(null)
const showConversionModal = ref(false)
const page = usePage()
const workspaceRef = toRef(props, 'workspace')
const categoriesRef = toRef(props, 'categories')

const {
  primaryApplicant,
  primaryContact,
  app,
  applicantForm,
  contactForm,
  visitForms,
  documentForms,
  selectedStageId,
} = useAdmissionWorkspaceForms(workspaceRef, categoriesRef)

const applicationRef = computed(() => app.value)
const primaryApplicantRef = computed(() => primaryApplicant.value)
const primaryContactRef = computed(() => primaryContact.value)

const {
  daysOpen,
  engagementCount,
  duplicateRisk,
  duplicateAnalysis,
  engagementTimeline,
  executiveBadges,
  pipelineSteps,
  readinessBlockers,
  matchedFamily,
  kpiCards,
  applicantName,
  targetGrade,
  decisionReadiness,
  overview,
} = useAdmissionCommandCenter(workspaceRef, applicationRef, primaryApplicantRef, primaryContactRef)

const tabs = [
  { id: 'overview', label: 'نظرة عامة', icon: 'bi-grid' },
  { id: 'applicant', label: 'المتقدم', icon: 'bi-person' },
  { id: 'contacts', label: 'جهات الاتصال', icon: 'bi-people' },
  { id: 'visits', label: 'الزيارات', icon: 'bi-calendar-event' },
  { id: 'documents', label: 'المستندات', icon: 'bi-folder' },
  { id: 'timeline', label: 'الجدول الزمني', icon: 'bi-clock-history' },
]

const activeTab = ref('overview')

const isReadOnly = computed(() => !!app.value?.is_read_only)
const conversionReadiness = computed(() => props.workspace.conversion_readiness || { ready: false, errors: [] })
const quickActions = computed(() => props.workspace.quick_actions || {})
const showConvertActions = computed(() => !!quickActions.value?.convert)

onMounted(() => {
  if (page.props.flash?.success) toast.success(page.props.flash.success)
  syncTabFromUrl()
})

watch(() => page.url, () => syncTabFromUrl())

watch(() => page.props.flash?.success, (message) => {
  if (message) toast.success(message)
})

watch(() => page.props.flash?.error, (message) => {
  if (message) toast.error(message)
})

function syncTabFromUrl() {
  const tab = new URLSearchParams(window.location.search).get('tab')
  if (tab && tabs.some((t) => t.id === tab)) activeTab.value = tab
}

function setTab(tabId) {
  activeTab.value = tabId
  const url = new URL(window.location.href)
  url.searchParams.set('tab', tabId)
  window.history.replaceState({}, '', url)
}

function openModal(name) {
  modals.value?.openModal(name)
}

function formatDate(v) {
  if (!v) return '—'
  return new Date(v).toLocaleDateString('ar-EG')
}

function formatDateTime(v) {
  if (!v) return '—'
  return new Date(v).toLocaleString('ar-EG')
}

function reloadOptions() {
  return { preserveScroll: true, onSuccess: () => toast.success('تم الحفظ') }
}

function saveApplicant() {
  if (!primaryApplicant.value || !app.value) return
  applicantForm.patch(
    route('admin.admissions.applicants.update', [app.value.id, primaryApplicant.value.id]),
    reloadOptions(),
  )
}

function saveContact() {
  if (!primaryContact.value || !app.value) return
  contactForm.patch(
    route('admin.admissions.contacts.update', [app.value.id, primaryContact.value.id]),
    reloadOptions(),
  )
}

function saveVisit(visitId) {
  const data = visitForms.value[visitId]
  if (!data || !app.value) return
  router.patch(route('admin.admissions.visits.update', [app.value.id, visitId]), data, reloadOptions())
}

function saveDocument(docId) {
  const data = documentForms.value[docId]
  if (!data || !app.value) return
  router.patch(route('admin.admissions.documents.update', [app.value.id, docId]), data, reloadOptions())
}

function uploadDocument(docId, event) {
  const file = event.target.files?.[0]
  if (!file || !app.value) return
  const formData = new FormData()
  formData.append('file', file)
  router.post(route('admin.admissions.documents.upload', { admission: app.value.id, document: docId }), formData, {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => toast.success('تم رفع الملف'),
  })
}

function removeDocumentFile(docId) {
  if (!app.value) return
  router.delete(route('admin.admissions.documents.remove-file', { admission: app.value.id, document: docId }), {
    preserveScroll: true,
    onSuccess: () => toast.success('تم حذف الملف'),
  })
}

function reviewDocument(docId, action, payload = {}) {
  if (!app.value) return
  router.patch(
    route('admin.admissions.documents.review', { admission: app.value.id, document: docId }),
    { action, ...payload },
    reloadOptions(),
  )
}

function openConversionModal() {
  showConversionModal.value = true
}

function closeConversionModal() {
  showConversionModal.value = false
}
</script>

<template>
  <Head :title="`قبول — ${app?.reference_code}`" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="card-body px-1 px-sm-3 py-3">
        <div class="admissions-command-center">
          <div class="eduvera-toolbar mb-0">
            <Link :href="route('admin.admissions.index')" class="btn btn-sm btn-outline-secondary">
              <i class="bi bi-arrow-right"></i> صندوق القبول
            </Link>
            <Link :href="route('admin.admissions.visits.index')" class="btn btn-sm btn-outline-primary">
              <i class="bi bi-calendar-week"></i> عرض كل الزيارات
            </Link>
            <div class="d-flex flex-wrap gap-2 ms-auto">
              <button
                v-if="showConvertActions"
                type="button"
                class="btn btn-sm btn-success"
                @click="openConversionModal"
              >
                <i class="bi bi-person-plus"></i> تحويل إلى طالب
              </button>
              <div class="dropdown">
                <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                  <i class="bi bi-lightning"></i> إجراءات سريعة
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li v-if="quickActions.accept"><button type="button" class="dropdown-item text-success" @click="openModal('accept')"><i class="bi bi-check-circle me-2"></i>قبول</button></li>
                  <li v-if="quickActions.reject"><button type="button" class="dropdown-item text-danger" @click="openModal('reject')"><i class="bi bi-x-circle me-2"></i>رفض</button></li>
                  <li v-if="quickActions.waitlist"><button type="button" class="dropdown-item" @click="openModal('waitlist')"><i class="bi bi-hourglass me-2"></i>قائمة الانتظار</button></li>
                  <li v-if="quickActions.withdraw"><button type="button" class="dropdown-item" @click="openModal('withdraw')"><i class="bi bi-box-arrow-left me-2"></i>انسحاب</button></li>
                  <li v-if="showConvertActions"><hr class="dropdown-divider" /></li>
                  <li v-if="showConvertActions"><button type="button" class="dropdown-item fw-semibold text-success" @click="openConversionModal"><i class="bi bi-person-plus me-2"></i>تحويل إلى طالب</button></li>
                  <li v-if="!isReadOnly"><hr class="dropdown-divider" /></li>
                  <li v-if="!isReadOnly"><button type="button" class="dropdown-item" @click="openModal('stage')"><i class="bi bi-signpost-split me-2"></i>تحديث المرحلة</button></li>
                  <li v-if="!isReadOnly"><button type="button" class="dropdown-item" @click="openModal('assign')"><i class="bi bi-person-check me-2"></i>تعيين مسؤول</button></li>
                  <li v-if="!isReadOnly"><button type="button" class="dropdown-item" @click="openModal('note')"><i class="bi bi-chat-left-text me-2"></i>ملاحظة داخلية</button></li>
                </ul>
              </div>
            </div>
          </div>

          <AdmissionsHeaderCard
            :application="app"
            :applicant-name="applicantName"
            :target-grade="targetGrade"
            :executive-badges="executiveBadges"
            :days-open="daysOpen"
            :last-activity="overview.last_activity_at"
            :engagement-count="engagementCount"
            :duplicate-risk="duplicateRisk"
            :format-date="formatDate"
            :format-date-time="formatDateTime"
          />

          <AdmissionsPipelineProgress :steps="pipelineSteps" />

          <AdmissionsExecutiveKPIs :cards="kpiCards" />

          <DecisionReadinessHero
            :decision-readiness="decisionReadiness"
            :document-summary="overview.document_summary || {}"
            :blockers="readinessBlockers"
            :pipeline-stage="app?.pipeline_stage"
            :is-read-only="isReadOnly"
          />

          <div class="row g-2 g-md-3">
            <div class="col-lg-6">
              <EngagementTimelineWidget
                :engagements="engagementTimeline"
                :format-date-time="formatDateTime"
                @open-full="setTab('timeline')"
              />
            </div>
            <div class="col-lg-6">
              <VisitCommandCenterWidget
                :visits="workspace.visits"
                :application="app"
                :filter-options="filterOptions"
                :format-date="formatDate"
                @open-tab="setTab"
              />
            </div>
          </div>

          <DuplicateDetectionHero
            :duplicate-analysis="duplicateAnalysis"
            :risk-level="duplicateRisk"
          />

          <FamilyMatchHero :matched-family="matchedFamily" />

          <AdaptiveStageDashboard
            :application="app"
            :workspace="workspace"
            :primary-applicant="primaryApplicant"
            :primary-contact="primaryContact"
            :conversion-readiness="conversionReadiness"
            :decision-readiness="decisionReadiness"
            :is-read-only="isReadOnly"
            :can-convert="!!quickActions.convert"
            :filter-options="filterOptions"
            :format-date="formatDate"
            :format-date-time="formatDateTime"
            @convert="openConversionModal"
            @open-tab="setTab"
          />

          <div v-if="isReadOnly" class="alert alert-info py-2 small mb-0">
            <strong>طلب محوّل:</strong> هذا الطلب للقراءة فقط.
          </div>

          <ul class="nav nav-tabs flex-nowrap overflow-auto mb-0">
            <li v-for="tab in tabs" :key="tab.id" class="nav-item">
              <button type="button" class="nav-link" :class="{ active: activeTab === tab.id }" @click="setTab(tab.id)">
                <i :class="['bi', tab.icon, 'me-1']"></i>{{ tab.label }}
              </button>
            </li>
          </ul>

          <div class="pt-2">
            <OverviewTab v-show="activeTab === 'overview'" :workspace="workspace" :format-date-time="formatDateTime" />

            <ApplicantTab
              v-show="activeTab === 'applicant'"
              v-model:selected-stage-id="selectedStageId"
              :primary-applicant="primaryApplicant"
              :applicant-form="applicantForm"
              :categories="categories"
              :is-read-only="isReadOnly"
              @save="saveApplicant"
            />

            <ContactsTab
              v-show="activeTab === 'contacts'"
              :primary-contact="primaryContact"
              :contact-form="contactForm"
              :is-read-only="isReadOnly"
              @save="saveContact"
            />

            <VisitsTab
              v-show="activeTab === 'visits'"
              :visits="workspace.visits"
              :visit-forms="visitForms"
              :filter-options="filterOptions"
              :is-read-only="isReadOnly"
              @save="saveVisit"
            />

            <DocumentsTab
              v-show="activeTab === 'documents'"
              :application-id="app?.id"
              :documents="workspace.documents"
              :document-forms="documentForms"
              :document-summary="workspace.overview.document_summary"
              :filter-options="filterOptions"
              :is-read-only="isReadOnly"
              @save="saveDocument"
              @upload="uploadDocument"
              @remove-file="removeDocumentFile"
              @review="reviewDocument"
            />

            <TimelineTab
              v-show="activeTab === 'timeline'"
              :timeline="workspace.timeline"
              :engagement-timeline="workspace.engagement_timeline"
              :notes="workspace.notes"
              :format-date-time="formatDateTime"
            />
          </div>
        </div>
      </div>
    </div>

    <AdmissionWorkspaceModals
      ref="modals"
      :application-id="app?.id"
      :pipeline="workspace.pipeline"
      :quick-actions="quickActions"
      :decision-readiness="decisionReadiness"
      :key="`${app?.id}-${app?.pipeline_stage}-${app?.decision}-${workspace.timeline.length}`"
      :filter-options="filterOptions"
    />

    <AdmissionConversionModal
      :show="showConversionModal"
      :application-id="app?.id"
      :application="app"
      :primary-applicant="primaryApplicant"
      :primary-contact="primaryContact"
      :conversion-readiness="conversionReadiness"
      @close="closeConversionModal"
    />
  </AppLayout>
</template>

