<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  days: Array,
  periods: Array,
  onCellClick: Function,
  showAssignments: {
    type: Boolean,
    default: true
  },
  readonly: {
    type: Boolean,
    default: false
  }
})

const MAX_VISIBLE_PERIODS = 3

function timeToMinutes(time) {
  const [hours, minutes] = time.split(':').map(Number)
  return hours * 60 + minutes
}

function minutesToTime(minutes) {
  const hours = Math.floor(minutes / 60)
  const mins = minutes % 60
  return `${String(hours).padStart(2, '0')}:${String(mins).padStart(2, '0')}`
}

const MINUTE_INTERVAL = 5

const hourBlocks = computed(() => {
  if (!props.periods || props.periods.length === 0) {
    return []
  }

  let minTime = null
  let maxTime = null

  props.periods.forEach(period => {
    const start = timeToMinutes(period.time_from)
    const end = timeToMinutes(period.time_to)
    if (minTime === null || start < minTime) minTime = start
    if (maxTime === null || end > maxTime) maxTime = end
  })

  if (minTime === null || maxTime === null) return []

  minTime = Math.floor(minTime / MINUTE_INTERVAL) * MINUTE_INTERVAL
  maxTime = Math.ceil(maxTime / MINUTE_INTERVAL) * MINUTE_INTERVAL

  const allSlots = []
  for (let slotMinutes = minTime; slotMinutes < maxTime; slotMinutes += MINUTE_INTERVAL) {
    allSlots.push({
      time: minutesToTime(slotMinutes),
      minutes: slotMinutes,
      displayTime: minutesToTime(slotMinutes)
    })
  }

  const blocks = []
  let currentBlock = null

  allSlots.forEach(slot => {
    const hour = Math.floor(slot.minutes / 60)
    const blockStart = hour * 60

    if (!currentBlock || currentBlock.startMinutes !== blockStart) {
      if (currentBlock) blocks.push(currentBlock)
      currentBlock = {
        startTime: minutesToTime(blockStart),
        endTime: minutesToTime(blockStart + 60),
        startMinutes: blockStart,
        endMinutes: blockStart + 60,
        minuteSlots: [],
        rowspan: 12
      }
    }

    currentBlock.minuteSlots.push(slot)
  })

  if (currentBlock) blocks.push(currentBlock)

  return blocks
})

const allMinuteSlots = computed(() => {
  const slots = []
  hourBlocks.value.forEach(block => {
    block.minuteSlots.forEach(slot => {
      slots.push({ ...slot, hourBlock: block })
    })
  })
  return slots
})

const dayTimeRange = computed(() => {
  if (hourBlocks.value.length === 0) return { min: 480, max: 1020 }
  return {
    min: hourBlocks.value[0].startMinutes,
    max: hourBlocks.value[hourBlocks.value.length - 1].endMinutes
  }
})

function isFirstMinuteInHour(slot) {
  return slot.minutes % 60 === 5
}

function isLastMinuteInHour(slot) {
  return slot.minutes % 60 === 0
}

const periodsByDay = computed(() => {
  const grouped = {}
  props.days?.forEach(day => { grouped[day.id] = [] })
  props.periods?.forEach(period => {
    if (grouped[period.timetable_day_id]) {
      grouped[period.timetable_day_id].push(period)
    }
  })
  return grouped
})

function getPeriodsForMinuteSlot(dayId, slotMinutes) {
  const periods = periodsByDay.value[dayId] || []
  return periods.filter(period => {
    const startMinutes = timeToMinutes(period.time_from)
    return startMinutes >= slotMinutes && startMinutes < slotMinutes + MINUTE_INTERVAL
  })
}

function getPeriodRowspan(period) {
  const duration = timeToMinutes(period.time_to) - timeToMinutes(period.time_from)
  return Math.ceil(duration / MINUTE_INTERVAL)
}

