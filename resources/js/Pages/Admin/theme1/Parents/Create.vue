<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  students: { type: Array, default: () => [] },
})

const studentSearch = ref('')

const filteredStudents = computed(() => {
  if (!studentSearch.value) return props.students
  const q = studentSearch.value.toLowerCase()
  return props.students.filter(s =>
    s.name?.toLowerCase().includes(q) ||
    s.email?.toLowerCase().includes(q) ||
    s.national_id?.toLowerCase().includes(q)
  )
})

const form = useForm({
  name: '',
  email: '',
  phone: '',
  job_title: '',
  national_id: '',
  password: '',
  password_confirmation: '',
  student_ids: [],
})

function toggleStudent(id) {
  const idx = form.student_ids.indexOf(id)
  if (idx === -1) {
    form.student_ids.push(id)
  } else {
    form.student_ids.splice(idx, 1)
  }
}

function isSelected(id) {
  return form.student_ids.includes(id)
}

function submit() {
  form.post(route('admin.parents.store'))
}
</script>

<template>
  <Head title="إضافة ولي أمر جديد" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="card-body px-1 px-sm-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h4>إضافة ولي أمر جديد</h4>
          <Link :href="route('admin.parents.index')" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> العودة
          </Link>
        </div>

        <div class="card">
          <div class="card-body">
            <form @submit.prevent="submit">
              <div class="row g-3">

                <!-- Name -->
                <div class="col-md-6">
                  <label class="form-label">الاسم <span class="text-danger">*</span></label>
                  <input
                    type="text"
                    class="form-control"
                    :class="{ 'is-invalid': form.errors.name }"
                    v-model="form.name"
                    required
                  >
                  <div v-if="form.errors.name" class="invalid-feedback">{{ form.errors.name }}</div>
                </div>

                <!-- Email -->
                <div class="col-md-6">
                  <label class="form-label">البريد الإلكتروني <span class="text-danger">*</span></label>
                  <input
                    type="email"
                    class="form-control"
                    :class="{ 'is-invalid': form.errors.email }"
                    v-model="form.email"
                    required
                  >
                  <div v-if="form.errors.email" class="invalid-feedback">{{ form.errors.email }}</div>
                </div>

                <!-- Phone -->
                <div class="col-md-6">
                  <label class="form-label">رقم الهاتف</label>
                  <input
                    type="tel"
                    class="form-control"
                    :class="{ 'is-invalid': form.errors.phone }"
                    v-model="form.phone"
                  >
                  <div v-if="form.errors.phone" class="invalid-feedback">{{ form.errors.phone }}</div>
                </div>

                <!-- Job Title -->
                <div class="col-md-6">
                  <label class="form-label">الوظيفة</label>
                  <input
                    type="text"
                    class="form-control"
                    :class="{ 'is-invalid': form.errors.job_title }"
                    v-model="form.job_title"
                    placeholder="مثال: مهندس، طبيب، معلم..."
                  >
                  <div v-if="form.errors.job_title" class="invalid-feedback">{{ form.errors.job_title }}</div>
                </div>

                <!-- National ID -->
                <div class="col-md-6">
                  <label class="form-label">الرقم القومي <span class="text-danger">*</span></label>
                  <input
                    type="text"
                    class="form-control"
                    :class="{ 'is-invalid': form.errors.national_id }"
                    v-model="form.national_id"
                    placeholder="أدخل الرقم القومي"
                    required
                  >
                  <div v-if="form.errors.national_id" class="invalid-feedback">{{ form.errors.national_id }}</div>
                </div>

                <!-- Password -->
                <div class="col-md-6">
                  <label class="form-label">كلمة المرور <span class="text-danger">*</span></label>
                  <input
                    type="password"
                    class="form-control"
                    :class="{ 'is-invalid': form.errors.password }"
                    v-model="form.password"
                    required
                  >
                  <div v-if="form.errors.password" class="invalid-feedback">{{ form.errors.password }}</div>
                </div>

                <!-- Password Confirmation -->
                <div class="col-md-6">
                  <label class="form-label">تأكيد كلمة المرور <span class="text-danger">*</span></label>
                  <input
                    type="password"
                    class="form-control"
                    :class="{ 'is-invalid': form.errors.password_confirmation }"
                    v-model="form.password_confirmation"
                    required
                  >
                  <div v-if="form.errors.password_confirmation" class="invalid-feedback">{{ form.errors.password_confirmation }}</div>
                </div>

                <!-- Linked Students -->
                <div class="col-12">
                  <div class="card border-0 bg-light rounded-3">
                    <div class="card-header bg-white border-bottom d-flex align-items-center gap-2 rounded-top-3">
                      <i class="bi bi-person-lines-fill text-primary fs-5"></i>
                      <span class="fw-semibold">ربط بالطلاب</span>
                      <span v-if="form.student_ids.length > 0" class="badge bg-primary ms-auto">
                        {{ form.student_ids.length }} طالب مختار
                      </span>
                    </div>
                    <div class="card-body">
                      <p class="text-muted small mb-3">اختر الطلاب المرتبطين بهذا ولي الأمر. يمكن ربط أكثر من طالب.</p>

                      <div class="input-group mb-3">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input
                          type="text"
                          class="form-control border-start-0"
                          placeholder="ابحث بالاسم أو البريد أو الرقم القومي..."
                          v-model="studentSearch"
                        >
                      </div>

                      <div class="students-list" style="max-height: 260px; overflow-y: auto;">
                        <div v-if="filteredStudents.length === 0" class="text-center text-muted py-4">
                          <i class="bi bi-inbox fs-3 d-block mb-1"></i>
                          لا توجد طلاب مطابقة
                        </div>
                        <div
                          v-for="student in filteredStudents"
                          :key="student.id"
                          class="student-item d-flex align-items-center gap-3 p-2 rounded-2 mb-1"
                          :class="isSelected(student.id) ? 'selected' : ''"
                          @click="toggleStudent(student.id)"
                        >
                          <div
                            class="student-check flex-shrink-0 d-flex align-items-center justify-content-center rounded-circle"
                            :class="isSelected(student.id) ? 'bg-primary text-white' : 'bg-white border'"
                            style="width:28px; height:28px;"
                          >
                            <i v-if="isSelected(student.id)" class="bi bi-check2 fw-bold" style="font-size:14px;"></i>
                          </div>
                          <div class="flex-grow-1 min-width-0">
                            <div class="fw-semibold text-dark lh-sm">{{ student.name }}</div>
                            <div class="text-muted small">{{ student.email }}</div>
                          </div>
                          <span v-if="student.national_id" class="badge bg-secondary bg-opacity-75 flex-shrink-0">{{ student.national_id }}</span>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div v-if="form.errors.student_ids" class="text-danger small mt-1">{{ form.errors.student_ids }}</div>
                </div>

              </div>

              <div class="d-flex justify-content-end gap-2 mt-4">
                <Link :href="route('admin.parents.index')" class="btn btn-secondary">إلغاء</Link>
                <button type="submit" class="btn btn-primary" :disabled="form.processing">
                  <span v-if="form.processing" class="spinner-border spinner-border-sm me-2"></span>
                  حفظ
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
.student-item {
  cursor: pointer;
  transition: background 0.15s;
}
.student-item:hover {
  background: #f0f4ff;
}
.student-item.selected {
  background: #e8f0fe;
}
.students-list {
  scrollbar-width: thin;
  scrollbar-color: #c1c9d6 transparent;
}
.students-list::-webkit-scrollbar {
  width: 5px;
}
.students-list::-webkit-scrollbar-thumb {
  background: #c1c9d6;
  border-radius: 4px;
}
</style>
