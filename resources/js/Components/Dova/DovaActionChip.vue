<script setup>
const props = defineProps({
  action: { type: Object, required: true },
  variant: { type: String, default: 'outline' },
})

const emit = defineEmits(['execute'])

const variantClass = {
  primary: 'dova-action-chip--primary',
  outline: 'dova-action-chip--outline',
  ghost: 'dova-action-chip--ghost',
}[props.variant] ?? 'dova-action-chip--outline'
</script>

<template>
  <button
    type="button"
    class="dova-action-chip"
    :class="variantClass"
    @click="emit('execute', action)"
  >
    <i v-if="action.icon" :class="['bi', action.icon, 'dova-action-chip__icon']" aria-hidden="true" />
    <span>{{ action.label }}</span>
    <i class="bi bi-arrow-right-short dova-action-chip__arrow" aria-hidden="true" />
  </button>
</template>

<style scoped>
.dova-action-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.45rem 0.75rem;
  border-radius: 999px;
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1.2;
  cursor: pointer;
  transition: background 0.15s ease, border-color 0.15s ease, transform 0.15s ease;
  border: 1px solid transparent;
  white-space: nowrap;
}

.dova-action-chip:hover {
  transform: translateY(-1px);
}

.dova-action-chip--primary {
  background: #0f4c81;
  color: #fff;
  border-color: #0f4c81;
}

.dova-action-chip--outline {
  background: #fff;
  color: #0f4c81;
  border-color: rgba(0, 180, 219, 0.45);
}

.dova-action-chip--outline:hover {
  background: rgba(0, 180, 219, 0.08);
}

.dova-action-chip--ghost {
  background: rgba(15, 76, 129, 0.06);
  color: #0f4c81;
}

.dova-action-chip__icon {
  font-size: 0.95em;
  opacity: 0.9;
}

.dova-action-chip__arrow {
  font-size: 1.1em;
  opacity: 0.65;
}

[dir='rtl'] .dova-action-chip__arrow {
  transform: scaleX(-1);
}
</style>
