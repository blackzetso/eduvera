<script setup>
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  day: Object,
  periods: Array,
  time: String,
  teachers: Array,
  subjects: Array,
  categories: Array,
  showAssignments: {
    type: Boolean,
    default: true
  }
})

const selectedPeriod = ref(null)
const showAssignModal = ref(false)

const assignForm = useForm({
  timetable_period_id: null,
  teacher_id: null,
  subject_id: null,
  type: 'main'
})

function formatTime(time) {
  return time ? time.substring(0, 5) : ''
}

// Build a flat map of categoryId → parent node by walking the full category tree
const parentMap = computed(() => {
  const map = {}
  function walk(node, parent) {
    if (parent) {
      map[node.id] = parent
    }
    if (node.children && node.children.length > 0) {
      node.children.forEach(child => walk(child, node))
    }
  }
  ;(props.categories ?? []).forEach(root => walk(root, null))
  return map
})

// Group periods by their parent category name (e.g. "أولى إعدادي عربي")
const groupedPeriods = computed(() => {
  const groups = {}
  const order = []

  ;(props.periods ?? []).forEach(period => {
    const cat = period.category
    let label = null

    // Try direct parent from eager-loaded relation
    if (cat?.parent?.name) {
      label = cat.parent.name
    } else if (cat?.id && parentMap.value[cat.id]) {
      // Fallback: use parentMap built from categories tree
      label = parentMap.value[cat.id].name
    } else {
      // Last resort: use the category itself
      label = cat?.name ?? 'غير محدد'
    }

    if (!groups[label]) {
      groups[label] = []
      order.push(label)
    }
    groups[label].push(period)
  })

  return order.map(label => ({ label, periods: groups[label] }))
})

function openAssignModal(period) {
  selectedPeriod.value = period
  assignForm.timetable_period_id = period.id
  assignForm.teacher_id = null
  assignForm.subject_id = null
  assignForm.type = 'main'
  showAssignModal.value = true
}

function closeModal() {
  showAssignModal.value = false
  assignForm.reset()
  selectedPeriod.value = null
}

function submitAssignment() {
  assignForm.post(route('admin.timetable.assign-teacher'), {
    preserveScroll: true,
    onSuccess: () => {
      showAssignModal.value = false
      assignForm.reset()
      selectedPeriod.value = null
    }
  })
}

function deleteAssignment(assignmentId) {
  if (confirm('هل أنت متأكد من حذف هذا التعيين؟')) {
    router.delete(route('admin.timetable.assignments.remove', assignmentId), {
      preserveScroll: true
    })
  }
}
</script>

