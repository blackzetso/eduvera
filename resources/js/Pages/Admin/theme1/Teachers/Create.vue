<script setup>
import { ref } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import Swal from 'sweetalert2'

const props = defineProps({
  subjects: Array
})

const form = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  phone: '',
  department: '',
  job_title: '',
  subjects: []
})

function saveForm() {
  form.post(route('admin.teachers.store'), {
    onSuccess: () => {
      Swal.fire('تم الحفظ!', 'تم إضافة المدرس بنجاح.', 'success')
      form.reset()
    },
    onError: () => {
      Swal.fire('خطأ!', 'يرجى التأكد من البيانات المدخلة.', 'error')
    }
  })
}
</script>

<template>
  <Head title="إضافة مدرس جديد" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="card-body px-1 px-sm-4">
        <h4>إضافة مدرس جديد</h4>
        <Link :href="route('admin.teachers.index')">
          <i class="fas fa-arrow-left"></i> رجوع
        </Link>
        <hr />

        <div class="row g-4">
          <!-- الاسم -->
          <div class="col-md-6">
            <label class="form-label">الاسم <span class="text-danger">*</span></label>
            <input class="form-control" v-model="form.name" type="text" placeholder="اسم المدرس" />
            <div v-if="form.errors.name" class="text-danger">{{ form.errors.name }}</div>
          </div>

          <!-- البريد الإلكتروني -->
          <div class="col-md-6">
            <label class="form-label">البريد الإلكتروني <span class="text-danger">*</span></label>
            <input class="form-control" v-model="form.email" type="email" placeholder="email@example.com" />
            <div v-if="form.errors.email" class="text-danger">{{ form.errors.email }}</div>
          </div>

          <!-- كلمة المرور -->
          <div class="col-md-6">
            <label class="form-label">كلمة المرور <span class="text-danger">*</span></label>
            <input class="form-control" v-model="form.password" type="password" />
            <div v-if="form.errors.password" class="text-danger">{{ form.errors.password }}</div>
          </div>

          <!-- تأكيد كلمة المرور -->
          <div class="col-md-6">
            <label class="form-label">تأكيد كلمة المرور <span class="text-danger">*</span></label>
            <input class="form-control" v-model="form.password_confirmation" type="password" />
          </div>

          <!-- الهاتف -->
          <div class="col-md-6">
            <label class="form-label">رقم الهاتف</label>
            <input class="form-control" v-model="form.phone" type="text" placeholder="01xxxxxxxxx" />
            <div v-if="form.errors.phone" class="text-danger">{{ form.errors.phone }}</div>
          </div>

          <div class="col-md-6">
            <label class="form-label">القسم</label>
            <input class="form-control" v-model="form.department" type="text" placeholder="مثال: العلوم" />
            <div v-if="form.errors.department" class="text-danger">{{ form.errors.department }}</div>
          </div>

          <div class="col-md-6">
            <label class="form-label">الوظيفة</label>
            <input class="form-control" v-model="form.job_title" type="text" placeholder="مثال: مدرس رياضيات" />
            <div v-if="form.errors.job_title" class="text-danger">{{ form.errors.job_title }}</div>
          </div>

          <!-- اختيار المواد -->
          <div class="col-12">
            <label class="form-label">المواد الدراسية <span class="text-danger">*</span></label>
            <div class="border p-3 rounded bg-light" style="max-height: 200px; overflow-y: auto;">
              <div v-if="subjects.length === 0" class="text-muted">لا توجد مواد مضافة بعد.</div>
              <div v-for="subject in subjects" :key="subject.id" class="form-check">
                <input
                  class="form-check-input"
                  type="checkbox"
                  :value="subject.id"
                  v-model="form.subjects"
                  :id="`subject-${subject.id}`"
                />
                <label class="form-check-label" :for="`subject-${subject.id}`">
                  {{ subject.name }}
                </label>
              </div>
            </div>
            <div v-if="form.errors.subjects" class="text-danger">{{ form.errors.subjects }}</div>
          </div>
          
          <!-- زر الحفظ -->
          <div class="d-flex justify-content-end mt-3">
            <button
              type="button"
              class="btn btn-primary mb-0"
              :disabled="form.processing"
              @click="saveForm"
            >
              حفظ المدرس
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
