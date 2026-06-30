<script setup>
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AdmissionDecisionReadinessPanel from './AdmissionDecisionReadinessPanel.vue'

const props = defineProps({
  applicationId: { type: Number, required: true },
  pipeline: { type: Object, required: true },
  quickActions: { type: Object, default: () => ({}) },
  decisionReadiness: { type: Object, default: () => ({ ready: false, checks: [] }) },
  filterOptions: { type: Object, default: () => ({}) },
})

const canConfirmAccept = computed(() => !!props.decisionReadiness?.ready)

const canConfirmDecision = computed(() => {
  if (activeModal.value === 'accept') {
    return canConfirmAccept.value
  }
  if (['reject', 'withdraw'].includes(activeModal.value)) {
    return decisionForm.reason.trim().length > 0
  }
  return true
})

const activeModal = ref(null)

const stageForm = useForm({ to_stage: '', reason: '', notes: '' })
const assignForm = useForm({ assigned_to_user_id: '', notes: '' })
const noteForm = useForm({ content: '', visibility: 'internal' })
const decisionForm = useForm({ reason: '', notes: '' })

function defaultForwardStage() {
  const options = props.pipeline?.stage_options ?? []
  const next = options.find((opt) => opt.value !== props.pipeline?.current_stage)
  return next?.value ?? ''
}

function openModal(name) {
  activeModal.value = name
  if (name === 'stage') {
    stageForm.clearErrors()
    stageForm.reset()
    stageForm.to_stage = defaultForwardStage()
  } else if (name === 'assign') {
    assignForm.reset()
  } else if (name === 'note') {
    noteForm.reset()
    noteForm.visibility = 'internal'
  } else if (['accept', 'reject', 'waitlist', 'withdraw'].includes(name)) {
    decisionForm.clearErrors()
    decisionForm.reset()
  }
}

function closeModal() {
  activeModal.value = null
}

function submitStage() {
  stageForm.post(route('admin.admissions.stage', props.applicationId), {
    preserveScroll: true,
    preserveState: false,
    onSuccess: closeModal,
  })
}

function submitAssign() {
  assignForm.post(route('admin.admissions.assign', props.applicationId), {
    preserveScroll: true,
    onSuccess: closeModal,
  })
}

function submitNote() {
  noteForm.post(route('admin.admissions.notes.store', props.applicationId), {
    preserveScroll: true,
    onSuccess: closeModal,
  })
}

const decisionRoutes = {
  accept: 'admin.admissions.decision.accept',
  reject: 'admin.admissions.decision.reject',
  waitlist: 'admin.admissions.decision.waitlist',
  withdraw: 'admin.admissions.decision.withdraw',
}

const decisionTitles = {
  accept: 'قبول الطلب',
  reject: 'رفض الطلب',
  waitlist: 'إدراج في قائمة الانتظار',
  withdraw: 'تسجيل انسحاب',
}

function submitDecision(action) {
  const routeName = decisionRoutes[action]
  if (!routeName) return

  decisionForm.post(route(routeName, props.applicationId), {
    preserveScroll: true,
    preserveState: false,
    onSuccess: closeModal,
  })
}

defineExpose({ openModal })
</script>

