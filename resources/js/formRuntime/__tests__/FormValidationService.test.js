import { describe, expect, it } from 'vitest'
import { FormValidationService } from '../FormValidationService'
import { FormLogicEffects } from '../FormLogicEffects'

describe('FormValidationService', () => {
  const validator = new FormValidationService()

  function field(key, validation = {}, type = 'text', sectionId = null) {
    return {
      key,
      type,
      sectionId,
      required: Boolean(validation.required),
      hidden: false,
      readonly: false,
      validation,
    }
  }

  it('required rule fails for empty values', () => {
    const result = validator.validate([
      field('fld_1', { required: true }),
    ], {})

    expect(result.valid).toBe(false)
    expect(result.errors[0].rule).toBe('required')
    expect(result.errors[0].field_key).toBe('fld_1')
  })

  it('min and max length rules', () => {
    const f = field('fld_1', { min_length: 3, max_length: 5 })

    expect(validator.validate([f], { fld_1: 'ab' }).valid).toBe(false)
    expect(validator.validate([f], { fld_1: 'abcdef' }).valid).toBe(false)
    expect(validator.validate([f], { fld_1: 'abcd' }).valid).toBe(true)
  })

  it('min and max value rules for number fields', () => {
    const f = field('fld_2', { min_value: 10, max_value: 20 }, 'number')

    expect(validator.validate([f], { fld_2: 5 }).valid).toBe(false)
    expect(validator.validate([f], { fld_2: 25 }).valid).toBe(false)
    expect(validator.validate([f], { fld_2: 15 }).valid).toBe(true)
  })

  it('regex rule', () => {
    const f = field('fld_3', { regex: '^[A-Z]{3}$' })

    expect(validator.validate([f], { fld_3: 'abc' }).valid).toBe(false)
    expect(validator.validate([f], { fld_3: 'ABC' }).valid).toBe(true)
  })

  it('email rule from flag and field type', () => {
    const flagged = field('fld_4', { email: true }, 'text')
    const typed = field('fld_5', {}, 'email')

    expect(validator.validate([flagged], { fld_4: 'not-an-email' }).valid).toBe(false)
    expect(validator.validate([typed], { fld_5: 'bad@' }).valid).toBe(false)
    expect(validator.validate([flagged, typed], {
      fld_4: 'user@example.com',
      fld_5: 'user@example.com',
    }).valid).toBe(true)
  })

  it('phone rule from flag and field type', () => {
    const flagged = field('fld_6', { phone: true }, 'text')
    const typed = field('fld_7', {}, 'phone')

    expect(validator.validate([flagged], { fld_6: 'abc' }).valid).toBe(false)
    expect(validator.validate([flagged, typed], {
      fld_6: '+966501234567',
      fld_7: '01000000000',
    }).valid).toBe(true)
  })

  it('logic required overlay makes optional field required', () => {
    const effects = new FormLogicEffects({ requiredByLogic: { fld_8: true } })

    const result = validator.validate([
      field('fld_8', { required: false }),
    ], {}, effects)

    expect(result.valid).toBe(false)
    expect(result.errors[0].rule).toBe('required')
  })

  it('hidden and skipped fields are not validated', () => {
    const effects = new FormLogicEffects({
      hiddenByLogic: { fld_9: true },
      skippedSections: { sec_1: true },
    })

    const result = validator.validate([
      field('fld_9', { required: true }),
      field('fld_10', { required: true }, 'text', 'sec_1'),
    ], {}, effects)

    expect(result.valid).toBe(true)
  })

  it('multi select empty array triggers required', () => {
    const result = validator.validate([
      field('fld_13', { required: true }, 'multi_select'),
    ], { fld_13: [] })

    expect(result.valid).toBe(false)
    expect(result.errors[0].rule).toBe('required')
  })

  it('optional empty field skips downstream rules', () => {
    const result = validator.validate([
      field('fld_14', { min_length: 5 }),
    ], { fld_14: '' })

    expect(result.valid).toBe(true)
  })

  it('validation stops at first failing rule per field', () => {
    const result = validator.validate([
      field('fld_15', { min_length: 5, max_length: 3 }),
    ], { fld_15: 'ab' })

    expect(result.errors).toHaveLength(1)
    expect(result.errors[0].rule).toBe('min_length')
  })

  it('result includes localized errors', () => {
    const result = validator.validate([
      field('fld_16', { required: true }),
    ], [])

    expect(result.errors[0].message_ar).toBe('هذا الحقل مطلوب')
    expect(result.errors[0].message_en).toBe('This field is required')
  })
})
