<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { toast } from 'vue3-toastify'

const props = defineProps({
  timetable: Object,
  plan: Object,
  planRows: { type: Array, default: () => [] },
  staffing: { type: Array, default: () => [] },
  workforceReport: Object,
  departments: { type: Array, default: () => [] },
  teachers: { type: Array, default: () => [] },
  subjects: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  executiveSummary: { type: Array, default: () => [] },
  selectedDepartment: String,
})

const activeTab = ref('plan')

const itemForm = useForm({
  items: props.planRows.map((r) => ({
    subject_id: r.subject_id,
    category_id: null,
    required_periods: r.required_periods,
  })),
})

const staffingRows = ref(
  props.staffing.length
    ? [...props.staffing]
    : [{ teacher_id: null, subject_id: null, category_id: null, allocated_periods: 0 }]
)

const staffingForm = useForm({
  staffing: staffingRows.value,
})

function addStaffingRow() {
  staffingRows.value.push({
    teacher_id: null,
    subject_id: null,
    category_id: null,
    allocated_periods: 0,
  })
}

function saveItems() {
  if (!props.plan?.id) return
  itemForm.post(route('department-plan.items.sync', props.plan.id), {
    preserveScroll: true,
    onSuccess: () => toast.success('تم حفظ المتطلبات'),
  })
}

function saveStaffing() {
  if (!props.plan?.id) return
  staffingForm.staffing = staffingRows.value.filter(
    (r) => r.teacher_id && r.subject_id && r.allocated_periods > 0
  )
  staffingForm.post(route('department-plan.staffing.sync', props.plan.id), {
    preserveScroll: true,
    onSuccess: () => toast.success('تم حفظ التوزيع'),
  })
}

function activatePlan() {
  if (!props.plan?.id) return
  router.post(route('department-plan.activate', props.plan.id), {}, {
    preserveScroll: true,
    onSuccess: () => toast.success('تم تفعيل الخطة للتوليد'),
  })
}

function switchDepartment(label) {
  router.get(route('department-plan.index'), { department: label }, { preserveState: false })
}

const reportSubjects = computed(() => props.workforceReport?.subjects ?? [])
</script>

