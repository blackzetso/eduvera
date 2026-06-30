<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import CategoryPicker from './CategoryPicker.vue'

const props = defineProps({
  studentId: { type: Number, required: true },
  enrollments: { type: Object, required: true },
  lifecycle: { type: Object, required: true },
  categories: { type: Array, default: () => [] },
})

const activeModal = ref(null)
const selectedTopId = ref(null)
const selectedMidId = ref(null)
const selectedSectionId = ref(null)

function today() {
  return new Date().toISOString().slice(0, 10)
}

function defaultAcademicYear() {
  return props.enrollments?.current?.academic_year
    || `${new Date().getFullYear()}-${new Date().getFullYear() + 1}`
}

const promoteForm = useForm({ category_id: '', academic_year: '', enrollment_date: '', notes: '' })
const transferForm = useForm({ category_id: '', transfer_date: '', reason: '', notes: '' })
const withdrawForm = useForm({ withdrawal_date: '', reason: '', notes: '' })
const reEnrollForm = useForm({ category_id: '', academic_year: '', enrollment_date: '', notes: '' })
const graduateForm = useForm({ graduation_date: '', notes: '' })
const statusForm = useForm({ status: '', effective_date: '', reason: '', notes: '' })

function resetCategoryPicker() {
  selectedTopId.value = null
  selectedMidId.value = null
  selectedSectionId.value = null
}

function openModal(name) {
  activeModal.value = name
  resetCategoryPicker()
  const d = today()
  const year = defaultAcademicYear()

  if (name === 'promote') {
    promoteForm.reset()
    promoteForm.academic_year = year
    promoteForm.enrollment_date = d
  } else if (name === 'transfer') {
    transferForm.reset()
    transferForm.transfer_date = d
  } else if (name === 'withdraw') {
    withdrawForm.reset()
    withdrawForm.withdrawal_date = d
  } else if (name === 're_enroll') {
    reEnrollForm.reset()
    reEnrollForm.academic_year = year
    reEnrollForm.enrollment_date = d
  } else if (name === 'graduate') {
    graduateForm.reset()
    graduateForm.graduation_date = d
  } else if (name === 'status') {
    statusForm.reset()
    statusForm.effective_date = d
    statusForm.status = props.lifecycle.status_transitions?.[0]?.value || ''
  }
}

function closeModal() {
  activeModal.value = null
}

function submitPromote() {
  promoteForm.post(route('admin.students.lifecycle.promote', props.studentId), { preserveScroll: true, onSuccess: closeModal })
}
function submitTransfer() {
  transferForm.post(route('admin.students.lifecycle.transfer', props.studentId), { preserveScroll: true, onSuccess: closeModal })
}
function submitWithdraw() {
  withdrawForm.post(route('admin.students.lifecycle.withdraw', props.studentId), { preserveScroll: true, onSuccess: closeModal })
}
function submitReEnroll() {
  reEnrollForm.post(route('admin.students.lifecycle.re-enroll', props.studentId), { preserveScroll: true, onSuccess: closeModal })
}
function submitGraduate() {
  graduateForm.post(route('admin.students.lifecycle.graduate', props.studentId), { preserveScroll: true, onSuccess: closeModal })
}
function submitStatus() {
  statusForm.post(route('admin.students.lifecycle.status', props.studentId), { preserveScroll: true, onSuccess: closeModal })
}

defineExpose({ openModal })
</script>

