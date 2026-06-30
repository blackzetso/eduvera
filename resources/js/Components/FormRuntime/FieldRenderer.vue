<script setup>
import { computed } from 'vue'
import { inputTypeForField } from '@/formRuntime/fieldRegistry'
import { localizedFieldText } from '@/formRuntime/runtimeHelpers'

const props = defineProps({
  field: { type: Object, required: true },
  modelValue: { type: [String, Number, Array, null], default: '' },
  locale: { type: String, default: 'ar' },
  error: { type: String, default: '' },
  required: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const label = computed(() => localizedFieldText(props.field, 'label', props.locale))
const placeholder = computed(() => localizedFieldText(props.field, 'placeholder', props.locale))
const help = computed(() => localizedFieldText(props.field, 'help', props.locale))
const options = computed(() => {
  const resolved = props.field.resolved_options ?? []

  if (resolved.length > 0) {
    return resolved
  }

  return (props.field.options ?? []).map((opt) => ({
    value: String(opt.value ?? ''),
    label: props.locale === 'en'
      ? (opt.label_en ?? opt.label_ar ?? opt.value)
      : (opt.label_ar ?? opt.label_en ?? opt.value),
  }))
})

const inputType = computed(() => inputTypeForField(props.field.type))

function updateValue(value) {
  emit('update:modelValue', value)
}

function onInput(event) {
  const value = props.field.type === 'number'
    ? (event.target.value === '' ? null : Number(event.target.value))
    : event.target.value

  updateValue(value)
}

function isOptionChecked(optionValue) {
  if (!Array.isArray(props.modelValue)) {
    return false
  }

  return props.modelValue.includes(optionValue)
}

function toggleCheckbox(optionValue, checked) {
  const current = Array.isArray(props.modelValue) ? [...props.modelValue] : []

  if (checked) {
    if (!current.includes(optionValue)) {
      current.push(optionValue)
    }
  } else {
    const index = current.indexOf(optionValue)

    if (index !== -1) {
      current.splice(index, 1)
    }
  }

  updateValue(current)
}

function onMultiSelectChange(event) {
  const selected = Array.from(event.target.selectedOptions).map((opt) => opt.value)
  updateValue(selected)
}
</script>

<template>
  <div class="form-runtime-field mb-3" :class="{ 'has-error': !!error }">
    <label class="form-label fw-semibold" :for="field.key">
      {{ label }}
      <span v-if="required" class="text-danger">*</span>
    </label>
    <p v-if="help" class="small text-muted mb-1">{{ help }}</p>

    <textarea
      v-if="field.type === 'textarea'"
      :id="field.key"
      class="form-control"
      :class="{ 'is-invalid': !!error }"
      rows="4"
      :placeholder="placeholder"
      :readonly="readonly"
      :disabled="readonly"
      :value="modelValue ?? ''"
      @input="onInput"
    />

    <select
      v-else-if="field.type === 'select'"
      :id="field.key"
      class="form-select"
      :class="{ 'is-invalid': !!error }"
      :disabled="readonly"
      :value="modelValue ?? ''"
      @change="onInput"
    >
      <option value="" disabled>{{ placeholder || '—' }}</option>
      <option v-for="opt in options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
    </select>

    <select
      v-else-if="field.type === 'multi_select'"
      :id="field.key"
      class="form-select"
      :class="{ 'is-invalid': !!error }"
      multiple
      :disabled="readonly"
      @change="onMultiSelectChange"
    >
      <option
        v-for="opt in options"
        :key="opt.value"
        :value="opt.value"
        :selected="isOptionChecked(opt.value)"
      >
        {{ opt.label }}
      </option>
    </select>

    <div v-else-if="field.type === 'radio'">
      <div v-for="opt in options" :key="opt.value" class="form-check">
        <input
          :id="`${field.key}_${opt.value}`"
          class="form-check-input"
          type="radio"
          :name="field.key"
          :value="opt.value"
          :checked="modelValue === opt.value"
          :disabled="readonly"
          @change="updateValue(opt.value)"
        />
        <label class="form-check-label" :for="`${field.key}_${opt.value}`">{{ opt.label }}</label>
      </div>
    </div>

    <div v-else-if="field.type === 'checkbox'">
      <div v-for="opt in options" :key="opt.value" class="form-check">
        <input
          :id="`${field.key}_${opt.value}`"
          class="form-check-input"
          type="checkbox"
          :checked="isOptionChecked(opt.value)"
          :disabled="readonly"
          @change="toggleCheckbox(opt.value, $event.target.checked)"
        />
        <label class="form-check-label" :for="`${field.key}_${opt.value}`">{{ opt.label }}</label>
      </div>
    </div>

    <input
      v-else
      :id="field.key"
      class="form-control"
      :class="{ 'is-invalid': !!error }"
      :type="inputType"
      :placeholder="placeholder"
      :readonly="readonly"
      :disabled="readonly"
      :value="modelValue ?? ''"
      @input="onInput"
    />

    <div v-if="error" class="invalid-feedback d-block">{{ error }}</div>
  </div>
</template>
