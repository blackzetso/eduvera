<script setup>
import { ref } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import Swal from 'sweetalert2'
import CategoryOptions from '../Categories/CategoryOptions.vue'

const props = defineProps({
  categories: Array
})

const form = useForm({
  name: '',
  category_ids: []
})

function saveForm() {
  form.post(route('admin.subjects.store'), {
    onSuccess: () => {
      Swal.fire('تم الحفظ!', 'تم إنشاء المادة بنجاح.', 'success')
    },
    onError: () => {
      Swal.fire('خطأ!', 'حدثت مشكلة أثناء الحفظ.', 'error')
    }
  })
}
</script>

<template>
  <Head title="إضافة مادة جديدة" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="card-body px-1 px-sm-4">
        <h4>إضافة مادة جديدة</h4>
        <Link :href="route('admin.subjects.index')">
          <i class="fas fa-arrow-left"></i> رجوع
        </Link>
        <hr />

        <div class="row g-4">
          <!-- اسم المادة -->
          <div class="col-12">
            <label class="form-label">اسم المادة <span class="text-danger">*</span></label>
            <input
              class="form-control"
              v-model="form.name"
              type="text"
              placeholder="اكتب اسم المادة"
            />
            <div v-if="form.errors.name" class="text-danger">{{ form.errors.name }}</div>
          </div>

          <!-- اختيار الصفوف الدراسية -->
          <div class="col-12">
            <label class="form-label">الصفوف الدراسية (اختياري)</label>
            <div class="border p-3 rounded" style="max-height: 300px; overflow-y: auto;">
              <div v-for="category in categories" :key="category.id" class="mb-2">
                <div class="form-check">
                  <input
                    class="form-check-input"
                    type="checkbox"
                    :value="category.id"
                    v-model="form.category_ids"
                    :id="`category-${category.id}`"
                  />
                  <label class="form-check-label" :for="`category-${category.id}`">
                    {{ category.name }}
                  </label>
                </div>
                <!-- عرض الأقسام الفرعية -->
                <div v-if="category.children && category.children.length > 0" class="ms-4 mt-1">
                  <div
                    v-for="child in category.children"
                    :key="child.id"
                    class="form-check"
                  >
                    <input
                      class="form-check-input"
                      type="checkbox"
                      :value="child.id"
                      v-model="form.category_ids"
                      :id="`category-${child.id}`"
                    />
                    <label class="form-check-label" :for="`category-${child.id}`">
                      {{ child.name }}
                    </label>
                  </div>
                </div>
              </div>
            </div>
            <div v-if="form.errors.category_ids" class="text-danger">{{ form.errors.category_ids }}</div>
          </div>
          
          <!-- زر الحفظ -->
          <div class="d-flex justify-content-end mt-3">
            <button
              type="button"
              class="btn btn-primary mb-0"
              :disabled="form.processing"
              @click="saveForm"
            >
              حفظ المادة
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

