<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, useForm, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import Swal from 'sweetalert2'
import { toast } from 'vue3-toastify'
import CalendarView from './CalendarView.vue'
import CategoryOptions from '@/Pages/Admin/theme1/Categories/CategoryOptions.vue'

const props = defineProps({
  timetable: Object,
  teachers: Array,
  subjects: Array,
  categories: Array
})

const assignForm = useForm({
  timetable_period_id: null,
  teacher_id: null,
  subject_id: null,
  type: 'main'
})

const showAssignModal = ref(false)
const selectedPeriod = ref(null)
const selectedCategoryFilter = ref(null)
const filterType = ref('all')
const selectedTeacherFilter = ref(null)
const dateRangeFilter = ref({
  from_date: '',
  to_date: ''
})

function getTodayArabicDayName() {
  return new Intl.DateTimeFormat('ar-EG', {
    weekday: 'long',
    timeZone: 'Africa/Cairo'
  }).format(new Date())
}

function getInitialDay() {
  return getTodayArabicDayName()
}

const selectedDay = ref(getInitialDay())

const visibleDays = computed(() =>
  props.timetable?.days?.filter(d => d.day_name === selectedDay.value) ?? []
)

// Filter days = timetable days + today if today is not already present
const filterDays = computed(() => {
  const days = props.timetable?.days ?? []
  const today = getTodayArabicDayName()
  const alreadyIn = days.some(d => d.day_name === today)
  if (alreadyIn) return days
  return [{ id: null, day_name: today }, ...days]
})

// All periods from all days
const allPeriods = computed(() => {
  const periods = []
  props.timetable?.days?.forEach(day => {
    day.periods?.forEach(period => {
      periods.push({
        ...period,
        timetable_day_id: day.id
      })
    })
  })
  return periods
})

function getAllCategoryIds(category, allIds = []) {
  if (!category) return allIds
  allIds.push(category.id)
  if (category.children && category.children.length > 0) {
    category.children.forEach(child => {
      getAllCategoryIds(child, allIds)
    })
  }
  return allIds
}

const filteredPeriods = computed(() => {
  if (!selectedCategoryFilter.value) {
    return allPeriods.value
  }

  function findCategory(categories, categoryId) {
    for (const cat of categories) {
      if (cat.id === categoryId) return cat
      if (cat.children && cat.children.length > 0) {
        const found = findCategory(cat.children, categoryId)
        if (found) return found
      }
    }
    return null
  }

  const selectedCategory = findCategory(props.categories, selectedCategoryFilter.value)
  if (!selectedCategory) return allPeriods.value

  const categoryIds = getAllCategoryIds(selectedCategory)
  return allPeriods.value.filter(period =>
    period.category_id && categoryIds.includes(period.category_id)
  )
})

// Only periods belonging to the currently visible day — scopes time range to that day
const visibleFilteredPeriods = computed(() => {
  const visibleDayIds = new Set(visibleDays.value.map(d => d.id))
  return filteredPeriods.value.filter(p => visibleDayIds.has(p.timetable_day_id))
})

function handleCellClick(day, time) {
  const period = filteredPeriods.value.find(p =>
    p.timetable_day_id === day.id &&
    p.time_from <= time &&
    p.time_to > time
  )
  if (period) {
    openAssignModal(period)
  }
}

function openAssignModal(period) {
  selectedPeriod.value = period
  assignForm.timetable_period_id = period.id
  assignForm.teacher_id = null
  assignForm.subject_id = null
  assignForm.type = 'main'
  showAssignModal.value = true
}

function createLessonFromPeriod(periodId) {
  router.visit(route('admin.timetable.periods.create-lesson', periodId))
}

function applyFilters() {
  if (filterType.value === 'backup') {
    router.visit(route('admin.timetable.filters.backup'))
  } else if (filterType.value === 'teacher' && selectedTeacherFilter.value) {
    router.visit(route('admin.timetable.filters.teacher', selectedTeacherFilter.value))
  } else if (filterType.value === 'dateRange' && dateRangeFilter.value.from_date && dateRangeFilter.value.to_date) {
    router.visit(route('admin.timetable.filters.backup-report'), {
      data: dateRangeFilter.value
    })
  }
}

