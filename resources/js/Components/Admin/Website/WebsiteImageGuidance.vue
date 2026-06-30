<script setup>
import { computed } from 'vue'
import { getImageSpec } from '@/data/website-image-specs'

const props = defineProps({
  specKey: { type: String, required: true },
  compact: { type: Boolean, default: false },
})

const spec = computed(() => getImageSpec(props.specKey))
</script>

<template>
  <div class="website-image-guidance" :class="{ 'website-image-guidance--compact': compact }">
    <div class="row g-2 small text-muted">
      <div :class="compact ? 'col-12' : 'col-md-6'">
        <div><span class="text-dark">Recommended Size:</span> {{ spec.width }} × {{ spec.height }}</div>
        <div><span class="text-dark">Aspect Ratio:</span> {{ spec.aspectRatio }}</div>
      </div>
      <div :class="compact ? 'col-12' : 'col-md-6'">
        <div><span class="text-dark">Accepted Formats:</span> {{ spec.formats }}</div>
        <div><span class="text-dark">Maximum Size:</span> {{ spec.maxMb }} MB</div>
      </div>
      <div class="col-12">
        <div><span class="text-dark">Usage:</span> {{ spec.usage }}</div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.website-image-guidance {
  margin-top: 0.5rem;
  padding: 0.65rem 0.75rem;
  background: var(--bs-light, #f8f9fa);
  border-radius: 0.375rem;
  border: 1px solid var(--bs-border-color, #dee2e6);
}
.website-image-guidance--compact {
  padding: 0.5rem;
}
</style>
