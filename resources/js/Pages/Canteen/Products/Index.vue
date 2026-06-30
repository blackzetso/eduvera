<script setup>
import { ref } from 'vue'
import CanteenLayout from '@/Pages/Canteen/Layout/CanteenLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  products: Object,
  categories: Object,
  filters: Object,
})

const search = ref(props.filters?.search ?? '')
const categoryId = ref(props.filters?.category_id ?? '')

function applyFilters() {
  router.get(route('canteen.products.index'), {
    search: search.value,
    category_id: categoryId.value,
  }, { preserveState: true })
}

function destroy(id) {
  if (!confirm('حذف المنتج؟')) return
  router.delete(route('canteen.products.destroy', id))
}
</script>

<template>
  <CanteenLayout>
    <Head title="Products" />

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="mb-0">المنتجات</h4>
      <Link :href="route('canteen.products.create')" class="btn btn-primary">إضافة منتج</Link>
    </div>

    <div class="card border mb-4">
      <div class="card-body row g-2">
        <div class="col-md-4">
          <input v-model="search" class="form-control" placeholder="بحث..." @keyup.enter="applyFilters">
        </div>
        <div class="col-md-4">
          <select v-model="categoryId" class="form-select">
            <option value="">كل التصنيفات</option>
            <option v-for="c in categories?.data ?? categories ?? []" :key="c.id" :value="c.id">
              {{ c.name_ar || c.name }}
            </option>
          </select>
        </div>
        <div class="col-auto">
          <button class="btn btn-outline-primary" @click="applyFilters">بحث</button>
        </div>
      </div>
    </div>

    <div class="card border">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>المنتج</th>
              <th>SKU</th>
              <th>السعر</th>
              <th>المخزون</th>
              <th>القيود</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in products?.data ?? []" :key="p.id">
              <td>{{ p.name_ar || p.name }}</td>
              <td>{{ p.sku }}</td>
              <td>{{ p.selling_price }}</td>
              <td>{{ p.on_hand ?? '0' }}</td>
              <td>
                <span v-if="p.is_restricted_default" class="badge bg-danger me-1">مقيد</span>
                <span v-for="tag in p.restriction_tags ?? []" :key="tag" class="badge bg-warning text-dark me-1">{{ tag }}</span>
                <span v-if="!p.is_restricted_default && !(p.restriction_tags?.length)" class="text-muted small">—</span>
              </td>
              <td class="text-end">
                <Link :href="route('canteen.products.edit', p.id)" class="btn btn-sm btn-outline-primary me-1">تعديل</Link>
                <button class="btn btn-sm btn-outline-danger" @click="destroy(p.id)">حذف</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </CanteenLayout>
</template>
