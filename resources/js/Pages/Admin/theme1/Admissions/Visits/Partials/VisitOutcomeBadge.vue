<script setup>
import { visitOutcomeBadge } from '@/Shared/admissionsBadges'

defineProps({
  outcome: { type: String, default: '' },
  label: { type: String, default: '' },
})
</script>

<template>
  <span
    v-if="outcome"
    class="visit-status-chip"
    :class="visitOutcomeBadge(outcome)"
  >
    {{ label || outcome }}
  </span>
  <span v-else class="text-muted small">—</span>
</template>
