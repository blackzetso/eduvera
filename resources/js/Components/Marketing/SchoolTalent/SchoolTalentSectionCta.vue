<script setup>
defineProps({
  ctas: { type: Array, default: () => [] },
  align: { type: String, default: 'center' },
})
</script>

<template>
  <div v-if="ctas.length" class="st-section-cta" :class="`st-section-cta--${align}`">
    <a
      v-for="c in ctas"
      :key="c.href + c.label"
      :href="c.href"
      class="st-btn st-btn--sm"
      :class="
        c.variant === 'gold' || c.variant === 'primary'
          ? 'st-btn--primary'
          : c.variant === 'outline-light'
            ? 'st-btn--outline-light'
            : 'st-btn--outline'
      "
    >
      {{ c.label }}
    </a>
  </div>
</template>
