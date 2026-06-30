<script setup>
import { computed, ref } from 'vue'
import VisitOutcomeBadge from './VisitOutcomeBadge.vue'
import {
  buildMonthGrid,
  buildWeekDays,
  visitCalendarCardClass,
  formatTime,
  localDateStr,
} from '@/composables/useVisitCommandCenter'

const props = defineProps({
  visits: { type: Array, default: () => [] },
})

const emit = defineEmits(['select'])

const viewMode = ref('month')
const anchor = ref(new Date())

const weekDayNames = ['أحد', 'إثن', 'ثلا', 'أرب', 'خمي', 'جمع', 'سبت']

const monthLabel = computed(() =>
  anchor.value.toLocaleDateString('ar-EG', { month: 'long', year: 'numeric' })
)

const monthCells = computed(() =>
  buildMonthGrid(anchor.value.getFullYear(), anchor.value.getMonth(), props.visits)
)

const weekDays = computed(() => {
  const dateStr = localDateStr(anchor.value)
  return buildWeekDays(dateStr, props.visits)
})

const dayVisits = computed(() => {
  const dateStr = localDateStr(anchor.value)
  return props.visits
    .filter(v => v.scheduled_date === dateStr)
    .sort((a, b) => (a.scheduled_time || '').localeCompare(b.scheduled_time || ''))
})

function shiftAnchor(delta) {
  const d = new Date(anchor.value)
  if (viewMode.value === 'month') {
    d.setMonth(d.getMonth() + delta)
  } else if (viewMode.value === 'week') {
    d.setDate(d.getDate() + delta * 7)
  } else {
    d.setDate(d.getDate() + delta)
  }
  anchor.value = d
}

function goToday() {
  anchor.value = new Date()
}
</script>