function getPeriodRowspanForSlot(dayId, slotMinutes) {
  const periods = getPeriodsForMinuteSlot(dayId, slotMinutes)
  if (periods.length === 0) return 1
  return Math.max(...periods.map(p => getPeriodRowspan(p)))
}

function isCellCoveredByPeriod(dayId, slotMinutes) {
  const periods = periodsByDay.value[dayId] || []
  return periods.some(period => {
    const startMinutes = timeToMinutes(period.time_from)
    const endMinutes = timeToMinutes(period.time_to)
    return slotMinutes > startMinutes && slotMinutes < endMinutes
  })
}

function getOverlappingPeriods(dayId, slotMinutes) {
  const periods = periodsByDay.value[dayId] || []
  return periods.filter(period => {
    const startMinutes = timeToMinutes(period.time_from)
    const endMinutes = timeToMinutes(period.time_to)
    return startMinutes <= slotMinutes && endMinutes > slotMinutes
  })
}

function handleCellClick(day, timeSlot) {
  if (!props.readonly && props.onCellClick) {
    props.onCellClick(day, timeSlot)
  }
}

function formatTime(time) {
  return time.substring(0, 5)
}

function openPeriodsPage(day, periods, time) {
  const periodIds = periods.map(p => p.id).join(',')
  const url = route('admin.timetable.periods.list') +
    `?day_id=${day.id}` +
    (time ? `&time=${encodeURIComponent(time)}` : '') +
    `&period_ids=${periodIds}`
  router.visit(url)
}

function getHourColorClass(minuteSlot) {
  const hour = Math.floor(minuteSlot.minutes / 60)
  const colors = ['hour-bg-1', 'hour-bg-2', 'hour-bg-3', 'hour-bg-4',
                  'hour-bg-5', 'hour-bg-6', 'hour-bg-7', 'hour-bg-8']
  return colors[hour % colors.length]
}
</script>

