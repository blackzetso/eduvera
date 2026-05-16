<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import Swal from 'sweetalert2'
import { toast } from 'vue3-toastify'

const props = defineProps({
  students: Array,
  guardians: Array,
  categories: Array,
  tab: String
})

const activeTab = ref(props.tab || 'students')
const searchStudent = ref('')
const searchGuardian = ref('')
const selectedCategory = ref('')

const filteredStudents = computed(() => {
  let filtered = props.students || []

  if (searchStudent.value) {
    filtered = filtered.filter(s =>
      s.name.includes(searchStudent.value) ||
      s.email.includes(searchStudent.value)
    )
  }

  if (selectedCategory.value) {
    filtered = filtered.filter(s => s.category_id == selectedCategory.value)
  }

  return filtered
})

const filteredGuardians = computed(() => {
  let filtered = props.guardians || []

  if (searchGuardian.value) {
    filtered = filtered.filter(g =>
      g.name.includes(searchGuardian.value) ||
      g.email.includes(searchGuardian.value) ||
      g.phone.includes(searchGuardian.value)
    )
  }

  return filtered
})

function formatDate(dateString) {
  if (!dateString) return ''
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

function deleteGuardian(id, name) {
  Swal.fire({
    title: 'حذف ولي الأمر',
    text: `هل أنت متأكد من حذف ولي الأمر ${name}؟`,
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
          toast.success('تم حذف ولي الأمر بنجاح')
        }
      })
    }
  })
}
</script>

<template>
  <Head title="الطلاب وأولياء الأمور" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="card-body px-1 px-sm-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h4>الطلاب وأولياء الأمور</h4>
          <div class="d-flex align-items-center gap-2">
            <Link :href="route('admin.students.bulk-data')" class="btn btn-info">
              <i class="bi bi-upload"></i> Bulk Data
            </Link>
            <Link :href="route('admin.students.create')" class="btn btn-primary">
              <i class="bi bi-plus-circle"></i> إضافة جديد
            </Link>
          </div>
        </div>

        <!-- Tabs Navigation -->
        <ul class="nav nav-tabs mb-4" role="tablist">
          <li class="nav-item" role="presentation">
            <button
              class="nav-link"
              :class="{ active: activeTab === 'students' }"
              @click="activeTab = 'students'"
              type="button"
              role="tab"
            >
              <i class="bi bi-people"></i> الطلاب
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button
              class="nav-link"
              :class="{ active: activeTab === 'guardians' }"
              @click="activeTab = 'guardians'"
              type="button"
              role="tab"
            >
              <i class="bi bi-person-heart"></i> أولياء الأمور
            </button>
          </li>
        </ul>

        <!-- Students Tab -->
        <div v-if="activeTab === 'students'" class="tab-pane fade show active">
          <div class="card mb-4">
            <div class="card-body">
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <input
                    type="text"
                    class="form-control"
                    placeholder="البحث بالاسم أو البريد..."
                    v-model="searchStudent"
                  >
                </div>
                <div class="col-md-6">
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
              <div v-if="filteredStudents.length > 0" class="table-responsive">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th>الاسم</th>
                      <th>البريد الإلكتروني</th>
                      <th>رقم الهاتف</th>
                      <th>الصف/المرحلة</th>
                      <th>تاريخ التسجيل</th>
                      <th>الإجراءات</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="student in filteredStudents" :key="student.id">
                      <td>
                        <strong>{{ student.name }}</strong>
                      </td>
                      <td>{{ student.email }}</td>
                      <td>{{ student.phone || '-' }}</td>
                      <td>
                        <span v-if="student.category" class="badge bg-info">
                          {{ student.category.name }}
                        </span>
                      </td>
                      <td>{{ formatDate(student.created_at) }}</td>
                      <td>
                        <Link
                          :href="route('admin.students.edit', student.id)"
                          class="btn btn-sm btn-warning me-2"
                        >
                          <i class="bi bi-pencil"></i>
                        </Link>
                        <button
                          class="btn btn-sm btn-danger"
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

        <!-- Guardians Tab -->
        <div v-if="activeTab === 'guardians'" class="tab-pane fade show active">
          <div class="card mb-4">
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-12">
                  <input
                    type="text"
                    class="form-control"
                    placeholder="البحث بالاسم أو البريد أو الهاتف..."
                    v-model="searchGuardian"
                  >
                </div>
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card-header">
              <h6 class="mb-0">
                إجمالي أولياء الأمور: <span class="badge bg-primary">{{ filteredGuardians.length }}</span>
              </h6>
            </div>
            <div class="card-body">
              <div v-if="filteredGuardians.length > 0" class="table-responsive">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th>الاسم</th>
                      <th>البريد الإلكتروني</th>
                      <th>رقم الهاتف</th>
                      <th>تاريخ التسجيل</th>
                      <th>الإجراءات</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="guardian in filteredGuardians" :key="guardian.id">
                      <td>
                        <strong>{{ guardian.name }}</strong>
                      </td>
                      <td>{{ guardian.email }}</td>
                      <td>{{ guardian.phone || '-' }}</td>
                      <td>{{ formatDate(guardian.created_at) }}</td>
                      <td>
                        <Link
                          :href="route('admin.students.edit', guardian.id)"
                          class="btn btn-sm btn-warning me-2"
                        >
                          <i class="bi bi-pencil"></i>
                        </Link>
                        <button
                          class="btn btn-sm btn-danger"
                          @click="deleteGuardian(guardian.id, guardian.name)"
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
    </div>
  </AppLayout>
</template>

<style scoped>
.nav-tabs .nav-link {
  color: #495057;
  border: 1px solid #dee2e6;
}

.nav-tabs .nav-link:hover {
  border-color: #dee2e6;
  color: #495057;
}

.nav-tabs .nav-link.active {
  background-color: #0d6efd;
  color: white;
  border-color: #0d6efd;
}

.table-responsive {
  overflow-x: auto;
}

.badge {
  padding: 0.35em 0.65em;
}

.btn-group-sm {
  gap: 0.25rem;
}
</style>
