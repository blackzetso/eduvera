<script setup>
import { computed } from 'vue'

const props = defineProps({
  finance: { type: Object, required: true },
  formatCurrency: { type: Function, required: true },
  formatDateTime: { type: Function, required: true },
})

const emit = defineEmits(['open-tab'])

const walletTypeLabels = {
  credit: 'إيداع',
  debit: 'خصم',
  transfer_in: 'تحويل وارد',
  transfer_out: 'تحويل صادر',
}

const installmentBadgeClass = computed(() => {
  const status = props.finance.installment_status
  if (status === 'overdue') return 'bg-danger'
  if (status === 'due_soon') return 'bg-warning text-dark'
  return 'bg-success'
})
</script>

<template>
  <div class="card student-finance-card border-0 shadow-sm h-100">
    <div class="card-body p-2 p-md-3">
      <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
        <div class="student-section-title mb-0">
          <i class="bi bi-cash-coin me-1 text-warning"></i>
          مالي
        </div>
        <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 small" @click="emit('open-tab', 'wallet')">
          التفاصيل
        </button>
      </div>

      <div class="row g-2 mb-2 student-cc-row-tight">
        <div class="col-6">
          <div
            class="student-finance-hero student-finance-hero--outstanding h-100"
            :class="{ 'border-danger': finance.outstanding_balance > 0 }"
          >
            <div class="student-snapshot-kpi__label mb-0">مستحق</div>
            <div
              class="student-finance-hero__value"
              :class="finance.outstanding_balance > 0 ? 'text-danger' : ''"
            >
              {{ formatCurrency(finance.outstanding_balance) }}
            </div>
          </div>
        </div>
        <div class="col-6">
          <div class="student-finance-hero student-finance-hero--wallet h-100">
            <div class="student-snapshot-kpi__label mb-0">المحفظة</div>
            <div class="student-finance-hero__value text-success">
              {{ formatCurrency(finance.wallet_balance) }}
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex align-items-center justify-content-between gap-2 mb-2 p-2 rounded border border-opacity-25">
        <span class="small fw-semibold text-muted">الأقساط</span>
        <span class="badge rounded-pill" :class="installmentBadgeClass">
          {{ finance.installment_status_label }}
        </span>
      </div>

      <div class="text-muted small mb-1" style="font-size: 0.65rem">آخر الحركات</div>
      <ul v-if="finance.recent_transactions?.length" class="list-unstyled mb-0 small">
        <li
          v-for="t in finance.recent_transactions.slice(0, 3)"
          :key="t.id"
          class="d-flex justify-content-between py-0 border-bottom border-opacity-25 gap-2"
        >
          <span class="text-truncate">{{ walletTypeLabels[t.type] || t.type }}</span>
          <span class="fw-bold text-nowrap">{{ formatCurrency(t.amount) }}</span>
        </li>
      </ul>
      <p v-else class="text-muted small mb-0">لا توجد حركات</p>
    </div>
  </div>
</template>
