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
  categories: Array
})

const form = useForm({
  name: props.timetable?.name ?? '',
  academic_year: props.timetable?.academic_year ?? '',
  status: props.timetable?.status ?? 'active'
})

const dayForm = useForm({
  day_name: '',
  day_order: 0
})

const periodForm = useForm({
  timetable_day_id: null,
  period_number: 1,
  time_from: '',
  time_to: '',
  category_id: null
})

const editingDay = ref(null)
const editingPeriod = ref(null)
const showDayModal = ref(false)
const showPeriodModal = ref(false)
const selectedDayForPeriod = ref(null)
const selectedTimeForPeriod = ref(null)
const selectedCategoryFilter = ref(null)

// Get all periods from all days
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

// Helper function to get all category IDs including children recursively
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

// Filter periods by selected category
const filteredPeriods = computed(() => {
  if (!selectedCategoryFilter.value) {
    return allPeriods.value
  }
  
  // Find the selected category from the categories tree
  function findCategory(categories, categoryId) {
    for (const cat of categories) {
      if (cat.id === categoryId) {
        return cat
      }
      if (cat.children && cat.children.length > 0) {
        const found = findCategory(cat.children, categoryId)
        if (found) return found
      }
    }
    return null
  }
  
  const selectedCategory = findCategory(props.categories, selectedCategoryFilter.value)
  if (!selectedCategory) {
    return allPeriods.value
  }
  
  // Get all category IDs including children
  const categoryIds = getAllCategoryIds(selectedCategory)
  
  // Filter periods that belong to any of these category IDs
  return allPeriods.value.filter(period => {
    return period.category_id && categoryIds.includes(period.category_id)
  })
})

function submit() {
  form.put(route('admin.timetable.update'), {
    onSuccess: () => {
      toast.success('تم تحديث الجدول بنجاح')
    }
  })
}

// Day Management
function openAddDayModal() {
  editingDay.value = null
  dayForm.reset()
  dayForm.day_order = (props.timetable?.days?.length || 0) + 1
  showDayModal.value = true
}

function openEditDayModal(day) {
  editingDay.value = day
  dayForm.day_name = day.day_name
  dayForm.day_order = day.day_order
  showDayModal.value = true
}

function saveDay() {
  if (editingDay.value) {
    dayForm.put(route('admin.timetable.days.update', editingDay.value.id), {
      onSuccess: () => {
        showDayModal.value = false
        toast.success('تم تحديث اليوم بنجاح')
        router.reload()
      }
    })
  } else {
    dayForm.post(route('admin.timetable.days.add'), {
      onSuccess: () => {
        showDayModal.value = false
        toast.success('تم إضافة اليوم بنجاح')
        router.reload()
      }
    })
  }
}

function deleteDay(id) {
  Swal.fire({
    title: 'هل أنت متأكد؟',
    text: "سيتم حذف جميع الحصص في هذا اليوم!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'نعم، احذف',
    cancelButtonText: 'إلغاء'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('admin.timetable.days.delete', id), {
        onSuccess: () => {
          toast.success('تم حذف اليوم بنجاح')
          router.reload()
        }
      })
    }
  })
}

// Period Management
function handleCellClick(day, time) {
  selectedDayForPeriod.value = day
  selectedTimeForPeriod.value = time
  
  // Check if there's a period at this day and time
  const existingPeriod = allPeriods.value.find(p => 
    p.timetable_day_id === day.id && 
    time >= p.time_from && 
    time < p.time_to
  )
  
  if (existingPeriod) {
    // Open in edit mode if period exists
    openEditPeriodModal(existingPeriod)
  } else {
    // Open in add mode with pre-filled data
    openAddPeriodModal(day.id, time)
  }
}

function openAddPeriodModal(dayId = null, time = null) {
  editingPeriod.value = null
  periodForm.reset()
  periodForm.timetable_day_id = dayId || selectedDayForPeriod.value?.id || null
  periodForm.period_number = 1
  
  // Use provided time or selected time
  const selectedTime = time || selectedTimeForPeriod.value
  
  if (selectedTime) {
    // Convert to 24-hour format if needed
    periodForm.time_from = convertTo24Hour(selectedTime)
    // Add 1 hour as default end time
    const [hours, minutes] = periodForm.time_from.split(':')
    const endTime = new Date()
    endTime.setHours(parseInt(hours) + 1, parseInt(minutes))
    periodForm.time_to = `${String(endTime.getHours()).padStart(2, '0')}:${String(endTime.getMinutes()).padStart(2, '0')}`
  }
  showPeriodModal.value = true
}

function openEditPeriodModal(period) {
  editingPeriod.value = period
  periodForm.timetable_day_id = period.timetable_day_id
  periodForm.period_number = period.period_number
  periodForm.time_from = period.time_from
  periodForm.time_to = period.time_to
  periodForm.category_id = period.category_id
  showPeriodModal.value = true
}