<template>
  <div class="card visit-dashboard-card">
    <div class="card-body">
      <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="btn-group btn-group-sm">
          <button
            type="button"
            class="btn"
            :class="viewMode === 'month' ? 'btn-primary' : 'btn-outline-primary'"
            @click="viewMode = 'month'"
          >
            شهر
          </button>
          <button
            type="button"
            class="btn"
            :class="viewMode === 'week' ? 'btn-primary' : 'btn-outline-primary'"
            @click="viewMode = 'week'"
          >
            أسبوع
          </button>
          <button
            type="button"
            class="btn"
            :class="viewMode === 'day' ? 'btn-primary' : 'btn-outline-primary'"
            @click="viewMode = 'day'"
          >
            يوم
          </button>
        </div>

        <div class="d-flex align-items-center gap-2 ms-md-auto">
          <button type="button" class="btn btn-sm btn-outline-secondary" @click="shiftAnchor(-1)">
            <i class="bi bi-chevron-right"></i>
          </button>
          <button type="button" class="btn btn-sm btn-outline-secondary" @click="goToday">
            اليوم
          </button>
          <button type="button" class="btn btn-sm btn-outline-secondary" @click="shiftAnchor(1)">
            <i class="bi bi-chevron-left"></i>
          </button>
        </div>
      </div>

      <!-- Month -->
      <div v-if="viewMode === 'month'">
        <div class="fw-semibold mb-3 text-center">{{ monthLabel }}</div>
        <div class="visit-calendar-grid mb-2">
          <div
            v-for="name in weekDayNames"
            :key="name"
            class="text-center small text-muted fw-semibold py-1"
          >
            {{ name }}
          </div>
        </div>
        <div class="visit-calendar-grid">
          <div
            v-for="(cell, idx) in monthCells"
            :key="idx"
            class="visit-calendar-day"
            :class="{
              'visit-calendar-day--muted': !cell.date,
              'visit-calendar-day--today': cell.isToday,
            }"
          >
            <div v-if="cell.date" class="small fw-semibold mb-1">{{ cell.day }}</div>
            <div
              v-for="visit in (cell.visits || []).slice(0, 2)"
              :key="visit.id"
              :class="visitCalendarCardClass(visit)"
              class="p-1 mb-1 small"
              role="button"
              @click="emit('select', visit)"
            >
              <div class="text-truncate fw-semibold">{{ visit.applicant_name }}</div>
              <div class="text-muted text-truncate" style="font-size: 0.65rem">
                {{ formatTime(visit.scheduled_time) }}
              </div>
            </div>
            <div
              v-if="cell.visits && cell.visits.length > 2"
              class="text-muted"
              style="font-size: 0.65rem"
            >
              +{{ cell.visits.length - 2 }}
            </div>
          </div>
        </div>
      </div>

      <!-- Week -->
      <div v-else-if="viewMode === 'week'" class="row g-2">
        <div
          v-for="day in weekDays"
          :key="day.date"
          class="col-12 col-md"
        >
          <div
            class="visit-calendar-day h-100"
            :class="{ 'visit-calendar-day--today': day.isToday }"
          >
            <div class="small fw-semibold mb-2">{{ day.label }}</div>
            <div
              v-for="visit in day.visits"
              :key="visit.id"
              :class="visitCalendarCardClass(visit)"
              class="p-2 mb-2"
              role="button"
              @click="emit('select', visit)"
            >
              <div class="fw-semibold small">{{ visit.applicant_name }}</div>
              <div class="text-muted" style="font-size: 0.75rem">{{ visit.parent_name }}</div>
              <div class="d-flex flex-wrap gap-1 mt-1">
                <span class="visit-status-chip bg-light text-dark">{{ formatTime(visit.scheduled_time) }}</span>
                <span class="visit-status-chip bg-secondary">{{ visit.pipeline_stage_label }}</span>
              </div>
              <VisitOutcomeBadge
                v-if="visit.outcome"
                :outcome="visit.outcome"
                :label="visit.outcome_label"
                class="mt-1"
              />
            </div>
            <div v-if="!day.visits.length" class="text-muted small">—</div>
          </div>
        </div>
      </div>

      <!-- Day -->
      <div v-else>
        <div class="fw-semibold mb-3">
          {{ anchor.toLocaleDateString('ar-EG', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) }}
        </div>
        <div v-if="!dayVisits.length" class="text-muted text-center py-5">لا توجد زيارات في هذا اليوم</div>
        <div
          v-for="visit in dayVisits"
          :key="visit.id"
          :class="visitCalendarCardClass(visit)"
          class="p-3 mb-2 d-flex flex-wrap align-items-center gap-3"
          role="button"
          @click="emit('select', visit)"
        >
          <div class="fw-bold text-primary">{{ formatTime(visit.scheduled_time) }}</div>
          <div class="flex-grow-1 min-w-0">
            <div class="fw-semibold">{{ visit.applicant_name }}</div>
            <div class="small text-muted">{{ visit.parent_name }} · {{ visit.pipeline_stage_label }}</div>
          </div>
          <span class="visit-status-chip bg-light text-dark">{{ visit.status_label }}</span>
          <VisitOutcomeBadge :outcome="visit.outcome" :label="visit.outcome_label" />
        </div>
      </div>

      <div class="d-flex flex-wrap gap-3 mt-3 pt-3 border-top small text-muted">
        <span><span class="d-inline-block rounded-circle bg-primary" style="width:0.6rem;height:0.6rem"></span> مطلوبة</span>
        <span><span class="d-inline-block rounded-circle bg-success" style="width:0.6rem;height:0.6rem"></span> حضر</span>
        <span><span class="d-inline-block rounded-circle bg-danger" style="width:0.6rem;height:0.6rem"></span> لم يحضر</span>
        <span><span class="d-inline-block rounded-circle bg-warning" style="width:0.6rem;height:0.6rem"></span> أُعيدت جدولتها</span>
      </div>
    </div>
  </div>
</template>
