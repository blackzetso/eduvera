<script setup>
/**
 * Full image with safe-zone / crop overlays (percent-based).
 */
defineProps({
  imageUrl: { type: String, required: true },
  zones: { type: Array, default: () => [] },
  maxHeight: { type: String, default: '220px' },
})
</script>

<template>
  <div class="wiszo" :style="{ maxHeight }">
    <img class="wiszo__img" :src="imageUrl" alt="" />
    <div
      v-for="zone in zones"
      :key="zone.id"
      class="wiszo__zone"
      :class="`wiszo__zone--${zone.variant || 'safe'}`"
      :style="{
        left: zone.left + '%',
        top: zone.top + '%',
        width: zone.width + '%',
        height: zone.height + '%',
      }"
      :title="zone.label"
    >
      <span class="wiszo__zone-label">{{ zone.label }}</span>
    </div>
  </div>
</template>

<style scoped>
.wiszo {
  position: relative;
  width: 100%;
  max-width: 520px;
  border-radius: 0.375rem;
  overflow: hidden;
  border: 1px solid var(--bs-border-color, #dee2e6);
  background: #0f172a;
}
.wiszo__img {
  display: block;
  width: 100%;
  height: auto;
  vertical-align: middle;
}
.wiszo__zone {
  position: absolute;
  box-sizing: border-box;
  pointer-events: none;
}
.wiszo__zone--safe {
  border: 2px solid rgba(25, 135, 84, 0.85);
  background: rgba(25, 135, 84, 0.12);
}
.wiszo__zone--crop {
  border: 2px dashed rgba(220, 53, 69, 0.65);
  background: rgba(220, 53, 69, 0.06);
}
.wiszo__zone-label {
  position: absolute;
  left: 0;
  top: 0;
  font-size: 0.62rem;
  font-weight: 700;
  line-height: 1.2;
  padding: 0.15rem 0.35rem;
  background: rgba(15, 23, 42, 0.82);
  color: #fff;
  max-width: 100%;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
</style>
