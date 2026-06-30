<script setup>
import { computed, ref, toRef, watch } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import VisitKpiRow from './Partials/VisitKpiRow.vue'
import VisitCalendar from './Partials/VisitCalendar.vue'
import VisitTodayBoard from './Partials/VisitTodayBoard.vue'
import VisitFollowUpQueue from './Partials/VisitFollowUpQueue.vue'
import VisitDetailDrawer from './Partials/VisitDetailDrawer.vue'
import VisitOutcomeBadge from './Partials/VisitOutcomeBadge.vue'
import { useVisitCommandCenter, formatTime, visitAlerts, visitColorKey } from '@/composables/useVisitCommandCenter'

const props = defineProps({
  visits: { type: Object, default: () => ({ data: [] }) },
  calendarVisits: { type: Array, default: () => [] },
  calendarMeta: { type: Object, default: () => ({ truncated: false, total_in_range: 0, returned: 0, limit: 2000 }) },
  followUpVisits: { type: Array, default: () => [] },
  metrics: { type: Object, default: () => ({}) },
  filters: { type: Object, default: () => ({}) },
  filterOptions: { type: Object, default: () => ({}) },
})

const activeSection = ref('calendar')
const selectedVisit = ref(null)
const drawerOpen = ref(false)

const dateFrom = ref(props.filters.date_from || '')
const dateTo = ref(props.filters.date_to || '')
const selectedStatus = ref(props.filters.status || '')
const searchQuery = ref(props.filters.search || '')
const selectedOfficer = ref(props.filters.assigned_to || '')

const visitRows = computed(() => props.visits?.data ?? [])
const calendarVisitsRef = toRef(props, 'calendarVisits')
const followUpVisitsRef = toRef(props, 'followUpVisits')
const metricsRef = toRef(props, 'metrics')

const { kpiCards, todayBoard, followUpQueue, visitsWithMeta } = useVisitCommandCenter(
  calendarVisitsRef,
  metricsRef,
  followUpVisitsRef,
)

const pagination = computed(() => ({
  current_page: props.visits?.current_page ?? 1,
  last_page: props.visits?.last_page ?? 1,
  per_page: props.visits?.per_page ?? 25,
  total: props.visits?.total ?? visitRows.value.length,
  from: props.visits?.from ?? 0,
  to: props.visits?.to ?? 0,
  links: props.visits?.links ?? [],
}))

const sections = [
  { id: 'calendar', label: 'التقويم', icon: 'bi-calendar3' },
  { id: 'today', label: 'زيارات اليوم', icon: 'bi-kanban' },
  { id: 'followup', label: 'متابعة مطلوبة', icon: 'bi-bell' },
  { id: 'all', label: 'كل الزيارات', icon: 'bi-list-ul' },
]

const sortedAllVisits = computed(() =>
  [...visitRows.value]
    .map((visit) => ({
      ...visit,
      colorKey: visitColorKey(visit),
      alerts: visitAlerts(visit),
    }))
    .sort((a, b) => {
    const da = a.scheduled_date || ''
    const db = b.scheduled_date || ''
    if (da !== db) return db.localeCompare(da)
    return (b.scheduled_time || '').localeCompare(a.scheduled_time || '')
  })
)

let searchDebounce = null

function applyFilters(page = 1) {
  router.get(route('admin.admissions.visits.index'), {
    date_from: dateFrom.value || undefined,
    date_to: dateTo.value || undefined,
    status: selectedStatus.value || undefined,
    search: searchQuery.value || undefined,
    assigned_to: selectedOfficer.value || undefined,
    page: page > 1 ? page : undefined,
    per_page: pagination.value.per_page,
  }, {
    preserveState: true,
    replace: true,
  })
}

watch([dateFrom, dateTo, selectedStatus, selectedOfficer], () => applyFilters(1))

watch(searchQuery, () => {
  clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => applyFilters(1), 400)
})

function goToPage(link) {
  if (!link?.url || link.active) return
  router.get(link.url, {}, { preserveState: true, replace: true })
}

function openVisit(visit) {
  selectedVisit.value = visit
  drawerOpen.value = true
}

function closeDrawer() {
  drawerOpen.value = false
}

function formatDate(v) {
  if (!v) return '—'
  return new Date(v).toLocaleDateString('ar-EG')
}
</script>

