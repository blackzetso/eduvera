<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  conversionReadiness: { type: Object, required: true },
  application: { type: Object, required: true },
  primaryApplicant: { type: Object, default: null },
  primaryContact: { type: Object, default: null },
  documentSummary: { type: Object, default: () => ({}) },
  isReadOnly: { type: Boolean, default: false },
  canConvert: { type: Boolean, default: false },
  convertedStudent: { type: Object, default: null },
  convertedGuardian: { type: Object, default: null },
})

const emit = defineEmits(['convert'])

const isReady = computed(() => !!props.conversionReadiness?.ready)

const checklist = computed(() => props.conversionReadiness?.checks || [])

const completedCount = computed(() => checklist.value.filter((item) => item.ok).length)
const totalCount = computed(() => checklist.value.length)
const progressPercent = computed(() =>
  props.conversionReadiness?.completion_percentage
  ?? (totalCount.value ? Math.round((completedCount.value / totalCount.value) * 100) : 0),
)

const showPanel = computed(() =>
  props.isReadOnly
  || props.application.decision === 'accepted'
  || (props.conversionReadiness?.errors?.length > 0)
  || checklist.value.length > 0,
)

function checkIconClass(item) {
  if (item.ok) return 'bi-check-circle-fill text-success'
  if (!item.blocking) return 'bi-exclamation-triangle-fill text-warning'
  return 'bi-exclamation-circle-fill text-warning'
}
</script>

<template>
  <div v-if="showPanel" class="card admission-command-card border-0 shadow-sm mb-4" :class="{ 'admission-readiness--ready': isReady && canConvert, 'admission-readiness--converted': isReadOnly }">
    <div class="card-body p-4">
      <div v-if="isReadOnly && convertedStudent" class="text-center py-2">
        <div class="display-6 text-success mb-2"><i class="bi bi-check-circle-fill"></i></div>
        <h5 class="text-success mb-2">تم تحويل الطلب بنجاح</h5>
        <p class="text-muted small mb-3">تم إنشاء ملف الطالب والقيد المرتبط بهذا الطلب.</p>
        <div class="d-flex flex-wrap justify-content-center gap-2">
          <Link :href="convertedStudent.profile_url" class="btn btn-success btn-sm">
            <i class="bi bi-person-badge me-1"></i>
            فتح ملف الطالب — {{ convertedStudent.name }}
          </Link>
          <Link
            v-if="convertedGuardian?.profile_url"
            :href="convertedGuardian.profile_url"
            class="btn btn-outline-success btn-sm"
          >
            <i class="bi bi-people me-1"></i>
            فتح ملف العائلة — {{ convertedGuardian.name }}
          </Link>
        </div>
      </div>

      <template v-else>
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
          <div>
            <h6 class="mb-1 fw-bold">
              <i class="bi bi-clipboard-check me-1"></i>
              جاهزية التحويل
            </h6>
            <p class="text-muted small mb-0">متطلبات تحويل الطلب إلى طالب مسجل</p>
          </div>
          <div class="text-end">
            <div class="fw-bold fs-5" :class="isReady ? 'text-success' : 'text-primary'">
              {{ completedCount }} / {{ totalCount }}
            </div>
            <div class="small text-muted">{{ progressPercent }}%</div>
          </div>
        </div>

        <div class="progress mb-3 rounded-pill" style="height: 8px;">
          <div
            class="progress-bar rounded-pill"
            :class="isReady ? 'bg-success' : 'bg-primary'"
            role="progressbar"
            :style="{ width: `${progressPercent}%` }"
            :aria-valuenow="progressPercent"
            aria-valuemin="0"
            aria-valuemax="100"
          ></div>
        </div>

        <ul class="list-unstyled mb-0 admission-readiness-checklist">
          <li
            v-for="item in checklist"
            :key="item.id"
            class="d-flex align-items-center gap-2 py-1 small"
          >
            <i class="bi flex-shrink-0" :class="checkIconClass(item)"></i>
            <span :class="{ 'text-muted': item.ok, 'fw-semibold': !item.ok }">
              {{ item.label }}
              <span
                v-if="item.id === 'documents_complete' && item.detail && !item.ok"
                class="d-block"
                :class="item.blocking ? 'text-danger' : 'text-warning'"
              >
                {{ item.detail }}
              </span>
            </span>
          </li>
        </ul>

        <div v-if="conversionReadiness.warnings?.length" class="alert alert-info mt-3 mb-0 py-2 small">
          <strong>تحذيرات:</strong>
          <ul class="mb-0 mt-1 ps-3">
            <li v-for="(warning, i) in conversionReadiness.warnings" :key="i">{{ warning }}</li>
          </ul>
        </div>

        <div v-if="isReady && canConvert" class="alert alert-success mt-3 mb-0 py-3 text-center">
          <div class="fw-bold mb-1">
            <i class="bi bi-lightning-charge-fill me-1"></i>
            جاهز للتحويل
          </div>
          <p class="small mb-2 mb-md-3">جميع المتطلبات مكتملة — يمكن تحويل الطلب إلى طالب.</p>
          <button type="button" class="btn btn-success" @click="emit('convert')">
            <i class="bi bi-person-plus me-1"></i>
            تحويل إلى طالب
          </button>
        </div>

        <div v-else-if="conversionReadiness.errors?.length" class="alert alert-warning mt-3 mb-0 py-2 small">
          <strong>متطلبات غير مكتملة:</strong>
          <ul class="mb-0 mt-1 ps-3">
            <li v-for="(err, i) in conversionReadiness.errors" :key="i">{{ err }}</li>
          </ul>
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
.admission-readiness--ready {
  border-inline-start: 4px solid var(--bs-success) !important;
}

.admission-readiness--converted {
  border-inline-start: 4px solid var(--bs-success) !important;
  background: rgba(var(--bs-success-rgb), 0.04);
}
</style>
