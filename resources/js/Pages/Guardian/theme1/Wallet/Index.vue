<script setup>
import { Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import GuardianDashboardLayout from '@/Layouts/GuardianDashboardLayout.vue'
import { route } from 'ziggy-js'

const props = defineProps({
  guardian: Object,
  children: Array,
  guardianWallet: Object,
})

const selectedChildId = ref(props.children[0]?.id ?? null)
const transferAmount   = ref('')
const transferNote     = ref('')
const transferring     = ref(false)
const error            = ref('')

const typeLabels = {
  credit: 'شحن',
  debit: 'خصم',
  transfer_in: 'استلام تحويل',
  transfer_out: 'تحويل لابن/ابنة',
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

function doTransfer() {
  error.value = ''
  const amount = parseFloat(transferAmount.value)
  if (!selectedChildId.value) {
    error.value = 'اختر ابناً أولاً'
    return
  }
  if (!amount || amount <= 0) {
    error.value = 'أدخل مبلغاً صحيحاً'
    return
  }
  if (amount > Number(props.guardianWallet.balance)) {
    error.value = 'الرصيد غير كافٍ'
    return
  }

  transferring.value = true
  router.post(route('guardian.wallet.transfer'), {
    to_student_id: selectedChildId.value,
    amount,
    description: transferNote.value || `تحويل مصروف`,
  }, {
    onFinish: () => { transferring.value = false },
    onError: (errs) => { error.value = Object.values(errs).join(' | ') },
  })
}
</script>

<template>
  <Head title="محفظتي" />
  <GuardianDashboardLayout
    :guardian="guardian"
    :children="children"
    :student="null"
    active-menu="wallet"
  >
    <!-- Guardian balance -->
    <div class="d-flex align-items-center p-4 bg-warning bg-opacity-10 rounded-3 mb-4">
      <span class="display-6 text-warning"><i class="bi bi-wallet2" /></span>
      <div class="ms-4">
        <h5 class="mb-0 fw-bold">{{ Number(guardianWallet.balance).toFixed(2) }} ج.م</h5>
        <span class="text-muted">رصيدك الحالي</span>
      </div>
    </div>

    <div class="row g-4">
      <!-- Transfer card -->
      <div class="col-lg-5">
        <div class="card border h-100">
          <div class="card-header bg-transparent">
            <h6 class="mb-0"><i class="bi bi-arrow-left-right me-2 text-primary" />تحويل مصروف لابن</h6>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label small fw-semibold">الابن / الابنة</label>
              <select v-model="selectedChildId" class="form-select">
                <option v-for="c in children" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold">المبلغ (ج.م)</label>
              <input
                v-model="transferAmount"
                type="number"
                min="1"
                step="1"
                class="form-control"
                placeholder="مثال: 50"
              >
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold">ملاحظة (اختياري)</label>
              <input v-model="transferNote" type="text" class="form-control" placeholder="مصروف الأسبوع…">
            </div>
            <div v-if="error" class="alert alert-danger py-2 small">{{ error }}</div>
            <button
              class="btn btn-primary w-100"
              :disabled="transferring"
              @click="doTransfer"
            >
              <span v-if="transferring" class="spinner-border spinner-border-sm me-2" />
              تحويل الرصيد
            </button>
          </div>
        </div>
      </div>

      <!-- Guardian transaction history -->
      <div class="col-lg-7">
        <div class="card border">
          <div class="card-header bg-transparent">
            <h6 class="mb-0"><i class="bi bi-clock-history me-2 text-secondary" />آخر حركاتك</h6>
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
                  <tr v-for="tx in guardianWallet.transactions" :key="tx.id">
                    <td><span class="badge bg-secondary-soft text-secondary">{{ typeLabels[tx.type] ?? tx.type }}</span></td>
                    <td class="small">{{ tx.description }}</td>
                    <td :class="typeClass[tx.type]" class="fw-semibold">{{ signedAmount(tx) }}</td>
                    <td class="text-muted small">{{ tx.created_at?.slice(0, 10) }}</td>
                  </tr>
                  <tr v-if="!guardianWallet.transactions?.length">
                    <td colspan="4" class="text-center text-muted py-3">لا توجد حركات بعد</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </GuardianDashboardLayout>
</template>
