import { route } from 'ziggy-js'

const IMAGE_MIMES = new Set(['image/jpeg', 'image/jpg', 'image/png', 'image/webp'])
const PDF_MIMES = new Set(['application/pdf'])

export function resolveDocumentPreviewType(doc) {
  if (!doc?.file_path) return null

  const mime = (doc.mime_type || '').toLowerCase()
  const name = (doc.original_filename || doc.file_path || '').toLowerCase()

  if (IMAGE_MIMES.has(mime) || /\.(jpe?g|png|webp)$/.test(name)) {
    return 'image'
  }

  if (PDF_MIMES.has(mime) || name.endsWith('.pdf')) {
    return 'pdf'
  }

  if (
    /\.(doc|docx)$/.test(name)
    || mime.includes('msword')
    || mime.includes('wordprocessingml')
  ) {
    return 'unsupported'
  }

  return 'unsupported'
}

export function admissionDocumentDownloadUrl(applicationId, documentId, preview = false) {
  const url = route('admin.admissions.documents.download', {
    admission: applicationId,
    document: documentId,
  })

  return preview ? `${url}?preview=1` : url
}

export function documentCompletionProgress(documents) {
  const list = documents || []
  const required = list.filter((d) => d.required)
  const pool = required.length ? required : list
  const total = pool.length
  const completed = pool.filter((d) => d.status === 'approved').length
  const percent = total ? Math.round((completed / total) * 100) : 0

  return { total, completed, percent, blocking: Math.max(0, total - completed) }
}

export function documentSummaryProgress(summary) {
  const total = summary?.progress_total ?? summary?.required_total ?? summary?.total ?? 0
  const completed = summary?.progress_approved ?? summary?.required_approved ?? 0
  const percent = summary?.progress_percent ?? (total ? Math.round((completed / total) * 100) : 0)
  const blocking = summary?.required_incomplete ?? Math.max(0, total - completed)

  return { total, completed, percent, blocking }
}

export function requiredDocumentStatusBreakdown(summary) {
  return [
    { key: 'approved', label: 'معتمد', count: summary?.required_approved ?? 0 },
    { key: 'review_pending', label: 'قيد المراجعة', count: summary?.required_pending_review ?? 0 },
    { key: 'reupload_required', label: 'يحتاج إعادة رفع', count: summary?.required_reupload_required ?? 0 },
    { key: 'rejected', label: 'مرفوض', count: summary?.required_rejected ?? 0 },
  ]
}
