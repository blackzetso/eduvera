<script setup>
import { ref } from 'vue'
import CanteenLayout from '@/Pages/Canteen/Layout/CanteenLayout.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  products: Object,
  filters: Object,
})

const search = ref(props.filters?.search ?? '')
const showAdjust = ref(false)

const form = useForm({
  product_id: '',
  type: 'adjustment',
  quantity_delta: 1,
  unit_cost: '',
  notes: '',
})

function applyFilters() {
  router.get(route('canteen.inventory.index'), { search: search.value }, { preserveState: true })
}

function openAdjust(product) {
  form.product_id = product.id
  form.quantity_delta = 1
  form.notes = ''
  showAdjust.value = true
}

function submitAdjust() {
  form.post(route('canteen.inventory.adjust'), {
    onSuccess: () => { showAdjust.value = false },
  })
}
</script>

<template>
  <CanteenLayout>
    <Head title="Inventory" />

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="mb-0">المخزون</h4>
    </div>

    <div class="card border mb-4">
      <div class="card-body row g-2">
        <div class="col-md-4">
          <input v-model="search" class="form-control" placeholder="بحث..." @keyup.enter="applyFilters">
        </div>
        <div class="col-auto">
          <button class="btn btn-outline-primary" @click="applyFilters">بحث</button>
        </div>
      </div>
    </div>

    <div v-if="showAdjust" class="card border mb-4">
      <div class="card-body">
        <h5>تعديل المخزون</h5>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">النوع</label>
            <select v-model="form.type" class="form-select">
              <option value="adjustment">تعديل</option>
              <option value="receive">استلام</option>
              <option value="damage">تالف</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">الكمية (+/-)</label>
            <input v-model.number="form.quantity_delta" type="number" class="form-control" />
          </div>
          <div class="col-md-4">
            <label class="form-label">ملاحظات</label>
            <input v-model="form.notes" class="form-control" />
          </div>
        </div>
        <div class="mt-3">
          <button class="btn btn-primary me-2" :disabled="form.processing" @click="submitAdjust">حفظ</button>
          <button class="btn btn-light" @click="showAdjust = false">إلغاء</button>
        </div>
      </div>
    </div>

    <div class="card border">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>المنتج</th>
              <th>المتوفر</th>
              <th>الحالة</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in products?.data ?? []" :key="p.id">
              <td>{{ p.name_ar || p.name }}</td>
              <td>{{ p.on_hand ?? '0' }}</td>
              <td>
                <span v-if="p.is_out_of_stock" class="badge bg-danger">نفد</span>
                <span v-else-if="p.is_low_stock" class="badge bg-warning text-dark">منخفض</span>
                <span v-else class="badge bg-success">متوفر</span>
              </td>
              <td class="text-end">
                <Link :href="route('canteen.inventory.ledger', p.id)" class="btn btn-sm btn-outline-secondary me-1">السجل</Link>
                <button class="btn btn-sm btn-outline-primary" @click="openAdjust(p)">تعديل</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </CanteenLayout>
</template>
