import { describe, expect, it } from 'vitest'
import { FormLogicEvaluator } from '../FormLogicEvaluator'

describe('FormLogicEvaluator', () => {
  const evaluator = new FormLogicEvaluator()

  it('show rule makes target visible when condition matches', () => {
    const effects = evaluator.evaluate(
      [{
        field_key: 'fld_1',
        operator: 'equals',
        value: 'teacher',
        action: 'show',
        target_field_key: 'fld_2',
      }],
      { fld_1: 'teacher' },
      {},
      ['fld_1', 'fld_2'],
    )

    expect(effects.visibleByLogic.fld_2).toBe(true)
    expect(effects.isFieldVisibleByLogic('fld_2')).toBe(true)
  })

  it('hide overrides show for same target', () => {
    const effects = evaluator.evaluate(
      [
        {
          field_key: 'fld_1',
          operator: 'equals',
          value: 'yes',
          action: 'show',
          target_field_key: 'fld_2',
        },
        {
          field_key: 'fld_1',
          operator: 'equals',
          value: 'yes',
          action: 'hide',
          target_field_key: 'fld_2',
        },
      ],
      { fld_1: 'yes' },
      {},
      ['fld_1', 'fld_2'],
    )

    expect(effects.hiddenByLogic.fld_2).toBe(true)
    expect(effects.isFieldVisibleByLogic('fld_2')).toBe(false)
  })

  it('require rule marks target as logic required', () => {
    const effects = evaluator.evaluate(
      [{
        field_key: 'fld_1',
        operator: 'equals',
        value: 'admin',
        action: 'require',
        target_field_key: 'fld_3',
      }],
      { fld_1: 'admin' },
      {},
      ['fld_1', 'fld_3'],
    )

    expect(effects.isLogicRequired('fld_3')).toBe(true)
  })

  it('skip section hides all section fields', () => {
    const effects = evaluator.evaluate(
      [{
        field_key: 'fld_1',
        operator: 'equals',
        value: 'skip',
        action: 'skip_section',
        target_section_id: 'sec_2',
      }],
      { fld_1: 'skip' },
      { sec_2: ['fld_4', 'fld_5'] },
      ['fld_1', 'fld_4', 'fld_5'],
    )

    expect(effects.isSectionSkipped('sec_2')).toBe(true)
    expect(effects.isFieldVisibleByLogic('fld_4')).toBe(false)
    expect(effects.isFieldVisibleByLogic('fld_5')).toBe(false)
  })

  it('not_equals operator', () => {
    expect(evaluator.isConditionMet({
      field_key: 'fld_1',
      operator: 'not_equals',
      value: 'teacher',
    }, { fld_1: 'admin' })).toBe(true)

    expect(evaluator.isConditionMet({
      field_key: 'fld_1',
      operator: 'not_equals',
      value: 'teacher',
    }, { fld_1: 'teacher' })).toBe(false)
  })

  it('contains operator for string and array values', () => {
    expect(evaluator.isConditionMet({
      field_key: 'fld_1',
      operator: 'contains',
      value: '@school',
    }, { fld_1: 'parent@school.edu' })).toBe(true)

    expect(evaluator.isConditionMet({
      field_key: 'fld_1',
      operator: 'contains',
      value: 'math',
    }, { fld_1: ['science', 'math'] })).toBe(true)
  })

  it('rules with empty field_key are ignored', () => {
    const effects = evaluator.evaluate(
      [{
        field_key: '',
        operator: 'equals',
        value: 'x',
        action: 'hide',
        target_field_key: 'fld_2',
      }],
      {},
      {},
      ['fld_2'],
    )

    expect(effects.hiddenByLogic.fld_2).toBeUndefined()
  })

  it('evaluator is stable across fixed-point passes', () => {
    const rules = [{
      field_key: 'fld_1',
      operator: 'equals',
      value: 'a',
      action: 'show',
      target_field_key: 'fld_2',
    }]

    const first = evaluator.evaluate(rules, { fld_1: 'a' }, {}, ['fld_1', 'fld_2'])
    const second = evaluator.evaluate(rules, { fld_1: 'a' }, {}, ['fld_1', 'fld_2'])

    expect(first.equals(second)).toBe(true)
  })
})
