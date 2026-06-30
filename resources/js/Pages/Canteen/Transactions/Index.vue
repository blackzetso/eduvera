<script setup>
import { ref } from 'vue'
import CanteenLayout from '@/Pages/Canteen/Layout/CanteenLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  sales: Object,
  filters: Object,
})

const date = ref(props.filters?.date ?? '')
const studentRef = ref(props.filters?.student_id_ref ?? '')

function applyFilters() {
  router.get(route('canteen.transactions.index'), {
    date: date.value,
    student_id_ref: studentRef.value,
  }, { preserveState: true })
}
</script>

<template>
  <CanteenLayout>
    <Head title="Transactions" />

    <h4 class="mb-4">المعاملات</h4>

    <div class="card border mb-4">
      <div class="card-body row g-2">
        <div class="col-md-3">
          <input v-model="date" type="date" class="form-control">
        </div>
        <div class="col-md-4">
          <input v-model="studentRef" class="form-control" placeholder="رقم الطالب">
        </div>
        <div class="col-auto">
          <button class="btn btn-outline-primary" @click="applyFilters">تصفية</button>
        </div>
      </div>
    </div>

    <div class="card border">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>رقم العملية</th>
              <th>الطالب</th>
              <th>المبلغ</th>
              <th>الحالة</th>
              <th>التاريخ</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="sale in sales?.data ?? []" :key="sale.id">
              <td>{{ sale.sale_number }}</td>
              <td>{{ sale.student_name }}</td>
              <td>{{ sale.total }}</td>
              <td>{{ sale.status }}</td>
              <td>{{ sale.sold_at }}</td>
              <td class="text-end">
                <Link :href="route('canteen.transactions.show', sale.id)" class="btn btn-sm btn-outline-primary">عرض</Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </CanteenLayout>
</template>