function assignTeacher() {
  assignForm.post(route('admin.timetable.assign-teacher'), {
    onSuccess: () => {
      showAssignModal.value = false
      toast.success('تم تعيين المدرس بنجاح')
      router.reload()
    },
    onError: (errors) => {
      if (errors.teacher_id) {
        toast.error(errors.teacher_id)
      } else if (errors.timetable_period_id) {
        toast.error(errors.timetable_period_id)
      } else if (errors.subject_id) {
        toast.error(errors.subject_id)
      } else {
        Object.values(errors).forEach(error => {
          if (Array.isArray(error)) {
            error.forEach(err => toast.error(err))
          } else {
            toast.error(error)
          }
        })
      }
    }
  })
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
</script>

<template>
  <Head title="عرض الجدول" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="card-body px-1 px-sm-4">
        <h4>عرض الجدول الدراسي</h4>
        <div class="d-flex gap-2 mb-3 flex-wrap">
          <Link :href="route('admin.timetable.edit')" class="btn btn-primary">
            <i class="bi bi-pencil"></i> منشئ الجدول
          </Link>
          <div class="dropdown">
            <button class="btn btn-info dropdown-toggle" type="button" data-bs-toggle="dropdown">
              <i class="bi bi-funnel"></i> الفلاتر والتقارير
            </button>
            <ul class="dropdown-menu">
              <li>
                <a class="dropdown-item" :href="route('admin.timetable.filters.backup')">
                  <i class="bi bi-person-check"></i> الحصص الاحتياطية فقط
                </a>
              </li>
              <li><hr class="dropdown-divider"></li>
              <li>
                <label class="dropdown-item-text">
                  <strong>جدول مدرس:</strong>
                </label>
              </li>
              <li v-for="teacher in teachers" :key="teacher.id">
                <a class="dropdown-item" :href="route('admin.timetable.filters.teacher', teacher.id)">
                  {{ teacher.name }}
                </a>
              </li>
            </ul>
          </div>
        </div>
        <hr />

        <!-- Day picker — starts from the right, today highlighted green -->
        <div v-if="timetable?.days && timetable.days.length > 0"
             class="d-flex flex-wrap gap-2 mb-3 align-items-center justify-content-start"
             dir="rtl">
          <span class="fw-semibold ms-1">اليوم:</span>
          <button
            v-for="day in filterDays"
            :key="day.id"
            type="button"
            class="btn btn-sm"
            :class="day.day_name === getTodayArabicDayName()
              ? (selectedDay === day.day_name ? 'btn-success' : 'btn-outline-success')
              : (selectedDay === day.day_name ? 'btn-primary' : 'btn-outline-secondary')"
            @click="selectedDay = day.day_name"
          >
            {{ day.day_name }}
          </button>
        </div>

        <!-- Calendar card -->
        <div v-if="timetable?.days && timetable.days.length > 0" class="card">
          <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
              <h5 class="mb-0">
                جدول يوم {{ selectedDay }}
                <small v-if="visibleDays.length === 0" class="text-muted fs-6"> — لا يوجد جدول لهذا اليوم</small>
              </h5>
              <select v-model="selectedCategoryFilter" class="form-select" style="width: auto; min-width: 200px;">
                <option :value="null">جميع المراحل</option>
                <CategoryOptions :categories="categories" :prefix="''" />
              </select>
            </div>
          </div>
          <div class="card-body">
            <div v-if="visibleDays.length === 0" class="alert alert-info mb-0">
              لا توجد حصص مسجّلة لهذا اليوم.
            </div>
            <CalendarView
              v-else
              :days="visibleDays"
              :periods="visibleFilteredPeriods"
              :show-assignments="true"
              :readonly="true"
            />
          </div>
        </div>
        <div v-else class="alert alert-info">
          لا توجد أيام في الجدول. يرجى إضافة أيام من صفحة التصميم.
        </div>
      </div>
    </div>

    <!-- Assign Teacher Modal (kept for other pages that reuse this layout) -->
    <div v-if="showAssignModal" class="modal show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5)">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">تعيين مدرس للحصة</h5>
            <button type="button" class="btn-close" @click="showAssignModal = false"></button>
          </div>
          <div class="modal-body">
            <div v-if="selectedPeriod" class="mb-3">
              <p><strong>اليوم:</strong> {{ timetable?.days?.find(d => d.id === selectedPeriod.timetable_day_id)?.day_name }}</p>
              <p><strong>الحصة:</strong> {{ selectedPeriod.period_number }}</p>
              <p><strong>الوقت:</strong> {{ selectedPeriod.time_from }} - {{ selectedPeriod.time_to }}</p>
              <p v-if="selectedPeriod.category"><strong>المرحلة:</strong> {{ selectedPeriod.category.name }}</p>
              <div class="mt-2">
                <button
                  type="button"
                  class="btn btn-sm btn-success"
                  @click="createLessonFromPeriod(selectedPeriod.id)"
                >
                  <i class="bi bi-plus-circle"></i> إنشاء درس من هذه الحصة
                </button>
              </div>
            </div>
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">المدرس</label>
                <select v-model="assignForm.teacher_id" class="form-select" required>
                  <option :value="null">اختر المدرس</option>
                  <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id">
                    {{ teacher.name }}
                  </option>
                </select>
                <div v-if="assignForm.errors.teacher_id" class="text-danger">
                  {{ assignForm.errors.teacher_id }}
                </div>
              </div>
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
              <div class="col-12">
                <label class="form-label">نوع التعيين</label>
                <div class="form-check">
                  <input
                    class="form-check-input"
                    type="radio"
                    v-model="assignForm.type"
                    value="main"
                    id="typeMain"
                  >
                  <label class="form-check-label" for="typeMain">حصة أساسية</label>
                </div>
                <div class="form-check">
                  <input
                    class="form-check-input"
                    type="radio"
                    v-model="assignForm.type"
                    value="backup"
                    id="typeBackup"
                  >
                  <label class="form-check-label" for="typeBackup">حصة احتياطية</label>
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
              @click="assignTeacher"
            >
              تعيين
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
