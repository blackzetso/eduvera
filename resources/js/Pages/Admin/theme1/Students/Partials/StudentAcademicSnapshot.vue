<script setup>
import { computed } from 'vue'

const props = defineProps({
  classInfo: { type: Object, default: null },
  grades: { type: Object, required: true },
  overview: { type: Object, required: true },
  academicHealth: { type: Object, default: null },
  formatDate: { type: Function, required: true },
})

const emit = defineEmits(['open-tab'])

const recentGrades = computed(() => (props.grades.items || []).slice(0, 3))

const attendanceRate = computed(() => props.overview.attendance_rate_percent)
const gradeAverage = computed(() => props.grades.average_percent)

const trendLabel = computed(() => props.academicHealth?.trend_label || null)
const trendIcon = computed(() => {
  const t = props.academicHealth?.trend
  if (t === 'up') return '↗'
  if (t === 'down') return '↘'
  if (t === 'stable') return '→'
  return ''
})
</script>

<template>
  <div class="card student-command-card border-0 shadow-sm h-100">
    <div class="card-body p-2 p-md-3">
      <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
        <div class="student-section-title mb-0">
          <i class="bi bi-journal-bookmark me-1 text-primary"></i>
          أكاديمي
        </div>
        <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 small" @click="emit('open-tab', 'academic')">
          التفاصيل
        </button>
      </div>

      <div class="row g-2 mb-2 student-cc-row-tight">
        <div class="col-3">
          <div class="student-snapshot-kpi student-snapshot-kpi--highlight h-100">
            <div class="student-snapshot-kpi__value text-primary">
              {{ attendanceRate != null ? `${attendanceRate}%` : '—' }}
            </div>
            <div class="student-snapshot-kpi__label">حضور</div>
          </div>
        </div>
        <div class="col-3">
          <div class="student-snapshot-kpi student-snapshot-kpi--highlight h-100">
            <div class="student-snapshot-kpi__value">
              {{ gradeAverage != null ? `${gradeAverage}%` : '—' }}
            </div>
            <div class="student-snapshot-kpi__label">متوسط</div>
          </div>
        </div>
        <div class="col-3">
          <div class="student-snapshot-kpi h-100">
            <div class="student-snapshot-kpi__value small text-truncate" style="font-size: 0.85rem">
              {{ classInfo?.path_label || '—' }}
            </div>
            <div class="student-snapshot-kpi__label">الفئة</div>
          </div>
        </div>
        <div class="col-3">
          <div class="student-snapshot-kpi h-100">
            <div class="student-snapshot-kpi__value small text-truncate" style="font-size: 0.85rem">
              {{ classInfo?.name || '—' }}
            </div>
            <div class="student-snapshot-kpi__label">المسار</div>
          </div>
        </div>
      </div>

      <div v-if="trendLabel" class="small text-muted mb-1">
        <span class="fw-semibold">{{ trendIcon }} {{ trendLabel }}</span>
      </div>

      <div class="text-muted small mb-1" style="font-size: 0.65rem">آخر التقييمات</div>
      <ul v-if="recentGrades.length" class="list-unstyled mb-0 small">
        <li v-for="g in recentGrades" :key="g.id" class="d-flex justify-content-between py-0 border-bottom border-opacity-25">
          <span class="text-truncate me-2">{{ g.title }}</span>
          <span class="fw-bold text-nowrap">{{ g.percentage }}%</span>
        </li>
      </ul>
      <p v-else class="text-muted small mb-0">لا توجد تقييمات</p>
    </div>
  </div>
</template>
