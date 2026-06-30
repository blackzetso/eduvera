<script setup>
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import VisitOutcomeBadge from './VisitOutcomeBadge.vue'
defineProps({
  queue: { type: Array, default: () => [] },
})

const emit = defineEmits(['select'])

function formatDate(v) {
  if (!v) return '—'
  return new Date(v).toLocaleDateString('ar-EG')
}
</script>

<template>
  <div v-if="!queue.length" class="card visit-followup-card">
    <div class="card-body text-center text-muted py-5">
      <i class="bi bi-inbox fs-2 d-block mb-2"></i>
      لا توجد متابعات مطلوبة حالياً
    </div>
  </div>

  <div v-else class="row g-3">
    <div
      v-for="item in queue"
      :key="item.id"
      class="col-12 col-lg-6"
    >
      <div
        class="card visit-followup-card visit-priority-card h-100"
        role="button"
        @click="emit('select', item)"
      >
        <div class="card-body d-flex gap-3">
          <div
            class="visit-priority-card__bar"
            :class="item.follow_up_priority?.bar"
          ></div>
          <div class="flex-grow-1 min-w-0">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
              <span class="fw-bold">{{ item.applicant_name || '—' }}</span>
              <span class="badge rounded-pill" :class="item.follow_up_priority?.bar">
                أولوية {{ item.follow_up_priority?.label }}
              </span>
              <VisitOutcomeBadge :outcome="item.outcome" :label="item.outcome_label" />
            </div>
            <div class="small text-muted mb-2">
              زار المدرسة · {{ item.outcome_label }} · لا يزال {{ item.pipeline_stage_label }}
            </div>
            <div class="small">
              <span class="text-muted">آخر نشاط:</span>
              {{ formatDate(item.last_activity_at) }}
              <span v-if="item.scheduled_date" class="ms-2 text-warning fw-semibold">
                منذ {{ item.days_since_visit }} يوم
              </span>
            </div>
            <div class="mt-2 d-flex flex-wrap gap-1">
              <span
                v-for="alert in item.alerts"
                :key="alert.type"
                class="visit-alert-chip"
                :class="alert.class"
              >
                {{ alert.label }}
              </span>
            </div>
          </div>
          <Link
            :href="route('admin.admissions.show', item.application_id)"
            class="btn btn-sm btn-outline-primary align-self-start"
            @click.stop
          >
            فتح
          </Link>
        </div>
      </div>
    </div>
  </div>
</template>