<template>
  <div>
    <div v-if="activeModal" class="modal show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5)">
      <div class="modal-dialog modal-lg eduvera-modal-dialog">
        <div class="modal-content">
          <div v-if="activeModal === 'stage'" class="modal-body p-4">
            <h5 class="mb-3">تحديث مرحلة الطلب</h5>
            <p class="small text-muted mb-3">
              المرحلة الحالية:
              <strong>{{ pipeline.stage_options.find(o => o.value === pipeline.current_stage)?.label || pipeline.current_stage }}</strong>
            </p>
            <div v-if="Object.keys(stageForm.errors).length" class="alert alert-danger py-2 small">
              <div v-for="(msg, key) in stageForm.errors" :key="key">{{ msg }}</div>
            </div>
            <div class="mb-3">
              <label class="form-label">المرحلة الجديدة</label>
              <select v-model="stageForm.to_stage" class="form-select" required>
                <option value="" disabled>اختر المرحلة</option>
                <option
                  v-for="opt in pipeline.stage_options.filter(o => o.value !== pipeline.current_stage)"
                  :key="opt.value"
                  :value="opt.value"
                >{{ opt.label }}</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">السبب</label>
              <input v-model="stageForm.reason" class="form-control" />
            </div>
            <div class="mb-3">
              <label class="form-label">ملاحظات</label>
              <textarea v-model="stageForm.notes" class="form-control" rows="3" />
            </div>
            <div class="d-flex justify-content-end gap-2">
              <button type="button" class="btn btn-secondary" @click="closeModal">إلغاء</button>
              <button type="button" class="btn btn-primary" :disabled="stageForm.processing" @click="submitStage">حفظ</button>
            </div>
          </div>

          <div v-else-if="activeModal === 'assign'" class="modal-body p-4">
            <h5 class="mb-3">تعيين مسؤول القبول</h5>
            <div class="mb-3">
              <label class="form-label">المسؤول</label>
              <select v-model="assignForm.assigned_to_user_id" class="form-select">
                <option value="">— بدون تعيين —</option>
                <option v-for="opt in filterOptions.officers" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">ملاحظات</label>
              <textarea v-model="assignForm.notes" class="form-control" rows="3" />
            </div>
            <div class="d-flex justify-content-end gap-2">
              <button type="button" class="btn btn-secondary" @click="closeModal">إلغاء</button>
              <button type="button" class="btn btn-primary" :disabled="assignForm.processing" @click="submitAssign">تعيين</button>
            </div>
          </div>

          <div v-else-if="['accept', 'reject', 'waitlist', 'withdraw'].includes(activeModal)" class="modal-body p-4">
            <h5 class="mb-3">{{ decisionTitles[activeModal] }}</h5>
            <AdmissionDecisionReadinessPanel
              v-if="activeModal === 'accept'"
              :decision-readiness="decisionReadiness"
              class="mb-3"
            />
            <div v-if="Object.keys(decisionForm.errors).length" class="alert alert-danger py-2 small">
              <div v-for="(msg, key) in decisionForm.errors" :key="key">{{ msg }}</div>
            </div>
            <div class="mb-3">
              <label class="form-label">
                السبب
                <span v-if="['reject', 'withdraw'].includes(activeModal)" class="text-danger">*</span>
              </label>
              <input
                v-model="decisionForm.reason"
                class="form-control"
                :required="['reject', 'withdraw'].includes(activeModal)"
              />
            </div>
            <div class="mb-3">
              <label class="form-label">ملاحظات</label>
              <textarea v-model="decisionForm.notes" class="form-control" rows="3" />
            </div>
            <div class="d-flex justify-content-end gap-2">
              <button type="button" class="btn btn-secondary" @click="closeModal">إلغاء</button>
              <button
                type="button"
                class="btn"
                :class="activeModal === 'accept' ? 'btn-success' : activeModal === 'reject' ? 'btn-danger' : 'btn-primary'"
                :disabled="decisionForm.processing || !canConfirmDecision"
                @click="submitDecision(activeModal)"
              >تأكيد</button>
            </div>
          </div>

          <div v-else-if="activeModal === 'note'" class="modal-body p-4">
            <h5 class="mb-3">إضافة ملاحظة داخلية</h5>
            <div class="mb-3">
              <label class="form-label">الظهور</label>
              <select v-model="noteForm.visibility" class="form-select">
                <option v-for="opt in filterOptions.note_visibilities" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">المحتوى</label>
              <textarea v-model="noteForm.content" class="form-control" rows="4" required />
            </div>
            <div class="d-flex justify-content-end gap-2">
              <button type="button" class="btn btn-secondary" @click="closeModal">إلغاء</button>
              <button type="button" class="btn btn-primary" :disabled="noteForm.processing" @click="submitNote">إضافة</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