<template>
  <Head title="خطة القسم" />
  <AppLayout>
    <div class="page-content-wrapper border" dir="rtl">
      <div class="card-body px-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
          <div>
            <h4 class="mb-1">خطة القسم</h4>
            <p class="text-muted small mb-0">Department Plan — توزيع احتياجات التدريس قبل اكتمال الجدول</p>
          </div>
          <div class="d-flex gap-2 align-items-center">
            <select
              class="form-select form-select-sm"
              :value="selectedDepartment"
              @change="switchDepartment($event.target.value)"
            >
              <option v-for="d in departments" :key="d" :value="d">{{ d }}</option>
              <option v-if="!departments.length" :value="selectedDepartment">{{ selectedDepartment }}</option>
            </select>
            <button type="button" class="btn btn-sm btn-success" @click="activatePlan">تفعيل للتوليد</button>
          </div>
        </div>

        <ul class="nav nav-tabs mb-3">
          <li class="nav-item">
            <button
              type="button"
              class="nav-link"
              :class="{ active: activeTab === 'plan' }"
              @click="activeTab = 'plan'"
            >
              الخطة
            </button>
          </li>
          <li class="nav-item">
            <button
              type="button"
              class="nav-link"
              :class="{ active: activeTab === 'report' }"
              @click="activeTab = 'report'"
            >
              تقرير احتياجات القسم
            </button>
          </li>
          <li class="nav-item">
            <button
              type="button"
              class="nav-link"
              :class="{ active: activeTab === 'executive' }"
              @click="activeTab = 'executive'"
            >
              ملخص الأقسام
            </button>
          </li>
        </ul>

        <div v-if="activeTab === 'plan'">
          <div class="table-responsive mb-4">
            <table class="table table-bordered align-middle">
              <thead class="table-light">
                <tr>
                  <th>المادة</th>
                  <th>المطلوب (حصة/أسبوع)</th>
                  <th>المعيّن</th>
                  <th>المتبقي</th>
                  <th>الحالة</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in planRows" :key="row.subject_id">
                  <td class="fw-semibold">{{ row.subject_name }}</td>
                  <td>{{ row.required_periods }}</td>
                  <td>
                    <div v-for="(t, i) in row.teachers" :key="i" class="small">
                      {{ t.teacher_name }} → {{ t.allocated_periods }}
                    </div>
                    <span v-if="!row.teachers?.length" class="text-muted small">—</span>
                  </td>
                  <td>{{ row.remaining_periods }}</td>
                  <td>
                    <span
                      class="badge"
                      :class="row.status === 'need_teacher' ? 'bg-warning text-dark' : 'bg-success'"
                    >
                      {{ row.status_label }}
                    </span>
                  </td>
                </tr>
                <tr v-if="!planRows.length">
                  <td colspan="5" class="text-center text-muted">أضف متطلبات المواد أدناه</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="card border mb-4">
            <div class="card-header fw-bold">متطلبات المواد</div>
            <div class="card-body">
              <div
                v-for="(item, idx) in itemForm.items"
                :key="idx"
                class="row g-2 mb-2 align-items-end"
              >
                <div class="col-md-5">
                  <label class="form-label small">المادة</label>
                  <select v-model="item.subject_id" class="form-select form-select-sm">
                    <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }}</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label small">الحصص المطلوبة</label>
                  <input v-model.number="item.required_periods" type="number" min="0" class="form-control form-control-sm" />
                </div>
              </div>
              <button type="button" class="btn btn-sm btn-primary mt-2" :disabled="itemForm.processing" @click="saveItems">
                حفظ المتطلبات
              </button>
            </div>
          </div>

          <div class="card border">
            <div class="card-header fw-bold d-flex justify-content-between">
              <span>توزيع المعلمين</span>
              <button type="button" class="btn btn-sm btn-outline-primary" @click="addStaffingRow">+ صف</button>
            </div>
            <div class="card-body">
              <div
                v-for="(row, idx) in staffingRows"
                :key="idx"
                class="row g-2 mb-2 align-items-end"
              >
                <div class="col-md-3">
                  <select v-model="row.teacher_id" class="form-select form-select-sm">
                    <option :value="null">المعلم</option>
                    <option v-for="t in teachers" :key="t.id" :value="t.id">{{ t.name }}</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <select v-model="row.subject_id" class="form-select form-select-sm">
                    <option :value="null">المادة</option>
                    <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }}</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <input
                    v-model.number="row.allocated_periods"
                    type="number"
                    min="0"
                    class="form-control form-control-sm"
                    placeholder="عدد الحصص"
                  />
                </div>
              </div>
              <button type="button" class="btn btn-sm btn-primary mt-2" :disabled="staffingForm.processing" @click="saveStaffing">
                حفظ التوزيع
              </button>
            </div>
          </div>
        </div>

        <div v-else-if="activeTab === 'report'">
          <h5 class="fw-bold mb-3">تقرير احتياجات القسم — {{ workforceReport?.department }}</h5>
          <table class="table table-bordered">
            <thead class="table-light">
              <tr>
                <th>المادة</th>
                <th>المطلوب</th>
                <th>المتاح</th>
                <th>النقص</th>
                <th>التغطية %</th>
                <th>الحالة</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(s, i) in reportSubjects" :key="i">
                <td>{{ s.subject_name }}</td>
                <td>{{ s.required_periods }}</td>
                <td>{{ s.available_periods }}</td>
                <td>{{ s.shortage }}</td>
                <td>{{ s.coverage_percent }}%</td>
                <td>{{ s.status_label }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else>
          <h5 class="fw-bold mb-3">احتياجات الأقسام</h5>
          <ul class="list-group">
            <li
              v-for="(e, i) in executiveSummary"
              :key="i"
              class="list-group-item d-flex justify-content-between"
            >
              <span>{{ e.department }}</span>
              <span
                class="badge"
                :class="{
                  'bg-danger': e.status === 'shortage',
                  'bg-success': e.status === 'complete',
                  'bg-warning text-dark': e.status === 'surplus',
                }"
              >
                {{ e.label }}
              </span>
            </li>
            <li v-if="!executiveSummary.length" class="list-group-item text-muted">لا توجد خطط أقسام بعد</li>
          </ul>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