<template>
  <div class="calendar-view" :class="{ 'calendar-readonly': readonly }">
    <div v-if="hourBlocks.length === 0" class="text-center text-muted py-5">
      لا توجد حصص. يرجى إضافة حصص لعرض الجدول.
    </div>
    <div v-else class="table-responsive">
      <table class="table table-bordered calendar-table">
        <thead>
          <tr>
            <th v-for="day in days" :key="day.id" class="day-column">
              {{ day.day_name }}
            </th>
          </tr>
        </thead>
        <tbody>
          <template v-for="(block, blockIndex) in hourBlocks" :key="block.startTime">
            <tr
              v-for="(minuteSlot, slotIndex) in block.minuteSlots"
              :key="`${block.startTime}-${minuteSlot.minutes}`"
              class="time-row"
              :class="getHourColorClass(minuteSlot)"
            >
              <template v-for="day in days" :key="day.id">
                <td
                  v-if="!isCellCoveredByPeriod(day.id, minuteSlot.minutes)"
                  class="period-cell"
                  :rowspan="getPeriodRowspanForSlot(day.id, minuteSlot.minutes)"
                  @click="handleCellClick(day, minuteSlot.time)"
                >
                  <!--
                    READONLY mode: flex row — each card gets a fixed width so text never
                    wraps into a single character. The row scrolls horizontally when there
                    are many periods.
                  -->
                  <div
                    v-if="readonly"
                    class="periods-row-readonly"
                    :style="{ minHeight: `${getPeriodRowspanForSlot(day.id, minuteSlot.minutes) * 25}px` }"
                  >
                    <div
                      v-for="p in getPeriodsForMinuteSlot(day.id, minuteSlot.minutes)"
                      :key="p.id"
                      class="period-item-readonly"
                      :class="{ 'has-assignment': showAssignments && p.assignments && p.assignments.length > 0 }"
                    >
                      <div class="period-time">{{ formatTime(p.time_from) }} - {{ formatTime(p.time_to) }}</div>
                      <div v-if="p.category" class="period-category">{{ p.category.name }}</div>
                      <div v-if="showAssignments && p.assignments && p.assignments.length > 0" class="period-assignment">
                        <div v-for="assignment in p.assignments" :key="assignment.id">
                          <strong>{{ assignment.teacher?.name }}</strong>
                          <br>
                          <small>{{ assignment.subject?.name }}</small>
                        </div>
                      </div>
                      <div v-else class="period-empty">غير معين</div>
                    </div>
                  </div>

                  <!--
                    INTERACTIVE mode: absolute positioning with MAX_VISIBLE_PERIODS cap
                    and "المزيد" navigation button.
                  -->
                  <div v-else class="periods-container-absolute">
                    <template v-for="(p, idx) in getPeriodsForMinuteSlot(day.id, minuteSlot.minutes)" :key="p.id">
                      <div
                        v-if="idx < MAX_VISIBLE_PERIODS"
                        class="period-item-absolute"
                        :class="{
                          'has-assignment': showAssignments && p.assignments && p.assignments.length > 0
                        }"
                        :style="getPeriodsForMinuteSlot(day.id, minuteSlot.minutes).length > MAX_VISIBLE_PERIODS
                          ? {
                              left: `calc(${idx} * (100% - 56px) / ${MAX_VISIBLE_PERIODS})`,
                              width: `calc((100% - 56px) / ${MAX_VISIBLE_PERIODS})`,
                              height: `${getPeriodRowspan(p) * 25}px`,
                              top: '0'
                            }
                          : {
                              left: `${idx * (100 / Math.min(getPeriodsForMinuteSlot(day.id, minuteSlot.minutes).length, MAX_VISIBLE_PERIODS))}%`,
                              width: `${100 / Math.min(getPeriodsForMinuteSlot(day.id, minuteSlot.minutes).length, MAX_VISIBLE_PERIODS)}%`,
                              height: `${getPeriodRowspan(p) * 25}px`,
                              top: '0'
                            }
                        "
                        @click.stop="onCellClick && onCellClick(day, p.time_from)"
                      >
                        <div class="period-info">
                          <div class="period-time">{{ formatTime(p.time_from) }} - {{ formatTime(p.time_to) }}</div>
                          <div v-if="p.category" class="period-category">{{ p.category.name }}</div>
                          <div v-if="showAssignments && p.assignments && p.assignments.length > 0" class="period-assignment">
                            <div v-for="assignment in p.assignments" :key="assignment.id">
                              <strong>{{ assignment.teacher?.name }}</strong>
                              <br>
                              <small>{{ assignment.subject?.name }}</small>
                            </div>
                          </div>
                          <div v-else class="period-empty">غير معين</div>
                        </div>
                      </div>
                    </template>

                    <div
                      v-if="getPeriodsForMinuteSlot(day.id, minuteSlot.minutes).length > MAX_VISIBLE_PERIODS"
                      class="period-item-absolute more-periods-btn"
                      :style="{
                        left: 'calc(100% - 56px)',
                        width: '56px',
                        height: `${getPeriodRowspanForSlot(day.id, minuteSlot.minutes) * 25}px`,
                        top: '0'
                      }"
                      @click.stop="openPeriodsPage(day, getPeriodsForMinuteSlot(day.id, minuteSlot.minutes), minuteSlot.time)"
                    >
                      <div class="period-info text-center">
                        <div class="more-text">+ المزيد</div>
                        <div class="more-count">({{ getPeriodsForMinuteSlot(day.id, minuteSlot.minutes).length - MAX_VISIBLE_PERIODS }})</div>
                      </div>
                    </div>
                  </div>
                </td>
              </template>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.calendar-view {
  width: 100%;
  overflow-x: auto;
}

.calendar-table {
  width: auto;
  min-width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
  table-layout: fixed;
}

.time-row {
  height: 25px;
  min-height: 25px;
}

.day-column {
  min-width: 220px;
  width: 220px;
  background-color: #e9ecef;
  font-weight: bold;
  text-align: center;
}

