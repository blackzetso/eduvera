<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import Quill from 'quill'
import 'quill/dist/quill.snow.css'

const props = defineProps({
  show: Boolean,
  gap: Object,
  categories: Array,
  loading: Boolean,
})

const emit = defineEmits(['close', 'saved'])

const editorEn = ref(null)
const editorAr = ref(null)
let quillEn = null
let quillAr = null

const form = useForm({
  question_en: '',
  question_ar: '',
  answer_en: '',
  answer_ar: '',
  category_id: '',
})

function destroyEditors() {
  quillEn = null
  quillAr = null
}

function initEditors() {
  destroyEditors()

  if (!editorEn.value || !editorAr.value) {
    return
  }

  quillEn = new Quill(editorEn.value, {
    theme: 'snow',
    modules: { toolbar: [['bold', 'italic'], [{ list: 'ordered' }, { list: 'bullet' }], ['link'], ['clean']] },
    placeholder: 'Write the answer in English...',
  })

  quillAr = new Quill(editorAr.value, {
    theme: 'snow',
    modules: { toolbar: [['bold', 'italic'], [{ list: 'ordered' }, { list: 'bullet' }], ['link'], ['clean']] },
    placeholder: 'اكتب الإجابة بالعربية...',
    direction: 'rtl',
  })

  quillEn.root.innerHTML = form.answer_en || ''
  quillAr.root.innerHTML = form.answer_ar || ''

  quillEn.on('text-change', () => {
    form.answer_en = quillEn.root.innerHTML
  })

  quillAr.on('text-change', () => {
    form.answer_ar = quillAr.root.innerHTML
  })
}

function fillFormFromGap(gapData) {
  const faq = gapData?.faq
  const prefill = gapData?.prefill
  const gap = gapData?.gap

  form.question_en = faq?.question_en || prefill?.question_en || gap?.question || ''
  form.question_ar = faq?.question_ar || prefill?.question_ar || ''
  form.answer_en = faq?.answer_en || prefill?.answer_en || ''
  form.answer_ar = faq?.answer_ar || prefill?.answer_ar || ''
  form.category_id = faq?.category_id || prefill?.category_id || ''
}

watch(() => props.show, async (visible) => {
  if (visible && props.gap) {
    fillFormFromGap(props.gap)
    await nextTick()
    initEditors()
  } else {
    destroyEditors()
  }
})

watch(() => props.gap, async (gapData) => {
  if (props.show && gapData) {
    fillFormFromGap(gapData)
    await nextTick()
    if (quillEn && quillAr) {
      quillEn.root.innerHTML = form.answer_en || ''
      quillAr.root.innerHTML = form.answer_ar || ''
    }
  }
})

onBeforeUnmount(() => destroyEditors())

function syncAnswers() {
  if (quillEn) form.answer_en = quillEn.root.innerHTML
  if (quillAr) form.answer_ar = quillAr.root.innerHTML
}

function saveDraft() {
  if (!props.gap?.gap?.id) return
  syncAnswers()
  form.post(route('admin.dova-knowledge.unanswered.draft', props.gap.gap.id), {
    preserveScroll: true,
    onSuccess: () => emit('saved'),
  })
}

function publishFaq() {
  if (!props.gap?.gap?.id) return
  syncAnswers()
  form.post(route('admin.dova-knowledge.unanswered.publish', props.gap.gap.id), {
    preserveScroll: true,
    onSuccess: () => {
      emit('saved')
      emit('close')
    },
  })
}

function statusBadge(status) {
  return {
    unanswered: 'bg-danger',
    draft: 'bg-secondary',
    pending_review: 'bg-warning text-dark',
    published: 'bg-success',
    ignored: 'bg-dark',
  }[status] || 'bg-light text-dark'
}
</script>

<template>
  <div v-if="show" class="modal fade show d-block" tabindex="-1" style="background: rgba(15,23,42,0.45);">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">الإجابة على السؤال</h5>
          <button type="button" class="btn-close" aria-label="Close" @click="emit('close')" />
        </div>

        <div v-if="loading" class="modal-body text-center py-5 text-muted">
          <div class="spinner-border spinner-border-sm me-2" role="status" />
          جاري التحميل...
        </div>

        <div v-else-if="gap?.gap" class="modal-body">
          <div class="row g-2 mb-3 small">
            <div class="col-md-6">
              <span class="text-muted">المصدر:</span>
              <strong class="ms-1">{{ gap.gap.sourceModule }}</strong>
            </div>
            <div class="col-md-6">
              <span class="text-muted">التكرار:</span>
              <strong class="ms-1">{{ gap.gap.frequency }}×</strong>
            </div>
            <div class="col-md-6">
              <span class="text-muted">أول ظهور:</span>
              <strong class="ms-1">{{ gap.gap.firstSeen }}</strong>
            </div>
            <div class="col-md-6">
              <span class="text-muted">آخر ظهور:</span>
              <strong class="ms-1">{{ gap.gap.lastSeen }}</strong>
            </div>
            <div class="col-12">
              <span class="badge" :class="statusBadge(gap.gap.status)">{{ gap.gap.statusLabel }}</span>
              <span v-if="gap.gap.faqId" class="badge bg-info ms-1">FAQ #{{ gap.gap.faqId }}</span>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">السؤال</label>
            <textarea v-model="form.question_en" class="form-control" rows="2" readonly />
          </div>

          <div class="mb-3">
            <label class="form-label">التصنيف</label>
            <select v-model="form.category_id" class="form-select">
              <option value="">— اختر التصنيف —</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">الإجابة (English) *</label>
            <div ref="editorEn" class="bg-white" style="min-height: 140px;" />
            <div v-if="form.errors.answer_en" class="text-danger small mt-1">{{ form.errors.answer_en }}</div>
          </div>

          <div class="mb-3">
            <label class="form-label">الإجابة (العربية)</label>
            <div ref="editorAr" class="bg-white" style="min-height: 140px;" />
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" @click="emit('close')">إلغاء</button>
          <button type="button" class="btn btn-outline-primary" :disabled="form.processing" @click="saveDraft">
            حفظ مسودة
          </button>
          <button type="button" class="btn btn-primary" :disabled="form.processing" @click="publishFaq">
            نشر السؤال الشائع
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
