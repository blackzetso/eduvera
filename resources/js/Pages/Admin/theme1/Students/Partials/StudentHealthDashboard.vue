<script setup>
const props = defineProps({
  health: { type: Object, required: true },
})

const emit = defineEmits(['open-tab'])

const cards = [
  { key: 'attendance', tab: 'attendance', icon: 'bi-calendar-check', shortLabel: 'الحضور' },
  { key: 'academic', tab: 'academic', icon: 'bi-journal-bookmark', shortLabel: 'أكاديمي' },
  { key: 'behavior', tab: 'behavior', icon: 'bi-emoji-smile', shortLabel: 'السلوك' },
  { key: 'financial', tab: 'wallet', icon: 'bi-cash-stack', shortLabel: 'مالي' },
  { key: 'wallet', tab: 'wallet', icon: 'bi-wallet2', shortLabel: 'المحفظة' },
  { key: 'enrollment', tab: 'enrollment', icon: 'bi-clock-history', shortLabel: 'القيد' },
]

function cardData(key) {
  return props.health[key] || {}
}

function trendIcon(trend) {
  if (trend === 'up') return '↗'
  if (trend === 'down') return '↘'
  if (trend === 'stable') return '→'
  return ''
}
</script>

<template>
  <div>
    <div class="student-section-title">
      <i class="bi bi-heart-pulse me-1 text-primary"></i>
      مؤشرات الصحة
    </div>

    <div class="row g-2 student-cc-row-tight">
      <div
        v-for="item in cards"
        :key="item.key"
        class="col-6 col-md-4 col-xl-2"
      >
        <button
          type="button"
          class="card student-health-card student-command-card w-100 text-start border-0"
          :class="`student-health-card--${cardData(item.key).status || 'amber'}`"
          @click="emit('open-tab', item.tab)"
        >
          <div class="card-body p-2">
            <div class="d-flex justify-content-between align-items-start mb-1">
              <div
                class="student-health-kpi-value"
                :class="`student-health-kpi-value--${cardData(item.key).status || 'amber'}`"
              >
                {{ cardData(item.key).value || '—' }}
              </div>
              <i :class="['bi text-muted opacity-50', item.icon]" style="font-size: 0.85rem"></i>
            </div>
            <div class="student-health-kpi-label mb-1">{{ item.shortLabel }}</div>
            <div class="student-health-kpi-footer d-flex justify-content-between align-items-center gap-1">
              <span class="badge rounded-pill bg-light text-dark border" style="font-size: 0.6rem">
                {{ cardData(item.key).status_label }}
              </span>
              <span v-if="cardData(item.key).alert_count" class="text-danger fw-bold">
                {{ cardData(item.key).alert_count }}!
              </span>
              <span v-else-if="cardData(item.key).trend_label" class="text-muted">
                {{ trendIcon(cardData(item.key).trend) }} {{ cardData(item.key).trend_label }}
              </span>
            </div>
          </div>
        </button>
      </div>
    </div>
  </div>
</template>
