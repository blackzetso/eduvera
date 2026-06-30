<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import VisitOutcomeBadge from './VisitOutcomeBadge.vue'
import { formatTime } from '@/composables/useVisitCommandCenter'

const props = defineProps({
  visit: { type: Object, default: null },
  show: { type: Boolean, default: false },
})

const emit = defineEmits(['close'])

const timelineSteps = computed(() => {
  if (!props.visit) return []
  const v = props.visit
  const steps = [
    {
      key: 'scheduled',
      label: 'مجدولة',
      done: !!v.scheduled_date,
      at: v.scheduled_date,
      icon: 'bi-calendar-plus',
      color: 'text-primary',
    },
    {
      key: 'reminder',
      label: 'تذكير مُرسل',
      done: ['confirmed', 'completed'].includes(v.status),
      at: null,
      icon: 'bi-bell',
      color: 'text-info',
    },
    {
      key: 'checkin',
      label: 'تسجيل حضور',
      done: v.attendance_status === 'attended',
      at: v.attendance_status === 'attended' ? v.updated_at : null,
      icon: 'bi-door-open',
      color: 'text-success',
    },
    {
      key: 'outcome',
      label: 'تسجيل النتيجة',
      done: !!v.outcome,
      at: v.completed_at || (v.outcome ? v.updated_at : null),
      icon: 'bi-clipboard-check',
      color: 'text-warning',
    },
  ]
  return steps
})

function formatDate(v) {
  if (!v) return '—'
  return new Date(v).toLocaleDateString('ar-EG')
}

function formatDateTime(v) {
  if (!v) return '—'
  return new Date(v).toLocaleString('ar-EG')
}
</script>

<template>
  <div
    class="offcanvas offcanvas-start visit-drawer"
    :class="{ show: show && visit }"
    tabindex="-1"
    :style="show && visit ? 'visibility: visible;' : ''"
  >
    <div v-if="visit" class="offcanvas-header border-bottom">
      <div>
        <h5 class="offcanvas-title mb-1">تفاصيل الزيارة</h5>
        <div class="small text-muted">
          {{ formatDate(visit.scheduled_date) }} · {{ formatTime(visit.scheduled_time) }}
        </div>
      </div>
      <button type="button" class="btn-close" @click="emit('close')"></button>
    </div>
    <div v-if="visit" class="offcanvas-body">
      <div class="mb-3">
        <VisitOutcomeBadge :outcome="visit.outcome" :label="visit.outcome_label" />
        <span class="visit-status-chip bg-light text-dark ms-1">{{ visit.status_label }}</span>
      </div>

      <dl class="row g-2 small mb-4">
        <dt class="col-5 text-muted">المتقدم</dt>
        <dd class="col-7 fw-semibold mb-0">{{ visit.applicant_name || '—' }}</dd>
        <dt class="col-5 text-muted">ولي الأمر</dt>
        <dd class="col-7 mb-0">{{ visit.parent_name || '—' }}</dd>
        <dt class="col-5 text-muted">الهاتف</dt>
        <dd class="col-7 mb-0" dir="ltr">{{ visit.parent_phone || '—' }}</dd>
        <dt class="col-5 text-muted">البريد</dt>
        <dd class="col-7 mb-0 text-break">{{ visit.parent_email || '—' }}</dd>
        <dt class="col-5 text-muted">الصف المستهدف</dt>
        <dd class="col-7 mb-0">{{ visit.target_grade || '—' }}</dd>
        <dt class="col-5 text-muted">مسؤول القبول</dt>
        <dd class="col-7 mb-0">{{ visit.assigned_officer || '—' }}</dd>
      </dl>

      <div v-if="visit.notes" class="mb-3">
        <div class="small text-muted mb-1">ملاحظات الزيارة</div>
        <div class="p-2 bg-light rounded-3 small">{{ visit.notes }}</div>
      </div>
      <div v-if="visit.follow_up_notes" class="mb-4">
        <div class="small text-muted mb-1">متابعة</div>
        <div class="p-2 bg-light rounded-3 small">{{ visit.follow_up_notes }}</div>
      </div>

      <div class="mb-4">
        <div class="fw-semibold mb-3">الجدول الزمني</div>
        <div
          v-for="step in timelineSteps"
          :key="step.key"
          class="d-flex gap-3 mb-3"
        >
          <div class="text-center" style="width: 2rem">
            <i :class="['bi fs-5', step.icon, step.done ? step.color : 'text-muted opacity-50']"></i>
          </div>
          <div class="flex-grow-1">
            <div :class="['fw-semibold small', step.done ? '' : 'text-muted']">{{ step.label }}</div>
            <div v-if="step.at" class="text-muted" style="font-size: 0.75rem">
              {{ step.key === 'scheduled' ? formatDate(step.at) : formatDateTime(step.at) }}
            </div>
          </div>
          <i v-if="step.done" class="bi bi-check-lg text-success"></i>
        </div>
      </div>

      <Link
        :href="route('admin.admissions.show', visit.application_id) + '?tab=visits'"
        class="btn btn-primary w-100"
      >
        <i class="bi bi-box-arrow-up-left me-1"></i>
        فتح مساحة القبول
      </Link>
    </div>
  </div>
  <div
    v-if="show && visit"
    class="offcanvas-backdrop fade show"
    @click="emit('close')"
  ></div>
</template>
