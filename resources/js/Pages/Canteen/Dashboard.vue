<script setup>
import CanteenLayout from '@/Pages/Canteen/Layout/CanteenLayout.vue'
import { Head } from '@inertiajs/vue3'

defineProps({
  kpis: Object,
})
</script>

<template>
  <CanteenLayout>
    <Head title="Canteen Dashboard" />

    <div class="row mb-4">
      <div class="col-12">
        <h4 class="mb-0">لوحة الكافتيريا</h4>
        <p class="text-muted mb-0">{{ kpis?.date }}</p>
      </div>
    </div>

    <div class="row g-4 mb-4">
      <div class="col-md-4 col-lg">
        <div class="card border">
          <div class="card-body">
            <h6 class="text-muted">إيرادات اليوم</h6>
            <h3 class="mb-0">{{ kpis?.revenue ?? '0' }} EGP</h3>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card border">
          <div class="card-body">
            <h6 class="text-muted">عدد المعاملات</h6>
            <h3 class="mb-0">{{ kpis?.transactions_count ?? 0 }}</h3>
          </div>
        </div>
      </div>
      <div class="col-md-4 col-lg">
        <div class="card border">
          <div class="card-body">
            <h6 class="text-muted">منتجات منخفضة المخزون</h6>
            <h3 class="mb-0">{{ kpis?.low_stock?.length ?? 0 }}</h3>
          </div>
        </div>
      </div>
      <div class="col-md-4 col-lg">
        <div class="card border">
          <div class="card-body">
            <h6 class="text-muted">قواعد قيود نشطة</h6>
            <h3 class="mb-0">{{ kpis?.restrictions?.active_rules ?? 0 }}</h3>
          </div>
        </div>
      </div>
      <div class="col-md-4 col-lg">
        <div class="card border">
          <div class="card-body">
            <h6 class="text-muted">طلاب بقيود</h6>
            <h3 class="mb-0">{{ kpis?.restrictions?.students_with_restrictions ?? 0 }}</h3>
          </div>
        </div>
      </div>
      <div class="col-md-4 col-lg">
        <div class="card border">
          <div class="card-body">
            <h6 class="text-muted">مخالفات قيود</h6>
            <h3 class="mb-0">{{ kpis?.restrictions?.restriction_violations ?? 0 }}</h3>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <div class="col-lg-6">
        <div class="card border h-100">
          <div class="card-header bg-transparent"><strong>أفضل المنتجات</strong></div>
          <div class="card-body p-0">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th>المنتج</th>
                  <th>الكمية</th>
                  <th>الإيراد</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in kpis?.top_products ?? []" :key="row.product_name">
                  <td>{{ row.product_name }}</td>
                  <td>{{ row.qty }}</td>
                  <td>{{ row.revenue }}</td>
                </tr>
                <tr v-if="!(kpis?.top_products?.length)">
                  <td colspan="3" class="text-center text-muted">لا توجد مبيعات اليوم</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card border h-100">
          <div class="card-header bg-transparent"><strong>آخر المعاملات</strong></div>
          <div class="card-body p-0">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th>الطالب</th>
                  <th>المبلغ</th>
                  <th>الوقت</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="sale in kpis?.recent_transactions ?? []" :key="sale.id">
                  <td>{{ sale.student_name }}</td>
                  <td>{{ sale.total }}</td>
                  <td>{{ sale.sold_at }}</td>
                </tr>
                <tr v-if="!(kpis?.recent_transactions?.length)">
                  <td colspan="3" class="text-center text-muted">لا توجد معاملات</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </CanteenLayout>
</template>