<template>
  <Head title="جميع الحصص" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="card-body px-1 px-sm-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h4>جميع الحصص</h4>
          <Link :href="route('admin.timetable.show')" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> العودة للجدول
          </Link>
        </div>

        <div class="card">
          <div class="card-header">
            <div v-if="day" class="mb-1">
              <p class="mb-1"><strong>اليوم:</strong> {{ day.day_name }}</p>
              <p v-if="time" class="mb-0"><strong>الوقت:</strong> {{ formatTime(time) }}</p>
            </div>
          </div>
          <div class="card-body">
            <div v-if="periods && periods.length > 0">
              <!-- One row per parent-category group -->
              <div
                v-for="group in groupedPeriods"
                :key="group.label"
                class="group-block mb-4"
              >
                <div class="group-header mb-2">{{ group.label }}</div>
                <div class="periods-row">
                  <div
                    v-for="period in group.periods"
                    :key="period.id"
                    class="period-card"
                  >
                    <div class="period-card-header">
                      <span class="period-cat-name">{{ period.category?.name }}</span>
                    </div>
                    <div class="period-time-range">
                      {{ formatTime(period.time_from) }} - {{ formatTime(period.time_to) }}
                    </div>

                    <div v-if="showAssignments">
                      <div
                        v-if="period.assignments && period.assignments.length > 0"
                        class="assignments-list"
                      >
                        <div
                          v-for="assignment in period.assignments"
                          :key="assignment.id"
                          class="assignment-item"
                        >
                          <div class="teacher-name">{{ assignment.teacher?.name }}</div>
                          <div class="subject-name">{{ assignment.subject?.name }}</div>
                          <span
                            class="badge"
                            :class="assignment.type === 'main' ? 'bg-success' : 'bg-warning'"
                          >
                            {{ assignment.type === 'main' ? 'أساسي' : 'احتياطي' }}
                          </span>
                          <button
                            class="btn btn-sm btn-outline-danger mt-1 d-block w-100"
                            @click="deleteAssignment(assignment.id)"
                          >
                            <i class="bi bi-trash"></i>
                          </button>
                        </div>
                      </div>
                      <div v-else class="empty-assignment">غير معين</div>
                    </div>

                    <button
                      class="btn btn-sm btn-primary mt-2 w-100"
                      @click="openAssignModal(period)"
                    >
                      <i class="bi bi-person-plus"></i> تعيين
                    </button>
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="alert alert-info">
              لا توجد حصص متاحة
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Assignment Modal -->
    <div
      v-if="showAssignModal"
      class="modal fade show d-block"
      tabindex="-1"
      style="background-color: rgba(0,0,0,0.5);"
    >
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">تعيين مدرس للحصة</h5>
            <button type="button" class="btn-close" @click="closeModal"></button>
          </div>
          <div class="modal-body">
            <div v-if="selectedPeriod" class="mb-3">
              <p><strong>الوقت:</strong> {{ formatTime(selectedPeriod.time_from) }} - {{ formatTime(selectedPeriod.time_to) }}</p>
              <p v-if="selectedPeriod.category"><strong>الفئة:</strong> {{ selectedPeriod.category.name }}</p>
            </div>

            <form @submit.prevent="submitAssignment">
              <div class="mb-3">
                <label class="form-label">المدرس <span class="text-danger">*</span></label>
                <select
                  v-model="assignForm.teacher_id"
                  class="form-select"
                  :class="{ 'is-invalid': assignForm.errors.teacher_id }"
                  required
                >
                  <option value="">اختر المدرس</option>
                  <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id">
                    {{ teacher.name }}
                  </option>
                </select>
                <div v-if="assignForm.errors.teacher_id" class="invalid-feedback">
                  {{ assignForm.errors.teacher_id }}
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">المادة <span class="text-danger">*</span></label>
                <select
                  v-model="assignForm.subject_id"
                  class="form-select"
                  :class="{ 'is-invalid': assignForm.errors.subject_id }"
                  required
                >
                  <option value="">اختر المادة</option>
                  <option v-for="subject in subjects" :key="subject.id" :value="subject.id">
                    {{ subject.name }}
                  </option>
                </select>
                <div v-if="assignForm.errors.subject_id" class="invalid-feedback">
                  {{ assignForm.errors.subject_id }}
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">نوع الحصة <span class="text-danger">*</span></label>
                <select
                  v-model="assignForm.type"
                  class="form-select"
                  :class="{ 'is-invalid': assignForm.errors.type }"
                  required
                >
                  <option value="main">أساسي</option>
                  <option value="backup">احتياطي</option>
                </select>
                <div v-if="assignForm.errors.type" class="invalid-feedback">
                  {{ assignForm.errors.type }}
                </div>
              </div>

              <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-secondary" @click="closeModal">إلغاء</button>
                <button type="submit" class="btn btn-primary" :disabled="assignForm.processing">
                  <span v-if="assignForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                  تعيين
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.group-header {
  font-weight: 700;
  font-size: 1rem;
  color: #343a40;
  border-bottom: 2px solid #dee2e6;
  padding-bottom: 0.25rem;
}

.periods-row {
  display: flex;
  flex-wrap: nowrap;
  gap: 0.5rem;
  overflow-x: auto;
}

.period-card {
  flex: 1 1 0;
  min-width: 120px;
  border: 1px solid #dee2e6;
  border-radius: 6px;
  padding: 0.6rem;
  background: #fff;
  display: flex;
  flex-direction: column;
  font-size: 0.85rem;
}

.period-card-header {
  font-weight: 600;
  color: #495057;
  margin-bottom: 0.25rem;
}

.period-cat-name {
  font-size: 0.9rem;
}

.period-time-range {
  color: #6c757d;
  font-size: 0.8rem;
  margin-bottom: 0.5rem;
}

.assignments-list {
  flex-grow: 1;
}

.assignment-item {
  background: #d4edda;
  border: 1px solid #c3e6cb;
  border-radius: 4px;
  padding: 0.3rem 0.4rem;
  margin-bottom: 0.25rem;
}

.teacher-name {
  font-weight: 600;
  color: #155724;
  font-size: 0.85rem;
}

.subject-name {
  color: #6c757d;
  font-size: 0.8rem;
}

.empty-assignment {
  color: #6c757d;
  font-style: italic;
  font-size: 0.8rem;
  padding: 0.25rem 0;
  flex-grow: 1;
}
</style>
