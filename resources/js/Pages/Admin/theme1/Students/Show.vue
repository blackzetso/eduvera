<script setup>
import { ref, onMounted } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { toast } from 'vue3-toastify'
import StudentLifecycleModals from './Partials/StudentLifecycleModals.vue'
import FamilyGuardianManager from './Partials/FamilyGuardianManager.vue'
import StudentHeaderCard from './Partials/StudentHeaderCard.vue'
import StudentHealthDashboard from './Partials/StudentHealthDashboard.vue'
import StudentRiskPanel from './Partials/StudentRiskPanel.vue'
import StudentFamilySummary from './Partials/StudentFamilySummary.vue'
import StudentAcademicSnapshot from './Partials/StudentAcademicSnapshot.vue'
import StudentFinanceSnapshot from './Partials/StudentFinanceSnapshot.vue'
import StudentTimelinePreview from './Partials/StudentTimelinePreview.vue'
import StudentQuickActions from './Partials/StudentQuickActions.vue'
import WorkspaceLayoutAdapter from '@/Components/Workspace/WorkspaceLayoutAdapter.vue'

const props = defineProps({
  profile: { type: Object, required: true },
  workspace_context: { type: Object, default: null },
  student_context: { type: Object, default: () => ({}) },
  command_center: { type: Object, default: () => ({}) },
  guardians: { type: Array, default: () => [] },
  siblings: { type: Array, default: () => [] },
  classInfo: { type: Object, default: null },
  overview: { type: Object, required: true },
  attendance: { type: Object, required: true },
  grades: { type: Object, required: true },
  behavior: { type: Object, required: true },
  wallet: { type: Object, required: true },
  enrollments: { type: Object, required: true },
  lifecycle: { type: Object, required: true },
  activity_timeline: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  relationshipTypeOptions: { type: Array, default: () => [] },
})

const lifecycleModals = ref(null)
const page = usePage()
const activeTab = ref('overview')

const tabs = [
  { id: 'overview', label: 'نظرة عامة', icon: 'bi-grid' },
  { id: 'personal', label: 'البيانات الشخصية', icon: 'bi-person' },
  { id: 'family', label: 'العائلة', icon: 'bi-people' },
  { id: 'enrollment', label: 'سجل القيد', icon: 'bi-clock-history' },
  { id: 'academic', label: 'أكاديمي', icon: 'bi-journal-bookmark' },
  { id: 'attendance', label: 'الحضور', icon: 'bi-calendar-check' },
  { id: 'behavior', label: 'السلوك', icon: 'bi-emoji-smile' },
  { id: 'wallet', label: 'المحفظة', icon: 'bi-wallet2' },
]

const severityLabels = {
  positive: 'إيجابي',
  neutral: 'محايد',
  negative: 'سلبي',
}

const walletTypeLabels = {
  credit: 'إيداع',
  debit: 'خصم',
  transfer_in: 'تحويل وارد',
  transfer_out: 'تحويل صادر',
}

const health = () => props.command_center?.health || {}
const risks = () => props.command_center?.risks || []
const financeSnapshot = () => props.command_center?.finance_snapshot || {}
const timelinePreview = () => props.command_center?.timeline_preview || props.activity_timeline || []

onMounted(() => {
  if (page.props.flash?.success) {
    toast.success(page.props.flash.success)
  }
  syncTabFromUrl()
})

