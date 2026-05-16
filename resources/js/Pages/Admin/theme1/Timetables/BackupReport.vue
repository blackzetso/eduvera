<script setup>
import { ref } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import Swal from 'sweetalert2'
import { toast } from 'vue3-toastify'

const props = defineProps({
  assignments: Array,
  emptyPeriods: Array,
  timetable: Object,
  from_date: String,
  to_date: String,
  from_time: String,
  to_time: String,
  teacher_id: String,
  category_id: String,
  filter_type: String,
  teachers: Array,
  categories: Array
})

const filterType = ref(props.filter_type || 'all')
const fromDate = ref(props.from_date || '')
const toDate = ref(props.to_date || '')
const fromTime = ref(props.from_time || '')
const toTime = ref(props.to_time || '')
const selectedTeacher = ref(props.teacher_id || '')
const selectedCategory = ref(props.category_id || '')

function formatTime(time) {
  return time ? time.substring(0, 5) : ''
}

function formatDate(dateString) {
  if (!dateString) return ''
  return new Date(dateString).toLocaleDateString('ar-EG')
}

function applyFilters() {
  router.get(route('admin.timetable.filters.backup-report'), {
    from_date: fromDate.value,
    to_date: toDate.value,
    from_time: fromTime.value,
    to_time: toTime.value,
    teacher_id: selectedTeacher.value,
    category_id: selectedCategory.value,
    filter_type: filterType.value
  }, {
    preserveScroll: true
  })
}

function resetFilters() {
  fromDate.value = ''
  toDate.value = ''
  fromTime.value = ''
  toTime.value = ''
  selectedTeacher.value = ''
  selectedCategory.value = ''
  filterType.value = 'all'
  router.get(route('admin.timetable.filters.backup-report'))
}

function removeAssignment(id) {
  Swal.fire({
    title: 'هل أنت متأكد؟',
    text: "سيتم إزالة تعيين المدرس من هذه الحصة",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'نعم، احذف',
    cancelButtonText: 'إلغاء'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('admin.timetable.assignments.remove', id), {
        onSuccess: () => {
          toast.success('تم إزالة التعيين بنجاح')
          router.reload()
        }
      })
    }
  })
}

function assignTeacherToEmpty(periodId) {
  // Navigate to assign teacher modal or page
  router.visit(route('admin.timetable.periods.list', {
    period_ids: periodId
  }))
}
</script>

