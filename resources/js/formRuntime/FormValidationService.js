import {
  PHONE_PATTERN,
  VALIDATION_MESSAGES,
  VALIDATION_RULE_ORDER,
} from './constants'
import { FormLogicEffects } from './FormLogicEffects'

/**
 * Port of App\Services\FormBuilder\Runtime\FormValidationService
 */
export class FormValidationService {
  validate(fields, values, effects = new FormLogicEffects(), locale = 'ar') {
    const errors = []

    for (const field of fields) {
      if (field.readonly || !effects.isFieldEffective(field)) {
        continue
      }

      errors.push(...this.validateField(field, values[field.key] ?? null, effects, locale))
    }

    return errors.length === 0
      ? { valid: true, errors: [] }
      : { valid: false, errors }
  }

  validateField(field, value, effects, locale = 'ar') {
    const validation = field.validation ?? {}
    const required = effects.isFieldRequired(field)
    const isEmpty = this.isEmpty(value, field.type)

    if (required && isEmpty) {
      return [this.makeError(field.key, 'required', locale)]
    }

    if (isEmpty) {
      return []
    }

    const errors = []

    for (const rule of VALIDATION_RULE_ORDER) {
      const error = this.applyRule(rule, field, value, validation, locale)

      if (error !== null) {
        errors.push(error)
        break
      }
    }

    return errors
  }

  applyRule(rule, field, value, validation, locale) {
    switch (rule) {
      case 'min_length':
        return this.validateMinLength(field, value, validation, locale)
      case 'max_length':
        return this.validateMaxLength(field, value, validation, locale)
      case 'min_value':
        return this.validateMinValue(field, value, validation, locale)
      case 'max_value':
        return this.validateMaxValue(field, value, validation, locale)
      case 'regex':
        return this.validateRegex(field, value, validation, locale)
      case 'email':
        return this.validateEmail(field, value, validation, locale)
      case 'phone':
        return this.validatePhone(field, value, validation, locale)
      default:
        return null
    }
  }

  validateMinLength(field, value, validation, locale) {
    const min = validation.min_length

    if (min === null || min === undefined || !this.supportsLengthRules(field.type)) {
      return null
    }

    if ([...String(value)].length < min) {
      return this.makeError(field.key, 'min_length', locale, { min })
    }

    return null
  }

  validateMaxLength(field, value, validation, locale) {
    const max = validation.max_length

    if (max === null || max === undefined || !this.supportsLengthRules(field.type)) {
      return null
    }

    if ([...String(value)].length > max) {
      return this.makeError(field.key, 'max_length', locale, { max })
    }

    return null
  }

  validateMinValue(field, value, validation, locale) {
    const min = validation.min_value

    if (min === null || min === undefined || !this.supportsNumericRules(field.type)) {
      return null
    }

    if (!this.isNumeric(value) || Number(value) < Number(min)) {
      return this.makeError(field.key, 'min_value', locale, { min })
    }

    return null
  }

  validateMaxValue(field, value, validation, locale) {
    const max = validation.max_value

    if (max === null || max === undefined || !this.supportsNumericRules(field.type)) {
      return null
    }

    if (!this.isNumeric(value) || Number(value) > Number(max)) {
      return this.makeError(field.key, 'max_value', locale, { max })
    }

    return null
  }

  validateRegex(field, value, validation, locale) {
    const raw = validation.regex

    if (raw === null || raw === undefined || raw === '' || !this.supportsLengthRules(field.type)) {
      return null
    }

    const pattern = this.normalizeRegexPattern(String(raw))

    if (pattern === null) {
      return null
    }

    if (!pattern.test(String(value))) {
      return this.makeError(field.key, 'regex', locale)
    }

    return null
  }

  validateEmail(field, value, validation, locale) {
    const shouldValidate = Boolean(validation.email) || field.type === 'email'

    if (!shouldValidate) {
      return null
    }

    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

    if (!emailPattern.test(String(value))) {
      return this.makeError(field.key, 'email', locale)
    }

    return null
  }

  validatePhone(field, value, validation, locale) {
    const shouldValidate = Boolean(validation.phone) || field.type === 'phone'

    if (!shouldValidate) {
      return null
    }

    if (!PHONE_PATTERN.test(String(value))) {
      return this.makeError(field.key, 'phone', locale)
    }

    return null
  }

  isEmpty(value, type) {
    if (value === null || value === undefined) {
      return true
    }

    if (type === 'multi_select' || type === 'checkbox') {
      return !Array.isArray(value) || value.length === 0
    }

    if (typeof value === 'string') {
      return value.trim() === ''
    }

    if (Array.isArray(value)) {
      return value.length === 0
    }

    return false
  }

  supportsLengthRules(type) {
    return ['text', 'textarea', 'email', 'phone', 'url', 'date', 'time'].includes(type)
  }

  supportsNumericRules(type) {
    return ['number', 'slider', 'rating'].includes(type)
  }

  normalizeRegexPattern(pattern) {
    const trimmed = pattern.trim()

    if (trimmed === '') {
      return null
    }

    const source = trimmed.startsWith('/') ? trimmed : `/${trimmed}/u`

    try {
      return new RegExp(source.slice(1, source.lastIndexOf('/')), source.slice(source.lastIndexOf('/') + 1))
    } catch {
      return null
    }
  }

  isNumeric(value) {
    return value !== '' && !Number.isNaN(Number(value))
  }

  makeError(fieldKey, rule, locale, replacements = {}) {
    const messages = VALIDATION_MESSAGES[rule] ?? { ar: 'قيمة غير صالحة', en: 'Invalid value' }

    return {
      field_key: fieldKey,
      rule,
      message: this.formatMessage(locale === 'en' ? messages.en : messages.ar, replacements),
      message_ar: this.formatMessage(messages.ar, replacements),
      message_en: this.formatMessage(messages.en, replacements),
    }
  }

  formatMessage(message, replacements) {
    let result = message

    for (const [key, value] of Object.entries(replacements)) {
      result = result.replace(`:${key}`, String(value))
    }

    return result
  }
}

export const formValidationService = new FormValidationService()
