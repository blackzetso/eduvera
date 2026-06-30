<script setup>
const props = defineProps({
  attendance: { type: Object, required: true },
})

const emit = defineEmits(['open-student'])
</script>

<template>
  <div class="card family-snapshot-card border-0 shadow-sm h-100 rounded-4">
    <div class="card-body p-3 p-md-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0 fw-bold">
          <i class="bi bi-calendar-check me-1 text-success"></i>
          حضور العائلة
        </h6>
        <span class="badge" :class="attendance.status === 'red' ? 'bg-danger' : attendance.status === 'amber' ? 'bg-warning text-dark' : 'bg-success'">
          {{ attendance.family_average != null ? `${attendance.family_average}%` : '—' }}
        </span>
      </div>

      <p class="small text-muted mb-3">
        متوسط حضور العائلة
        <span v-if="attendance.below_threshold_count" class="text-danger fw-semibold">
          — {{ attendance.below_threshold_count }} طالب دون العتبة
        </span>
      </p>

      <ul v-if="attendance.students?.length" class="list-unstyled mb-0 small">
        <li
          v-for="s in attendance.students"
          :key="s.student_id"
          class="d-flex justify-content-between align-items-center py-2 border-bottom"
          :class="{ 'bg-warning bg-opacity-10 rounded px-2': s.below_threshold || s.has_alert }"
        >
          <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-start" @click="emit('open-student', s.student_id)">
            {{ s.student_name }}
          </button>
          <span>
            <span class="fw-semibold">{{ s.rate_percent != null ? `${s.rate_percent}%` : '—' }}</span>
            <span class="text-muted ms-2">غ:{{ s.absent }} ت:{{ s.late }}</span>
          </span>
        </li>
      </ul>
      <p v-else class="text-muted small mb-0">لا يوجد أبناء مرتبطون</p>
    </div>
  </div>
</template>
