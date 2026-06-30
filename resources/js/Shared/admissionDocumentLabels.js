/** Presentation-layer Arabic labels for admission document checklist items. */

const KEY_LABELS_AR = {
  'birth-certificate-copy': 'شهادة الميلاد',
  'birth-certificate': 'شهادة الميلاد',
  'immunization-record': 'سجل التطعيمات',
  'passport-size-photographs': 'الصور الشخصية',
  'previous-school-reports-2-years': 'تقارير المدرسة السابقة',
  'previous-school-reports': 'تقارير المدرسة السابقة',
}

const TEXT_LABELS_AR = {
  'Birth certificate (copy)': 'شهادة الميلاد',
  'Birth certificate': 'شهادة الميلاد',
  'Immunization record': 'سجل التطعيمات',
  'Passport-size photographs': 'الصور الشخصية',
  'Previous school reports (2 years)': 'تقارير المدرسة السابقة',
  'Previous school reports': 'تقارير المدرسة السابقة',
}

export const DOCUMENT_STATUS_LABELS_AR = {
  needs_upload: 'يحتاج رفع',
  review_pending: 'قيد المراجعة',
  submitted: 'مُقدَّم',
  pending: 'قيد المراجعة',
  approved: 'معتمد',
  reupload_required: 'يحتاج إعادة رفع',
  rejected: 'مرفوض',
}

const REVIEWABLE_STATUSES = new Set(['review_pending', 'submitted', 'pending'])

export function admissionDocumentLabelAr(doc) {
  if (!doc) return '—'

  const byKey = KEY_LABELS_AR[doc.document_key]
  if (byKey) return byKey

  const byText = TEXT_LABELS_AR[doc.label]
  if (byText) return byText

  if (doc.label && /[\u0600-\u06FF]/.test(doc.label)) {
    return doc.label
  }

  return 'مستند'
}

export function admissionDocumentStatusLabelAr(status, fallback = '') {
  if (DOCUMENT_STATUS_LABELS_AR[status]) {
    return DOCUMENT_STATUS_LABELS_AR[status]
  }

  if (fallback && /[\u0600-\u06FF]/.test(fallback)) {
    return fallback
  }

  return '—'
}

export function isDocumentReviewable(doc) {
  return REVIEWABLE_STATUSES.has(doc?.status)
}

export function showParentCommunication(doc) {
  return ['reupload_required', 'rejected'].includes(doc?.status) && !!doc?.notes
}
