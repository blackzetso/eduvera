<script setup>
import { ref, watch } from 'vue'
import { computed } from 'vue'
import WebsiteImageGuidance from '@/Components/Admin/Website/WebsiteImageGuidance.vue'
import WebsiteImageUsagePreviewPanel from '@/Components/Admin/Website/WebsiteImageUsagePreviewPanel.vue'
import { getImageSpec } from '@/data/website-image-specs'
import { hasUsagePreview } from '@/data/website-image-preview-profiles'
import { analyzeImageUrl } from '@/utils/imageDimensionCheck'

const props = defineProps({
  specKey: { type: String, required: true },
  modelValue: { type: String, default: '' },
  label: { type: String, default: '' },
  placeholder: { type: String, default: 'https://...' },
  hint: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])

const spec = getImageSpec(props.specKey)
const showUsagePreview = computed(() => hasUsagePreview(props.specKey))
const urlDims = ref(null)
const dimensionWarning = ref(null)
const previewError = ref(false)

async function checkUrl(url) {
  previewError.value = false
  dimensionWarning.value = null
  urlDims.value = null
  if (!url?.trim()) return

  const result = await analyzeImageUrl(url.trim(), props.specKey)
  if (!result.dims) {
    previewError.value = true
    return
  }
  urlDims.value = result.dims
  dimensionWarning.value = result.dimCheck.message
}

watch(
  () => props.modelValue,
  (v) => checkUrl(v),
  { immediate: true }
)

function onInput(event) {
  emit('update:modelValue', event.target.value)
}
</script>

<template>
  <div class="website-image-url-field mb-2">
    <label v-if="label" class="form-label">{{ label }}</label>
    <input
      type="url"
      class="form-control"
      :value="modelValue"
      :placeholder="placeholder"
      @input="onInput"
      @blur="checkUrl(modelValue)"
    />
    <div v-if="hint" class="form-text">{{ hint }}</div>
    <WebsiteImageGuidance :spec-key="specKey" class="mt-2" compact />

    <div v-if="modelValue && !previewError && urlDims" class="small mt-2">
      <span class="text-muted">Image:</span>
      <strong>{{ urlDims.width }} × {{ urlDims.height }}</strong>
      <span class="text-muted ms-2">Recommended: {{ spec.width }} × {{ spec.height }}</span>
    </div>

    <WebsiteImageUsagePreviewPanel
      v-if="showUsagePreview && modelValue && !previewError"
      :image-url="modelValue"
      :spec-key="specKey"
      :image-dims="urlDims"
    />

    <div v-else-if="modelValue && !previewError" class="mt-2">
      <div class="website-image-url-field__preview">
        <img :src="modelValue" alt="" @error="previewError = true" />
      </div>
    </div>

    <div v-if="dimensionWarning" class="alert alert-warning py-2 small mt-2 mb-0">{{ dimensionWarning }}</div>
  </div>
</template>

<style scoped>
.website-image-url-field__preview {
  max-width: 280px;
  border-radius: 0.375rem;
  overflow: hidden;
  border: 1px solid var(--bs-border-color, #dee2e6);
}
.website-image-url-field__preview img {
  display: block;
  width: 100%;
  max-height: 160px;
  object-fit: cover;
}
</style>
