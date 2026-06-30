<script setup>
import { ref, computed, watch } from 'vue'
import AppLayout from '@/Pages/Teacher/Layout/App.vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { toast } from 'vue3-toastify'

const props = defineProps({
  timetable: Object,
  myAssignments: Array,
  availablePeriods: Array,
  subjects: Array,
})

const assignForm = useForm({
  timetable_period_id: null,
  subject_id: null
})

const showAssignModal = ref(false)
const selectedPeriod = ref(null)

// Values match TimetableDay.day_name (Arabic)
const weekDays = [
  { value: 'الأحد',      label: 'الأحد' },
  { value: 'الإثنين',    label: 'الإثنين' },
  { value: 'الثلاثاء',   label: 'الثلاثاء' },
  { value: 'الأربعاء',   label: 'الأربعاء' },
  { value: 'الخميس',     label: 'الخميس' },
  { value: 'الجمعة',     label: 'الجمعة' },
  { value: 'السبت',      label: 'السبت' },
]

function openAssignModal(period) {
  selectedPeriod.value = period
  assignForm.timetable_period_id = period.id
  assignForm.subject_id = null
  showAssignModal.value = true
}

function assignSelf() {
  assignForm.post(route('teacher.timetables.assign-self'), {
    onSuccess: () => {
      showAssignModal.value = false
      toast.success('تم إضافة نفسك للحصة بنجاح')
      router.reload()
    }
  })
}

function categoryGradeName(category) {
  if (!category) return '-'
  if (category.parent?.name) return category.parent.name
  const parts = category.name?.split(' - ')
  return parts?.length > 1 ? parts.slice(0, -1).join(' - ') : (category.name || '-')
}

function categoryClassName(category) {
  if (!category?.name) return '-'
  const parts = category.name.split(' - ')
  return parts.length > 1 ? parts[parts.length - 1].trim() : '-'
}

// Group my assignments by day (keyed by Arabic day_name matching TimetableDay.day_name)
const mySchedule = computed(() => {
  const schedule = {}
  weekDays.forEach(day => {
    schedule[day.value] = []
  })

  ;(props.myAssignments || []).forEach(assignment => {
    const dayName = assignment.period?.day?.day_name
    if (dayName && schedule[dayName] !== undefined) {
      schedule[dayName].push({
        period: assignment.period,
        assignment,
      })
    }
  })

  // Sort by period_number within each day
  Object.keys(schedule).forEach(day => {
    schedule[day].sort((a, b) => a.period.period_number - b.period.period_number)
  })

  return schedule
})

const daysWithSchedule = computed(() =>
  weekDays.filter(day => (mySchedule.value[day.value]?.length ?? 0) > 0)
)

const selectedDay = ref(null)

function selectDay(dayValue) {
  selectedDay.value = dayValue
}

watch(daysWithSchedule, (days) => {
  if (!days.length) {
    selectedDay.value = null
    return
  }
  if (!selectedDay.value || !days.some(d => d.value === selectedDay.value)) {
    selectedDay.value = days[0].value
  }
}, { immediate: true })

const selectedDayLabel = computed(() =>
  weekDays.find(d => d.value === selectedDay.value)?.label ?? ''
)

const selectedDayItems = computed(() =>
  selectedDay.value ? (mySchedule.value[selectedDay.value] ?? []) : []
)

const filterGrade = ref('')
const filterClass = ref('')
const filterSubject = ref('')

watch(selectedDay, () => {
  filterGrade.value = ''
  filterClass.value = ''
  filterSubject.value = ''
})

watch(filterGrade, () => {
  filterClass.value = ''
})

const gradeFilterOptions = computed(() =>
  [...new Set(selectedDayItems.value.map(item => categoryGradeName(item.period.category)))]
    .filter(name => name && name !== '-')
    .sort((a, b) => a.localeCompare(b, 'ar'))
)

