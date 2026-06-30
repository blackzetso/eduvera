<script setup>
import { ref } from 'vue'
import CanteenLayout from '@/Pages/Canteen/Layout/CanteenLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  categories: Object,
  filters: Object,
})

const search = ref(props.filters?.search ?? '')
const showForm = ref(false)
const editing = ref(null)

const form = useForm({
  name: '',
  name_ar: '',
  slug: '',
  description: '',
  sort_order: 0,
  is_active: true,
})

function applyFilters() {
  router.get(route('canteen.categories.index'), { search: search.value }, { preserveState: true })
}

function openCreate() {
  editing.value = null
  form.reset()
  form.is_active = true
  showForm.value = true
}

function openEdit(category) {
  editing.value = category
  form.name = category.name
  form.name_ar = category.name_ar ?? ''
  form.slug = category.slug ?? ''
  form.description = category.description ?? ''
  form.sort_order = category.sort_order ?? 0
  form.is_active = category.is_active
  showForm.value = true
}

function submit() {
  if (editing.value) {
    form.put(route('canteen.categories.update', editing.value.id), {
      onSuccess: () => { showForm.value = false },
    })
  } else {
    form.post(route('canteen.categories.store'), {
      onSuccess: () => { showForm.value = false; form.reset() },
    })
  }
}

function destroy(id) {
  if (!confirm('حذف التصنيف؟')) return
  router.delete(route('canteen.categories.destroy', id))
}
</script>

<template>
  <CanteenLayout>
    <Head title="Categories" />

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="mb-0">التصنيفات</h4>
      <button class="btn btn-primary" @click="openCreate">إضافة تصنيف</button>
    </div>

    <div class="card border mb-4">
      <div class="card-body">
        <div class="row g-2">
          <div class="col-md-4">
            <input v-model="search" class="form-control" placeholder="بحث..." @keyup.enter="applyFilters">
          </div>
          <div class="col-auto">
            <button class="btn btn-outline-primary" @click="applyFilters">بحث</button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="showForm" class="card border mb-4">
      <div class="card-body">
        <h5>{{ editing ? 'تعديل' : 'إضافة' }} تصنيف</h5>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">الاسم</label>
            <input v-model="form.name" class="form-control" />
          </div>
          <div class="col-md-6">
            <label class="form-label">الاسم (عربي)</label>
            <input v-model="form.name_ar" class="form-control" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Slug</label>
            <input v-model="form.slug" class="form-control" />
          </div>
          <div class="col-md-6">
            <label class="form-label">الترتيب</label>
            <input v-model.number="form.sort_order" type="number" class="form-control" />
          </div>
          <div class="col-12">
            <label class="form-label">الوصف</label>
            <textarea v-model="form.description" class="form-control" rows="2"></textarea>
          </div>
          <div class="col-12">
            <div class="form-check">
              <input v-model="form.is_active" class="form-check-input" type="checkbox" id="catActive">
              <label class="form-check-label" for="catActive">نشط</label>
            </div>
          </div>
        </div>
        <div class="mt-3">
          <button class="btn btn-primary me-2" :disabled="form.processing" @click="submit">حفظ</button>
          <button class="btn btn-light" @click="showForm = false">إلغاء</button>
        </div>
      </div>
    </div>

    <div class="card border">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>الاسم</th>
              <th>المنتجات</th>
              <th>الحالة</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="cat in categories?.data ?? []" :key="cat.id">
              <td>{{ cat.name_ar || cat.name }}</td>
              <td>{{ cat.products_count ?? 0 }}</td>
              <td>
                <span :class="['badge', cat.is_active ? 'bg-success' : 'bg-secondary']">
                  {{ cat.is_active ? 'نشط' : 'معطل' }}
                </span>
              </td>
              <td class="text-end">
                <button class="btn btn-sm btn-outline-primary me-1" @click="openEdit(cat)">تعديل</button>
                <button class="btn btn-sm btn-outline-danger" @click="destroy(cat.id)">حذف</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </CanteenLayout>
</template>
