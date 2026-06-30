<script setup>
import { onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'

defineProps({
  type: String,
  typeLabel: String,
  report: Object,
  filters: Object,
  generatedAt: String,
})

const labels = {
  sales: 'تقرير المبيعات',
  products: 'مبيعات المنتجات',
  inventory: 'تقرير المخزون',
  students: 'مصروفات الطلاب',
  categories: 'مبيعات التصنيفات',
}

const statusLabels = {
  pending_payment: 'قيد التسوية',
  completed: 'مكتمل',
  voided: 'ملغي',
  failed: 'فاشل',
}

onMounted(() => {
  setTimeout(() => window.print(), 400)
})
</script>

<template>
  <Head :title="`Print: ${typeLabel}`" />

  <div class="print-report p-4">
    <div class="text-center mb-4">
      <h2 class="mb-1">{{ labels[type] ?? typeLabel }}</h2>
      <p v-if="report?.from && report?.to" class="text-muted mb-0">{{ report.from }} — {{ report.to }}</p>
      <p class="text-muted small">تم الإنشاء: {{ generatedAt }}</p>
    </div>

    <div v-if="type === 'sales' && report?.summary" class="row g-2 mb-3 summary-row">
      <div class="col-4"><strong>المبيعات:</strong> {{ report.summary.total_sales }}</div>
      <div class="col-4"><strong>الإيرادات:</strong> {{ report.summary.total_revenue }} EGP</div>
      <div class="col-4"><strong>المتوسط:</strong> {{ report.summary.average_sale_value }} EGP</div>
    </div>

    <table v-if="type === 'sales'" class="table table-bordered table-sm w-100">
      <thead>
        <tr>
          <th>التاريخ</th><th>رقم العملية</th><th>الطالب</th><th>الكاشير</th>
          <th>الدفع</th><th>الإجمالي</th><th>الحالة</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(row, i) in report?.rows ?? []" :key="i">
          <td>{{ row.date }}</td><td>{{ row.sale_number }}</td><td>{{ row.student }}</td>
          <td>{{ row.cashier }}</td><td>{{ row.payment_method }}</td>
          <td>{{ row.total }}</td><td>{{ statusLabels[row.status] ?? row.status }}</td>
        </tr>
      </tbody>
    </table>

    <table v-else-if="type === 'products'" class="table table-bordered table-sm w-100">
      <thead><tr><th>المنتج</th><th>الكمية</th><th>الإيراد</th><th>التكلفة</th><th>الربح</th></tr></thead>
      <tbody>
        <tr v-for="(row, i) in report?.rows ?? []" :key="i">
          <td>{{ row.product }}</td><td>{{ row.quantity_sold }}</td>
          <td>{{ row.revenue }}</td><td>{{ row.cost }}</td><td>{{ row.profit }}</td>
        </tr>
      </tbody>
    </table>

    <table v-else-if="type === 'inventory'" class="table table-bordered table-sm w-100">
      <thead><tr><th>المنتج</th><th>المخزون</th><th>الحد الأدنى</th><th>منخفض</th><th>نفد</th></tr></thead>
      <tbody>
        <tr v-for="(row, i) in report?.rows ?? []" :key="i">
          <td>{{ row.product }}</td><td>{{ row.current_stock }}</td><td>{{ row.minimum_stock }}</td>
          <td>{{ row.is_low_stock ? 'نعم' : 'لا' }}</td><td>{{ row.is_out_of_stock ? 'نعم' : 'لا' }}</td>
        </tr>
      </tbody>
    </table>

    <table v-else-if="type === 'students'" class="table table-bordered table-sm w-100">
      <thead><tr><th>الطالب</th><th>الصف</th><th>المشتريات</th><th>المصروف</th><th>المتوسط</th></tr></thead>
      <tbody>
        <tr v-for="(row, i) in report?.rows ?? []" :key="i">
          <td>{{ row.student }}</td><td>{{ row.grade }}</td>
          <td>{{ row.total_purchases }}</td><td>{{ row.total_spent }}</td><td>{{ row.average_spend }}</td>
        </tr>
      </tbody>
    </table>

    <table v-else-if="type === 'categories'" class="table table-bordered table-sm w-100">
      <thead><tr><th>التصنيف</th><th>الكمية</th><th>الإيراد</th><th>النسبة %</th></tr></thead>
      <tbody>
        <tr v-for="(row, i) in report?.rows ?? []" :key="i">
          <td>{{ row.category }}</td><td>{{ row.quantity_sold }}</td>
          <td>{{ row.revenue }}</td><td>{{ row.percentage_of_total }}%</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
@media print {
  .print-report { font-size: 12px; }
  table { page-break-inside: auto; }
  tr { page-break-inside: avoid; }
}
</style>
