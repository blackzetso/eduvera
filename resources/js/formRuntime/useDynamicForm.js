import { computed, reactive, ref, watch } from 'vue'
import { formLogicEvaluator } from './FormLogicEvaluator'
import { formValidationService } from './FormValidationService'
import {
  buildSectionFieldIndex,
  collectAllFieldKeys,
  collectSubmittableFields,
  initValuesFromRuntime,
  mergeSubmissionValues,
  setRuntimeSupportedTypes,
} from './runtimeHelpers'
import {
  fetchRuntime,
  fetchSubmission,
  fieldErrorsFromPayload,
  isAccessDenied,
  isSnapshotMismatch,
  isValidationError,
  submitForm,
  SubmissionBridgeError,
} from './submissionBridge'

export function useDynamicForm(options) {
  const formId = options.formId
  const locale = ref(options.locale ?? 'ar')
  const submissionId = ref(options.submissionId ?? null)

  const runtime = ref(null)
  const values = reactive({})
  const fieldErrors = reactive({})
  const formError = ref(null)
  const formErrorReason = ref(null)
  const loading = ref(false)
  const submitting = ref(false)
  const successMessage = ref(null)
  const lastSubmission = ref(null)

  const sections = computed(() => runtime.value?.sections ?? [])
  const logicRules = computed(() => runtime.value?.logic_rules ?? [])
  const capabilities = computed(() => runtime.value?.capabilities ?? {})
  const settings = computed(() => runtime.value?.settings ?? {})
  const snapshotHash = computed(() => runtime.value?.form?.snapshot_hash ?? '')
  const formMeta = computed(() => runtime.value?.form ?? {})
  const allowsDraft = computed(() => Boolean(capabilities.value.draft))

  const sectionFieldIndex = computed(() => buildSectionFieldIndex(sections.value))
  const allFieldKeys = computed(() => collectAllFieldKeys(sections.value))

  const effects = computed(() => formLogicEvaluator.evaluate(
    logicRules.value,
    values,
    sectionFieldIndex.value,
    allFieldKeys.value,
  ))

  const submittableFields = computed(() => collectSubmittableFields(sections.value, effects.value))

  function applyRuntimePayload(payload, initialData = null) {
    runtime.value = payload
    setRuntimeSupportedTypes(payload.capabilities?.supported_field_types)

    const seeded = initialData
      ? mergeSubmissionValues(payload.sections, initialData)
      : initValuesFromRuntime(payload.sections)

    Object.keys(values).forEach((key) => delete values[key])
    Object.assign(values, seeded)
  }

  async function loadSubmissionData(id) {
    const submission = await fetchSubmission(formId, id)

    if (submission.status && submission.status !== 'draft') {
      throw new SubmissionBridgeError({
        message: 'Only draft submissions can be resumed.',
        reason: 'not_allowed',
      }, 403)
    }

    return submission.data ?? {}
  }

  async function loadRuntime(initialData = null) {
    loading.value = true
    formError.value = null
    formErrorReason.value = null

    try {
      const payload = await fetchRuntime(formId, locale.value)
      let resumeData = initialData

      if (submissionId.value && !resumeData) {
        resumeData = await loadSubmissionData(submissionId.value)
      }

      applyRuntimePayload(payload, resumeData)
    } catch (error) {
      handleLoadError(error)
    } finally {
      loading.value = false
    }
  }

  function handleLoadError(error) {
    if (error instanceof SubmissionBridgeError) {
      formError.value = error.payload.message ?? error.message
      formErrorReason.value = error.reason
    } else {
      formError.value = error.message ?? 'Failed to load form'
      formErrorReason.value = 'load_failed'
    }
  }

  function clearFieldErrors() {
    Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
  }

  function applyServerFieldErrors(payload) {
    clearFieldErrors()
    Object.assign(fieldErrors, fieldErrorsFromPayload(payload, locale.value))
  }

  function validateClient() {
    clearFieldErrors()

    const result = formValidationService.validate(
      submittableFields.value,
      values,
      effects.value,
      locale.value,
    )

    if (!result.valid) {
      for (const error of result.errors) {
        fieldErrors[error.field_key] = error.message
      }
    }

    return result.valid
  }

  async function reloadAfterSnapshotMismatch() {
    const currentValues = { ...values }
    await loadRuntime(currentValues)
    formError.value = locale.value === 'en'
      ? 'Form definition has changed. Please review and submit again.'
      : 'تغيّر تعريف النموذج. يرجى المراجعة والإرسال مرة أخرى.'
    formErrorReason.value = 'snapshot_mismatch'
  }

  async function postSubmission(targetStatus) {
    if (!runtime.value) {
      return
    }

    submitting.value = true
    formError.value = null
    formErrorReason.value = null
    successMessage.value = null
    clearFieldErrors()

    const isDraft = targetStatus === 'draft'

    if (!isDraft && !validateClient()) {
      submitting.value = false
      return
    }

    const body = {
      locale: locale.value,
      target_status: targetStatus,
      submission_id: submissionId.value,
      data: { ...values },
    }

    if (!isDraft) {
      body.snapshot_hash = snapshotHash.value
    }

    try {
      const result = await submitForm(formId, body)
      lastSubmission.value = result.submission ?? null
      submissionId.value = result.submission?.id ?? submissionId.value
      successMessage.value = locale.value === 'en'
        ? (result.message_en ?? result.message)
        : result.message
    } catch (error) {
      if (isSnapshotMismatch(error)) {
        await reloadAfterSnapshotMismatch()
      } else if (isValidationError(error)) {
        applyServerFieldErrors(error.payload)
        formError.value = error.payload.message ?? 'Validation failed'
        formErrorReason.value = 'validation_failed'
      } else if (isAccessDenied(error)) {
        formError.value = error.payload.message ?? error.message
        formErrorReason.value = error.payload.reason ?? 'access_denied'
      } else if (error instanceof SubmissionBridgeError) {
        formError.value = error.payload.message ?? error.message
        formErrorReason.value = error.reason
      } else {
        formError.value = error.message ?? 'Submission failed'
        formErrorReason.value = 'submit_failed'
      }
    } finally {
      submitting.value = false
    }
  }

  function submitFinal() {
    return postSubmission('submitted')
  }

  function saveDraft() {
    return postSubmission('draft')
  }

  watch(locale, () => {
    if (runtime.value) {
      loadRuntime({ ...values })
    }
  })

  return {
    locale,
    runtime,
    values,
    fieldErrors,
    formError,
    formErrorReason,
    loading,
    submitting,
    successMessage,
    lastSubmission,
    submissionId,
    sections,
    effects,
    formMeta,
    settings,
    capabilities,
    allowsDraft,
    snapshotHash,
    loadRuntime,
    submitFinal,
    saveDraft,
    validateClient,
  }
}
