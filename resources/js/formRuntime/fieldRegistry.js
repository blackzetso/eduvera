import { SUPPORTED_FIELD_TYPES } from './constants'

/**
 * Maps runtime field types to renderer component keys.
 * All MVP types use the shared FieldRenderer with type-specific slots.
 */
export const FIELD_REGISTRY = Object.fromEntries(
  SUPPORTED_FIELD_TYPES.map((type) => [type, 'FieldRenderer']),
)

export function resolveFieldComponent(type) {
  return FIELD_REGISTRY[type] ?? null
}

export function isMultiValueType(type) {
  return type === 'multi_select' || type === 'checkbox'
}

export function inputTypeForField(type) {
  switch (type) {
    case 'email':
      return 'email'
    case 'phone':
      return 'tel'
    case 'number':
      return 'number'
    case 'date':
      return 'date'
    case 'time':
      return 'time'
    case 'url':
      return 'url'
    default:
      return 'text'
  }
}
