<script setup>
import { computed } from 'vue'

const props = defineProps({
  studentQuery: String,
  studentResults: Array,
  studentSearched: Boolean,
  selectedStudent: Object,
  eligibility: Object,
  eligibilityError: String,
  limitStatus: String,
})

const emit = defineEmits(['update:studentQuery', 'search', 'select', 'pick', 'clear', 'retry-eligibility'])

function onSearchKeydown(event) {
  if (event.key === 'Enter') {
    event.preventDefault()
    if (props.studentResults.length === 1) {
      emit('pick')
    } else {
      emit('search')
    }
  }
}

const limitClass = computed(() => {
  switch (props.limitStatus) {
    case 'danger': return 'pos-student-bar__limit--danger'
    case 'warning': return 'pos-student-bar__limit--warning'
    case 'success': return 'pos-student-bar__limit--success'
    default: return 'pos-student-bar__limit--neutral'
  }
})

const hasDailyLimit = computed(() => {
  const limit = props.eligibility?.daily_limit?.limit
  return limit != null && limit !== ''
})

const dailyLimitLabel = computed(() => {
  if (!props.eligibility) return '…'
  if (!hasDailyLimit.value) return 'بدون حد'
  return props.eligibility.daily_limit.limit
})

const remainingLabel = computed(() => {
  if (!props.eligibility) return '…'
  if (!hasDailyLimit.value) return '—'
  return props.eligibility.daily_limit?.remaining ?? '—'
})

const walletLabel = computed(() => {
  if (!props.eligibility) return '…'
  const balance = props.eligibility.wallet_balance
  if (balance == null || balance === '') return '0.00'
  const n = Number.parseFloat(balance)
  return Number.isFinite(n) ? n.toFixed(2) : '0.00'
})

const spentTodayLabel = computed(() => {
  const spent = props.eligibility?.daily_limit?.spent
  if (spent == null || spent === '') return null
  const n = Number.parseFloat(spent)
  return Number.isFinite(n) && n > 0 ? n.toFixed(2) : null
})
</script>

<template>
  <div class="pos-student-bar">
    <template v-if="!selectedStudent">
      <div class="pos-student-bar__search-row">
        <i class="bi bi-person-badge pos-student-bar__search-icon"></i>
        <input
          :value="studentQuery"
          class="pos-student-bar__input"
          placeholder="ابحث عن الطالب لبدء عملية البيع — بالاسم أو رقم الطالب..."
          @input="emit('update:studentQuery', $event.target.value)"
          @keydown="onSearchKeydown"
        >
        <button type="button" class="pos-student-bar__search-btn" @click="$emit('search')">
          بحث
        </button>
      </div>

      <div v-if="studentResults.length" class="pos-student-bar__results">
        <p v-if="studentResults.length > 1" class="pos-student-bar__hint pos-student-bar__hint--pick mb-2">
          <i class="bi bi-hand-index me-1"></i>اضغط على الطالب لتحديده
        </p>
        <button
          v-for="s in studentResults"
          :key="s.student_id_ref"
          type="button"
          class="pos-student-bar__result"
          @click="emit('select', s)"
        >
          <span class="fw-bold">{{ s.student_name }}</span>
          <small class="text-muted">{{ s.student_id_ref }}</small>
          <span class="pos-student-bar__result-action">اختيار</span>
        </button>
      </div>

      <p v-else-if="studentSearched" class="pos-student-bar__hint pos-student-bar__hint--warn mb-0">
        <i class="bi bi-search me-1"></i>لا توجد نتائج
      </p>
      <p v-else class="pos-student-bar__hint mb-0">
        <i class="bi bi-info-circle me-1"></i>ابحث عن الطالب لبدء عملية البيع
      </p>
    </template>

    <template v-else>
      <div class="pos-student-bar__summary" :class="limitClass">
        <div class="pos-student-bar__summary-main">
          <div class="pos-student-bar__avatar"><i class="bi bi-person-fill"></i></div>
          <div class="min-w-0">
            <div class="pos-student-bar__name">{{ selectedStudent.student_name }}</div>
            <div class="pos-student-bar__meta">
              <span>{{ selectedStudent.student_id_ref }}</span>
              <span class="pos-student-bar__dot">•</span>
              <span>{{ selectedStudent.grade || eligibility?.student?.grade || '—' }}</span>
              <span class="pos-student-bar__dot">•</span>
              <span>{{ selectedStudent.class_name || eligibility?.student?.class_name || '—' }}</span>
            </div>
          </div>
        </div>
        <div class="pos-student-bar__limits">
          <div class="pos-student-bar__limit-chip pos-student-bar__limit-chip--wallet pos-student-bar__limit-chip--primary">
            <span class="label"><i class="bi bi-wallet2 me-1"></i>رصيد المحفظة</span>
            <strong>{{ walletLabel }} <small class="pos-currency">ج.م</small></strong>
          </div>
          <div class="pos-student-bar__limit-chip">
            <span class="label">الحد اليومي</span>
            <strong>{{ dailyLimitLabel }}</strong>
          </div>
          <div v-if="hasDailyLimit" class="pos-student-bar__limit-chip pos-student-bar__limit-chip--highlight">
            <span class="label">المتبقي اليوم</span>
            <strong>{{ remainingLabel }}</strong>
          </div>
          <div v-else-if="spentTodayLabel" class="pos-student-bar__limit-chip">
            <span class="label">مصروف اليوم</span>
            <strong>{{ spentTodayLabel }}</strong>
          </div>
        </div>
        <button type="button" class="pos-student-bar__change" title="تغيير الطالب" @click="$emit('clear')">
          <i class="bi bi-arrow-repeat"></i>
        </button>
      </div>

      <div v-if="eligibilityError" class="alert alert-danger py-2 mt-2 mb-0">
        <div class="small">{{ eligibilityError }}</div>
        <button type="button" class="btn btn-sm btn-outline-danger mt-1" @click="$emit('retry-eligibility')">
          إعادة المحاولة
        </button>
      </div>
    </template>
  </div>
</template>
