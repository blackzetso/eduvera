<script setup>
import CanteenLayout from '@/Pages/Canteen/Layout/CanteenLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

defineProps({
  restrictionsSummary: Object,
})

const reports = [
  { type: 'sales', label: 'تقرير المبيعات' },
  { type: 'products', label: 'مبيعات المنتجات' },
  { type: 'inventory', label: 'تقرير المخزون' },
  { type: 'students', label: 'مصروفات الطلاب' },
  { type: 'categories', label: 'مبيعات التصنيفات' },
]
</script>

<template>
  <CanteenLayout>
    <Head title="Reports" />

    <h4 class="mb-4">التقارير</h4>

    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="card border h-100">
          <div class="card-body">
            <h6 class="text-muted">قواعد القيود النشطة</h6>
            <h3 class="mb-0">{{ restrictionsSummary?.active_rules ?? 0 }}</h3>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card border h-100">
          <div class="card-body">
            <h6 class="text-muted">طلاب لديهم قيود</h6>
            <h3 class="mb-0">{{ restrictionsSummary?.students_with_restrictions ?? 0 }}</h3>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card border h-100">
          <div class="card-body">
            <h6 class="text-muted">مخالفات القيود</h6>
            <h3 class="mb-0">{{ restrictionsSummary?.restriction_violations ?? 0 }}</h3>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3">
      <div v-for="r in reports" :key="r.type" class="col-md-4">
        <Link :href="route('canteen.reports.show', r.type)" class="card border text-decoration-none h-100">
          <div class="card-body">
            <h5 class="card-title text-dark mb-0">{{ r.label }}</h5>
          </div>
        </Link>
      </div>
    </div>
  </CanteenLayout>
</template>
