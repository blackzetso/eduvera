<script setup>
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  show: { type: Boolean, default: false },
  applicationId: { type: Number, required: true },
  application: { type: Object, required: true },
  primaryApplicant: { type: Object, default: null },
  primaryContact: { type: Object, default: null },
  conversionReadiness: { type: Object, required: true },
})

const processing = ref(false)

const emit = defineEmits(['close'])

const applicantName = computed(() =>
  props.primaryApplicant?.display_name
  || [props.primaryApplicant?.first_name, props.primaryApplicant?.father_name].filter(Boolean).join(' ')
  || '—',
)

const targetCategoryName = computed(() =>
  props.conversionReadiness?.target_category?.name
  || props.application.target_grade
  || props.application.target_category?.name
  || '—',
)

const checklist = computed(() => {
  const checks = props.conversionReadiness?.checks || []
  if (checks.length) {
    return checks.map((check) => ({ ok: check.ok, label: check.label, blocking: check.blocking }))
  }

  const errors = props.conversionReadiness?.errors || []
  if (props.conversionReadiness?.ready) {
    return [{ ok: true, label: 'جميع متطلبات التحويل مكتملة', blocking: true }]
  }

  return errors.map((err) => ({ ok: false, label: err, blocking: true }))
})

function confirmConvert() {
  processing.value = true
  router.post(route('admin.admissions.convert', props.applicationId), {}, {
    preserveScroll: true,
    onFinish: () => {
      processing.value = false
      emit('close')
    },
  })
}
</script>

<template>
  <div v-if="show" class="modal fade show d-block" tabindex="-1" style="background: rgba(15, 23, 42, 0.45);">
    <div class="modal-dialog modal-lg modal-dialog-scrollable eduvera-modal-dialog">
      <div class="modal-content border-0 shadow">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold">
            <i class="bi bi-person-plus-fill text-primary me-2"></i>
            تحويل إلى طالب
          </h5>
          <button type="button" class="btn-close" aria-label="إغلاق" @click="emit('close')"></button>
        </div>

        <div class="modal-body pt-3">
          <div class="alert alert-warning py-2 small mb-4">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            <strong>تنبيه:</strong>
            هذا الإجراء ينشئ تلقائياً:
            <strong>ملف طالب</strong>،
            <strong>قيداً دراسياً</strong>،
            و<strong>ربط ولي الأمر</strong>.
            لا يمكن التراجع عن التحويل.
          </div>

          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <div class="card bg-light border-0 h-100">
                <div class="card-body py-3">
                  <h6 class="text-muted small mb-2">ملخص المتقدم</h6>
                  <div class="fw-bold mb-1">{{ applicantName }}</div>
                  <div v-if="primaryApplicant?.date_of_birth" class="small text-muted">
                    <i class="bi bi-calendar me-1"></i>
                    {{ primaryApplicant.date_of_birth }}
                  </div>
                  <div v-if="primaryApplicant?.gender" class="small text-muted">
                    <i class="bi bi-gender-ambiguous me-1"></i>
                    {{ primaryApplicant.gender === 'male' ? 'ذكر' : primaryApplicant.gender === 'female' ? 'أنثى' : primaryApplicant.gender }}
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="card bg-light border-0 h-100">
                <div class="card-body py-3">
                  <h6 class="text-muted small mb-2">ملخص ولي الأمر</h6>
                  <div class="fw-bold mb-1">{{ primaryContact?.name || '—' }}</div>
                  <div v-if="primaryContact?.phone" class="small text-muted">
                    <i class="bi bi-telephone me-1"></i>
                    {{ primaryContact.phone }}
                  </div>
                  <div v-if="primaryContact?.email" class="small text-muted">
                    <i class="bi bi-envelope me-1"></i>
                    {{ primaryContact.email }}
                  </div>
                </div>
              </div>
            </div>
            <div class="col-12">
              <div class="card bg-light border-0">
                <div class="card-body py-3 d-flex flex-wrap align-items-center gap-2">
                  <span class="text-muted small">الفئة المستهدفة:</span>
                  <span class="fw-bold">{{ targetCategoryName }}</span>
                  <span class="badge bg-primary rounded-pill">{{ application.reference_code }}</span>
                </div>
              </div>
            </div>
          </div>

          <h6 class="small text-muted mb-2">قائمة الجاهزية</h6>
          <ul class="list-unstyled mb-0">
            <li
              v-for="(item, i) in checklist"
              :key="i"
              class="d-flex align-items-center gap-2 py-1 small"
            >
              <i
                class="bi"
                :class="item.ok ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger'"
              ></i>
              <span>{{ item.label }}</span>
            </li>
          </ul>
        </div>

        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-secondary" @click="emit('close')">إلغاء</button>
          <button
            type="button"
            class="btn btn-primary"
            :disabled="processing || !conversionReadiness.ready"
            @click="confirmConvert"
          >
            <span v-if="processing" class="spinner-border spinner-border-sm me-1" role="status"></span>
            <i v-else class="bi bi-person-plus me-1"></i>
            تحويل
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