// Convert time to 24-hour format (HH:MM)
function convertTo24Hour(timeString) {
  if (!timeString) return timeString
  
  // Remove any whitespace
  timeString = timeString.trim()
  
  // If already in 24-hour format (HH:MM), return as is
  if (/^\d{2}:\d{2}$/.test(timeString)) {
    return timeString
  }
  
  // If in 12-hour format (HH:MM AM/PM), convert it
  const timeRegex = /(\d{1,2}):(\d{2})\s*(AM|PM)/i
  const match = timeString.match(timeRegex)
  
  if (match) {
    let hours = parseInt(match[1])
    const minutes = match[2]
    const ampm = match[3].toUpperCase()
    
    if (ampm === 'PM' && hours !== 12) {
      hours += 12
    } else if (ampm === 'AM' && hours === 12) {
      hours = 0
    }
    
    return `${String(hours).padStart(2, '0')}:${minutes}`
  }
  
  // Try to extract HH:MM from any format
  const simpleMatch = timeString.match(/(\d{1,2}):(\d{2})/)
  if (simpleMatch) {
    const hours = String(parseInt(simpleMatch[1])).padStart(2, '0')
    const minutes = simpleMatch[2]
    return `${hours}:${minutes}`
  }
  
  return timeString
}

function savePeriod() {
  // Convert times to 24-hour format before sending
  const timeFrom = convertTo24Hour(periodForm.time_from)
  const timeTo = convertTo24Hour(periodForm.time_to)
  
  // Update form data with converted times
  periodForm.time_from = timeFrom
  periodForm.time_to = timeTo
  
  if (editingPeriod.value) {
    periodForm.put(route('admin.timetable.periods.update', editingPeriod.value.id), {
      onSuccess: () => {
        showPeriodModal.value = false
        toast.success('تم تحديث الحصة بنجاح')
        router.reload()
      }
    })
  } else {
    periodForm.post(route('admin.timetable.periods.add'), {
      onSuccess: () => {
        showPeriodModal.value = false
        toast.success('تم إضافة الحصة بنجاح')
        router.reload()
      }
    })
  }
}

function deletePeriod(id) {
  Swal.fire({
    title: 'هل أنت متأكد؟',
    text: "لن تتمكن من التراجع عن هذا الإجراء!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'نعم، احذف',
    cancelButtonText: 'إلغاء'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('admin.timetable.periods.delete', id), {
        onSuccess: () => {
          showPeriodModal.value = false
          toast.success('تم حذف الحصة بنجاح')
          router.reload()
        }
      })
    }
  })
}
</script>

