<script setup>
import { ref, computed, watch } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import Swal from 'sweetalert2'
import { toast } from 'vue3-toastify'

const props = defineProps({
  students: Array,
  categories: Array,
  filters: { type: Object, default: () => ({}) },
  filterOptions: { type: Object, default: () => ({}) },
})

const searchStudent = ref('')
const selectedCategory = ref('')
const selectedStatus = ref(props.filters.status || '')
const selectedAcademicYear = ref(props.filters.academic_year || '')
const selectedStageId = ref(props.filters.stage_id || '')

const statusBadgeClass = {
  active: 'bg-success',
  pending: 'bg-warning text-dark',
  suspended: 'bg-danger',
  withdrawn: 'bg-secondary',
  graduated: 'bg-info',
  transferred: 'bg-primary',
}

const filteredStudents = computed(() => {
  let filtered = props.students || []

  if (searchStudent.value) {
    const q = searchStudent.value.toLowerCase()
    filtered = filtered.filter(s =>
      s.name?.toLowerCase().includes(q) ||
      s.email?.toLowerCase().includes(q) ||
      s.student_code?.toLowerCase().includes(q) ||
      String(s.id).includes(q)
    )
  }

  if (selectedCategory.value) {
    filtered = filtered.filter(s => s.category_id == selectedCategory.value)
  }

  return filtered
})

function statusLabel(student) {
  return props.filterOptions.statuses?.find(s => s.value === student.student_status)?.label
    || student.student_status
    || '—'
}

function applyServerFilters() {
  router.get(route('admin.students.index'), {
    status: selectedStatus.value || undefined,
    academic_year: selectedAcademicYear.value || undefined,
    stage_id: selectedStageId.value || undefined,
  }, {
    preserveState: true,
    replace: true,
  })
}

watch([selectedStatus, selectedAcademicYear, selectedStageId], applyServerFilters)

function enrollmentPath(student) {
  const e = student.current_student_enrollment
  if (!e) return student.category?.name || null
  return [e.stage_name, e.grade_name, e.class_name].filter(Boolean).join(' / ') || student.category?.name
}

function formatDate(dateString) {
  if (!dateString) return '—'
  return new Date(dateString).toLocaleDateString('ar-EG')
}

function deleteStudent(id, name) {
  Swal.fire({
    title: 'حذف الطالب',
    text: `هل أنت متأكد من حذف الطالب ${name}؟`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'نعم، احذف',
    cancelButtonText: 'إلغاء'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('admin.students.destroy', id), {
        onSuccess: () => {
          toast.success('تم حذف الطالب بنجاح')
        }
      })
    }
  })
}
</script>

<template>
  <Head title="الطلاب" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="card-body px-1 px-sm-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
          <h4 class="mb-0">الطلاب</h4>
          <div class="d-flex flex-wrap align-items-center gap-2">
            <Link :href="route('admin.parents.index')" class="btn btn-outline-secondary">
              <i class="bi bi-person-heart"></i> أولياء الأمور
            </Link>
            <Link :href="route('admin.students.bulk-data')" class="btn btn-info">
              <i class="bi bi-upload"></i> Bulk Data
            </Link>
            <Link :href="route('admin.students.create')" class="btn btn-primary">
              <i class="bi bi-plus-circle"></i> إضافة طالب
            </Link>
          </div>
        </div>

        <div class="card mb-4">
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6 col-lg-3">
                <input
                  type="text"
                  class="form-control"
                  placeholder="البحث بالاسم أو البريد أو كود الطالب أو رقم النظام..."
                  v-model="searchStudent"
                >
              </div>
              <div class="col-md-6 col-lg-3">
                <select class="form-select" v-model="selectedStatus">
                  <option value="">— كل الحالات —</option>
                  <option
                    v-for="status in filterOptions.statuses || []"
                    :key="status.value"
                    :value="status.value"
                  >
                    {{ status.label }}
                  </option>
                </select>
              </div>
              <div class="col-md-6 col-lg-3">
                <select class="form-select" v-model="selectedAcademicYear">
                  <option value="">— كل الأعوام —</option>
                  <option
                    v-for="year in filterOptions.academic_years || []"
                    :key="year"
                    :value="year"
                  >
                    {{ year }}
                  </option>
                </select>
              </div>
              <div class="col-md-6 col-lg-3">
                <select class="form-select" v-model="selectedStageId">
                  <option value="">— كل المراحل —</option>
                  <option
                    v-for="stage in filterOptions.stages || []"
                    :key="stage.id"
                    :value="stage.id"
                  >
                    {{ stage.name }}
                  </option>
                </select>
              </div>
              <div class="col-md-6 col-lg-3">
                <select class="form-select" v-model="selectedCategory">
                  <option value="">-- كل الصفوف --</option>
                  <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                    {{ cat.name }}
                  </option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <h6 class="mb-0">
              إجمالي الطلاب: <span class="badge bg-primary">{{ filteredStudents.length }}</span>
            </h6>
          </div>
          <div class="card-body">
            <div v-if="filteredStudents.length > 0" class="eduvera-table-wrap">
              <table class="table table-striped">
                <thead>
                  <tr>
                    <th>الاسم</th>
                    <th title="يُستخدم في بحث الكافتيريا (POS)">رقم النظام</th>
                    <th>كود الطالب</th>
                    <th>الحالة</th>
                    <th>البريد الإلكتروني</th>
                    <th>الصف/المرحلة</th>
                    <th>العام الدراسي</th>
                    <th>تاريخ القيد</th>
                    <th>الإجراءات</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="student in filteredStudents" :key="student.id">
                    <td>
                      <Link
                        :href="route('admin.students.show', student.id)"
                        class="fw-semibold text-decoration-none"
                      >
                        {{ student.name }}
                      </Link>
                    </td>
                    <td><code class="text-primary">{{ student.id }}</code></td>
                    <td><code>{{ student.student_code || '—' }}</code></td>
                    <td>
                      <span
                        class="badge"
                        :class="statusBadgeClass[student.student_status] || 'bg-secondary'"
                      >
                        {{ statusLabel(student) }}
                      </span>
                    </td>
                    <td>{{ student.email }}</td>
                    <td>
                      <span v-if="enrollmentPath(student)" class="badge bg-info">
                        {{ enrollmentPath(student) }}
                      </span>
                      <span v-else>—</span>
                    </td>
                    <td>{{ student.current_student_enrollment?.academic_year || '—' }}</td>
                    <td>{{ formatDate(student.enrollment_date || student.created_at) }}</td>
                    <td class="text-nowrap">
                      <Link
                        :href="route('admin.students.show', student.id)"
                        class="btn btn-sm btn-primary me-1"
                        title="ملف الطالب"
                      >
                        <i class="bi bi-person-badge"></i>
                      </Link>
                      <Link
                        :href="route('admin.students.edit', student.id)"
                        class="btn btn-sm btn-warning me-1"
                        title="تعديل"
                      >
                        <i class="bi bi-pencil"></i>
                      </Link>
                      <button
                        class="btn btn-sm btn-danger"
                        title="حذف"
                        @click="deleteStudent(student.id, student.name)"
                      >
                        <i class="bi bi-trash"></i>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-else class="alert alert-info mb-0">
              لا توجد نتائج مطابقة
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
