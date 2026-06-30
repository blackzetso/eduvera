<script setup>
import { Head } from '@inertiajs/vue3'
import GuardianDashboardLayout from '@/Layouts/GuardianDashboardLayout.vue'

defineProps({
  guardian: Object,
  children: Array,
  student: Object,
  summary: Object,
  wallet: Object,
})

const typeLabels = {
  credit: 'شحن',
  debit: 'خصم',
  transfer_in: 'استلام تحويل',
  transfer_out: 'تحويل صادر',
}

const typeClass = {
  credit: 'text-success',
  debit: 'text-danger',
  transfer_in: 'text-primary',
  transfer_out: 'text-warning',
}

function signedAmount(tx) {
  const negative = tx.type === 'debit' || tx.type === 'transfer_out'
  return (negative ? '−' : '+') + ' ' + Number(tx.amount).toFixed(2) + ' ج.م'
}
</script>

<template>
  <Head :title="`محفظة ${student.name}`" />
  <GuardianDashboardLayout
    :guardian="guardian"
    :children="children"
    :student="student"
    active-menu="wallet"
  >
    <div class="d-flex align-items-center p-4 bg-success bg-opacity-10 rounded-3 mb-4">
      <span class="display-6 text-success"><i class="bi bi-wallet2" /></span>
      <div class="ms-4">
        <h5 class="mb-0 fw-bold">{{ Number(wallet.balance).toFixed(2) }} ج.م</h5>
        <span class="text-muted">رصيد {{ student.name }}</span>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-sm-6">
        <div class="p-3 bg-light rounded-3 text-center">
          <div class="fw-bold text-success">{{ Number(wallet.total_credited).toFixed(2) }} ج.م</div>
          <small class="text-muted">إجمالي الواردات</small>
        </div>
      </div>
      <div class="col-sm-6">
        <div class="p-3 bg-light rounded-3 text-center">
          <div class="fw-bold text-danger">{{ Number(wallet.total_debited).toFixed(2) }} ج.م</div>
          <small class="text-muted">إجمالي المصروفات</small>
        </div>
      </div>
    </div>

    <div class="card border">
      <div class="card-header bg-transparent">
        <h6 class="mb-0"><i class="bi bi-clock-history me-2 text-secondary" />حركات المحفظة</h6>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead class="table-light">
              <tr>
                <th>النوع</th>
                <th>البيان</th>
                <th>المبلغ</th>
                <th>التاريخ</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="tx in wallet.transactions" :key="tx.id">
                <td><span class="badge bg-secondary-soft text-secondary">{{ typeLabels[tx.type] ?? tx.type }}</span></td>
                <td class="small">{{ tx.description }}</td>
                <td :class="typeClass[tx.type]" class="fw-semibold">{{ signedAmount(tx) }}</td>
                <td class="text-muted small">{{ tx.created_at?.slice(0, 10) }}</td>
              </tr>
              <tr v-if="!wallet.transactions?.length">
                <td colspan="4" class="text-center text-muted py-3">لا توجد حركات بعد</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </GuardianDashboardLayout>
</template>
