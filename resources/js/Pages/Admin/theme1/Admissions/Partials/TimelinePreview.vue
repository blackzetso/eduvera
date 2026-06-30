<script setup>
import { computed } from 'vue'
import { timelineEventColor, timelineEventIcons } from '@/Shared/admissionsBadges'

const props = defineProps({
  timeline: { type: Array, default: () => [] },
  formatDateTime: { type: Function, required: true },
  limit: { type: Number, default: 5 },
})

const emit = defineEmits(['open-full'])

const preview = computed(() => (props.timeline || []).slice(0, props.limit))
const hasMore = computed(() => (props.timeline || []).length > props.limit)
</script>

<template>
  <div class="card admission-adaptive-card border-0 shadow-sm mb-4">
    <div class="card-body p-3 p-md-4">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h6 class="mb-0 fw-bold">
          <i class="bi bi-clock-history me-1 text-primary"></i>
          آخر الأحداث
        </h6>
        <button type="button" class="btn btn-sm btn-outline-primary" @click="emit('open-full')">
          عرض السجل الكامل
          <i class="bi bi-arrow-left-short"></i>
        </button>
      </div>

      <div v-if="!preview.length" class="text-muted small text-center py-3">لا توجد أحداث بعد.</div>

      <ul class="list-unstyled mb-0 admission-timeline-preview">
        <li
          v-for="(event, idx) in preview"
          :key="idx"
          class="admission-timeline-preview__item d-flex gap-2 gap-md-3 py-2"
        >
          <i
            :class="['bi flex-shrink-0', timelineEventIcons[event.type] || 'bi-circle', timelineEventColor(event.type)]"
            style="margin-top: 2px"
          ></i>
          <div class="flex-grow-1 min-w-0">
            <div class="fw-semibold small text-truncate">{{ event.title }}</div>
            <div class="text-muted small text-truncate">{{ event.description }}</div>
          </div>
          <div class="small text-muted text-nowrap d-none d-sm-block">
            {{ formatDateTime(event.occurred_at) }}
          </div>
        </li>
      </ul>

      <div v-if="hasMore" class="text-center mt-2">
        <button type="button" class="btn btn-link btn-sm text-decoration-none" @click="emit('open-full')">
          +{{ timeline.length - limit }} أحداث أخرى
        </button>
      </div>
    </div>
  </div>
</template>
