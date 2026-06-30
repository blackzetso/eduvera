import { LOGIC_MAX_PASSES } from './constants'
import { FormLogicEffects } from './FormLogicEffects'

/**
 * Port of App\Services\FormBuilder\Runtime\FormLogicEvaluator
 */
export class FormLogicEvaluator {
  evaluate(rules, values, sectionFieldIndex = {}, allFieldKeys = []) {
    const keys = this.resolveFieldKeys(allFieldKeys, sectionFieldIndex, rules)
    let effects = this.evaluatePass(rules, values, sectionFieldIndex, keys)

    for (let pass = 1; pass < LOGIC_MAX_PASSES; pass++) {
      const next = this.evaluatePass(rules, values, sectionFieldIndex, keys)

      if (next.equals(effects)) {
        return next
      }

      effects = next
    }

    return effects
  }

  evaluatePass(rules, values, sectionFieldIndex) {
    const effects = new FormLogicEffects()

    for (const rule of rules) {
      if (!this.isConditionMet(rule, values)) {
        continue
      }

      this.applyAction(effects, rule, sectionFieldIndex)
    }

    return effects
  }

  isConditionMet(rule, values) {
    const fieldKey = String(rule.field_key ?? '')

    if (fieldKey === '') {
      return false
    }

    const operator = String(rule.operator ?? 'equals')
    const expected = rule.value ?? null
    const actual = values[fieldKey] ?? null

    switch (operator) {
      case 'not_equals':
        return !this.valuesEqual(actual, expected)
      case 'contains':
        return this.valueContains(actual, expected)
      default:
        return this.valuesEqual(actual, expected)
    }
  }

  applyAction(effects, rule, sectionFieldIndex) {
    const action = String(rule.action ?? '')

    switch (action) {
      case 'show':
        this.applyShow(effects, rule)
        break
      case 'hide':
        this.applyHide(effects, rule)
        break
      case 'require':
        this.applyRequire(effects, rule)
        break
      case 'skip_section':
        this.applySkipSection(effects, rule, sectionFieldIndex)
        break
      default:
        break
    }
  }

  applyShow(effects, rule) {
    const target = this.resolveFieldTarget(rule)

    if (target === null) {
      return
    }

    if (!effects.hiddenByLogic[target]) {
      effects.visibleByLogic[target] = true
    }
  }

  applyHide(effects, rule) {
    const target = this.resolveFieldTarget(rule)

    if (target === null) {
      return
    }

    effects.hiddenByLogic[target] = true
    delete effects.visibleByLogic[target]
  }

  applyRequire(effects, rule) {
    const target = this.resolveFieldTarget(rule)

    if (target === null) {
      return
    }

    effects.requiredByLogic[target] = true
  }

  applySkipSection(effects, rule, sectionFieldIndex) {
    const sectionId = this.resolveSectionTarget(rule)

    if (sectionId === null) {
      return
    }

    effects.skippedSections[sectionId] = true

    for (const fieldKey of sectionFieldIndex[sectionId] ?? []) {
      effects.hiddenByLogic[fieldKey] = true
      delete effects.visibleByLogic[fieldKey]
    }
  }

  resolveFieldTarget(rule) {
    const target = String(rule.target_field_key ?? '').trim()

    return target === '' ? null : target
  }

  resolveSectionTarget(rule) {
    const sectionId = rule.target_section_id

    if (typeof sectionId === 'string' && sectionId !== '') {
      return sectionId
    }

    const target = String(rule.target_field_key ?? '').trim()

    if (target.startsWith('sec_')) {
      return target
    }

    return null
  }

  valuesEqual(actual, expected) {
    if (Array.isArray(actual)) {
      return actual.includes(expected)
    }

    if (typeof actual === 'boolean') {
      const parsed = expected === true || expected === 'true' || expected === 1 || expected === '1'
      return actual === parsed
    }

    if (typeof actual === 'number' || typeof expected === 'number') {
      return String(actual) === String(expected)
    }

    return String(actual) === String(expected)
  }

  valueContains(actual, expected) {
    if (Array.isArray(actual)) {
      return actual.includes(expected)
        || actual.some((item) => this.valueContains(String(item), expected))
    }

    if (actual === null || actual === undefined) {
      return false
    }

    return String(actual).includes(String(expected))
  }

  resolveFieldKeys(allFieldKeys, sectionFieldIndex, rules) {
    if (allFieldKeys.length > 0) {
      return [...new Set(allFieldKeys)]
    }

    const keys = new Set()

    for (const fieldKeys of Object.values(sectionFieldIndex)) {
      for (const key of fieldKeys) {
        keys.add(key)
      }
    }

    for (const rule of rules) {
      for (const property of ['field_key', 'target_field_key']) {
        const value = String(rule[property] ?? '').trim()

        if (value !== '' && !value.startsWith('sec_')) {
          keys.add(value)
        }
      }
    }

    return [...keys]
  }
}

export const formLogicEvaluator = new FormLogicEvaluator()
