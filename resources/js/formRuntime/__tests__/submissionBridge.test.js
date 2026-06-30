import { describe, expect, it } from 'vitest'
import {
  SubmissionBridgeError,
  fieldErrorsFromPayload,
  isAccessDenied,
  isSnapshotMismatch,
  isValidationError,
  mapAxiosError,
} from '../submissionBridge'

describe('submissionBridge', () => {
  it('maps 409 snapshot mismatch', () => {
    const error = new SubmissionBridgeError({
      message: 'Snapshot mismatch',
      reason: 'snapshot_mismatch',
    }, 409)

    expect(isSnapshotMismatch(error)).toBe(true)
    expect(isValidationError(error)).toBe(false)
    expect(isAccessDenied(error)).toBe(false)
  })

  it('maps 422 validation errors', () => {
    const error = new SubmissionBridgeError({
      message: 'Validation failed',
      reason: 'validation_failed',
      errors: [{ field_key: 'fld_1', message: 'مطلوب', message_en: 'Required' }],
    }, 422)

    expect(isValidationError(error)).toBe(true)
    expect(isSnapshotMismatch(error)).toBe(false)
  })

  it('maps 403 access denied', () => {
    const error = new SubmissionBridgeError({
      message: 'Forbidden',
      reason: 'form_closed',
    }, 403)

    expect(isAccessDenied(error)).toBe(true)
  })

  it('fieldErrorsFromPayload picks locale message', () => {
    const payload = {
      errors: [
        { field_key: 'fld_1', message: 'عربي', message_en: 'English' },
      ],
    }

    expect(fieldErrorsFromPayload(payload, 'ar').fld_1).toBe('عربي')
    expect(fieldErrorsFromPayload(payload, 'en').fld_1).toBe('English')
  })

  it('fetchSubmission is exported', async () => {
    const { fetchSubmission } = await import('../submissionBridge')

    expect(typeof fetchSubmission).toBe('function')
  })

  it('mapAxiosError wraps response payload', () => {
    const wrapped = mapAxiosError({
      response: {
        status: 422,
        data: { message: 'Bad', reason: 'validation_failed', errors: [] },
      },
    })

    expect(wrapped).toBeInstanceOf(SubmissionBridgeError)
    expect(wrapped.status).toBe(422)
    expect(wrapped.reason).toBe('validation_failed')
  })
})
