<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import WebsiteImageGuidance from '@/Components/Admin/Website/WebsiteImageGuidance.vue'
import WebsiteImageUsagePreviewPanel from '@/Components/Admin/Website/WebsiteImageUsagePreviewPanel.vue'
import { getImageSpec } from '@/data/website-image-specs'
import { hasUsagePreview } from '@/data/website-image-preview-profiles'
import { analyzeImageFile, analyzeImageUrl } from '@/utils/imageDimensionCheck'

const props = defineProps({
  specKey: { type: String, required: true },
  modelValue: { type: [Object, File], default: null },
  existingUrl: { type: String, default: '' },
  label: { type: String, default: '' },
  inputClass: { type: String, default: 'form-control' },
  showGuidance: { type: Boolean, default: true },
})

const emit = defineEmits(['update:modelValue'])

const spec = computed(() => getImageSpec(props.specKey))
const showUsagePreview = computed(() => hasUsagePreview(props.specKey))
const activePreviewUrl = computed(() => previewUrl.value || props.existingUrl || '')
const activeDims = computed(() => previewUrl.value ? uploadedDims.value : existingDims.value)
const inputRef = ref(null)
const previewUrl = ref('')
const uploadedDims = ref(null)
const existingDims = ref(null)
const dimensionWarning = ref(null)
const sizeWarning = ref(null)
let objectUrl = null

function revokeObjectUrl() {
  if (objectUrl) {
    URL.revokeObjectURL(objectUrl)
    objectUrl = null
  }
}

async function inspectExisting(url) {
  if (!url) {
    existingDims.value = null
    return
  }
  const result = await analyzeImageUrl(url, props.specKey)
  existingDims.value = result.dims
}

async function inspectFile(file) {
  revokeObjectUrl()
  dimensionWarning.value = null
  sizeWarning.value = null
  uploadedDims.value = null
  previewUrl.value = ''

  if (!file) return

  const analysis = await analyzeImageFile(file, props.specKey)
  uploadedDims.value = analysis.dims
  dimensionWarning.value = analysis.dimCheck.message
  sizeWarning.value = analysis.sizeWarning

  objectUrl = URL.createObjectURL(file)
  previewUrl.value = objectUrl
}

function onFileChange(event) {
  const file = event.target.files?.[0] ?? null
  emit('update:modelValue', file)
  inspectFile(file)
}

watch(
  () => props.existingUrl,
  (url) => inspectExisting(url),
  { immediate: true }
)

watch(
  () => props.modelValue,
  async (file) => {
    if (file instanceof File) {
      await inspectFile(file)
    } else if (!file) {
      revokeObjectUrl()
      previewUrl.value = ''
      uploadedDims.value = null
      dimensionWarning.value = null
      sizeWarning.value = null
    }
  }
)

onBeforeUnmount(revokeObjectUrl)
</script>

<template>
  <div class="website-image-upload-field mb-3">
    <label v-if="label" class="form-label">{{ label }}</label>
    <input
      ref="inputRef"
      type="file"
      :class="inputClass"
      accept="image/jpeg,image/png,image/webp,image/gif,image/*"
      @change="onFileChange"
    />
    <WebsiteImageGuidance v-if="showGuidance" :spec-key="specKey" class="mt-2" />

    <div v-if="activePreviewUrl && activeDims" class="small mt-2">
      <span class="text-muted">{{ previewUrl ? 'Uploaded' : 'Current' }}:</span>
      <strong>{{ activeDims.width }} × {{ activeDims.height }}</strong>
      <span class="text-muted ms-2">Recommended: {{ spec.width }} × {{ spec.height }}</span>
    </div>

    <WebsiteImageUsagePreviewPanel
      v-if="showUsagePreview && activePreviewUrl"
      :image-url="activePreviewUrl"
      :spec-key="specKey"
      :image-dims="activeDims"
    />

    <div v-else-if="activePreviewUrl" class="mt-3">
      <div class="small text-muted mb-1">{{ previewUrl ? 'Preview (new upload)' : 'Current image' }}</div>
      <div class="website-image-upload-field__preview">
        <img :src="activePreviewUrl" alt="" />
      </div>
    </div>

    <div v-if="sizeWarning" class="alert alert-warning py-2 small mt-2 mb-0">{{ sizeWarning }}</div>
    <div v-if="dimensionWarning" class="alert alert-warning py-2 small mt-2 mb-0">{{ dimensionWarning }}</div>
  </div>
</template>

<style scoped>
.website-image-upload-field__preview {
  max-width: 320px;
  border-radius: 0.375rem;
  overflow: hidden;
  border: 1px solid var(--bs-border-color, #dee2e6);
  background: #fff;
}
.website-image-upload-field__preview img {
  display: block;
  width: 100%;
  height: auto;
  max-height: 200px;
  object-fit: contain;
}
</style>
