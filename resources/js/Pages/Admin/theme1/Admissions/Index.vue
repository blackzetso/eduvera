<script setup>
import { computed, ref, toRef, watch } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { useAdmissionInboxMetrics } from '@/composables/useAdmissionInboxMetrics'
import AdmissionInboxHeader from './Partials/AdmissionInboxHeader.vue'
import AdmissionInboxKpiRow from './Partials/AdmissionInboxKpiRow.vue'
import AdmissionInboxEngagementMetrics from './Partials/AdmissionInboxEngagementMetrics.vue'
import AdmissionInboxPipeline from './Partials/AdmissionInboxPipeline.vue'
import AdmissionInboxPriorityQueue from './Partials/AdmissionInboxPriorityQueue.vue'
import AdmissionInboxFilters from './Partials/AdmissionInboxFilters.vue'
import AdmissionInboxTable from './Partials/AdmissionInboxTable.vue'
import AdmissionInboxCards from './Partials/AdmissionInboxCards.vue'
import AdmissionInboxEmptyState from './Partials/AdmissionInboxEmptyState.vue'

const props = defineProps({
  applications: { type: Object, default: () => ({ data: [] }) },
  filters: { type: Object, default: () => ({}) },
  filterOptions: { type: Object, default: () => ({}) },
  metrics: { type: Object, default: () => ({}) },
})

const searchQuery = ref(props.filters.search || '')
const selectedStage = ref(props.filters.stage || '')
const selectedStatus = ref(props.filters.status || '')
const selectedAcademicYear = ref(props.filters.academic_year || '')
const selectedOfficer = ref(props.filters.assigned_to || '')

const metricsRef = toRef(props, 'metrics')

const {
  pipelineFunnel,
  bottleneck,
  priorityQueue,
  kpiCards,
  engagementCards,
} = useAdmissionInboxMetrics(metricsRef)

const applicationsRef = computed(() => props.applications?.data ?? [])

const hasApplications = computed(() => applicationsRef.value.length > 0)
const pagination = computed(() => ({
  current_page: props.applications?.current_page ?? 1,
  last_page: props.applications?.last_page ?? 1,
  per_page: props.applications?.per_page ?? 25,
  total: props.applications?.total ?? applicationsRef.value.length,
  from: props.applications?.from ?? 0,
  to: props.applications?.to ?? 0,
  links: props.applications?.links ?? [],
}))

let searchDebounce = null

function applyServerFilters(page = pagination.value.current_page) {
  router.get(route('admin.admissions.index'), {
    search: searchQuery.value || undefined,
    stage: selectedStage.value || undefined,
    status: selectedStatus.value || undefined,
    academic_year: selectedAcademicYear.value || undefined,
    assigned_to: selectedOfficer.value || undefined,
    page: page > 1 ? page : undefined,
    per_page: pagination.value.per_page,
  }, {
    preserveState: true,
    replace: true,
  })
}

watch([selectedStage, selectedStatus, selectedAcademicYear, selectedOfficer], () => applyServerFilters(1))

watch(searchQuery, () => {
  clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => applyServerFilters(1), 400)
})

function goToPage(link) {
  if (!link?.url || link.active) return
  router.get(link.url, {}, { preserveState: true, replace: true })
}

function formatDateTime(dateString) {
  if (!dateString) return '—'
  return new Date(dateString).toLocaleString('ar-EG')
}
</script>

<template>
  <Head title="صندوق القبول" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="card-body px-1 px-sm-4 py-4">
        <AdmissionInboxHeader />

        <AdmissionInboxKpiRow :cards="kpiCards" />

        <AdmissionInboxEngagementMetrics :cards="engagementCards" />

        <div class="row g-4 mb-1">
          <div class="col-lg-7">
            <AdmissionInboxPipeline
              :funnel="pipelineFunnel"
              :bottleneck="bottleneck"
              :total-applications="metrics.total_filtered ?? pagination.total"
            />
          </div>
          <div class="col-lg-5">
            <AdmissionInboxPriorityQueue :items="priorityQueue" />
          </div>
        </div>

        <AdmissionInboxFilters
          v-model:search-query="searchQuery"
          v-model:selected-stage="selectedStage"
          v-model:selected-status="selectedStatus"
          v-model:selected-academic-year="selectedAcademicYear"
          v-model:selected-officer="selectedOfficer"
          :filter-options="filterOptions"
        />

        <AdmissionInboxEmptyState v-if="!hasApplications" />

        <template v-else>
          <div class="d-none d-lg-block">
            <AdmissionInboxTable
              :applications="applicationsRef"
              :format-date-time="formatDateTime"
            />
          </div>
          <div class="d-lg-none">
            <AdmissionInboxCards
              :applications="applicationsRef"
              :format-date-time="formatDateTime"
            />
          </div>

          <div v-if="pagination.last_page > 1" class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3 px-2">
            <div class="small text-muted">
              عرض {{ pagination.from }}–{{ pagination.to }} من {{ pagination.total }}
            </div>
            <nav aria-label="صفحات صندوق القبول">
              <ul class="pagination pagination-sm mb-0">
                <li
                  v-for="link in pagination.links"
                  :key="link.label"
                  class="page-item"
                  :class="{ active: link.active, disabled: !link.url }"
                >
                  <button
                    type="button"
                    class="page-link"
                    :disabled="!link.url || link.active"
                    @click="goToPage(link)"
                    v-html="link.label"
                  />
                </li>
              </ul>
            </nav>
          </div>
        </template>
      </div>
    </div>
  </AppLayout>
</template>
