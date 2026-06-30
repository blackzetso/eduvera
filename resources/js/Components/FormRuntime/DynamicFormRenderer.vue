<script setup>
import { computed, onMounted } from 'vue'
import SectionRenderer from './SectionRenderer.vue'
import { useDynamicForm } from '@/formRuntime/useDynamicForm'

const props = defineProps({
  formId: { type: [Number, String], required: true },
  locale: { type: String, default: 'ar' },
  submissionId: { type: [Number, String], default: null },
  initialData: { type: Object, default: null },
})

const emit = defineEmits(['submitted', 'draft-saved', 'error'])

const {
  locale: activeLocale,
  values,
  fieldErrors,
  formError,
  formErrorReason,
  loading,
  submitting,
  successMessage,
  lastSubmission,
  sections,
  effects,
  formMeta,
  allowsDraft,
  loadRuntime,
  submitFinal,
  saveDraft,
} = useDynamicForm({
  formId: Number(props.formId),
  locale: props.locale,
  submissionId: props.submissionId ? Number(props.submissionId) : null,
})

const isRtl = computed(() => activeLocale.value === 'ar')

const formTitle = computed(() => formMeta.value?.name ?? '')
const formDescription = computed(() => formMeta.value?.description ?? null)

function onValuesUpdate(next) {
  Object.assign(values, next)
}

async function handleSubmit() {
  await submitFinal()

  if (lastSubmission.value) {
    emit('submitted', lastSubmission.value)
  } else if (formError.value) {
    emit('error', { message: formError.value, reason: formErrorReason.value })
  }
}

async function handleDraft() {
  await saveDraft()

  if (lastSubmission.value) {
    emit('draft-saved', lastSubmission.value)
  } else if (formError.value) {
    emit('error', { message: formError.value, reason: formErrorReason.value })
  }
}

function switchLocale(next) {
  activeLocale.value = next
}

onMounted(() => {
  loadRuntime(props.initialData)
})
</script>

<template>
  <div class="form-runtime" :dir="isRtl ? 'rtl' : 'ltr'">
    <div v-if="loading" class="form-runtime__loading py-4 text-center text-muted">
      {{ isRtl ? 'جاري تحميل النموذج...' : 'Loading form...' }}
    </div>

    <div v-else-if="formError && !sections.length" class="alert alert-danger" role="alert">
      {{ formError }}
    </div>

    <template v-else>
      <header v-if="formTitle" class="mb-4">
        <h2 class="h4 mb-2">{{ formTitle }}</h2>
        <p v-if="formDescription" class="text-muted mb-0">{{ formDescription }}</p>
      </header>

      <div v-if="formError" class="alert alert-warning" role="alert">
        {{ formError }}
      </div>

      <div v-if="successMessage" class="alert alert-success" role="status">
        {{ successMessage }}
      </div>

      <div class="d-flex gap-2 mb-3">
        <button
          type="button"
          class="btn btn-sm"
          :class="activeLocale === 'ar' ? 'btn-primary' : 'btn-outline-secondary'"
          @click="switchLocale('ar')"
        >
          عربي
        </button>
        <button
          type="button"
          class="btn btn-sm"
          :class="activeLocale === 'en' ? 'btn-primary' : 'btn-outline-secondary'"
          @click="switchLocale('en')"
        >
          English
        </button>
      </div>

      <form @submit.prevent="handleSubmit">
        <SectionRenderer
          v-for="section in sections"
          :key="section.id"
          :section="section"
          :values="values"
          :effects="effects"
          :field-errors="fieldErrors"
          :locale="activeLocale"
          @update:values="onValuesUpdate"
        />

        <div class="d-flex flex-wrap gap-2 mt-4">
          <button
            type="submit"
            class="btn btn-primary"
            :disabled="submitting"
          >
            {{ submitting ? (isRtl ? 'جاري الإرسال...' : 'Submitting...') : (isRtl ? 'إرسال' : 'Submit') }}
          </button>
          <button
            v-if="allowsDraft"
            type="button"
            class="btn btn-outline-secondary"
            :disabled="submitting"
            @click="handleDraft"
          >
            {{ isRtl ? 'حفظ مسودة' : 'Save draft' }}
          </button>
        </div>
      </form>
    </template>
  </div>
</template>
