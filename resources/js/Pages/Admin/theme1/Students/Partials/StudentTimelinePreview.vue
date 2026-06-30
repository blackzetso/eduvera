<script setup>
const props = defineProps({
  timeline: { type: Array, default: () => [] },
  formatDateTime: { type: Function, required: true },
  limit: { type: Number, default: 5 },
})

const emit = defineEmits(['open-full'])

const typeIcons = {
  enrollment: 'bi-journal-plus',
  status: 'bi-toggle-on',
  behavior: 'bi-emoji-smile',
  attendance: 'bi-calendar-check',
  wallet: 'bi-wallet2',
  payment: 'bi-cash',
}

const typeColors = {
  enrollment: 'text-primary',
  status: 'text-info',
  behavior: 'text-warning',
  attendance: 'text-success',
  wallet: 'text-warning',
  payment: 'text-success',
}
</script>

<template>
  <div class="card student-command-card border-0 shadow-sm">
    <div class="card-body p-2 p-md-3">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <div class="student-section-title mb-0">
          <i class="bi bi-clock-history me-1 text-primary"></i>
          آخر الأحداث
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.72rem" @click="emit('open-full')">
          الخط الزمني الكامل
          <i class="bi bi-arrow-left-short"></i>
        </button>
      </div>

      <div v-if="!timeline.length" class="text-muted small text-center py-2">لا توجد أحداث.</div>

      <ul v-else class="list-unstyled mb-0 student-timeline-preview">
        <li
          v-for="event in timeline.slice(0, limit)"
          :key="event.id"
          class="student-timeline-preview__item d-flex gap-2 align-items-start"
        >
          <span class="student-timeline-preview__icon">
            <i :class="['bi', typeIcons[event.type] || event.icon || 'bi-circle', typeColors[event.type] || 'text-muted']"></i>
          </span>
          <div class="flex-grow-1 min-w-0">
            <div class="fw-bold small lh-sm text-truncate">{{ event.title }}</div>
            <div v-if="event.subtitle" class="text-muted text-truncate" style="font-size: 0.68rem">{{ event.subtitle }}</div>
          </div>
          <div class="small text-muted text-nowrap" style="font-size: 0.65rem">
            {{ formatDateTime(event.occurred_at) }}
          </div>
        </li>
      </ul>
    </div>
  </div>
</template>