<template>
  <div>
    <div v-if="activeModal" class="modal show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5)">
      <div class="modal-dialog modal-lg eduvera-modal-dialog">
        <div class="modal-content">
          <template v-if="activeModal === 'promote'">
            <div class="modal-header">
              <h5 class="modal-title">ترقية الطالب</h5>
              <button type="button" class="btn-close" @click="closeModal"></button>
            </div>
            <div class="modal-body">
              <div class="alert alert-light border small mb-3">
                <strong>القيد الحالي:</strong> {{ enrollments.current?.path_label || '—' }} ({{ enrollments.current?.academic_year || '—' }})
              </div>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">العام الدراسي الجديد</label>
                  <input v-model="promoteForm.academic_year" class="form-control" placeholder="2026-2027">
                </div>
                <div class="col-md-6">
                  <label class="form-label">تاريخ القيد</label>
                  <input v-model="promoteForm.enrollment_date" type="date" class="form-control">
                </div>
                <div class="col-12">
                  <label class="form-label">المرحلة / الصف / الفصل الجديد</label>
                  <CategoryPicker
                    :categories="categories"
                    v-model:top-id="selectedTopId"
                    v-model:mid-id="selectedMidId"
                    v-model:section-id="selectedSectionId"
                    v-model:category-id="promoteForm.category_id"
                  />
                </div>
                <div class="col-12">
                  <label class="form-label">ملاحظات</label>
                  <textarea v-model="promoteForm.notes" class="form-control" rows="2"></textarea>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" @click="closeModal">إلغاء</button>
              <button type="button" class="btn btn-primary" :disabled="promoteForm.processing || !promoteForm.category_id" @click="submitPromote">تأكيد الترقية</button>
            </div>
          </template>

          <template v-else-if="activeModal === 'transfer'">
            <div class="modal-header">
              <h5 class="modal-title">نقل الطالب</h5>
              <button type="button" class="btn-close" @click="closeModal"></button>
            </div>
            <div class="modal-body">
              <div class="alert alert-light border small mb-3"><strong>الصف الحالي:</strong> {{ enrollments.current?.path_label || '—' }}</div>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">تاريخ النقل</label>
                  <input v-model="transferForm.transfer_date" type="date" class="form-control">
                </div>
                <div class="col-md-6">
                  <label class="form-label">السبب</label>
                  <input v-model="transferForm.reason" class="form-control">
                </div>
                <div class="col-12">
                  <label class="form-label">الصف الجديد</label>
                  <CategoryPicker
                    :categories="categories"
                    v-model:top-id="selectedTopId"
                    v-model:mid-id="selectedMidId"
                    v-model:section-id="selectedSectionId"
                    v-model:category-id="transferForm.category_id"
                  />
                </div>
                <div class="col-12">
                  <label class="form-label">ملاحظات</label>
                  <textarea v-model="transferForm.notes" class="form-control" rows="2"></textarea>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" @click="closeModal">إلغاء</button>
              <button type="button" class="btn btn-primary" :disabled="transferForm.processing || !transferForm.category_id" @click="submitTransfer">تأكيد النقل</button>
            </div>
          </template>

          <template v-else-if="activeModal === 'withdraw'">
            <div class="modal-header">
              <h5 class="modal-title">انسحاب الطالب</h5>
              <button type="button" class="btn-close" @click="closeModal"></button>
            </div>
            <div class="modal-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">تاريخ الانسحاب</label>
                  <input v-model="withdrawForm.withdrawal_date" type="date" class="form-control">
                </div>
                <div class="col-md-6">
                  <label class="form-label">السبب</label>
                  <input v-model="withdrawForm.reason" class="form-control">
                </div>
                <div class="col-12">
                  <label class="form-label">ملاحظات</label>
                  <textarea v-model="withdrawForm.notes" class="form-control" rows="2"></textarea>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" @click="closeModal">إلغاء</button>
              <button type="button" class="btn btn-danger" :disabled="withdrawForm.processing || !withdrawForm.reason" @click="submitWithdraw">تأكيد الانسحاب</button>
            </div>
          </template>

          <template v-else-if="activeModal === 're_enroll'">
            <div class="modal-header">
              <h5 class="modal-title">إعادة قيد الطالب</h5>
              <button type="button" class="btn-close" @click="closeModal"></button>
            </div>
            <div class="modal-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">العام الدراسي</label>
                  <input v-model="reEnrollForm.academic_year" class="form-control">
                </div>
                <div class="col-md-6">
                  <label class="form-label">تاريخ القيد</label>
                  <input v-model="reEnrollForm.enrollment_date" type="date" class="form-control">
                </div>
                <div class="col-12">
                  <label class="form-label">الصف الجديد</label>
                  <CategoryPicker
                    :categories="categories"
                    v-model:top-id="selectedTopId"
                    v-model:mid-id="selectedMidId"
                    v-model:section-id="selectedSectionId"
                    v-model:category-id="reEnrollForm.category_id"
                  />
                </div>
                <div class="col-12">
                  <label class="form-label">ملاحظات</label>
                  <textarea v-model="reEnrollForm.notes" class="form-control" rows="2"></textarea>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" @click="closeModal">إلغاء</button>
              <button type="button" class="btn btn-primary" :disabled="reEnrollForm.processing || !reEnrollForm.category_id" @click="submitReEnroll">تأكيد إعادة القيد</button>
            </div>
          </template>

          <template v-else-if="activeModal === 'graduate'">
            <div class="modal-header">
              <h5 class="modal-title">تخريج الطالب</h5>
              <button type="button" class="btn-close" @click="closeModal"></button>
            </div>
            <div class="modal-body">
              <div class="alert alert-info small">سيتم إغلاق القيد النشط وتعيين حالة الطالب إلى «متخرج».</div>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">تاريخ التخرج</label>
                  <input v-model="graduateForm.graduation_date" type="date" class="form-control">
                </div>
                <div class="col-12">
                  <label class="form-label">ملاحظات</label>
                  <textarea v-model="graduateForm.notes" class="form-control" rows="2"></textarea>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" @click="closeModal">إلغاء</button>
              <button type="button" class="btn btn-info" :disabled="graduateForm.processing" @click="submitGraduate">تأكيد التخرج</button>
            </div>
          </template>

          <template v-else-if="activeModal === 'status'">
            <div class="modal-header">
              <h5 class="modal-title">تغيير حالة الطالب</h5>
              <button type="button" class="btn-close" @click="closeModal"></button>
            </div>
            <div class="modal-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">الحالة الجديدة</label>
                  <select v-model="statusForm.status" class="form-select">
                    <option v-for="opt in lifecycle.status_transitions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">تاريخ السريان</label>
                  <input v-model="statusForm.effective_date" type="date" class="form-control">
                </div>
                <div class="col-12">
                  <label class="form-label">السبب</label>
                  <input v-model="statusForm.reason" class="form-control">
                </div>
                <div class="col-12">
                  <label class="form-label">ملاحظات</label>
                  <textarea v-model="statusForm.notes" class="form-control" rows="2"></textarea>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" @click="closeModal">إلغاء</button>
              <button type="button" class="btn btn-primary" :disabled="statusForm.processing || !statusForm.status" @click="submitStatus">تأكيد</button>
            </div>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>