const classFilterOptions = computed(() => {
  const items = filterGrade.value
    ? selectedDayItems.value.filter(item => categoryGradeName(item.period.category) === filterGrade.value)
    : selectedDayItems.value

  return [...new Set(items.map(item => categoryClassName(item.period.category)))]
    .filter(name => name && name !== '-')
    .sort((a, b) => a.localeCompare(b, 'ar'))
})

const subjectFilterOptions = computed(() =>
  [...new Set(selectedDayItems.value.map(item => item.assignment.subject?.name).filter(Boolean))]
    .sort((a, b) => a.localeCompare(b, 'ar'))
)

const filteredDayItems = computed(() =>
  selectedDayItems.value.filter(item => {
    const grade = categoryGradeName(item.period.category)
    const className = categoryClassName(item.period.category)
    const subject = item.assignment.subject?.name || '-'

    if (filterGrade.value && grade !== filterGrade.value) return false
    if (filterClass.value && className !== filterClass.value) return false
    if (filterSubject.value && subject !== filterSubject.value) return false

    return true
  })
)

const hasActiveFilters = computed(() =>
  Boolean(filterGrade.value || filterClass.value || filterSubject.value)
)

function clearScheduleFilters() {
  filterGrade.value = ''
  filterClass.value = ''
  filterSubject.value = ''
}

// Filter available periods (not assigned)
const unassignedPeriods = computed(() => {
  return props.availablePeriods?.filter(period => {
    return !period.assignments || period.assignments.length === 0
  }) || []
})
</script>

