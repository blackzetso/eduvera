<script setup>
import FormBuilderV2 from '@/Components/FormBuilder/FormBuilderV2.vue'

defineProps({
  form: Object,
  formMeta: Object,
  templates: { type: Array, default: () => [] },
  fieldTypes: { type: Object, default: () => ({}) },
  builderConfig: { type: Object, default: () => ({}) },
})
</script>

<template>
  <FormBuilderV2
    mode="edit"
    :initial-form="form"
    :form-meta="formMeta"
    :templates="templates"
    :builder-config="builderConfig"
  />
</template>
