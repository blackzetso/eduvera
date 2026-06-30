import { SUPPORTED_FIELD_TYPES, UNSUPPORTED_FIELD_TYPES } from './constants'

let runtimeSupportedTypes = null

export function setRuntimeSupportedTypes(types) {
  runtimeSupportedTypes = Array.isArray(types) && types.length > 0 ? [...types] : null
}

export function getSupportedFieldTypes() {
  return runtimeSupportedTypes ?? SUPPORTED_FIELD_TYPES
}

export function isSupportedFieldType(type) {
  return getSupportedFieldTypes().includes(type)
}

export function isHiddenUnsupportedType(type) {
  return UNSUPPORTED_FIELD_TYPES.includes(type)
}

export function buildSectionFieldIndex(sections) {
  const index = {}

  for (const section of sections ?? []) {
    const sectionId = String(section.id ?? '')

    if (sectionId === '') {
      continue
    }

    index[sectionId] = (section.fields ?? [])
      .map((field) => String(field.key ?? ''))
      .filter(Boolean)
  }

  return index
}

export function collectAllFieldKeys(sections) {
  const keys = []

  for (const section of sections ?? []) {
    for (const field of section.fields ?? []) {
      if (field.key) {
        keys.push(field.key)
      }
    }
  }

  return keys
}

export function normalizeField(field, sectionId) {
  return {
    key: String(field.key ?? ''),
    type: String(field.type ?? 'text'),
    sectionId: sectionId ?? null,
    required: Boolean(field.required),
    hidden: Boolean(field.hidden),
    readonly: Boolean(field.readonly),
    validation: field.validation ?? {},
    label: field.label ?? '',
    placeholder: field.placeholder ?? '',
    help: field.help ?? '',
    default_value: field.default_value ?? null,
    options: field.options ?? [],
    resolved_options: field.resolved_options ?? [],
    order: field.order ?? 0,
    _i18n: field._i18n ?? {},
  }
}

export function collectSubmittableFields(sections, effects) {
  const fields = []

  for (const section of sections ?? []) {
    const sectionId = String(section.id ?? '')

    for (const raw of section.fields ?? []) {
      if (!isSupportedFieldType(raw.type)) {
        continue
      }

      const field = normalizeField(raw, sectionId)

      if (!effects.isFieldEffective(field) || field.readonly) {
        continue
      }

      fields.push(field)
    }
  }

  return fields
}

export function initValuesFromRuntime(sections) {
  const values = {}

  for (const section of sections ?? []) {
    for (const field of section.fields ?? []) {
      if (!field.key || !isSupportedFieldType(field.type)) {
        continue
      }

      const defaultValue = field.default_value

      if (field.type === 'multi_select' || field.type === 'checkbox') {
        values[field.key] = Array.isArray(defaultValue) ? [...defaultValue] : []
      } else if (defaultValue !== null && defaultValue !== undefined && defaultValue !== '') {
        values[field.key] = defaultValue
      } else {
        values[field.key] = field.type === 'number' ? null : ''
      }
    }
  }

  return values
}

export function localizedFieldText(field, property, locale) {
  const i18n = field._i18n?.[property]

  if (i18n) {
    if (locale === 'en' && i18n.en) {
      return i18n.en
    }

    return i18n.ar ?? i18n.en ?? field[property] ?? ''
  }

  return field[property] ?? ''
}

export function mergeSubmissionValues(sections, submissionData) {
  const values = initValuesFromRuntime(sections)

  if (!submissionData || typeof submissionData !== 'object') {
    return values
  }

  for (const [key, value] of Object.entries(submissionData)) {
    if (key === '_meta') {
      continue
    }

    values[key] = value
  }

  return values
}
