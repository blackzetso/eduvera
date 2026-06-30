<script setup>
const props = defineProps({
  academic: { type: Object, required: true },
})

const emit = defineEmits(['open-student'])
</script>

<template>
  <div class="card family-snapshot-card border-0 shadow-sm h-100 rounded-4">
    <div class="card-body p-3 p-md-4">
      <h6 class="fw-bold mb-3">
        <i class="bi bi-journal-bookmark me-1 text-primary"></i>
        الأداء الأكاديمي
      </h6>

      <div v-if="academic.highest_performer" class="alert alert-success py-2 small mb-3">
        <strong>الأعلى أداءً:</strong>
        {{ academic.highest_performer.student_name }} — {{ academic.highest_performer.average_percent }}%
      </div>
      <div v-if="academic.needs_support" class="alert alert-warning py-2 small mb-3">
        <strong>يحتاج دعماً:</strong>
        {{ academic.needs_support.student_name }} — {{ academic.needs_support.average_percent }}%
      </div>

      <ul v-if="academic.students?.length" class="list-unstyled mb-0 small">
        <li
          v-for="s in academic.students"
          :key="s.student_id"
          class="d-flex justify-content-between py-2 border-bottom"
        >
          <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" @click="emit('open-student', s.student_id)">
            {{ s.student_name }}
          </button>
          <span class="fw-semibold">{{ s.average_percent != null ? `${s.average_percent}%` : '—' }}</span>
        </li>
      </ul>
      <p v-else class="text-muted small mb-0">لا توجد بيانات أكاديمية</p>
    </div>
  </div>
</template>
