<script setup>
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  timeline: { type: Array, default: () => [] },
  formatDateTime: { type: Function, required: true },
  limit: { type: Number, default: 10 },
})

const emit = defineEmits(['open-full'])

const typeColors = {
  enrollment: 'text-primary',
  status: 'text-info',
  behavior: 'text-warning',
  wallet: 'text-warning',
  admission: 'text-success',
  attendance: 'text-success',
}
</script>

<template>
  <div class="card family-command-card border-0 shadow-sm mb-4 rounded-4">
    <div class="card-body p-3 p-md-4">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h6 class="mb-0 fw-bold">
          <i class="bi bi-clock-history me-1 text-primary"></i>
          آخر أحداث العائلة
        </h6>
        <button type="button" class="btn btn-sm btn-outline-primary" @click="emit('open-full')">
          عرض الخط الزمني الكامل
        </button>
      </div>

      <div v-if="!timeline.length" class="text-muted small text-center py-3">لا توجد أحداث بعد.</div>

      <ul v-else class="list-unstyled mb-0 family-timeline-preview">
        <li
          v-for="event in timeline.slice(0, limit)"
          :key="event.id"
          class="family-timeline-preview__item d-flex gap-2 gap-md-3 py-2"
        >
          <i
            :class="['bi flex-shrink-0', event.icon || 'bi-circle', typeColors[event.type] || 'text-muted']"
            style="margin-top: 2px"
          ></i>
          <div class="flex-grow-1 min-w-0">
            <div class="fw-semibold small text-truncate">
              <Link
                v-if="event.type === 'admission' && event.profile_url"
                :href="event.profile_url"
                class="text-decoration-none"
              >
                {{ event.title }}
              </Link>
              <template v-else>{{ event.title }}</template>
            </div>
            <div class="text-muted small text-truncate">
              <Link
                v-if="event.type === 'admission' && event.profile_url"
                :href="event.profile_url"
                class="text-decoration-none"
              >
                {{ event.subtitle }}
              </Link>
              <template v-else>{{ event.subtitle }}</template>
              <span v-if="event.student_name"> · {{ event.student_name }}</span>
            </div>
          </div>
          <div class="small text-muted text-nowrap d-none d-sm-block">
            {{ formatDateTime(event.occurred_at) }}
          </div>
        </li>
      </ul>
    </div>
  </div>
</template>