function syncTabFromUrl() {
  const params = new URLSearchParams(window.location.search)
  const tab = params.get('tab')
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

function severityBadge(severity) {
  if (severity === 'positive') return 'bg-success'
  if (severity === 'negative') return 'bg-danger'
  return 'bg-secondary'
}

function openLifecycle(modal) {
  lifecycleModals.value?.openModal(modal)
}

function viewParent() {
  if (props.profile.primary_guardian_id) {
    window.location.href = route('admin.parents.show', props.profile.primary_guardian_id)
  }
}

async function copyStudentCode() {
  const code = props.profile.student_code
  if (!code) {
    toast.warning('لا يوجد كود طالب')
    return
  }
  try {
    await navigator.clipboard.writeText(code)
    toast.success('تم نسخ كود الطالب')
  } catch {
    toast.error('تعذر نسخ الكود')
  }
}

function openFullTimeline() {
  setTab('enrollment')
}
</script>

<template>
  <Head :title="`ملف الطالب — ${profile.name}`" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="card-body px-1 px-sm-4">
        <WorkspaceLayoutAdapter
          :workspace-context="workspace_context"
          :active-tab="activeTab"
          @set-tab="setTab"
        >
        <div class="workspace-command-center">
        <StudentHeaderCard
          :profile="profile"
          :format-date="formatDate"
          @copy-code="copyStudentCode"
        />

        <StudentQuickActions
          :profile="profile"
          :lifecycle="lifecycle"
          @lifecycle="openLifecycle"
          @open-tab="setTab"
          @view-parent="viewParent"
        />

        <StudentRiskPanel
          :risks="risks()"
          :format-date="formatDate"
          @open-tab="setTab"
        />

        <StudentHealthDashboard
          :health="health()"
          @open-tab="setTab"
        />

        <StudentFamilySummary
          :guardians="guardians"
          :siblings="siblings"
          :executive-metrics="{
            guardiansCount: profile.guardians_count ?? guardians.length,
            siblingsCount: profile.siblings_count ?? siblings.length,
            outstandingBalance: financeSnapshot().outstanding_balance,
            walletBalance: financeSnapshot().wallet_balance,
          }"
          :format-currency="formatCurrency"
          @open-tab="setTab"
        />

        <div class="row g-2 student-cc-row-tight">
          <div class="col-lg-6">
            <StudentAcademicSnapshot
              :class-info="classInfo"
              :grades="grades"
              :overview="overview"
              :academic-health="health().academic"
              :format-date="formatDate"
              @open-tab="setTab"
            />
          </div>
          <div class="col-lg-6">
            <StudentFinanceSnapshot
              :finance="financeSnapshot()"
              :format-currency="formatCurrency"
              :format-date-time="formatDateTime"
              @open-tab="setTab"
            />
          </div>
        </div>

        <StudentTimelinePreview
          :timeline="timelinePreview()"
          :format-date-time="formatDateTime"
          :limit="5"
          @open-full="openFullTimeline"
        />
        </div>

        <!-- Overview -->
        <div v-show="activeTab === 'overview'" class="tab-pane">
          <div v-if="overview.active_alert" class="alert alert-warning">
            <strong>تنبيه حضور ({{ overview.active_alert.level }})</strong>
            — غيابات: {{ overview.active_alert.absences_count }}
            <span v-if="overview.active_alert.triggered_at"> — {{ formatDate(overview.active_alert.triggered_at) }}</span>
          </div>
          <div class="card student-command-card border-0 shadow-sm">
            <div class="card-body p-4 text-center text-muted">
              <i class="bi bi-grid-3x3-gap display-6 text-primary mb-2 d-block"></i>
              <p class="mb-0">لوحة القيادة أعلاه تعرض ملخص الطالب. استخدم التبويبات للتفاصيل الكاملة.</p>
            </div>
          </div>
        </div>

        <!-- Personal -->
        <div v-show="activeTab === 'personal'" class="tab-pane">
          <div class="card border">
            <div class="card-body">
              <dl class="row mb-0">
                <dt class="col-sm-4">الاسم الكامل</dt>
                <dd class="col-sm-8">{{ profile.name }}</dd>
                <dt class="col-sm-4">الاسم الأول</dt>
                <dd class="col-sm-8">{{ profile.first_name || '—' }}</dd>
                <dt class="col-sm-4">اسم الأب</dt>
                <dd class="col-sm-8">{{ profile.father_name || '—' }}</dd>
                <dt class="col-sm-4">اسم الجد</dt>
                <dd class="col-sm-8">{{ profile.grandfather_name || '—' }}</dd>
                <dt class="col-sm-4">الرقم القومي</dt>
                <dd class="col-sm-8">{{ profile.national_id || '—' }}</dd>
                <dt class="col-sm-4">تاريخ الميلاد</dt>
                <dd class="col-sm-8">{{ formatDate(profile.date_of_birth) }}</dd>
                <dt class="col-sm-4">النوع</dt>
                <dd class="col-sm-8">
                  <template v-if="profile.gender === 'male'">ذكر</template>
                  <template v-else-if="profile.gender === 'female'">أنثى</template>
                  <template v-else>—</template>
                </dd>
                <dt class="col-sm-4">البريد الإلكتروني</dt>
                <dd class="col-sm-8">{{ profile.email }}</dd>
                <dt class="col-sm-4">الهاتف</dt>
                <dd class="col-sm-8">{{ profile.phone || '—' }}</dd>
                <dt class="col-sm-4">تاريخ القيد</dt>
                <dd class="col-sm-8">{{ formatDate(profile.enrollment_date) }}</dd>
                <dt class="col-sm-4">رقم النظام</dt>
                <dd class="col-sm-8">
                  <code class="text-primary">{{ profile.id }}</code>
                  <span class="text-muted small ms-2">مرجع بحث الكافتيريا (POS)</span>
                </dd>
                <dt class="col-sm-4">كود الطالب</dt>
                <dd class="col-sm-8">
                  <code>{{ profile.student_code || '—' }}</code>
                  <button
                    v-if="profile.student_code"
                    type="button"
                    class="btn btn-sm btn-outline-secondary ms-2"
                    @click="copyStudentCode"
                  >
                    <i class="bi bi-clipboard"></i> نسخ
                  </button>
                </dd>
              </dl>
            </div>
          </div>
        </div>

        <!-- Family -->
        <div v-show="activeTab === 'family'" class="tab-pane">
          <FamilyGuardianManager
            :student-id="profile.id"
            :guardians="guardians"
            :relationship-type-options="relationshipTypeOptions"
          />
          <div class="card border">
            <div class="card-header bg-transparent"><h6 class="mb-0">الإخوة (عبر أولياء الأمور المشتركين)</h6></div>
            <div class="card-body">
              <div v-if="siblings.length" class="eduvera-table-wrap">
                <table class="table table-sm mb-0">
                  <thead>
                    <tr>
                      <th>الاسم</th>
                      <th>الصف</th>
                      <th>الحالة</th>
                      <th>كود الطالب</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="s in siblings" :key="s.id">
                      <td>
                        <Link :href="route('admin.students.show', s.id)" class="fw-semibold">{{ s.name }}</Link>
                      </td>
                      <td>{{ s.grade_label || '—' }}</td>
                      <td><span class="badge" :class="s.status_badge_class">{{ s.status_label }}</span></td>
                      <td><code>{{ s.student_code || '—' }}</code></td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <p v-else class="text-muted mb-0">لا يوجد إخوة مسجلون عبر نفس أولياء الأمور.</p>
            </div>
          </div>
        </div>

        <!-- Enrollment History -->
        <div v-show="activeTab === 'enrollment'" class="tab-pane">
          <div v-if="enrollments.current" class="card border border-primary mb-4">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
              <h6 class="mb-0">القيد الحالي</h6>
              <span class="badge bg-primary">حالي</span>
            </div>
            <div class="card-body">
              <div class="row g-3 small">
                <div class="col-md-4">
                  <span class="text-muted d-block">العام الدراسي</span>
                  <strong>{{ enrollments.current.academic_year }}</strong>
                </div>
                <div class="col-md-4">
                  <span class="text-muted d-block">المرحلة / الصف / الفصل</span>
                  <strong>{{ enrollments.current.path_label || '—' }}</strong>
                </div>
                <div class="col-md-4">
                  <span class="text-muted d-block">نوع الحركة</span>
                  <strong>{{ enrollments.current.action_type_label }}</strong>
                </div>
                <div class="col-md-4">
                  <span class="text-muted d-block">تاريخ القيد</span>
                  <strong>{{ formatDate(enrollments.current.enrollment_date) }}</strong>
                </div>
                <div class="col-md-4">
                  <span class="text-muted d-block">الحالة</span>
                  <strong>{{ enrollments.current.status_label }}</strong>
                </div>
                <div v-if="enrollments.current.notes" class="col-12">
                  <span class="text-muted d-block">ملاحظات</span>
                  <span>{{ enrollments.current.notes }}</span>
                </div>
              </div>
            </div>
          </div>
          <div class="card border">
            <div class="card-header bg-transparent"><h6 class="mb-0">الخط الزمني للقيد</h6></div>
            <div class="card-body">
              <div v-if="enrollments.timeline.length" class="enrollment-timeline">
                <div
                  v-for="item in enrollments.timeline"
                  :key="item.id"
                  class="enrollment-timeline__item"
                  :class="{ 'enrollment-timeline__item--current': item.is_current }"
                >
                  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                    <div>
                      <span class="badge me-2" :class="item.is_current ? 'bg-primary' : 'bg-secondary'">
                        {{ item.action_type_label }}
                      </span>
                      <span class="badge bg-light text-dark">{{ item.academic_year }}</span>
                    </div>
                    <small class="text-muted">{{ formatDate(item.enrollment_date) }}</small>
                  </div>
                  <p class="mb-1 fw-semibold">{{ item.path_label || '—' }}</p>
                  <p class="mb-0 small text-muted">
                    الحالة: {{ item.status_label }}
                    <span v-if="item.promotion_date"> — ترقية: {{ formatDate(item.promotion_date) }}</span>
                    <span v-if="item.withdrawal_date"> — انسحاب: {{ formatDate(item.withdrawal_date) }}</span>
                  </p>
                  <p v-if="item.reason" class="mb-0 small mt-1">السبب: {{ item.reason }}</p>
                  <p v-if="item.notes" class="mb-0 small text-muted mt-1">{{ item.notes }}</p>
                </div>
              </div>
              <p v-else class="text-muted mb-0">لا يوجد سجل قيد مسجل بعد.</p>
            </div>
          </div>
          <div v-if="activity_timeline.length" class="card border mt-4">
            <div class="card-header bg-transparent"><h6 class="mb-0">سجل النشاط الكامل</h6></div>
            <div class="card-body">
              <div class="enrollment-timeline">
                <div v-for="event in activity_timeline" :key="event.id" class="enrollment-timeline__item">
                  <div class="d-flex flex-wrap justify-content-between gap-2 mb-1">
                    <div>
                      <span class="badge me-2" :class="event.badge_class">{{ event.title }}</span>
                      <span v-if="event.subtitle" class="small">{{ event.subtitle }}</span>
                    </div>
                    <small class="text-muted">{{ formatDateTime(event.occurred_at) }}</small>
                  </div>
                  <p v-if="event.reason" class="mb-0 small text-muted">السبب: {{ event.reason }}</p>
                  <p v-if="event.performed_by" class="mb-0 small text-muted">بواسطة: {{ event.performed_by }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Academic -->
        <div v-show="activeTab === 'academic'" class="tab-pane">
          <div class="card border mb-4">
            <div class="card-header bg-transparent"><h6 class="mb-0">الصف الحالي</h6></div>
            <div class="card-body">
              <template v-if="classInfo">
                <p class="mb-2"><strong>المسار:</strong> {{ classInfo.path_label }}</p>
                <p class="mb-0 text-muted small">عدد المواد المرتبطة: {{ classInfo.subjects_count }}</p>
              </template>
              <p v-else class="text-muted mb-0">لم يُعيَّن صف للطالب بعد.</p>
            </div>
          </div>
          <div class="card border">
            <div class="card-header bg-transparent"><h6 class="mb-0">الدرجات</h6></div>
            <div class="card-body">
              <p v-if="grades.average_percent != null" class="small text-muted">
                المتوسط: <strong>{{ grades.average_percent }}%</strong> — {{ grades.count }} سجل
              </p>
              <div v-if="grades.items.length" class="eduvera-table-wrap">
                <table class="table table-sm mb-0">
                  <thead>
                    <tr>
                      <th>العنوان</th>
                      <th>المادة</th>
                      <th>الدرجة</th>
                      <th>التاريخ</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="g in grades.items" :key="g.id">
                      <td>{{ g.title }}</td>
                      <td>{{ g.subject || '—' }}</td>
                      <td>{{ g.score }} / {{ g.max_score }} ({{ g.percentage }}%)</td>
                      <td>{{ formatDate(g.assessed_at) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <p v-else class="text-muted mb-0">لا توجد درجات مسجلة.</p>
            </div>
          </div>
        </div>

        <!-- Attendance -->
        <div v-show="activeTab === 'attendance'" class="tab-pane">
          <div class="row g-2 mb-3 eduvera-kpi-row">
            <div class="col-6 col-md-3">
              <div class="card p-2 text-center border h-100">
                <div class="small text-muted">نسبة الحضور</div>
                <strong>{{ attendance.summary.rate_percent != null ? `${attendance.summary.rate_percent}%` : '—' }}</strong>
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="card p-2 text-center border h-100">
                <div class="small text-muted">حاضر</div>
                <strong class="text-success">{{ attendance.summary.present }}</strong>
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="card p-2 text-center border h-100">
                <div class="small text-muted">غائب</div>
                <strong class="text-danger">{{ attendance.summary.absent }}</strong>
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="card p-2 text-center border h-100">
                <div class="small text-muted">متأخر / بعذر</div>
                <strong>{{ attendance.summary.late }} / {{ attendance.summary.excused }}</strong>
              </div>
            </div>
          </div>
          <div class="card border">
            <div class="card-body p-0">
              <div v-if="attendance.records.length" class="eduvera-table-wrap">
                <table class="table table-sm mb-0">
                  <thead>
                    <tr>
                      <th>التاريخ</th>
                      <th>النوع</th>
                      <th>الحالة</th>
                      <th>ملاحظات</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="r in attendance.records" :key="r.id">
                      <td>{{ formatDate(r.attendance_date) }}</td>
                      <td>{{ r.session_label || r.session_type }}</td>
                      <td>{{ r.status_label || r.status }}</td>
                      <td>{{ r.notes || '—' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <p v-else class="text-muted p-3 mb-0">لا توجد سجلات حضور.</p>
            </div>
          </div>
        </div>

        <!-- Behavior -->
        <div v-show="activeTab === 'behavior'" class="tab-pane">
          <div class="row g-3 mb-4 eduvera-kpi-row">
            <div class="col-4">
              <div class="card border text-center h-100">
                <div class="card-body">
                  <div class="fw-bold text-success fs-4">{{ behavior.counts.positive }}</div>
                  <small class="text-muted">إيجابي</small>
                </div>
              </div>
            </div>
            <div class="col-4">
              <div class="card border text-center h-100">
                <div class="card-body">
                  <div class="fw-bold fs-4">{{ behavior.counts.neutral }}</div>
                  <small class="text-muted">محايد</small>
                </div>
              </div>
            </div>
            <div class="col-4">
              <div class="card border text-center h-100">
                <div class="card-body">
                  <div class="fw-bold text-danger fs-4">{{ behavior.counts.negative }}</div>
                  <small class="text-muted">سلبي</small>
                </div>
              </div>
            </div>
          </div>
          <div class="vstack gap-3">
            <div v-for="r in behavior.items" :key="r.id" class="card border">
              <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                  <div>
                    <span class="badge me-2" :class="severityBadge(r.severity)">
                      {{ severityLabels[r.severity] || r.severity }}
                    </span>
                    <span v-if="r.category" class="badge bg-light text-dark">{{ r.category }}</span>
                    <h6 class="mt-2 mb-1">{{ r.title }}</h6>
                    <p v-if="r.description" class="small text-muted mb-1">{{ r.description }}</p>
                    <p v-if="r.recorded_by" class="small text-muted mb-0">سجّلها: {{ r.recorded_by }}</p>
                  </div>
                  <small class="text-muted">{{ formatDate(r.occurred_at) }}</small>
                </div>
              </div>
            </div>
            <p v-if="!behavior.items.length" class="text-muted mb-0">لا توجد ملاحظات سلوكية مسجلة.</p>
          </div>
        </div>

        <!-- Wallet -->
        <div v-show="activeTab === 'wallet'" class="tab-pane">
          <div class="row g-3 mb-4 eduvera-kpi-row">
            <div class="col-sm-4">
              <div class="card border h-100">
                <div class="card-body text-center">
                  <div class="display-6 text-warning"><i class="bi bi-wallet2"></i></div>
                  <h4 class="mb-0">{{ formatCurrency(wallet.balance) }}</h4>
                  <small class="text-muted">الرصيد الحالي</small>
                </div>
              </div>
            </div>
            <div class="col-sm-4">
              <div class="card border h-100">
                <div class="card-body text-center">
                  <h4 class="mb-0 text-success">{{ formatCurrency(wallet.total_credited) }}</h4>
                  <small class="text-muted">إجمالي الإيداع</small>
                </div>
              </div>
            </div>
            <div class="col-sm-4">
              <div class="card border h-100">
                <div class="card-body text-center">
                  <h4 class="mb-0 text-danger">{{ formatCurrency(wallet.total_debited) }}</h4>
                  <small class="text-muted">إجمالي الخصم</small>
                </div>
              </div>
            </div>
          </div>
          <div class="card border">
            <div class="card-header bg-transparent"><h6 class="mb-0">آخر الحركات</h6></div>
            <div class="card-body p-0">
              <div v-if="wallet.transactions.length" class="eduvera-table-wrap">
                <table class="table table-sm mb-0">
                  <thead>
                    <tr>
                      <th>التاريخ</th>
                      <th>النوع</th>
                      <th>المبلغ</th>
                      <th>الوصف</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="t in wallet.transactions" :key="t.id">
                      <td>{{ formatDateTime(t.created_at) }}</td>
                      <td>{{ walletTypeLabels[t.type] || t.type }}</td>
                      <td>{{ formatCurrency(t.amount) }}</td>
                      <td>{{ t.description || '—' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <p v-else class="text-muted p-3 mb-0">لا توجد حركات محفظة مسجلة.</p>
            </div>
          </div>
        </div>
        </WorkspaceLayoutAdapter>
      </div>
    </div>

    <StudentLifecycleModals
      ref="lifecycleModals"
      :student-id="profile.id"
      :enrollments="enrollments"
      :lifecycle="lifecycle"
      :categories="categories"
    />
  </AppLayout>
</template>

<style scoped>
.enrollment-timeline {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
.enrollment-timeline__item {
  border-inline-start: 3px solid var(--bs-secondary);
  padding-inline-start: 1rem;
}
.enrollment-timeline__item--current {
  border-inline-start-color: var(--bs-primary);
}
</style>
