<script setup>
import CanteenLayout from '@/Pages/Canteen/Layout/CanteenLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  sale: Object,
})

const form = useForm({ void_reason: '' })

function voidSale() {
  if (!confirm('إلغاء هذه العملية؟')) return
  form.post(route('canteen.transactions.void', props.sale.data?.id ?? props.sale.id))
}
</script>

<template>
  <CanteenLayout>
    <Head title="Transaction" />

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="mb-0">عملية #{{ sale?.data?.sale_number ?? sale?.sale_number }}</h4>
      <Link :href="route('canteen.transactions.index')" class="btn btn-light">رجوع</Link>
    </div>

    <div class="card border mb-4">
      <div class="card-body">
        <p><strong>الطالب:</strong> {{ sale?.data?.student_name ?? sale?.student_name }}</p>
        <p><strong>المبلغ:</strong> {{ sale?.data?.total ?? sale?.total }}</p>
        <p><strong>الحالة:</strong> {{ sale?.data?.status ?? sale?.status }}</p>
      </div>
    </div>

    <div class="card border mb-4">
      <div class="card-header bg-transparent"><strong>البنود</strong></div>
      <div class="table-responsive">
        <table class="table mb-0">
          <thead>
            <tr>
              <th>المنتج</th>
              <th>الكمية</th>
              <th>الإجمالي</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in (sale?.data?.items ?? sale?.items ?? [])" :key="item.id">
              <td>{{ item.product_name }}</td>
              <td>{{ item.quantity }}</td>
              <td>{{ item.line_total }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="(sale?.data?.status ?? sale?.status) === 'completed'" class="card border">
      <div class="card-body">
        <label class="form-label">سبب الإلغاء</label>
        <input v-model="form.void_reason" class="form-control mb-2">
        <button class="btn btn-danger" :disabled="form.processing" @click="voidSale">إلغاء العملية</button>
      </div>
    </div>
  </CanteenLayout>
</template>
