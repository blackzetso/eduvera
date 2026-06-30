<script setup>
const props = defineProps({
  health: { type: Object, required: true },
})

const emit = defineEmits(['open-tab'])

const cards = [
  { key: 'active_students', tab: 'children', icon: 'bi-people' },
  { key: 'attendance_average', tab: 'attendance', icon: 'bi-calendar-check' },
  { key: 'outstanding_balance', tab: 'finance', icon: 'bi-cash-stack' },
  { key: 'open_risks', tab: 'overview', icon: 'bi-shield-exclamation' },
  { key: 'wallet_balance', tab: 'finance', icon: 'bi-wallet2' },
  { key: 'pending_tasks', tab: 'documents', icon: 'bi-list-check' },
]
</script>

<template>
  <div class="mb-4">
    <h6 class="fw-bold mb-3">
      <i class="bi bi-heart-pulse me-1 text-primary"></i>
      صحة العائلة
    </h6>
    <div class="row g-3">
      <div v-for="item in cards" :key="item.key" class="col-sm-6 col-xl-4">
        <button
          type="button"
          class="card family-health-card family-command-card w-100 text-start rounded-4"
          :class="`family-health-card--${(health[item.key] || {}).status || 'amber'}`"
          @click="emit('open-tab', item.tab)"
        >
          <div class="card-body p-3">
            <div class="d-flex justify-content-between mb-2">
              <span class="d-flex align-items-center gap-2 small fw-semibold">
                <span :class="['family-health-indicator', `family-health-indicator--${(health[item.key] || {}).status || 'amber'}`]"></span>
                {{ (health[item.key] || {}).label }}
              </span>
              <i :class="['bi text-muted', item.icon]"></i>
            </div>
            <div class="fw-bold fs-5 mb-1">{{ (health[item.key] || {}).value || '—' }}</div>
            <div class="small text-muted">{{ (health[item.key] || {}).summary }}</div>
            <div v-if="(health[item.key] || {}).alert_count" class="small text-danger mt-1">
              {{ (health[item.key] || {}).alert_count }} تنبيه
            </div>
          </div>
        </button>
      </div>
    </div>
  </div>
</template>
