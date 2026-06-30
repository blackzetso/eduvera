<script setup>
const props = defineProps({
  finance: { type: Object, required: true },
  formatCurrency: { type: Function, required: true },
})

const emit = defineEmits(['open-tab'])
</script>

<template>
  <div class="card family-finance-card border-0 shadow-sm mb-4 rounded-4">
    <div class="card-body p-3 p-md-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0 fw-bold">
          <i class="bi bi-cash-coin me-1 text-warning"></i>
          لمحة مالية للعائلة
        </h6>
        <span class="badge" :class="finance.status === 'red' ? 'bg-danger' : finance.status === 'amber' ? 'bg-warning text-dark' : 'bg-success'">
          {{ finance.status_label }}
        </span>
      </div>

      <div class="row g-3">
        <div class="col-6 col-md-4 col-lg-2">
          <div class="family-snapshot-card p-3 text-center h-100 rounded-4">
            <div class="small text-muted">رصيد مستحق</div>
            <strong :class="finance.outstanding_balance > 0 ? 'text-danger' : ''">{{ formatCurrency(finance.outstanding_balance) }}</strong>
          </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
          <div class="family-snapshot-card p-3 text-center h-100 rounded-4">
            <div class="small text-muted">مدفوع</div>
            <strong class="text-success">{{ formatCurrency(finance.paid_this_year) }}</strong>
          </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
          <div class="family-snapshot-card p-3 text-center h-100 rounded-4">
            <div class="small text-muted">أقساط قادمة</div>
            <strong>{{ finance.upcoming_installments }}</strong>
          </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
          <div class="family-snapshot-card p-3 text-center h-100 rounded-4">
            <div class="small text-muted">متأخرة</div>
            <strong :class="finance.overdue_installments > 0 ? 'text-danger' : ''">{{ finance.overdue_installments }}</strong>
          </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
          <div class="family-snapshot-card p-3 text-center h-100 rounded-4">
            <div class="small text-muted">المحفظة</div>
            <strong>{{ formatCurrency(finance.wallet_balance) }}</strong>
          </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2 d-flex align-items-stretch">
          <button type="button" class="btn btn-outline-warning btn-sm w-100" @click="emit('open-tab', 'finance')">
            التفاصيل
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
