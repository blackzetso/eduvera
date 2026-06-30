import { describe, expect, it } from 'vitest'
import { formLogicEvaluator } from '../FormLogicEvaluator'
import { mergeSubmissionValues, setRuntimeSupportedTypes } from '../runtimeHelpers'

describe('ResumeFlow', () => {
  const sections = [{
    id: 'sec_1',
    fields: [
      { key: 'fld_1', type: 'select', default_value: '' },
      { key: 'fld_2', type: 'text', default_value: '' },
      { key: 'fld_grade', type: 'grade', default_value: '' },
    ],
  }]

  const logicRules = [{
    field_key: 'fld_1',
    operator: 'equals',
    value: 'teacher',
    action: 'show',
    target_field_key: 'fld_2',
  }]

  it('restores draft values into runtime shape', () => {
    setRuntimeSupportedTypes(['text', 'select'])

    const merged = mergeSubmissionValues(sections, {
      fld_1: 'teacher',
      fld_2: 'Restored Name',
      fld_grade: 'should-not-apply',
    })

    expect(merged.fld_1).toBe('teacher')
    expect(merged.fld_2).toBe('Restored Name')
    expect(merged.fld_grade).toBe('should-not-apply')
  })

  it('recalculates logic effects after draft restore', () => {
    const merged = mergeSubmissionValues(sections, {
      fld_1: 'teacher',
      fld_2: 'Restored Name',
    })

    const sectionFieldIndex = { sec_1: ['fld_1', 'fld_2', 'fld_grade'] }
    const effects = formLogicEvaluator.evaluate(
      logicRules,
      merged,
      sectionFieldIndex,
      ['fld_1', 'fld_2'],
    )

    expect(effects.isFieldVisibleByLogic('fld_2')).toBe(true)
    expect(effects.isFieldRequired({
      key: 'fld_2',
      type: 'text',
      sectionId: 'sec_1',
      required: false,
      hidden: false,
      readonly: false,
      validation: {},
    })).toBe(false)
  })

  it('ignores _meta when merging submission data', () => {
    const merged = mergeSubmissionValues(sections, {
      fld_1: 'x',
      _meta: { snapshot_hash: 'stale' },
    })

    expect(merged._meta).toBeUndefined()
    expect(merged.fld_1).toBe('x')
  })
})
