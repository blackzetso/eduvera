<script setup>
import { computed } from 'vue'
import { computeCoverCrop } from '@/utils/imageSafeZoneCheck'

const props = defineProps({
  imageUrl: { type: String, required: true },
  label: { type: String, required: true },
  aspectRatio: { type: Number, required: true },
  mock: { type: String, default: 'card-landscape' },
  imageDims: { type: Object, default: null },
  maxWidth: { type: String, default: '100%' },
})

const usesContain = computed(() => props.mock?.startsWith('logo-'))

const cropStyle = computed(() => {
  if (usesContain.value) {
    return { objectFit: 'contain', objectPosition: 'center center' }
  }
  const d = props.imageDims
  if (!d?.width || !d?.height) {
    return { objectFit: 'cover', objectPosition: 'center center' }
  }
  const crop = computeCoverCrop(d.width, d.height, props.aspectRatio)
  const posX = crop.offsetXPct + crop.visibleWidthPct / 2
  const posY = crop.offsetYPct + crop.visibleHeightPct / 2
  return { objectFit: 'cover', objectPosition: `${posX}% ${posY}%` }
})
</script>

<template>
  <div class="wifp" :style="{ maxWidth }">
    <div class="wifp__label small text-muted mb-1">{{ label }}</div>
    <div
      class="wifp__frame"
      :class="[`wifp__frame--${mock}`, { 'wifp__frame--circle': mock === 'testimonial-circle' }]"
      :style="{ aspectRatio: aspectRatio }"
    >
      <div v-if="mock === 'testimonial-circle'" class="wifp__circle-viewport">
        <img class="wifp__img" :src="imageUrl" alt="" :style="cropStyle" />
      </div>
      <img v-else class="wifp__img" :src="imageUrl" alt="" :style="cropStyle" />
      <div v-if="mock === 'hero-bg-desktop'" class="wifp__mock wifp__mock--hero-desktop">
        <span class="wifp__mock-pill">Headline area</span>
        <span class="wifp__mock-visual" />
      </div>
      <div v-else-if="mock === 'hero-bg-tablet'" class="wifp__mock wifp__mock--hero-tablet">
        <span class="wifp__mock-pill">Text</span>
      </div>
      <div v-else-if="mock === 'hero-bg-mobile'" class="wifp__mock wifp__mock--hero-mobile">
        <span class="wifp__mock-pill">Stacked layout</span>
      </div>
      <div v-else-if="mock === 'hero-visual'" class="wifp__mock wifp__mock--hero-visual">
        <span class="wifp__mock-badge">Badge</span>
      </div>
      <div v-else-if="mock === 'stage-card'" class="wifp__mock wifp__mock--stage-card">
        <span class="wifp__mock-stage-title">Stage name</span>
        <span class="wifp__mock-stage-meta">Ages · Programs</span>
      </div>
      <div v-else-if="mock === 'stage-modal'" class="wifp__mock wifp__mock--stage-modal">
        <span class="wifp__mock-stage-title">Stage detail title</span>
      </div>
      <div v-else-if="mock === 'news-card' || mock === 'event-card'" class="wifp__mock wifp__mock--card-body">
        <span class="wifp__mock-line wifp__mock-line--short" />
        <span class="wifp__mock-line" />
      </div>
      <div v-else-if="mock === 'news-article'" class="wifp__mock wifp__mock--article">
        <span class="wifp__mock-line wifp__mock-line--title" />
      </div>
      <div v-else-if="mock === 'testimonial-circle'" class="wifp__mock wifp__mock--testimonial">
        <span class="wifp__mock-avatar-ring" />
        <span class="wifp__mock-line wifp__mock-line--short" />
      </div>
      <div v-else-if="mock === 'testimonial-square'" class="wifp__mock wifp__mock--testimonial-square">
        <span class="wifp__mock-avatar-square" />
      </div>
      <div v-else-if="mock === 'logo-header'" class="wifp__mock wifp__mock--logo-header">
        <span class="wifp__mock-nav" />
      </div>
      <div v-else-if="mock === 'logo-footer'" class="wifp__mock wifp__mock--logo-footer">
        <span class="wifp__mock-logo-sm">Logo</span>
      </div>
      <div v-else-if="mock === 'portrait-block'" class="wifp__mock wifp__mock--portrait-text">
        <span class="wifp__mock-line" />
        <span class="wifp__mock-line wifp__mock-line--short" />
      </div>
    </div>
  </div>
</template>

