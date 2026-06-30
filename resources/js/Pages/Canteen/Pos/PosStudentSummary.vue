<script setup>
import { computed } from 'vue'

const props = defineProps({
  selectedStudent: Object,
  eligibility: Object,
  limitStatus: String,
})

const limitClass = computed(() => {
  if (!props.selectedStudent) return ''
  switch (props.limitStatus) {
    case 'danger': return 'pos-student-summary--danger'
    case 'warning': return 'pos-student-summary--warning'
    case 'success': return 'pos-student-summary--success'
    default: return 'pos-student-summary--neutral'
  }
})

const hasDailyLimit = computed(() => {
  const limit = props.eligibility?.daily_limit?.limit
  return limit != null && limit !== ''
})

const walletLabel = computed(() => {
  if (!props.eligibility) return '…'
  const balance = props.eligibility.wallet_balance
  if (balance == null || balance === '') return '0.00'
  const n = Number.parseFloat(balance)
  return Number.isFinite(n) ? n.toFixed(2) : '0.00'
})
</script>

<template>
  <div class="pos-student-summary" :class="limitClass">
    <template v-if="selectedStudent">
      <div class="pos-student-summary__name">{{ selectedStudent.student_name }}</div>
      <div class="pos-student-summary__row">
        <span>{{ selectedStudent.student_id_ref }}</span>
        <span>{{ selectedStudent.grade || eligibility?.student?.grade || '—' }} / {{ selectedStudent.class_name || eligibility?.student?.class_name || '—' }}</span>
      </div>
      <div class="pos-student-summary__wallet">
        <i class="bi bi-wallet2"></i>
        <span>رصيد المحفظة</span>
        <strong>{{ walletLabel }} ج.م</strong>
      </div>
      <div class="pos-student-summary__limits">
        <span>حد يومي: <strong>{{ hasDailyLimit ? eligibility.daily_limit.limit : 'بدون حد' }}</strong></span>
        <span v-if="hasDailyLimit">متبقي: <strong>{{ eligibility.daily_limit.remaining ?? '—' }}</strong></span>
        <span v-else-if="eligibility?.daily_limit?.spent && parseFloat(eligibility.daily_limit.spent) > 0">
          مصروف اليوم: <strong>{{ eligibility.daily_limit.spent }}</strong>
        </span>
      </div>
    </template>
    <div v-else class="pos-student-summary__empty text-muted">
      <i class="bi bi-person me-1"></i>لم يُختر طالب
    </div>
  </div>
</template>
