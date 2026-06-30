<script setup>
import FormBuilderV2 from '@/Components/FormBuilder/FormBuilderV2.vue'

defineProps({
  templates: { type: Array, default: () => [] },
  fieldTypes: { type: Object, default: () => ({}) },
  builderConfig: { type: Object, default: () => ({}) },
})
</script>

<template>
  <FormBuilderV2 mode="create" :templates="templates" :builder-config="builderConfig" />
</template>
