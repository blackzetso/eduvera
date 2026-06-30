<script setup>
import { computed, ref } from 'vue'
import { documentBadge } from '@/Shared/admissionsBadges'
import {
  admissionDocumentLabelAr,
  admissionDocumentStatusLabelAr,
  isDocumentReviewable,
  showParentCommunication,
} from '@/Shared/admissionDocumentLabels'
import {
  resolveDocumentPreviewType,
  admissionDocumentDownloadUrl,
  documentSummaryProgress,
  requiredDocumentStatusBreakdown,
} from '@/composables/useAdmissionDocumentPreview'
import AdmissionDocumentPreviewModal from '../AdmissionDocumentPreviewModal.vue'

const props = defineProps({
  applicationId: { type: Number, required: true },
  documents: { type: Array, default: () => [] },
  documentForms: { type: Object, required: true },
  documentSummary: { type: Object, default: () => ({}) },
  filterOptions: { type: Object, default: () => ({}) },
  isReadOnly: { type: Boolean, default: false },
})

const emit = defineEmits(['save', 'upload', 'remove-file', 'review'])

const previewOpen = ref(false)
const previewDocument = ref(null)
const previewType = ref(null)
const reuploadModalOpen = ref(false)
const reuploadDocId = ref(null)
const reuploadReason = ref('')
const rejectModalOpen = ref(false)
const rejectDocId = ref(null)
const rejectReason = ref('')
const editDocId = ref(null)

const progress = computed(() => documentSummaryProgress(props.documentSummary))

const summaryBadges = computed(() => requiredDocumentStatusBreakdown(props.documentSummary))

function openPreview(doc) {
  if (!doc.file_path) return
  previewDocument.value = doc
  previewType.value = resolveDocumentPreviewType(doc)
  previewOpen.value = true
}

function closePreview() {
  previewOpen.value = false
  previewDocument.value = null
  previewType.value = null
}

function downloadUrl(doc) {
  return admissionDocumentDownloadUrl(props.applicationId, doc.id, false)
}

function parentPreview(doc) {
  return doc.parent_communication || {
    document_label: admissionDocumentLabelAr(doc),
    status_label: admissionDocumentStatusLabelAr(doc.status, doc.status_label),
    admin_note: doc.notes,
  }
}

function openReuploadModal(docId) {
  reuploadDocId.value = docId
  reuploadReason.value = ''
  reuploadModalOpen.value = true
}

function closeReuploadModal() {
  reuploadModalOpen.value = false
  reuploadDocId.value = null
  reuploadReason.value = ''
}

function submitReupload() {
  if (!reuploadDocId.value || !reuploadReason.value.trim()) return
  emit('review', reuploadDocId.value, 'reupload', { reupload_reason: reuploadReason.value.trim() })
  closeReuploadModal()
}

function submitReview(docId, action) {
  emit('review', docId, action, {})
}

function openRejectModal(docId) {
  rejectDocId.value = docId
  rejectReason.value = ''
  rejectModalOpen.value = true
}

function closeRejectModal() {
  rejectModalOpen.value = false
  rejectDocId.value = null
  rejectReason.value = ''
}

function submitReject() {
  if (!rejectDocId.value || !rejectReason.value.trim()) return
  emit('review', rejectDocId.value, 'reject', { reject_reason: rejectReason.value.trim() })
  closeRejectModal()
}

function displayFilename(doc) {
  if (!doc.file_path) return ''
  return doc.original_filename || doc.file_path.split('/').pop() || doc.file_path
}

function truncateFilename(name, max = 22) {
  if (!name) return ''
  if (name.length <= max) return name
  return `${name.slice(0, max - 3)}...`
}

function canUpload(doc) {
  return !props.isReadOnly && ['needs_upload', 'reupload_required'].includes(doc.status)
}

function canEdit(doc) {
  return !props.isReadOnly && !isDocumentReviewable(doc)
}

function showEditPanel(doc) {
  return editDocId.value === doc.id
}

function toggleEdit(doc) {
  editDocId.value = editDocId.value === doc.id ? null : doc.id
}

