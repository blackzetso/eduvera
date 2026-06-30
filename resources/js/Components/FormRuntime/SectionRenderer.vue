<script setup>
import { computed } from 'vue'
import FieldRenderer from './FieldRenderer.vue'
import { isSupportedFieldType } from '@/formRuntime/runtimeHelpers'

const props = defineProps({
  section: { type: Object, required: true },
  values: { type: Object, required: true },
  effects: { type: Object, required: true },
  fieldErrors: { type: Object, default: () => ({}) },
  locale: { type: String, default: 'ar' },
})

const emit = defineEmits(['update:values'])

const sectionId = computed(() => String(props.section.id ?? ''))
const isSkipped = computed(() => props.effects.isSectionSkipped(sectionId.value))

const title = computed(() => {
  const i18n = props.section._i18n?.title

  if (i18n) {
    return props.locale === 'en' ? (i18n.en ?? i18n.ar) : (i18n.ar ?? i18n.en)
  }

  return props.section.title ?? ''
})

const description = computed(() => {
  const i18n = props.section._i18n?.description

  if (i18n) {
    const value = props.locale === 'en' ? (i18n.en ?? i18n.ar) : (i18n.ar ?? i18n.en)

    return value || null
  }

  return props.section.description ?? null
})

const visibleFields = computed(() => (props.section.fields ?? []).filter((field) => {
  if (!isSupportedFieldType(field.type)) {
    return false
  }

  const normalized = {
    key: field.key,
    type: field.type,
    sectionId: sectionId.value,
    required: Boolean(field.required),
    hidden: Boolean(field.hidden),
    readonly: Boolean(field.readonly),
    validation: field.validation ?? {},
  }

  return props.effects.isFieldEffective(normalized)
}))

function updateField(key, value) {
  emit('update:values', { ...props.values, [key]: value })
}

function fieldRequired(field) {
  const normalized = {
    key: field.key,
    type: field.type,
    sectionId: sectionId.value,
    required: Boolean(field.required),
    hidden: Boolean(field.hidden),
    readonly: Boolean(field.readonly),
    validation: field.validation ?? {},
  }

  return props.effects.isFieldRequired(normalized)
}
</script>

<template>
  <section v-if="!isSkipped && visibleFields.length" class="form-runtime-section mb-4">
    <h5 v-if="title" class="fw-bold mb-1">{{ title }}</h5>
    <p v-if="description" class="text-muted small mb-3">{{ description }}</p>

    <FieldRenderer
      v-for="field in visibleFields"
      :key="field.key"
      :field="field"
      :locale="locale"
      :model-value="values[field.key]"
      :error="fieldErrors[field.key]"
      :required="fieldRequired(field)"
      :readonly="field.readonly"
      @update:model-value="updateField(field.key, $event)"
    />
  </section>
</template>
