<script setup>
defineProps({
  stickyHeader: { type: Boolean, default: false },
})
</script>

<template>
  <div
    class="eduvera-table-wrap table-responsive"
    :class="{ 'eduvera-table-wrap--sticky-head': stickyHeader }"
  >
    <slot />
  </div>
</template>