function triggerUpload(docId, mobile = false) {
  const prefix = mobile ? 'admission-doc-upload-mobile' : 'admission-doc-upload'
  document.getElementById(`${prefix}-${docId}`)?.click()
}

function onUpload(docId, event) {
  emit('upload', docId, event)
}
</script>

<template>
  <div class="admissions-documents-tab">
    <div v-if="isReadOnly" class="alert alert-secondary mb-3 py-2 small">
      الطلب محوّل — تحديث حالة المستندات معطّل.
    </div>

    <div v-if="documents.length" class="card admissions-command-card border-0 shadow-sm mb-3">
      <div class="card-body py-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
          <div class="fw-semibold">المستندات المعتمدة</div>
          <div class="small text-muted">
            {{ progress.completed }} / {{ progress.total }}
            <span class="fw-bold text-success ms-1">{{ progress.percent }}%</span>
          </div>
        </div>
        <div class="progress rounded-pill mb-3" style="height: 8px;">
          <div
            class="progress-bar bg-success rounded-pill"
            role="progressbar"
            :style="{ width: `${progress.percent}%` }"
            :aria-valuenow="progress.percent"
            aria-valuemin="0"
            aria-valuemax="100"
          ></div>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <span
            v-for="badge in summaryBadges"
            :key="badge.key"
            class="badge rounded-pill"
            :class="documentBadge(badge.key)"
          >
            {{ badge.label }}: {{ badge.count ?? 0 }}
          </span>
        </div>
      </div>
    </div>

    <div v-if="!documents.length" class="text-muted">لا توجد مستندات في القائمة.</div>

    <!-- Desktop: dense table -->
    <div v-if="documents.length" class="card admissions-command-card border-0 shadow-sm mb-3 d-none d-lg-block">
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle admissions-documents-table mb-0">
          <thead class="table-light">
            <tr>
              <th scope="col">المستند</th>
              <th scope="col">الحالة</th>
              <th scope="col">الملف</th>
              <th scope="col" class="text-end">الإجراءات</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="doc in documents" :key="`desktop-${doc.id}`">
              <tr v-if="documentForms[doc.id]">
                <td class="admissions-documents-table__name">
                  <div class="fw-semibold text-truncate" style="max-width: 14rem;">
                    {{ admissionDocumentLabelAr(doc) }}
                    <span v-if="doc.required" class="text-danger">*</span>
                  </div>
                </td>
                <td>
                  <span class="badge rounded-pill" :class="documentBadge(doc.status)">
                    {{ admissionDocumentStatusLabelAr(doc.status, doc.status_label) }}
                  </span>
                </td>
                <td class="admissions-documents-table__file">
                  <button
                    v-if="doc.file_path"
                    type="button"
                    class="btn btn-link btn-sm p-0 text-decoration-none admissions-documents-table__filename"
                    :title="displayFilename(doc)"
                    @click="openPreview(doc)"
                  >
                    {{ truncateFilename(displayFilename(doc)) }}
                  </button>
                  <span v-else class="text-muted">—</span>
                </td>
                <td class="text-end">
                  <div class="d-inline-flex flex-wrap justify-content-end gap-1 admissions-documents-table__actions">
                    <template v-if="doc.file_path">
                      <button type="button" class="btn btn-sm btn-outline-info" @click="openPreview(doc)">
                        <i class="bi bi-eye"></i><span class="ms-1">عرض</span>
                      </button>
                      <a
                        :href="downloadUrl(doc)"
                        class="btn btn-sm btn-outline-primary"
                        target="_blank"
                        rel="noopener"
                      >
                        <i class="bi bi-download"></i><span class="ms-1">تحميل</span>
                      </a>
                      <button
                        v-if="canEdit(doc)"
                        type="button"
                        class="btn btn-sm btn-outline-secondary"
                        :class="{ active: showEditPanel(doc) }"
                        @click="toggleEdit(doc)"
                      >
                        <i class="bi bi-pencil"></i><span class="ms-1">تحديث</span>
                      </button>
                      <button
                        v-if="!isReadOnly"
                        type="button"
                        class="btn btn-sm btn-outline-danger"
                        @click="emit('remove-file', doc.id)"
                      >
                        <i class="bi bi-trash"></i><span class="ms-1">حذف</span>
                      </button>
                    </template>
                    <template v-if="canUpload(doc)">
                      <input
                        :id="`admission-doc-upload-${doc.id}`"
                        type="file"
                        class="d-none"
                        accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx"
                        @change="onUpload(doc.id, $event)"
                      />
                      <button type="button" class="btn btn-sm btn-outline-primary" @click="triggerUpload(doc.id)">
                        <i class="bi bi-upload"></i><span class="ms-1">رفع</span>
                      </button>
                    </template>
                    <template v-if="isDocumentReviewable(doc) && !isReadOnly">
                      <button type="button" class="btn btn-sm btn-success" @click="submitReview(doc.id, 'approve')">
                        <i class="bi bi-check-circle"></i><span class="ms-1">اعتماد</span>
                      </button>
                      <button type="button" class="btn btn-sm btn-warning" @click="openReuploadModal(doc.id)">
                        <i class="bi bi-arrow-repeat"></i><span class="ms-1">يحتاج إعادة رفع</span>
                      </button>
                      <button type="button" class="btn btn-sm btn-danger" @click="openRejectModal(doc.id)">
                        <i class="bi bi-x-circle"></i><span class="ms-1">رفض</span>
                      </button>
                    </template>
                  </div>
                </td>
              </tr>
              <tr
                v-if="documentForms[doc.id] && (showEditPanel(doc) || showParentCommunication(doc))"
                class="admissions-documents-table__details"
              >
                <td colspan="4" class="py-2 px-3">
                  <form
                    v-if="showEditPanel(doc)"
                    class="admissions-documents-table__edit"
                    @submit.prevent="emit('save', doc.id)"
                  >
                    <div class="row g-2 align-items-end">
                      <div
                        v-if="!['approved', 'rejected'].includes(doc.status)"
                        class="col-md-4"
                      >
                        <label class="form-label small text-muted mb-1">تحديث الحالة</label>
                        <select v-model="documentForms[doc.id].status" class="form-select form-select-sm">
                          <option
                            v-for="o in filterOptions.document_statuses"
                            :key="o.value"
                            :value="o.value"
                          >
                            {{ admissionDocumentStatusLabelAr(o.value, o.label) }}
                          </option>
                        </select>
                      </div>
                      <div class="col-md-5">
                        <label class="form-label small text-muted mb-1">ملاحظات التحقق</label>
                        <input
                          v-model="documentForms[doc.id].notes"
                          class="form-control form-control-sm"
                          placeholder="ملاحظات داخلية أو سبب الرفض"
                        />
                      </div>
                      <div class="col-md-3">
                        <button type="submit" class="btn btn-sm btn-primary w-100">حفظ التحديث</button>
                      </div>
                    </div>
                  </form>
                  <div
                    v-if="showParentCommunication(doc)"
                    class="alert py-2 small mb-0 mt-2"
                    :class="doc.status === 'rejected' ? 'alert-danger' : 'alert-warning'"
                  >
                    <div class="fw-semibold mb-1">معاينة رسالة ولي الأمر</div>
                    <div>{{ parentPreview(doc).document_label || admissionDocumentLabelAr(doc) }}</div>
                    <div>الحالة: {{ parentPreview(doc).status_label }}</div>
                    <div v-if="parentPreview(doc).admin_note">
                      ملاحظة الإدارة: {{ parentPreview(doc).admin_note }}
                    </div>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Mobile & tablet: card layout -->
    <div
      v-for="doc in documents"
      :key="`mobile-${doc.id}`"
      class="card admissions-command-card border-0 shadow-sm mb-2 d-lg-none admissions-documents-card"
    >
      <form
        v-if="documentForms[doc.id]"
        class="card-body py-3"
        @submit.prevent="emit('save', doc.id)"
      >
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
          <div class="min-w-0 flex-grow-1">
            <div class="fw-semibold">
              {{ admissionDocumentLabelAr(doc) }}
              <span v-if="doc.required" class="text-danger">*</span>
            </div>
          </div>
          <span class="badge rounded-pill flex-shrink-0" :class="documentBadge(doc.status)">
            {{ admissionDocumentStatusLabelAr(doc.status, doc.status_label) }}
          </span>
        </div>

        <div class="mb-2 small">
          <button
            v-if="doc.file_path"
            type="button"
            class="btn btn-link btn-sm p-0 text-decoration-none"
            :title="displayFilename(doc)"
            @click="openPreview(doc)"
          >
            {{ truncateFilename(displayFilename(doc), 40) }}
          </button>
          <span v-else class="text-muted">—</span>
        </div>

        <div
          v-if="showParentCommunication(doc)"
          class="alert py-2 small mb-2"
          :class="doc.status === 'rejected' ? 'alert-danger' : 'alert-warning'"
        >
          <div class="fw-semibold mb-1">معاينة رسالة ولي الأمر</div>
          <div>{{ parentPreview(doc).document_label || admissionDocumentLabelAr(doc) }}</div>
          <div>الحالة: {{ parentPreview(doc).status_label }}</div>
          <div v-if="parentPreview(doc).admin_note">
            ملاحظة الإدارة: {{ parentPreview(doc).admin_note }}
          </div>
        </div>

        <div v-if="isDocumentReviewable(doc) && !isReadOnly" class="d-flex flex-wrap gap-1 mb-2">
          <button type="button" class="btn btn-sm btn-success" @click="submitReview(doc.id, 'approve')">
            <i class="bi bi-check-circle me-1"></i>اعتماد
          </button>
          <button type="button" class="btn btn-sm btn-warning" @click="openReuploadModal(doc.id)">
            <i class="bi bi-arrow-repeat me-1"></i>يحتاج إعادة رفع
          </button>
          <button type="button" class="btn btn-sm btn-danger" @click="openRejectModal(doc.id)">
            <i class="bi bi-x-circle me-1"></i>رفض
          </button>
        </div>

        <div class="d-flex flex-wrap gap-1 mb-2">
          <template v-if="doc.file_path">
            <button type="button" class="btn btn-sm btn-outline-info" @click="openPreview(doc)">
              <i class="bi bi-eye"></i> عرض
            </button>
            <a
              :href="downloadUrl(doc)"
              class="btn btn-sm btn-outline-primary"
              target="_blank"
              rel="noopener"
            >
              <i class="bi bi-download"></i> تحميل
            </a>
            <button
              v-if="canEdit(doc)"
              type="button"
              class="btn btn-sm btn-outline-secondary"
              :class="{ active: showEditPanel(doc) }"
              @click="toggleEdit(doc)"
            >
              <i class="bi bi-pencil"></i> تحديث
            </button>
            <button
              v-if="!isReadOnly"
              type="button"
              class="btn btn-sm btn-outline-danger"
              @click="emit('remove-file', doc.id)"
            >
              <i class="bi bi-trash"></i> حذف
            </button>
          </template>
          <template v-if="canUpload(doc)">
            <input
              :id="`admission-doc-upload-mobile-${doc.id}`"
              type="file"
              class="d-none"
              accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx"
              @change="onUpload(doc.id, $event)"
            />
            <button type="button" class="btn btn-sm btn-outline-primary" @click="triggerUpload(doc.id, true)">
              <i class="bi bi-upload"></i> رفع
            </button>
          </template>
        </div>

        <div v-if="showEditPanel(doc) && canEdit(doc)" class="border-top pt-2 mt-1">
          <div v-if="!['approved', 'rejected'].includes(doc.status)" class="mb-2">
            <label class="form-label small text-muted mb-1">تحديث الحالة</label>
            <select v-model="documentForms[doc.id].status" class="form-select form-select-sm">
              <option
                v-for="o in filterOptions.document_statuses"
                :key="o.value"
                :value="o.value"
              >
                {{ admissionDocumentStatusLabelAr(o.value, o.label) }}
              </option>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label small text-muted mb-1">ملاحظات التحقق</label>
            <input
              v-model="documentForms[doc.id].notes"
              class="form-control form-control-sm"
              placeholder="ملاحظات داخلية أو سبب الرفض"
            />
          </div>
          <button type="submit" class="btn btn-sm btn-outline-primary">حفظ التحديث</button>
        </div>
      </form>
    </div>

    <AdmissionDocumentPreviewModal
      :show="previewOpen"
      :document="previewDocument"
      :application-id="applicationId"
      :preview-type="previewType"
      :title="previewDocument ? admissionDocumentLabelAr(previewDocument) : '—'"
      @close="closePreview"
    />

    <div
      v-if="reuploadModalOpen"
      class="modal fade show d-block"
      tabindex="-1"
      style="background: rgba(15, 23, 42, 0.45);"
      @click.self="closeReuploadModal"
    >
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
          <div class="modal-header border-0">
            <h5 class="modal-title fw-bold">يحتاج إعادة رفع</h5>
            <button type="button" class="btn-close" @click="closeReuploadModal"></button>
          </div>
          <div class="modal-body">
            <label class="form-label small fw-semibold">سبب إعادة الرفع <span class="text-danger">*</span></label>
            <textarea
              v-model="reuploadReason"
              class="form-control"
              rows="4"
              placeholder="مثال: الصورة غير واضحة، يرجى إعادة الرفع..."
              required
            ></textarea>
            <div class="form-text">ستُعرض هذه الملاحظة لولي الأمر عند إعادة الرفع.</div>
          </div>
          <div class="modal-footer border-0">
            <button type="button" class="btn btn-outline-secondary" @click="closeReuploadModal">إلغاء</button>
            <button
              type="button"
              class="btn btn-warning"
              :disabled="!reuploadReason.trim()"
              @click="submitReupload"
            >
              تأكيد
            </button>
          </div>
        </div>
      </div>
    </div>

    <div
      v-if="rejectModalOpen"
      class="modal fade show d-block"
      tabindex="-1"
      style="background: rgba(15, 23, 42, 0.45);"
      @click.self="closeRejectModal"
    >
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
          <div class="modal-header border-0">
            <h5 class="modal-title fw-bold">رفض المستند</h5>
            <button type="button" class="btn-close" @click="closeRejectModal"></button>
          </div>
          <div class="modal-body">
            <label class="form-label small fw-semibold">سبب الرفض <span class="text-danger">*</span></label>
            <textarea
              v-model="rejectReason"
              class="form-control"
              rows="4"
              placeholder="مثال: المستند غير صحيح"
              required
            ></textarea>
          </div>
          <div class="modal-footer border-0">
            <button type="button" class="btn btn-outline-secondary" @click="closeRejectModal">إلغاء</button>
            <button
              type="button"
              class="btn btn-danger"
              :disabled="!rejectReason.trim()"
              @click="submitReject"
            >
              تأكيد الرفض
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.admissions-documents-table th {
  font-size: 0.8rem;
  font-weight: 600;
  white-space: nowrap;
  padding: 0.5rem 0.75rem;
}

.admissions-documents-table td {
  padding: 0.45rem 0.75rem;
  vertical-align: middle;
}

.admissions-documents-table__filename {
  font-size: 0.8125rem;
  max-width: 11rem;
  text-align: start;
}

.admissions-documents-table__actions .btn {
  --bs-btn-padding-y: 0.15rem;
  --bs-btn-padding-x: 0.45rem;
  font-size: 0.75rem;
}

.admissions-documents-table__details > td {
  background: rgba(var(--bs-light-rgb), 0.65);
  border-top: none;
}

.admissions-documents-table__edit {
  max-width: 48rem;
}

.admissions-documents-card .card-body {
  padding-top: 0.75rem;
  padding-bottom: 0.75rem;
}

@media (min-width: 768px) and (max-width: 991.98px) {
  .admissions-documents-card .card-body {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 0.35rem 1rem;
    align-items: center;
  }

  .admissions-documents-card .card-body > :nth-child(1) {
    grid-column: 1;
  }

  .admissions-documents-card .card-body > :nth-child(2) {
    grid-column: 2;
    grid-row: 1 / span 2;
  }

  .admissions-documents-card .card-body > :nth-child(n + 3) {
    grid-column: 1 / -1;
  }
}
</style>
