<script setup>
import { ref } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import Swal from 'sweetalert2'

const props = defineProps({
  subject: Object,
  categories: Array
})

const form = useForm({
  name: props.subject.name ?? '',
  category_ids: props.subject.categories?.map(c => c.id) || []
})

function submit() {
  form.put(route('admin.subjects.update', props.subject.id), {
    onSuccess: () => {
      Swal.fire({
        title: 'تم الحفظ!',
        text: 'تم حفظ المادة بنجاح',
        icon: 'success',
        confirmButtonText: 'تمام'
      })
    },
    onError: () => {
      Swal.fire({
        title: 'خطأ!',
        text: 'حدثت مشكلة أثناء الحفظ',
        icon: 'error',
        confirmButtonText: 'موافق'
      })
    }
  })
}
</script>

<template>
  <Head title="تعديل المادة" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="card-body px-1 px-sm-4">
        <form @submit.prevent="submit">
          <h4>تعديل المادة</h4>
          <Link :href="route('admin.subjects.index')">
            <i class="fas fa-arrow-left"></i> رجوع
          </Link>
          <hr />

          <div class="row g-4">
            <!-- اسم المادة -->
            <div class="col-12">
              <label class="form-label">اسم المادة</label>
              <input
                class="form-control"
                v-model="form.name"
                type="text"
                placeholder="اكتب اسم المادة"
              />
              <div v-if="form.errors.name" class="text-danger">
                {{ form.errors.name }}
              </div>
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
              <div v-if="form.errors.category_ids" class="text-danger">
                {{ form.errors.category_ids }}
              </div>
            </div>

            <!-- زر الحفظ -->
            <div class="d-flex justify-content-end mt-3">
              <button
                type="submit"
                class="btn btn-primary"
                :disabled="form.processing"
              >
                حفظ
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