<template>
  <Head title="تصميم الجدول الدراسي" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="card-body px-1 px-sm-4">
        <h4>تصميم الجدول الدراسي</h4>
        <Link :href="route('admin.timetable.show')" class="btn btn-info me-2">
          <i class="bi bi-eye"></i> عرض الجدول
        </Link>
        <hr />

        <!-- Edit Timetable Info -->
        <div class="card mb-4">
          <div class="card-header">
            <h5>معلومات الجدول</h5>
          </div>
          <div class="card-body">
            <div class="row g-4">
              <div class="col-md-4">
                <label class="form-label">اسم الجدول</label>
                <input
                  class="form-control"
                  v-model="form.name"
                  type="text"
                />
                <div v-if="form.errors.name" class="text-danger">{{ form.errors.name }}</div>
              </div>
              <div class="col-md-4">
                <label class="form-label">السنة الدراسية</label>
                <input
                  class="form-control"
                  v-model="form.academic_year"
                  type="text"
                />
              </div>
              <div class="col-md-4">
                <label class="form-label">الحالة</label>
                <select v-model="form.status" class="form-select">
                  <option value="active">نشط</option>
                  <option value="inactive">غير نشط</option>
                </select>
              </div>
              <div class="col-12">
                <button
                  type="button"
                  class="btn btn-primary"
                  :disabled="form.processing"
                  @click="submit"
                >
                  حفظ التغييرات
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Days Management -->
        <div class="card mb-4">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5>إدارة الأيام</h5>
            <button class="btn btn-success" @click="openAddDayModal">
              <i class="bi bi-plus"></i> إضافة يوم
            </button>
          </div>
          <div class="card-body">
            <div v-if="timetable?.days && timetable.days.length > 0" class="table-responsive">
              <table class="table table-sm table-bordered">
                <thead>
                  <tr>
                    <th>الترتيب</th>
                    <th>اسم اليوم</th>
                    <th>الحالة</th>
                    <th>عدد الحصص</th>
                    <th>الإجراءات</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="day in timetable.days" :key="day.id">
                    <td>{{ day.day_order }}</td>
                    <td>{{ day.day_name }}</td>
                    <td>
                      <span :class="day.is_active ? 'badge bg-success' : 'badge bg-danger'">
                        {{ day.is_active ? 'نشط' : 'غير نشط' }}
                      </span>
                    </td>
                    <td>{{ day.periods?.length || 0 }}</td>
                    <td>
                      <button
                        class="btn btn-sm btn-primary me-1"
                        @click="openEditDayModal(day)"
                      >
                        <i class="bi bi-pencil"></i>
                      </button>
                      <button
                        class="btn btn-sm btn-danger"
                        @click="deleteDay(day.id)"
                      >
                        <i class="bi bi-trash"></i>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-else class="text-center text-muted py-4">
              لا توجد أيام. يرجى إضافة يوم للبدء.
            </div>
          </div>
        </div>

        <!-- Calendar View -->
        <div v-if="timetable?.days && timetable.days.length > 0" class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5>الجدول الدراسي (Calendar View)</h5>
            <div class="d-flex gap-2 align-items-center">
              <select v-model="selectedCategoryFilter" class="form-select" style="width: auto; min-width: 200px;">
                <option :value="null">جميع المراحل</option>
                <CategoryOptions :categories="categories" :prefix="''" />
              </select>
              <button class="btn btn-success" @click="openAddPeriodModal">
                <i class="bi bi-plus"></i> إضافة حصة
              </button>
            </div>
          </div>
          <div class="card-body">
            <CalendarView
              :days="timetable.days"
              :periods="filteredPeriods"
              :show-assignments="false"
              :on-cell-click="handleCellClick"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Day Modal -->
    <div v-if="showDayModal" class="modal show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5)">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ editingDay ? 'تعديل يوم' : 'إضافة يوم جديد' }}</h5>
            <button type="button" class="btn-close" @click="showDayModal = false"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">اسم اليوم</label>
                <input
                  type="text"
                  v-model="dayForm.day_name"
                  class="form-control"
                  placeholder="مثال: الأحد، الإثنين، يوم 1"
                />
                <div v-if="dayForm.errors.day_name" class="text-danger">{{ dayForm.errors.day_name }}</div>
              </div>
              <div class="col-12">
                <label class="form-label">الترتيب</label>
                <input
                  type="number"
                  v-model="dayForm.day_order"
                  class="form-control"
                  min="0"
                />
              </div>
            </div>
            <div v-if="dayForm.errors" class="text-danger mt-2">
              <div v-for="(error, key) in dayForm.errors" :key="key">{{ error }}</div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="showDayModal = false">إلغاء</button>
            <button
              type="button"
              class="btn btn-primary"
              :disabled="dayForm.processing"
              @click="saveDay"
            >
              حفظ
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Period Modal -->
    <div v-if="showPeriodModal" class="modal show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5)">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ editingPeriod ? 'تعديل حصة' : 'إضافة حصة جديدة' }}</h5>
            <button type="button" class="btn-close" @click="showPeriodModal = false"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">اليوم</label>
                <select v-model="periodForm.timetable_day_id" class="form-select" required>
                  <option :value="null">اختر اليوم</option>
                  <option v-for="day in timetable?.days" :key="day.id" :value="day.id">
                    {{ day.day_name }}
                  </option>
                </select>
                <div v-if="periodForm.errors.timetable_day_id" class="text-danger">
                  {{ periodForm.errors.timetable_day_id }}
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label">رقم الحصة</label>
                <input
                  type="number"
                  v-model="periodForm.period_number"
                  class="form-control"
                  min="1"
                />
              </div>
              <div class="col-md-6">
                <label class="form-label">من</label>
                <input
                  type="time"
                  v-model="periodForm.time_from"
                  class="form-control"
                />
              </div>
              <div class="col-md-6">
                <label class="form-label">إلى</label>
                <input
                  type="time"
                  v-model="periodForm.time_to"
                  class="form-control"
                />
              </div>
              <div class="col-md-6">
                <label class="form-label">المرحلة الدراسية</label>
                <select v-model="periodForm.category_id" class="form-select">
                  <option value="all">جميع المراحل</option>
                  <CategoryOptions :categories="categories" :prefix="''" />
                </select>
                <small class="text-muted">اختر "جميع المراحل" لإضافة الحصة لكل المراحل</small>
              </div>
            </div>
            <div v-if="periodForm.errors" class="text-danger mt-2">
              <div v-for="(error, key) in periodForm.errors" :key="key">{{ error }}</div>
            </div>
          </div>
          <div class="modal-footer">
            <button 
              v-if="editingPeriod" 
              type="button" 
              class="btn btn-danger me-auto"
              :disabled="periodForm.processing"
              @click="deletePeriod(editingPeriod.id)"
            >
              <i class="bi bi-trash"></i> حذف
            </button>
            <button type="button" class="btn btn-secondary" @click="showPeriodModal = false">إلغاء</button>
            <button
              type="button"
              class="btn btn-primary"
              :disabled="periodForm.processing"
              @click="savePeriod"
            >
              حفظ
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
