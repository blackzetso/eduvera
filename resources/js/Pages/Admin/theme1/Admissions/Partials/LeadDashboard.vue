<script setup>
import { computed } from 'vue'

const props = defineProps({
  application: { type: Object, required: true },
  overview: { type: Object, required: true },
  leadAgeDays: { type: Number, default: null },
  contactStatus: { type: Object, required: true },
  duplicateRisk: { type: Object, required: true },
  formatDate: { type: Function, required: true },
  formatDateTime: { type: Function, required: true },
})

const emit = defineEmits(['open-tab'])

const assigned = computed(() => !!props.application.assigned_to?.name)
const followUpDue = computed(() => {
  const days = props.leadAgeDays
  return days !== null && days >= 3
})
</script>

<template>
  <div class="admission-adaptive-dashboard mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
      <div>
        <h6 class="fw-bold mb-1">
          <i class="bi bi-person-plus me-1 text-secondary"></i>
          لوحة العميل المحتمل
        </h6>
        <p class="text-muted small mb-0">متابعة التواصل الأولي وجمع بيانات الاتصال</p>
      </div>
      <button type="button" class="btn btn-primary btn-sm w-100 w-sm-auto" @click="emit('open-tab', 'contacts')">
        <i class="bi bi-telephone me-1"></i>
        بدء التواصل
      </button>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-6 col-md-3">
        <div class="admission-stage-stat">
          <div class="text-muted small">عمر العميل المحتمل</div>
          <div class="fw-bold">{{ leadAgeDays ?? 0 }} يوم</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="admission-stage-stat">
          <div class="text-muted small">المصدر</div>
          <div class="fw-bold text-truncate">{{ overview.source_channel_label || '—' }}</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="admission-stage-stat">
          <div class="text-muted small">مسؤول القبول</div>
          <div class="fw-bold text-truncate">{{ application.assigned_to?.name || '—' }}</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="admission-stage-stat">
          <div class="text-muted small">آخر نشاط</div>
          <div class="fw-bold small">{{ formatDateTime(overview.last_activity_at) }}</div>
        </div>
      </div>
    </div>

    <div v-if="followUpDue" class="alert alert-warning py-2 small mb-3">
      <i class="bi bi-bell me-1"></i>
      تذكير متابعة — لم يُسجّل نشاط منذ {{ leadAgeDays }} يوم
    </div>

    <div class="row g-3">
      <div class="col-sm-6 col-lg-3">
        <div class="admission-adaptive-card h-100 p-3">
          <div class="d-flex align-items-center gap-2 mb-2">
            <i :class="['bi', contactStatus.ok ? 'bi-check-circle-fill text-success' : 'bi-exclamation-circle text-warning']"></i>
            <span class="fw-semibold small">حالة الاتصال</span>
          </div>
          <div class="small" :class="contactStatus.ok ? 'text-success' : 'text-muted'">
            {{ contactStatus.ok ? 'مكتمل' : `ناقص: ${contactStatus.missing.join('، ')}` }}
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-3">
        <div class="admission-adaptive-card h-100 p-3">
          <div class="d-flex align-items-center gap-2 mb-2">
            <i :class="['bi', assigned ? 'bi-person-check-fill text-success' : 'bi-person-dash text-warning']"></i>
            <span class="fw-semibold small">حالة التعيين</span>
          </div>
          <div class="small">{{ assigned ? 'مُعيَّن' : 'بانتظار التعيين' }}</div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-3">
        <div class="admission-adaptive-card h-100 p-3">
          <div class="d-flex align-items-center gap-2 mb-2">
            <i :class="['bi', followUpDue ? 'bi-alarm text-warning' : 'bi-check-lg text-success']"></i>
            <span class="fw-semibold small">حالة المتابعة</span>
          </div>
          <div class="small">{{ followUpDue ? 'متابعة مطلوبة' : 'ضمن المسار' }}</div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-3">
        <div class="admission-adaptive-card h-100 p-3">
          <div class="d-flex align-items-center gap-2 mb-2">
            <i class="bi bi-shield-exclamation" :class="duplicateRisk.class"></i>
            <span class="fw-semibold small">خطر التكرار</span>
          </div>
          <div class="small" :class="duplicateRisk.class">{{ duplicateRisk.label }}</div>
        </div>
      </div>
    </div>
  </div>
</template>