<template>
  <Head title="إدارة زيارات القبول" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="card-body px-1 px-sm-4 py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
          <div>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
              <Link :href="route('admin.admissions.index')" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-right"></i>
              </Link>
              <h4 class="mb-0 fw-bold">مركز إدارة زيارات القبول</h4>
            </div>
            <p class="text-muted mb-0">
              جدولة الزيارات، الحضور، النتائج، والمتابعات من مكان واحد
            </p>
          </div>
          <Link
            :href="route('admin.admissions.index')"
            class="btn btn-outline-primary btn-sm"
          >
            <i class="bi bi-inbox me-1"></i>
            صندوق القبول
          </Link>
        </div>

        <div class="card visit-dashboard-card mb-4">
          <div class="card-body">
            <div class="row g-2 align-items-end">
              <div class="col-md-3">
                <label class="form-label small mb-1">من تاريخ</label>
                <input v-model="dateFrom" type="date" class="form-control form-control-sm" />
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-1">إلى تاريخ</label>
                <input v-model="dateTo" type="date" class="form-control form-control-sm" />
              </div>
              <div class="col-md-2">
                <label class="form-label small mb-1">الحالة</label>
                <select v-model="selectedStatus" class="form-select form-select-sm">
                  <option value="">الكل</option>
                  <option v-for="opt in filterOptions.visit_statuses || []" :key="opt.value" :value="opt.value">
                    {{ opt.label }}
                  </option>
                </select>
              </div>
              <div class="col-md-2">
                <label class="form-label small mb-1">المسؤول</label>
                <select v-model="selectedOfficer" class="form-select form-select-sm">
                  <option value="">الكل</option>
                  <option v-for="opt in filterOptions.officers || []" :key="opt.value" :value="opt.value">
                    {{ opt.label }}
                  </option>
                </select>
              </div>
              <div class="col-md-2">
                <label class="form-label small mb-1">بحث</label>
                <input v-model="searchQuery" type="search" class="form-control form-control-sm" placeholder="اسم / مرجع" />
              </div>
            </div>
          </div>
        </div>

        <div
          v-if="calendarMeta.truncated"
          class="alert alert-warning py-2 small mb-3"
        >
          <i class="bi bi-exclamation-triangle-fill me-1"></i>
          عرض {{ calendarMeta.returned }} من {{ calendarMeta.total_in_range }} زيارة في النطاق الزمني
          (الحد الأقصى {{ calendarMeta.limit }}).
          ضيّق نطاق التاريخ لعرض جميع الزيارات.
        </div>

        <VisitKpiRow :cards="kpiCards" />

        <ul class="nav nav-pills flex-wrap gap-2 mb-4">
          <li v-for="section in sections" :key="section.id" class="nav-item">
            <button
              type="button"
              class="nav-link"
              :class="{ active: activeSection === section.id }"
              @click="activeSection = section.id"
            >
              <i :class="['bi me-1', section.icon]"></i>
              {{ section.label }}
              <span
                v-if="section.id === 'followup' && followUpQueue.length"
                class="badge bg-danger ms-1"
              >
                {{ followUpQueue.length }}
              </span>
            </button>
          </li>
        </ul>

        <VisitCalendar
          v-show="activeSection === 'calendar'"
          :visits="visitsWithMeta"
          @select="openVisit"
        />

        <div v-show="activeSection === 'today'">
          <div class="mb-3">
            <h5 class="fw-semibold mb-1">لوحة زيارات اليوم</h5>
            <p class="text-muted small mb-0">اسحب الإجراءات السريعة أو انقر لفتح التفاصيل</p>
          </div>
          <VisitTodayBoard :board="todayBoard" @select="openVisit" />
        </div>

        <div v-show="activeSection === 'followup'">
          <div class="mb-3">
            <h5 class="fw-semibold mb-1">قائمة المتابعة</h5>
            <p class="text-muted small mb-0">
              عائلات مهتمة لم تتقدم بعد في مسار القبول
            </p>
          </div>
          <VisitFollowUpQueue :queue="followUpQueue" @select="openVisit" />
        </div>

        <div v-show="activeSection === 'all'">
          <div class="card visit-dashboard-card">
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>التاريخ</th>
                      <th>الوقت</th>
                      <th>المتقدم</th>
                      <th>ولي الأمر</th>
                      <th>المرحلة</th>
                      <th>الحالة</th>
                      <th>النتيجة</th>
                      <th>تنبيهات</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="!sortedAllVisits.length">
                      <td colspan="9" class="text-center text-muted py-5">لا توجد زيارات</td>
                    </tr>
                    <tr
                      v-for="visit in sortedAllVisits"
                      :key="visit.id"
                      role="button"
                      @click="openVisit(visit)"
                    >
                      <td>{{ formatDate(visit.scheduled_date) }}</td>
                      <td dir="ltr">{{ formatTime(visit.scheduled_time) }}</td>
                      <td class="fw-semibold">{{ visit.applicant_name || '—' }}</td>
                      <td>{{ visit.parent_name || '—' }}</td>
                      <td>
                        <span class="visit-status-chip bg-light text-dark">{{ visit.pipeline_stage_label }}</span>
                      </td>
                      <td>
                        <span
                          class="visit-status-chip"
                          :class="`visit-calendar-card--${visit.colorKey}`"
                        >
                          {{ visit.status_label }}
                        </span>
                      </td>
                      <td>
                        <VisitOutcomeBadge :outcome="visit.outcome" :label="visit.outcome_label" />
                      </td>
                      <td>
                        <span
                          v-for="alert in visit.alerts"
                          :key="alert.type"
                          class="visit-alert-chip me-1"
                          :class="alert.class"
                        >
                          {{ alert.label }}
                        </span>
                      </td>
                      <td>
                        <Link
                          :href="route('admin.admissions.show', visit.application_id) + '?tab=visits'"
                          class="btn btn-sm btn-outline-primary"
                          @click.stop
                        >
                          مساحة القبول
                        </Link>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div v-if="pagination.last_page > 1" class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
            <div class="small text-muted">
              عرض {{ pagination.from }}–{{ pagination.to }} من {{ pagination.total }}
            </div>
            <nav aria-label="صفحات الزيارات">
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
        </div>
      </div>
    </div>

    <VisitDetailDrawer
      :visit="selectedVisit"
      :show="drawerOpen"
      @close="closeDrawer"
    />
  </AppLayout>
</template>