<template>
  <Head title="جدولي الدراسي" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="card-body px-1 px-sm-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
          <h4 class="mb-0">جدولي الدراسي</h4>
        </div>
        <hr />

        <!-- My Schedule -->
        <div class="card mb-4">
          <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="mb-0">حصصي</h5>
            <ul v-if="daysWithSchedule.length" class="nav nav-pills mb-0">
              <li v-for="day in daysWithSchedule" :key="day.value" class="nav-item">
                <button
                  type="button"
                  class="nav-link"
                  :class="{ active: selectedDay === day.value }"
                  @click="selectDay(day.value)"
                >
                  {{ day.label }}
                </button>
              </li>
            </ul>
          </div>
          <div class="card-body">
            <template v-if="daysWithSchedule.length">
              <h6 class="mb-3 text-muted">{{ selectedDayLabel }}</h6>
              <div class="row g-3 mb-3 align-items-end">
                <div :class="hasActiveFilters ? 'col-md-3' : 'col-md-4'">
                  <label class="form-label small mb-1">المرحلة الدراسية</label>
                  <select v-model="filterGrade" class="form-select form-select-sm">
                    <option value="">الكل</option>
                    <option v-for="grade in gradeFilterOptions" :key="grade" :value="grade">
                      {{ grade }}
                    </option>
                  </select>
                </div>
                <div :class="hasActiveFilters ? 'col-md-3' : 'col-md-4'">
                  <label class="form-label small mb-1">الفصل</label>
                  <select v-model="filterClass" class="form-select form-select-sm">
                    <option value="">الكل</option>
                    <option v-for="className in classFilterOptions" :key="className" :value="className">
                      {{ className }}
                    </option>
                  </select>
                </div>
                <div :class="hasActiveFilters ? 'col-md-3' : 'col-md-4'">
                  <label class="form-label small mb-1">المادة</label>
                  <select v-model="filterSubject" class="form-select form-select-sm">
                    <option value="">الكل</option>
                    <option v-for="subject in subjectFilterOptions" :key="subject" :value="subject">
                      {{ subject }}
                    </option>
                  </select>
                </div>
                <div v-if="hasActiveFilters" class="col-md-3">
                  <button
                    type="button"
                    class="btn btn-sm btn-danger w-100"
                    @click="clearScheduleFilters"
                  >
                    <i class="bi bi-x-lg"></i> مسح الفلاتر
                  </button>
                </div>
              </div>
              <div class="table-responsive">
                <table :key="selectedDay" class="table table-sm table-bordered">
                  <thead>
                    <tr>
                      <th>رقم الحصة</th>
                      <th>من</th>
                      <th>إلى</th>
                      <th>المرحلة الدراسية</th>
                      <th>الفصل</th>
                      <th>المادة</th>
                      <th>الإجراءات</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="item in filteredDayItems" :key="`${selectedDay}-${item.assignment.id}`">
                      <td>{{ item.period.period_number }}</td>
                      <td>{{ item.period.time_from }}</td>
                      <td>{{ item.period.time_to }}</td>
                      <td>{{ categoryGradeName(item.period.category) }}</td>
                      <td>{{ categoryClassName(item.period.category) }}</td>
                      <td>{{ item.assignment.subject?.name || '-' }}</td>
                      <td>
                        <Link
                          :href="route('teacher.lessons.from-period', item.period.id)"
                          class="btn btn-sm btn-primary"
                        >
                          <i class="bi bi-plus-lg"></i> إضافة درس
                        </Link>
                      </td>
                    </tr>
                    <tr v-if="filteredDayItems.length === 0">
                      <td colspan="7" class="text-center text-muted py-3">
                        لا توجد حصص مطابقة للفلاتر المحددة
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </template>
            <div v-else class="text-center text-muted py-4">
              لا توجد حصص في جدولك الدراسي
            </div>
          </div>
        </div>

        <!-- Available Periods -->
        <div class="card">
          <div class="card-header">
            <h5>الحصص المتاحة</h5>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-sm table-bordered">
                <thead>
                  <tr>
                    <th>الجدول</th>
                    <th>اليوم</th>
                    <th>رقم الحصة</th>
                    <th>من</th>
                    <th>إلى</th>
                    <th>المرحلة الدراسية</th>
                    <th>الفصل</th>
                    <th>الإجراءات</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="period in unassignedPeriods" :key="period.id">
                    <td>-</td>
                    <td>{{ period.day?.day_name ?? '-' }}</td>
                    <td>{{ period.period_number }}</td>
                    <td>{{ period.time_from }}</td>
                    <td>{{ period.time_to }}</td>
                    <td>{{ categoryGradeName(period.category) }}</td>
                    <td>{{ categoryClassName(period.category) }}</td>
                    <td>
                      <button
                        class="btn btn-sm btn-success"
                        @click="openAssignModal(period)"
                      >
                        <i class="bi bi-plus"></i> إضافة نفسي
                      </button>
                    </td>
                  </tr>
                  <tr v-if="unassignedPeriods.length === 0">
                    <td colspan="8" class="text-center text-muted">لا توجد حصص متاحة</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Assign Self Modal -->
    <div v-if="showAssignModal" class="modal show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5)">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">إضافة نفسي للحصة</h5>
            <button type="button" class="btn-close" @click="showAssignModal = false"></button>
          </div>
          <div class="modal-body">
            <div v-if="selectedPeriod" class="mb-3">
              <p><strong>الجدول:</strong> {{ selectedPeriod.timetable?.name || '-' }}</p>
              <p><strong>اليوم:</strong> {{ selectedPeriod.day?.day_name ?? '-' }}</p>
              <p><strong>الحصة:</strong> {{ selectedPeriod.period_number }}</p>
              <p><strong>الوقت:</strong> {{ selectedPeriod.time_from }} - {{ selectedPeriod.time_to }}</p>
            </div>
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">المادة</label>
                <select v-model="assignForm.subject_id" class="form-select" required>
                  <option :value="null">اختر المادة</option>
                  <option v-for="subject in subjects" :key="subject.id" :value="subject.id">
                    {{ subject.name }}
                  </option>
                </select>
                <div v-if="assignForm.errors.subject_id" class="text-danger">
                  {{ assignForm.errors.subject_id }}
                </div>
              </div>
            </div>
            <div v-if="assignForm.errors" class="text-danger mt-2">
              <div v-for="(error, key) in assignForm.errors" :key="key">{{ error }}</div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="showAssignModal = false">إلغاء</button>
            <button
              type="button"
              class="btn btn-primary"
              :disabled="assignForm.processing"
              @click="assignSelf"
            >
              إضافة
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