.period-cell {
  padding: 0;
  cursor: pointer;
  vertical-align: top;
  border: 1px solid #dee2e6;
  transition: background-color 0.2s;
  position: relative;
  min-height: 25px;
  height: 25px;
}

.period-cell:hover {
  background-color: #f0f0f0;
}

/* ── Readonly flex layout ─────────────────────────────── */
.periods-row-readonly {
  display: flex;
  flex-wrap: nowrap;
  overflow-x: auto;
  width: 100%;
  gap: 2px;
  padding: 1px;
  box-sizing: border-box;
}

.period-item-readonly {
  flex: 0 0 150px;
  min-width: 150px;
  padding: 4px 6px;
  border-radius: 4px;
  background-color: #fff;
  border: 1px solid #dee2e6;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  font-size: 0.8rem;
  box-sizing: border-box;
}

.period-item-readonly.has-assignment {
  background-color: #d4edda;
  border-color: #c3e6cb;
}

/* Readonly mode — disable pointer interactions on the cell */
.calendar-readonly .period-cell {
  cursor: default;
}

.calendar-readonly .period-cell:hover {
  background-color: inherit;
}

/* ── Interactive absolute layout ─────────────────────── */
.periods-container-absolute {
  position: relative;
  width: 100%;
  height: 100%;
  min-height: 25px;
}

.period-item-absolute {
  position: absolute;
  margin: 1px;
  padding: 4px 6px;
  border-radius: 4px;
  background-color: #fff;
  border: 1px solid #dee2e6;
  display: flex;
  flex-direction: column;
  justify-content: flex-start;
  cursor: pointer;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  font-size: 0.8rem;
  min-height: 40px;
  box-sizing: border-box;
}

.period-item-absolute.has-assignment {
  background-color: #d4edda;
  border-color: #c3e6cb;
}

.more-periods-btn {
  background-color: #e9ecef !important;
  border-color: #ced4da !important;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.more-periods-btn:hover {
  background-color: #dee2e6 !important;
}

/* ── Shared text styles ──────────────────────────────── */
.period-info {
  font-size: 0.85rem;
}

.period-time {
  font-weight: bold;
  color: #495057;
  margin-bottom: 4px;
}

.period-category {
  color: #6c757d;
  font-size: 0.8rem;
  margin-bottom: 4px;
}

.period-assignment {
  margin-top: 4px;
}

.period-assignment strong {
  color: #155724;
  font-size: 0.85rem;
}

.period-assignment small {
  color: #6c757d;
  font-size: 0.75rem;
}

.period-empty {
  color: #6c757d;
  font-style: italic;
  font-size: 0.8rem;
}

.more-text {
  font-weight: bold;
  color: #495057;
  font-size: 0.85rem;
  text-align: center;
}

.more-count {
  font-size: 0.75rem;
  color: #6c757d;
  text-align: center;
  margin-top: 2px;
}

/* ── Hour background colors ─────────────────────────── */
.hour-bg-1 { background-color: #e3f2fd !important; }
.hour-bg-2 { background-color: #e8f5e8 !important; }
.hour-bg-3 { background-color: #fff9c4 !important; }
.hour-bg-4 { background-color: #f3e5f5 !important; }
.hour-bg-5 { background-color: #fce4ec !important; }
.hour-bg-6 { background-color: #fff3e0 !important; }
.hour-bg-7 { background-color: #e0f2f1 !important; }
.hour-bg-8 { background-color: #f5f5f5 !important; }

.time-row.hour-bg-1 .period-cell,
.time-row.hour-bg-2 .period-cell,
.time-row.hour-bg-3 .period-cell,
.time-row.hour-bg-4 .period-cell,
.time-row.hour-bg-5 .period-cell,
.time-row.hour-bg-6 .period-cell,
.time-row.hour-bg-7 .period-cell,
.time-row.hour-bg-8 .period-cell {
  background-color: inherit;
}
</style>
