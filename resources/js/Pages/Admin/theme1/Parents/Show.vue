<script setup>
import { ref, onMounted, nextTick } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { toast } from 'vue3-toastify'
import ParentHeaderCard from './Partials/ParentHeaderCard.vue'
import FamilyQuickActions from './Partials/FamilyQuickActions.vue'
import FamilyHealthDashboard from './Partials/FamilyHealthDashboard.vue'
import FamilyChildrenOverview from './Partials/FamilyChildrenOverview.vue'
import FamilyRiskPanel from './Partials/FamilyRiskPanel.vue'
import FamilyAttendanceSnapshot from './Partials/FamilyAttendanceSnapshot.vue'
import FamilyAcademicSnapshot from './Partials/FamilyAcademicSnapshot.vue'
import FamilyFinanceSnapshot from './Partials/FamilyFinanceSnapshot.vue'
import FamilyTimelinePreview from './Partials/FamilyTimelinePreview.vue'
import WorkspaceLayoutAdapter from '@/Components/Workspace/WorkspaceLayoutAdapter.vue'

const props = defineProps({
  profile: { type: Object, required: true },
  workspace_context: { type: Object, default: null },
  family_context: { type: Object, default: () => ({}) },
  command_center: { type: Object, default: () => ({}) },
  children: { type: Array, default: () => [] },
  children_summary: { type: Object, default: () => ({}) },
  guardians: { type: Array, default: () => [] },
  finance_snapshot: { type: Object, default: () => ({}) },
  attendance_snapshot: { type: Object, default: () => ({}) },
  academic_snapshot: { type: Object, default: () => ({}) },
  risk_summary: { type: Object, default: () => ({}) },
  timeline_preview: { type: Array, default: () => [] },
  timeline_events: { type: Array, default: () => [] },
})

const page = usePage()
const activeTab = ref('overview')

const tabs = [
  { id: 'overview', label: 'نظرة عامة', icon: 'bi-grid' },
  { id: 'children', label: 'الأبناء', icon: 'bi-people' },
  { id: 'guardians', label: 'أولياء الأمور', icon: 'bi-person-heart' },
  { id: 'finance', label: 'المالية', icon: 'bi-cash-coin' },
  { id: 'attendance', label: 'الحضور', icon: 'bi-calendar-check' },
  { id: 'academic', label: 'أكاديمي', icon: 'bi-journal-bookmark' },
  { id: 'documents', label: 'المستندات', icon: 'bi-folder' },
  { id: 'profile', label: 'الملف الشخصي', icon: 'bi-person' },
]

const health = () => props.command_center?.health || {}
const risks = () => props.risk_summary?.items || props.command_center?.risks || []

onMounted(() => {
  if (page.props.flash?.success) {
    toast.success(page.props.flash.success)
  }
  syncTabFromUrl()
})

function syncTabFromUrl() {
  const tab = new URLSearchParams(window.location.search).get('tab')
  if (tab && tabs.some((t) => t.id === tab)) {
    activeTab.value = tab
  }
}

function setTab(tabId) {
  activeTab.value = tabId
  const url = new URL(window.location.href)
  url.searchParams.set('tab', tabId)
  window.history.replaceState({}, '', url)
}

function formatDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('ar-EG')
}

function formatDateTime(value) {
  if (!value) return '—'
  return new Date(value).toLocaleString('ar-EG')
}

function formatCurrency(value) {
  if (value == null) return '—'
  return parseFloat(value).toFixed(2)
}

function openStudent(studentId) {
  window.location.href = route('admin.students.show', studentId)
}

function openFullTimeline() {
  scrollToTimeline()
}

function scrollToTimeline() {
  setTab('overview')
  nextTick(() => {
    document.getElementById('family-full-timeline')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  })
}
</script>

