<script setup>
import { computed } from 'vue'

const props = defineProps({
  studentQuery: String,
  studentResults: Array,
  selectedStudent: Object,
  eligibility: Object,
  eligibilityError: String,
  limitStatus: String,
})

defineEmits(['update:studentQuery', 'search', 'select', 'clear', 'retry-eligibility'])

const remainingLabel = computed(() => {
  if (props.limitStatus === 'neutral') {
    return 'لا يوجد حد يومي'
  }
  return props.eligibility?.daily_limit?.remaining ?? '—'
})

const badgeClass = computed(() => {
  switch (props.limitStatus) {
    case 'danger': return 'text-danger'
    case 'warning': return 'text-warning'
    case 'success': return 'text-success'
    default: return ''
  }
})
</script>

<template>
  <div class="pos-customer-bar">
    <div class="pos-customer-bar__search">
      <input
        :value="studentQuery"
        placeholder="بحث بالاسم أو رقم الطالب..."
        @input="$emit('update:studentQuery', $event.target.value)"
        @keyup.enter="$emit('search')"
      >
      <button type="button" @click="$emit('search')">بحث</button>
    </div>

    <div v-if="studentResults.length && !selectedStudent" class="list-group pos-student-results">
      <button
        v-for="s in studentResults"
        :key="s.student_id_ref"
        type="button"
        class="list-group-item list-group-item-action py-2"
        @click="$emit('select', s)"
      >
        <div class="fw-bold">{{ s.student_name }}</div>
        <small class="text-muted">{{ s.student_id_ref }}</small>
      </button>
    </div>

    <div v-if="eligibilityError && selectedStudent" class="alert alert-danger py-2 mt-2 mb-0">
      <div class="small">{{ eligibilityError }}</div>
      <button type="button" class="btn btn-sm btn-outline-danger mt-2" @click="$emit('retry-eligibility')">
        إعادة المحاولة
      </button>
    </div>

    <div v-if="selectedStudent" class="pos-customer-bar__profile">
      <div class="pos-customer-bar__info flex-grow-1 min-w-0">
        <h5 class="text-truncate">{{ selectedStudent.student_name }}</h5>
        <small>{{ selectedStudent.student_id_ref }}</small>
      </div>
      <div class="pos-customer-bar__badge" :class="badgeClass">
        <span class="pos-customer-bar__badge-value">{{ remainingLabel }}</span>
        <span class="pos-customer-bar__badge-label">المتبقي اليومي</span>
      </div>
      <button type="button" class="pos-customer-bar__clear" title="تغيير الطالب" @click="$emit('clear')">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>

    <div v-if="selectedStudent" class="pos-customer-bar__metrics">
      <div class="pos-customer-bar__metric">
        <span>الصف</span>
        <strong>{{ selectedStudent.grade || eligibility?.student?.grade || '—' }}</strong>
      </div>
      <div class="pos-customer-bar__metric">
        <span>الحد اليومي</span>
        <strong>{{ eligibility?.daily_limit?.limit ?? '—' }}</strong>
      </div>
      <div class="pos-customer-bar__metric">
        <span>المصروف</span>
        <strong>{{ eligibility?.daily_limit?.spent ?? '—' }}</strong>
      </div>
    </div>

    <p v-else-if="!studentResults.length" class="small mb-0 mt-2 opacity-75">
      <i class="bi bi-info-circle me-1"></i>ابحث عن الطالب لبدء البيع
    </p>
  </div>
</template>
