<script setup>
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  application: { type: Object, required: true },
  visit: { type: Object, default: null },
  visitPassed: { type: Boolean, default: false },
  isReadOnly: { type: Boolean, default: false },
})

const emit = defineEmits(['open-tab'])

function patchVisit(payload) {
  if (!props.visit || props.isReadOnly) return
  router.patch(route('admin.admissions.visits.update', [props.application.id, props.visit.id]), {
    scheduled_date: props.visit.scheduled_date || '',
    scheduled_time: props.visit.scheduled_time || '',
    status: props.visit.status || '',
    outcome: props.visit.outcome || '',
    attendance_status: props.visit.attendance_status || '',
    notes: props.visit.notes || '',
    follow_up_notes: props.visit.follow_up_notes || '',
    ...payload,
  }, { preserveScroll: true })
}

function confirmVisit() {
  patchVisit({ status: 'confirmed' })
}

function markAttended() {
  patchVisit({ attendance_status: 'attended', status: 'completed' })
}

function reschedule() {
  patchVisit({ outcome: 'rescheduled', status: 'requested', attendance_status: '' })
}

function cancelVisit() {
  patchVisit({ status: 'cancelled', attendance_status: 'cancelled' })
}
</script>

<template>
  <div class="admission-adaptive-dashboard admissions-visit-ops mb-0">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
      <h6 class="fw-bold mb-0 small">
        <i class="bi bi-sliders me-1 text-primary"></i>
        عمليات الزيارة
      </h6>
      <Link
        :href="route('admin.admissions.visits.index')"
        class="btn btn-outline-secondary btn-sm"
      >
        <i class="bi bi-calendar-week me-1"></i>
        كل الزيارات
      </Link>
    </div>

    <div v-if="visitPassed && visit && !isReadOnly" class="alert alert-danger py-1 px-2 small mb-2 mb-md-3">
      <i class="bi bi-exclamation-triangle me-1"></i>
      إجراء مطلوب: حدّث الحضور أو أعد الجدولة من الأزرار أدناه.
    </div>

    <div v-if="!visit" class="text-muted small py-2">
      لا توجد زيارة للإدارة —
      <button type="button" class="btn btn-link btn-sm p-0 align-baseline" @click="emit('open-tab', 'visits')">
        جدولة زيارة
      </button>
    </div>

    <div v-else-if="!isReadOnly" class="d-flex flex-column flex-sm-row flex-wrap gap-2">
      <button type="button" class="btn btn-success btn-sm" @click="markAttended">
        <i class="bi bi-check-lg me-1"></i> تسجيل حضور
      </button>
      <button type="button" class="btn btn-primary btn-sm" @click="confirmVisit">
        <i class="bi bi-check2-circle me-1"></i> تأكيد الزيارة
      </button>
      <button type="button" class="btn btn-warning btn-sm" @click="reschedule">
        <i class="bi bi-arrow-repeat me-1"></i> إعادة جدولة
      </button>
      <button type="button" class="btn btn-outline-danger btn-sm" @click="cancelVisit">
        <i class="bi bi-x-lg me-1"></i> إلغاء
      </button>
    </div>

    <p v-else-if="visit && isReadOnly" class="text-muted small mb-0">
      الطلب للقراءة فقط — لا تتوفر عمليات تعديل.
    </p>
  </div>
</template>
