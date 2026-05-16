<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Pages/Teacher/Layout/App.vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import Swal from 'sweetalert2'
import { toast } from 'vue3-toastify'

const props = defineProps({
  timetable: Object,
  myAssignments: Array,
  availablePeriods: Array,
  subjects: Array
})

const assignForm = useForm({
  timetable_period_id: null,
  subject_id: null
})

const showAssignModal = ref(false)
const selectedPeriod = ref(null)

const weekDays = [
  { value: 'sunday', label: 'الأحد' },
  { value: 'monday', label: 'الإثنين' },
  { value: 'tuesday', label: 'الثلاثاء' },
  { value: 'wednesday', label: 'الأربعاء' },
  { value: 'thursday', label: 'الخميس' },
  { value: 'friday', label: 'الجمعة' },
  { value: 'saturday', label: 'السبت' }
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

function removeSelfAssignment(id) {
  Swal.fire({
    title: 'هل أنت متأكد؟',
    text: "سيتم إزالة نفسك من هذه الحصة",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'نعم، احذف',
    cancelButtonText: 'إلغاء'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('teacher.timetables.assignments.remove', id), {
        onSuccess: () => {
          toast.success('تم إزالة نفسك من الحصة بنجاح')
          router.reload()
        }
      })
    }
  })
}

// Group my assignments by day
const mySchedule = computed(() => {
  const schedule = {}
  weekDays.forEach(day => {
    schedule[day.value] = []
  })
  
  // (props.myAssignments || []).forEach(assignment => {
  //   const period = assignment.period
  //   if (period && schedule[period.day]) {
  //     schedule[period.day].push({
  //       period: period,
  //       assignment: assignment
  //     })
  //   }
  // })
  
  // Sort by period_number
  Object.keys(schedule).forEach(day => {
    schedule[day].sort((a, b) => a.period.period_number - b.period.period_number)
  })
  
  return schedule
})

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
        <h4>جدولي الدراسي</h4>
        <hr />

        <!-- My Schedule -->
        <div class="card mb-4">
          <div class="card-header">
            <h5>حصصي</h5>
          </div>
          <div class="card-body">
            <div v-for="day in weekDays" :key="day.value" class="mb-4">
              <h6 class="mb-2">{{ day.label }}</h6>
              <div class="table-responsive">
                <table class="table table-sm table-bordered">
                  <thead>
                    <tr>
                      <th>رقم الحصة</th>
                      <th>من</th>
                      <th>إلى</th>
                      <th>المرحلة الدراسية</th>
                      <th>المادة</th>
                      <th>الإجراءات</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="item in mySchedule[day.value] || []" :key="item.assignment.id">
                      <td>{{ item.period.period_number }}</td>
                      <td>{{ item.period.time_from }}</td>
                      <td>{{ item.period.time_to }}</td>
                      <td>{{ item.period.category?.name || '-' }}</td>
                      <td>{{ item.assignment.subject?.name || '-' }}</td>
                      <td>
                        <button
                          class="btn btn-sm btn-danger"
                          @click="removeSelfAssignment(item.assignment.id)"
                        >
                          <i class="bi bi-trash"></i> إزالة
                        </button>
                      </td>
                    </tr>
                    <tr v-if="!mySchedule[day.value] || mySchedule[day.value].length === 0">
                      <td colspan="6" class="text-center text-muted">لا توجد حصص</td>
                    </tr>
                  </tbody>
                </table>
              </div>
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
                    <th>الإجراءات</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="period in unassignedPeriods" :key="period.id">
                    <td>-</td>
                    <td>{{ weekDays.find(d => d.value === period.day)?.label || period.day }}</td>
                    <td>{{ period.period_number }}</td>
                    <td>{{ period.time_from }}</td>
                    <td>{{ period.time_to }}</td>
                    <td>{{ period.category?.name || '-' }}</td>
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
                    <td colspan="7" class="text-center text-muted">لا توجد حصص متاحة</td>
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
              <p><strong>اليوم:</strong> {{ weekDays.find(d => d.value === selectedPeriod.day)?.label }}</p>
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

