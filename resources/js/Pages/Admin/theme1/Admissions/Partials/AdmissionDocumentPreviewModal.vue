<script setup>
import { computed } from 'vue'
import { admissionDocumentDownloadUrl } from '@/composables/useAdmissionDocumentPreview'

const props = defineProps({
  show: { type: Boolean, default: false },
  document: { type: Object, default: null },
  applicationId: { type: Number, required: true },
  previewType: { type: String, default: null },
  title: { type: String, default: '—' },
})

const emit = defineEmits(['close'])

const previewUrl = computed(() => {
  if (!props.document?.id || !props.previewType) return null
  if (props.previewType === 'unsupported') return null
  return admissionDocumentDownloadUrl(props.applicationId, props.document.id, true)
})

const downloadUrl = computed(() => {
  if (!props.document?.id) return null
  return admissionDocumentDownloadUrl(props.applicationId, props.document.id, false)
})
</script>

<template>
  <div
    v-if="show"
    class="modal fade show d-block"
    tabindex="-1"
    role="dialog"
    aria-modal="true"
    style="background: rgba(15, 23, 42, 0.45);"
    @click.self="emit('close')"
  >
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable eduvera-modal-dialog">
      <div class="modal-content border-0 shadow">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold text-truncate">{{ title }}</h5>
          <button type="button" class="btn-close" aria-label="إغلاق" @click="emit('close')"></button>
        </div>

        <div class="modal-body pt-3">
          <div v-if="previewType === 'image' && previewUrl" class="admission-doc-preview admission-doc-preview--image">
            <img :src="previewUrl" :alt="title" class="img-fluid rounded border w-100" />
          </div>

          <div v-else-if="previewType === 'pdf' && previewUrl" class="admission-doc-preview admission-doc-preview--pdf">
            <iframe
              :src="previewUrl"
              :title="title"
              class="admission-doc-preview__iframe w-100 rounded border"
            ></iframe>
          </div>

          <div v-else class="admission-doc-preview admission-doc-preview--unsupported text-center py-4">
            <i class="bi bi-file-earmark-x text-muted display-6 d-block mb-3"></i>
            <p class="fw-semibold mb-2">لا يمكن معاينة هذا النوع</p>
            <p class="text-muted small mb-0">يمكن تحميل الملف وفتحه خارج النظام.</p>
          </div>
        </div>

        <div class="modal-footer border-0 pt-0 flex-wrap gap-2">
          <a
            v-if="downloadUrl"
            :href="downloadUrl"
            class="btn btn-primary"
            target="_blank"
            rel="noopener"
          >
            <i class="bi bi-download me-1"></i>
            تحميل الملف
          </a>
          <button type="button" class="btn btn-outline-secondary" @click="emit('close')">
            إغلاق
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.admission-doc-preview--image {
  max-height: min(70vh, 520px);
  overflow: auto;
  text-align: center;
}

.admission-doc-preview__iframe {
  height: min(70vh, 520px);
  min-height: 320px;
  border: 0;
}
</style>
