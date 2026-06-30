<script setup>
import { Head } from '@inertiajs/vue3'
import DynamicFormRenderer from '@/Components/FormRuntime/DynamicFormRenderer.vue'

const props = defineProps({
  formId: { type: Number, required: true },
  locale: { type: String, default: 'ar' },
  submissionId: { type: Number, default: null },
})
</script>

<template>
  <Head :title="`Form #${formId}`" />

  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-lg-8">
        <DynamicFormRenderer
          :form-id="formId"
          :locale="locale"
          :submission-id="submissionId"
        />
      </div>
    </div>
  </div>
</template>