<template>
  <Head :title="`مركز العائلة — ${profile.name}`" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="card-body px-1 px-sm-4">
        <WorkspaceLayoutAdapter
          :workspace-context="workspace_context"
          :active-tab="activeTab"
          @set-tab="setTab"
          @scroll-timeline="scrollToTimeline"
        >
        <div class="workspace-command-center">
        <div class="d-flex flex-wrap justify-content-end gap-2 mb-3">
          <Link :href="route('admin.parents.edit', profile.id)" class="btn btn-sm btn-warning rounded-4 shadow-sm">
            <i class="bi bi-pencil"></i> تعديل
          </Link>
        </div>

        <ParentHeaderCard :profile="profile" :format-currency="formatCurrency" />

        <FamilyQuickActions :profile="profile" @open-tab="setTab" />

        <FamilyHealthDashboard :health="health()" @open-tab="setTab" />

        <FamilyChildrenOverview :children="children" />

        <FamilyRiskPanel
          :risks="risks()"
          :format-date="formatDate"
          @open-student="openStudent"
          @open-tab="setTab"
        />

        <div class="row g-3 mb-4">
          <div class="col-lg-6">
            <FamilyAttendanceSnapshot
              :attendance="attendance_snapshot"
              @open-student="openStudent"
            />
          </div>
          <div class="col-lg-6">
            <FamilyAcademicSnapshot
              :academic="academic_snapshot"
              @open-student="openStudent"
            />
          </div>
        </div>

        <FamilyFinanceSnapshot
          :finance="finance_snapshot"
          :format-currency="formatCurrency"
          @open-tab="setTab"
        />

        <FamilyTimelinePreview
          :timeline="timeline_preview"
          :format-date-time="formatDateTime"
          @open-full="openFullTimeline"
        />
        </div>

        <!-- Overview -->
        <div v-show="activeTab === 'overview'" class="tab-pane">
          <div class="card family-command-card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4 text-center text-muted">
              <i class="bi bi-house-heart display-6 text-primary mb-2 d-block"></i>
              <p class="mb-0">مركز قيادة العائلة أعلاه يعرض الملخص التشغيلي. استخدم التبويبات للتفاصيل.</p>
            </div>
          </div>
          <div v-if="timeline_events.length" id="family-full-timeline" class="card border rounded-4">
            <div class="card-header bg-transparent"><h6 class="mb-0">الخط الزمني الكامل</h6></div>
            <div class="card-body">
              <ul class="list-unstyled mb-0 small">
                <li v-for="event in timeline_events" :key="event.id" class="py-2 border-bottom">
                  <div class="d-flex justify-content-between gap-2">
                    <span class="fw-semibold">
                      <Link
                        v-if="event.type === 'admission' && event.profile_url"
                        :href="event.profile_url"
                      >
                        {{ event.title }}
                      </Link>
                      <template v-else>{{ event.title }}</template>
                    </span>
                    <span class="text-muted">{{ formatDateTime(event.occurred_at) }}</span>
                  </div>
                  <div class="text-muted">
                    <Link
                      v-if="event.type === 'admission' && event.profile_url"
                      :href="event.profile_url"
                    >
                      {{ event.subtitle }}
                    </Link>
                    <template v-else>{{ event.subtitle }}</template>
                    <span v-if="event.student_name">— {{ event.student_name }}</span>
                  </div>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Children -->
        <div v-show="activeTab === 'children'" class="tab-pane">
          <FamilyChildrenOverview :children="children" />
        </div>

        <!-- Guardians -->
        <div v-show="activeTab === 'guardians'" class="tab-pane">
          <div class="card border rounded-4">
            <div class="card-header bg-transparent"><h6 class="mb-0">أولياء الأمور المرتبطون بالعائلة</h6></div>
            <div class="card-body p-0">
              <div v-if="guardians.length" class="eduvera-table-wrap">
                <table class="table table-sm mb-0">
                  <thead>
                    <tr>
                      <th>الاسم</th>
                      <th>الهاتف</th>
                      <th>البريد</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="g in guardians" :key="g.id" :class="{ 'table-primary': g.is_current }">
                      <td class="fw-semibold">{{ g.name }} <span v-if="g.is_current" class="badge bg-primary">الحالي</span></td>
                      <td>{{ g.phone || '—' }}</td>
                      <td>{{ g.email }}</td>
                      <td>
                        <Link v-if="!g.is_current" :href="g.profile_url" class="btn btn-sm btn-outline-primary">عرض</Link>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <p v-else class="text-muted p-3 mb-0">لا يوجد أولياء مرتبطون</p>
            </div>
          </div>
        </div>

        <!-- Finance -->
        <div v-show="activeTab === 'finance'" class="tab-pane">
          <FamilyFinanceSnapshot :finance="finance_snapshot" :format-currency="formatCurrency" @open-tab="setTab" />
          <div class="card border rounded-4">
            <div class="card-header bg-transparent"><h6 class="mb-0">تفاصيل مالية لكل ابن</h6></div>
            <div class="card-body">
              <div v-for="child in children" :key="child.id" class="d-flex flex-wrap justify-content-between align-items-center py-2 border-bottom">
                <button type="button" class="btn btn-link p-0 text-decoration-none fw-semibold" @click="openStudent(child.id)">{{ child.name }}</button>
                <div class="small">
                  محفظة: <strong>{{ formatCurrency(child.finance?.wallet_balance) }}</strong>
                  · مستحق: <strong>{{ formatCurrency(child.finance?.outstanding_balance) }}</strong>
                  · <span class="badge bg-light text-dark">{{ child.finance?.installment_status_label }}</span>
                </div>
              </div>
              <p v-if="!children.length" class="text-muted mb-0">لا يوجد أبناء</p>
            </div>
          </div>
        </div>

        <!-- Attendance -->
        <div v-show="activeTab === 'attendance'" class="tab-pane">
          <FamilyAttendanceSnapshot :attendance="attendance_snapshot" @open-student="openStudent" />
        </div>

        <!-- Academic -->
        <div v-show="activeTab === 'academic'" class="tab-pane">
          <FamilyAcademicSnapshot :academic="academic_snapshot" @open-student="openStudent" />
          <div v-for="child in children" :key="child.id" class="card border rounded-4 mb-3">
            <div class="card-header bg-transparent d-flex justify-content-between">
              <h6 class="mb-0">{{ child.name }}</h6>
              <button type="button" class="btn btn-sm btn-link" @click="openStudent(child.id)">ملف الطالب</button>
            </div>
            <div class="card-body">
              <ul v-if="child.academic?.recent?.length" class="list-unstyled mb-0 small">
                <li v-for="g in child.academic.recent" :key="g.id" class="py-1 border-bottom">
                  {{ g.title }} — {{ g.percentage }}% <span class="text-muted">({{ formatDate(g.assessed_at) }})</span>
                </li>
              </ul>
              <p v-else class="text-muted small mb-0">لا توجد تقييمات</p>
            </div>
          </div>
        </div>

        <!-- Documents / Admissions -->
        <div v-show="activeTab === 'documents'" class="tab-pane">
          <div class="card border rounded-4">
            <div class="card-header bg-transparent"><h6 class="mb-0">متابعات القبول والمستندات</h6></div>
            <div class="card-body">
              <div v-if="profile.pending_admissions?.length" class="vstack gap-2">
                <div v-for="adm in profile.pending_admissions" :key="adm.id" class="p-3 border rounded-4">
                  <div class="fw-semibold">{{ adm.message }}</div>
                  <div class="small text-muted">{{ adm.applicant_name || '—' }} · {{ formatDate(adm.date) }}</div>
                  <Link v-if="adm.profile_url" :href="adm.profile_url" class="btn btn-sm btn-outline-primary mt-2">فتح طلب القبول</Link>
                </div>
              </div>
              <p v-else class="text-muted mb-0">لا توجد متابعات قبول أو مستندات معلقة.</p>
            </div>
          </div>
        </div>

        <!-- Profile -->
        <div v-show="activeTab === 'profile'" class="tab-pane">
          <div class="card border rounded-4">
            <div class="card-body">
              <dl class="row mb-4">
                <dt class="col-sm-4">الاسم</dt><dd class="col-sm-8">{{ profile.name }}</dd>
                <dt class="col-sm-4">البريد</dt><dd class="col-sm-8">{{ profile.email }}</dd>
                <dt class="col-sm-4">الهاتف</dt><dd class="col-sm-8">{{ profile.phone || '—' }}</dd>
                <dt class="col-sm-4">الرقم القومي</dt><dd class="col-sm-8">{{ profile.national_id || '—' }}</dd>
                <dt class="col-sm-4">الوظيفة</dt><dd class="col-sm-8">{{ profile.job_title || '—' }}</dd>
                <dt class="col-sm-4">كود ولي الأمر</dt><dd class="col-sm-8"><code>{{ profile.parent_code }}</code></dd>
              </dl>
              <Link :href="route('admin.parents.edit', profile.id)" class="btn btn-primary">
                <i class="bi bi-pencil me-1"></i>تعديل البيانات وربط الأبناء
              </Link>
            </div>
          </div>
        </div>
        </WorkspaceLayoutAdapter>
      </div>
    </div>
  </AppLayout>
</template>
