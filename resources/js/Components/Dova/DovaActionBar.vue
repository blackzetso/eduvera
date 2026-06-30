<script setup>
import DovaActionChip from '@/Components/Dova/DovaActionChip.vue'

defineProps({
  actions: { type: Array, default: () => [] },
  title: { type: String, default: '' },
  workflow: { type: Array, default: null },
})

const emit = defineEmits(['execute'])
</script>

<template>
  <div v-if="actions.length" class="dova-action-bar">
    <p v-if="title" class="dova-action-bar__title">{{ title }}</p>

    <ul v-if="workflow?.length" class="dova-action-bar__workflow">
      <li v-for="(step, i) in workflow" :key="i">{{ step }}</li>
    </ul>

    <div class="dova-action-bar__chips" role="group" :aria-label="title || 'Dova actions'">
      <DovaActionChip
        v-for="action in actions"
        :key="action.id"
        :action="action"
        :variant="action.variant ?? 'outline'"
        @execute="emit('execute', $event)"
      />
    </div>
  </div>
</template>

<style scoped>
.dova-action-bar {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.dova-action-bar__title {
  margin: 0;
  font-size: 0.75rem;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.dova-action-bar__workflow {
  margin: 0;
  padding-inline-start: 1.1rem;
  font-size: 0.8125rem;
  color: #475569;
}

.dova-action-bar__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}
</style>
