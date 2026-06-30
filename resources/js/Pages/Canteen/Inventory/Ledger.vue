<script setup>
import CanteenLayout from '@/Pages/Canteen/Layout/CanteenLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

defineProps({
  product: Object,
  ledger: Object,
  on_hand: String,
})
</script>

<template>
  <CanteenLayout>
    <Head title="Inventory Ledger" />

    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="mb-0">{{ product?.data?.name_ar || product?.name_ar || product?.name }}</h4>
        <p class="text-muted mb-0">المتوفر: {{ on_hand }}</p>
      </div>
      <Link :href="route('canteen.inventory.index')" class="btn btn-light">رجوع</Link>
    </div>

    <div class="card border">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>النوع</th>
              <th>التغيير</th>
              <th>ملاحظات</th>
              <th>التاريخ</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in ledger?.data ?? []" :key="row.id">
              <td>{{ row.type }}</td>
              <td>{{ row.quantity_delta }}</td>
              <td>{{ row.notes }}</td>
              <td>{{ row.occurred_at }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </CanteenLayout>
</template>
