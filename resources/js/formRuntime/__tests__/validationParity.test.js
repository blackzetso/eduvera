import { describe, expect, it } from 'vitest'
import fixtures from '../__fixtures__/validation-parity.json'
import { FormValidationService } from '../FormValidationService'
import { FormLogicEffects } from '../FormLogicEffects'

describe('ValidationParity', () => {
  const validator = new FormValidationService()

  function fieldFromFixture(data) {
    return {
      key: data.key,
      type: data.type,
      sectionId: data.sectionId ?? null,
      required: Boolean(data.required),
      hidden: Boolean(data.hidden),
      readonly: Boolean(data.readonly ?? false),
      validation: data.validation ?? {},
    }
  }

  function effectsFromFixture(data = {}) {
    return new FormLogicEffects({
      visibleByLogic: data.visibleByLogic ?? {},
      hiddenByLogic: data.hiddenByLogic ?? {},
      requiredByLogic: data.requiredByLogic ?? {},
      skippedSections: data.skippedSections ?? {},
    })
  }

  it('parity fixtures match expected outcomes', () => {
    const failures = []

    for (const testCase of fixtures.parity) {
      const field = fieldFromFixture(testCase.field)
      const effects = effectsFromFixture(testCase.effects ?? {})
      const values = testCase.values ?? {}
      const result = validator.validate([field], values, effects, 'ar')

      if (result.valid !== testCase.expectedValid) {
        failures.push(`${testCase.id}: expected ${testCase.expectedValid ? 'valid' : 'invalid'}, got ${result.valid ? 'valid' : 'invalid'}`)
        continue
      }

      if (!result.valid && testCase.expectedRule) {
        const rule = result.errors[0]?.rule

        if (rule !== testCase.expectedRule) {
          failures.push(`${testCase.id}: expected rule ${testCase.expectedRule}, got ${rule}`)
        }
      }
    }

    expect(failures).toEqual([])
  })

  it('known difference fixtures are documented', () => {
    expect(fixtures.known_differences.length).toBeGreaterThan(0)

    for (const testCase of fixtures.known_differences) {
      expect(testCase.id).toBeTruthy()
      expect(testCase.note).toBeTruthy()

      if (testCase.documentOnly) {
        continue
      }

      const field = fieldFromFixture(testCase.field)
      const result = validator.validate([field], testCase.values ?? {}, new FormLogicEffects(), 'ar')

      expect(result.valid).toBe(testCase.jsExpectedValid)
    }
  })
})