<template>
  <Head title="تقرير الحصص الاحتياطية والفارغة" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="card-body px-1 px-sm-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h4>تقرير الحصص الاحتياطية والفارغة</h4>
          <Link :href="route('admin.timetable.show')" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> العودة للجدول
          </Link>
        </div>

        <!-- Filter Card -->
        <div class="card mb-4">
          <div class="card-body">
            <h6 class="card-title mb-3">الفلاتر</h6>
            
            <!-- Filter Type Tabs -->
            <div class="btn-group mb-3" role="group">
              <input 
                type="radio" 
                class="btn-check" 
                name="filterType" 
                id="filterAll" 
                value="all"
                v-model="filterType"
              >
              <label class="btn btn-outline-primary" for="filterAll">
                الكل
              </label>
              
              <input 
                type="radio" 
                class="btn-check" 
                name="filterType" 
                id="filterBackup" 
                value="backup"
                v-model="filterType"
              >
              <label class="btn btn-outline-success" for="filterBackup">
                🎓 الحصص الاحتياطية المعينة
              </label>
              
              <input 
                type="radio" 
                class="btn-check" 
                name="filterType" 
                id="filterEmpty" 
                value="empty"
                v-model="filterType"
              >
              <label class="btn btn-outline-danger" for="filterEmpty">
                ❌ الحصص الفارغة
              </label>
            </div>

            <div class="row g-3">
              <div class="col-md-3">
                <label class="form-label">من تاريخ</label>
                <input 
                  type="date" 
                  class="form-control"
                  v-model="fromDate"
                >
              </div>
              <div class="col-md-3">
                <label class="form-label">إلى تاريخ</label>
                <input 
                  type="date" 
                  class="form-control"
                  v-model="toDate"
                >
              </div>
              <div class="col-md-3">
                <label class="form-label">من الوقت</label>
                <input 
                  type="time" 
                  class="form-control"
                  v-model="fromTime"
                >
              </div>
              <div class="col-md-3">
                <label class="form-label">إلى الوقت</label>
                <input 
                  type="time" 
                  class="form-control"
                  v-model="toTime"
                >
              </div>
              <div class="col-md-4">
                <label class="form-label">المدرس</label>
                <select class="form-select" v-model="selectedTeacher">
                  <option value="">-- كل المدرسين --</option>
                  <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id">
                    {{ teacher.name }}
                  </option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">الصف/المرحلة</label>
                <select class="form-select" v-model="selectedCategory">
                  <option value="">-- كل الصفوف --</option>
                  <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                    {{ cat.name }}
                  </option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">&nbsp;</label>
                <div class="d-flex gap-2">
                  <button class="btn btn-primary flex-grow-1" @click="applyFilters">
                    <i class="bi bi-search"></i> بحث
                  </button>
                  <button class="btn btn-secondary" @click="resetFilters">
                    <i class="bi bi-arrow-counterclockwise"></i> إعادة تعيين
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Statistics -->
        <div class="row g-3 mb-4">
          <div class="col-md-4" v-if="filterType !== 'empty'">
            <div class="card text-center border-success">
              <div class="card-body">
                <h6 class="card-title">الحصص الاحتياطية المعينة</h6>
                <h3 class="text-success">{{ assignments?.length || 0 }}</h3>
              </div>
            </div>
          </div>
          <div class="col-md-4" v-if="filterType !== 'backup'">
            <div class="card text-center border-danger">
              <div class="card-body">
                <h6 class="card-title">الحصص الفارغة</h6>
                <h3 class="text-danger">{{ emptyPeriods?.length || 0 }}</h3>
              </div>
            </div>
          </div>
          <div class="col-md-4" v-if="filterType === 'all'">
            <div class="card text-center border-info">
              <div class="card-body">
                <h6 class="card-title">الإجمالي</h6>
                <h3 class="text-info">{{ (assignments?.length || 0) + (emptyPeriods?.length || 0) }}</h3>
              </div>
            </div>
          </div>
        </div>

        <!-- Backup Assignments Table -->
        <div v-if="filterType !== 'empty'" class="card mb-4">
          <div class="card-header bg-success text-white">
            <h6 class="mb-0">🎓 الحصص الاحتياطية المعينة</h6>
          </div>
          <div class="card-body">
            <div v-if="assignments && assignments.length > 0">
              <div class="table-responsive">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th>اليوم</th>
                      <th>الوقت</th>
                      <th>المرحلة</th>
                      <th>المدرس</th>
                      <th>المادة</th>
                      <th>تاريخ التعيين</th>
                      <th>الإجراءات</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="assignment in assignments" :key="assignment.id">
                      <td>{{ assignment.period?.day?.day_name }}</td>
                      <td>
                        {{ formatTime(assignment.period?.time_from) }} - 
                        {{ formatTime(assignment.period?.time_to) }}
                      </td>
                      <td>{{ assignment.period?.category?.name }}</td>
                      <td>{{ assignment.teacher?.name }}</td>
                      <td>{{ assignment.subject?.name }}</td>
                      <td>{{ formatDate(assignment.created_at) }}</td>
                      <td>
                        <button 
                          class="btn btn-sm btn-danger"
                          @click="removeAssignment(assignment.id)"
                        >
                          <i class="bi bi-trash"></i> إزالة
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <div v-else class="alert alert-info mb-0">
              لا توجد حصص احتياطية معينة
            </div>
          </div>
        </div>

        <!-- Empty Periods Table -->
        <div v-if="filterType !== 'backup'" class="card">
          <div class="card-header bg-danger text-white">
            <h6 class="mb-0">❌ الحصص الفارغة (بدون تعيين)</h6>
          </div>
          <div class="card-body">
            <div v-if="emptyPeriods && emptyPeriods.length > 0">
              <div class="table-responsive">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th>اليوم</th>
                      <th>الوقت</th>
                      <th>المرحلة</th>
                      <th>رقم الحصة</th>
                      <th>الإجراءات</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="period in emptyPeriods" :key="period.id" class="table-light">
                      <td>{{ period.day?.day_name }}</td>
                      <td>
                        {{ formatTime(period.time_from) }} - 
                        {{ formatTime(period.time_to) }}
                      </td>
                      <td>{{ period.category?.name }}</td>
                      <td>#{{ period.period_number }}</td>
                      <td>
                        <button 
                          class="btn btn-sm btn-primary"
                          @click="assignTeacherToEmpty(period.id)"
                        >
                          <i class="bi bi-person-plus"></i> تعيين
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <div v-else class="alert alert-success mb-0">
              ✅ لا توجد حصص فارغة! جميع الحصص معينة.
            </div>
          </div>
        </div>

        <div v-if="filterType === 'all' && (!assignments || assignments.length === 0) && (!emptyPeriods || emptyPeriods.length === 0)" class="alert alert-warning">
          لا توجد نتائج مطابقة للفلاتر المحددة
        </div>
      </div>
    </div>
  </AppLayout>
</template>
<style scoped>
.table th {
  background-color: #e9ecef;
  font-weight: bold;
}

.btn-group {
  width: 100%;
}

.btn-group label {
  flex: 1;
}

.card {
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.card-header {
  border-radius: 8px 8px 0 0;
  padding: 1rem;
}

.table-light {
  background-color: #fff3cd;
}

.text-success {
  color: #198754;
}

.text-danger {
  color: #dc3545;
}

.text-info {
  color: #0dcaf0;
}

.border-success {
  border-color: #198754 !important;
}

.border-danger {
  border-color: #dc3545 !important;
}

.border-info {
  border-color: #0dcaf0 !important;
}
</style>