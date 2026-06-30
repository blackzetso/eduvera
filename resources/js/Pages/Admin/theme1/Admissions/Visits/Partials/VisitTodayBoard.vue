<script setup>
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import VisitOutcomeBadge from './VisitOutcomeBadge.vue'
import { formatTime } from '@/composables/useVisitCommandCenter'

const props = defineProps({
  board: { type: Object, required: true },
})

const emit = defineEmits(['select'])

const columns = [
  { id: 'scheduled', label: 'مجدولة', icon: 'bi-clock', headerClass: 'text-primary' },
  { id: 'checked_in', label: 'تم تسجيل الحضور', icon: 'bi-door-open', headerClass: 'text-info' },
  { id: 'completed', label: 'مكتملة', icon: 'bi-check-circle', headerClass: 'text-success' },
  { id: 'no_show', label: 'لم يحضر', icon: 'bi-x-circle', headerClass: 'text-danger' },
]

function patchVisit(visit, payload) {
  router.patch(
    route('admin.admissions.visits.update', [visit.application_id, visit.id]),
    {
      scheduled_date: visit.scheduled_date,
      scheduled_time: visit.scheduled_time,
      status: visit.status,
      outcome: visit.outcome || '',
      attendance_status: visit.attendance_status || '',
      notes: visit.notes || '',
      follow_up_notes: visit.follow_up_notes || '',
      ...payload,
    },
    { preserveScroll: true }
  )
}

function markAttended(visit) {
  patchVisit(visit, { attendance_status: 'attended', status: 'completed' })
}

function markReschedule(visit) {
  patchVisit(visit, { outcome: 'rescheduled', status: 'requested', attendance_status: '' })
}

function markCancel(visit) {
  patchVisit(visit, { status: 'cancelled', attendance_status: 'cancelled' })
}

function markNoShow(visit) {
  patchVisit(visit, { status: 'no_show', attendance_status: 'no_show' })
}
</script>

<template>
  <div class="visit-board-columns row g-3">
    <div
      v-for="col in columns"
      :key="col.id"
      class="col-12 col-md-6 col-xl-3"
    >
      <div class="visit-board-column h-100">
        <div class="d-flex align-items-center gap-2 mb-3">
          <i :class="['bi', col.icon, col.headerClass]"></i>
          <span class="fw-semibold">{{ col.label }}</span>
          <span class="badge bg-secondary rounded-pill ms-auto">{{ (board[col.id] || []).length }}</span>
        </div>

        <div v-if="!(board[col.id] || []).length" class="text-muted small text-center py-4">
          لا توجد زيارات
        </div>

        <div
          v-for="visit in board[col.id]"
          :key="visit.id"
          class="visit-board-card p-3 mb-2"
          role="button"
          @click="emit('select', visit)"
        >
          <div class="fw-bold text-primary mb-1">{{ formatTime(visit.scheduled_time) }}</div>
          <div class="fw-semibold">{{ visit.applicant_name || '—' }}</div>
          <div class="small text-muted mb-2">{{ visit.target_grade || '—' }} · {{ visit.pipeline_stage_label }}</div>
          <VisitOutcomeBadge
            v-if="visit.outcome"
            :outcome="visit.outcome"
            :label="visit.outcome_label"
            class="mb-2"
          />

          <div v-if="col.id === 'scheduled'" class="d-flex flex-wrap gap-1 mt-2" @click.stop>
            <button type="button" class="btn btn-sm btn-success" title="حضر" @click="markAttended(visit)">
              <i class="bi bi-check-lg"></i>
            </button>
            <button type="button" class="btn btn-sm btn-warning" title="إعادة جدولة" @click="markReschedule(visit)">
              <i class="bi bi-arrow-repeat"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger" title="إلغاء" @click="markCancel(visit)">
              <i class="bi bi-x-lg"></i>
            </button>
          </div>

          <div v-if="col.id === 'checked_in'" class="d-flex flex-wrap gap-1 mt-2" @click.stop>
            <button type="button" class="btn btn-sm btn-success" @click="markAttended(visit)">
              إكمال
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger" @click="markNoShow(visit)">
              لم يحضر
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