<style scoped>
.wifp__frame {
  position: relative;
  width: 100%;
  border-radius: 0.375rem;
  overflow: hidden;
  border: 1px solid var(--bs-border-color, #dee2e6);
  background: #e2e8f0;
}
.wifp__frame--circle {
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
}
.wifp__circle-viewport {
  width: 42%;
  aspect-ratio: 1;
  border-radius: 50%;
  overflow: hidden;
  border: 3px solid rgba(13, 110, 253, 0.35);
  box-shadow: 0 6px 16px rgba(15, 23, 42, 0.12);
  position: relative;
  z-index: 1;
}
.wifp__circle-viewport .wifp__img {
  position: absolute;
  inset: 0;
}
.wifp__frame--logo-header .wifp__img,
.wifp__frame--logo-footer .wifp__img {
  padding: 10%;
  box-sizing: border-box;
}
.wifp__img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.wifp__mock {
  position: absolute;
  inset: 0;
  pointer-events: none;
  z-index: 2;
}
.wifp__mock--hero-desktop {
  display: grid;
  grid-template-columns: 1fr 1fr;
  padding: 8%;
  gap: 8%;
}
.wifp__mock-pill {
  align-self: start;
  font-size: 0.55rem;
  font-weight: 700;
  padding: 0.2rem 0.45rem;
  border-radius: 1rem;
  background: rgba(255, 255, 255, 0.92);
  color: #0d6efd;
  width: fit-content;
}
.wifp__mock-visual {
  border-radius: 0.5rem;
  border: 2px dashed rgba(255, 255, 255, 0.7);
  background: rgba(255, 255, 255, 0.15);
}
.wifp__mock--hero-tablet,
.wifp__mock--hero-mobile {
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  padding: 10%;
  background: linear-gradient(transparent 30%, rgba(15, 23, 42, 0.35) 100%);
}
.wifp__mock--hero-visual .wifp__mock-badge {
  position: absolute;
  bottom: 12%;
  right: 8%;
  font-size: 0.5rem;
  padding: 0.2rem 0.4rem;
  border-radius: 1rem;
  background: #fff;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}
.wifp__mock--stage-card {
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  padding: 10%;
  background: linear-gradient(transparent 25%, rgba(12, 31, 61, 0.88) 100%);
  color: #fff;
}
.wifp__mock-stage-title {
  font-size: 0.65rem;
  font-weight: 800;
}
.wifp__mock-stage-meta {
  font-size: 0.5rem;
  opacity: 0.85;
}
.wifp__mock--stage-modal {
  display: flex;
  align-items: flex-end;
  padding: 8%;
  background: linear-gradient(transparent, rgba(12, 31, 61, 0.85));
  color: #fff;
}
.wifp__mock--card-body {
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  gap: 0.25rem;
  padding: 8%;
  margin-top: 55%;
  background: linear-gradient(transparent, #fff 35%);
}
.wifp__mock-line {
  display: block;
  height: 0.35rem;
  border-radius: 2px;
  background: rgba(15, 23, 42, 0.12);
  width: 70%;
}
.wifp__mock-line--short {
  width: 40%;
}
.wifp__mock-line--title {
  height: 0.5rem;
  width: 55%;
  background: rgba(255, 255, 255, 0.9);
}
.wifp__mock--article {
  display: flex;
  align-items: flex-end;
  padding: 6%;
  background: linear-gradient(transparent 40%, rgba(15, 23, 42, 0.5));
}
.wifp__mock--testimonial {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  background: rgba(255, 255, 255, 0.75);
}
.wifp__mock-avatar-ring {
  width: 28%;
  aspect-ratio: 1;
  border-radius: 50%;
  border: 2px solid rgba(13, 110, 253, 0.5);
  box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.9);
  margin-top: 8%;
}
.wifp__mock--testimonial-square .wifp__mock-avatar-square {
  position: absolute;
  top: 8%;
  left: 8%;
  width: 22%;
  aspect-ratio: 1;
  border-radius: 0.25rem;
  border: 2px solid #0d6efd;
}
.wifp__mock--logo-header {
  display: flex;
  align-items: center;
  padding: 0 8%;
  background: rgba(255, 255, 255, 0.92);
  min-height: 28%;
}
.wifp__mock-nav {
  margin-left: auto;
  width: 35%;
  height: 0.35rem;
  border-radius: 2px;
  background: rgba(15, 23, 42, 0.1);
}
.wifp__mock--logo-footer {
  display: flex;
  align-items: center;
  padding: 8%;
  background: rgba(12, 31, 61, 0.88);
}
.wifp__mock-logo-sm {
  font-size: 0.55rem;
  color: #fff;
  font-weight: 700;
}
.wifp__mock--portrait-text {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 0.3rem;
  padding: 8%;
  margin-left: 45%;
  background: rgba(255, 255, 255, 0.88);
}
</style>
