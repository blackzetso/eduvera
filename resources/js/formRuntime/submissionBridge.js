import axios from 'axios'

export class SubmissionBridgeError extends Error {
  constructor(payload, status) {
    super(payload?.message ?? 'Request failed')
    this.name = 'SubmissionBridgeError'
    this.status = status
    this.reason = payload?.reason ?? 'unknown'
    this.payload = payload ?? {}
  }
}

function apiBase(formId) {
  return `/api/forms/${formId}`
}

export async function fetchRuntime(formId, locale = 'ar') {
  const { data } = await axios.get(`${apiBase(formId)}/runtime`, {
    params: { locale },
  })

  return data
}

export async function fetchSubmission(formId, submissionId) {
  try {
    const { data } = await axios.get(`${apiBase(formId)}/submissions/${submissionId}`)

    return data.submission ?? data
  } catch (error) {
    throw mapAxiosError(error)
  }
}

export async function submitForm(formId, body) {
  try {
    const { data } = await axios.post(`${apiBase(formId)}/submissions`, body)

    return data
  } catch (error) {
    throw mapAxiosError(error)
  }
}

export function mapAxiosError(error) {
  const status = error.response?.status ?? 0
  const payload = error.response?.data ?? {
    message: error.message,
    reason: 'network_error',
    errors: [],
  }

  return new SubmissionBridgeError(payload, status)
}

export function isSnapshotMismatch(error) {
  return error instanceof SubmissionBridgeError
    && (error.status === 409 || error.reason === 'snapshot_mismatch')
}

export function isValidationError(error) {
  return error instanceof SubmissionBridgeError
    && (error.status === 422 || error.reason === 'validation_failed')
}

export function isAccessDenied(error) {
  return error instanceof SubmissionBridgeError && error.status === 403
}

export function fieldErrorsFromPayload(payload, locale = 'ar') {
  const errors = payload?.errors ?? []

  return errors.reduce((map, item) => {
    const key = item.field_key

    if (key) {
      map[key] = locale === 'en'
        ? (item.message_en ?? item.message)
        : (item.message ?? item.message_ar)
    }

    return map
  }, {})
}
