<script setup>
import { computed } from 'vue'

const props = defineProps({
  overview: { type: Object, required: true },
  recentNotes: { type: Array, default: () => [] },
  applicantStatus: { type: Object, required: true },
  contactStatus: { type: Object, required: true },
  visitReadiness: { type: Object, required: true },
  formatDateTime: { type: Function, required: true },
})

const emit = defineEmits(['open-tab'])

const parentContacted = computed(() => props.contactStatus.ok && props.contactStatus.score >= 2)
const missingCount = computed(() => props.applicantStatus.missing?.length || 0)
</script>

<template>
  <div class="admission-adaptive-dashboard mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
      <div>
        <h6 class="fw-bold mb-1">
          <i class="bi bi-chat-dots me-1 text-info"></i>
          لوحة الاستفسار
        </h6>
        <p class="text-muted small mb-0">تأهيل العائلة والاستعداد لحجز زيارة الحرم</p>
      </div>
      <button type="button" class="btn btn-info btn-sm text-dark w-100 w-sm-auto" @click="emit('open-tab', 'visits')">
        <i class="bi bi-calendar-plus me-1"></i>
        حجز زيارة
      </button>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-md-4">
        <div class="admission-adaptive-card p-3 h-100">
          <div class="text-muted small mb-2">ملخص التفاعل</div>
          <div v-if="!recentNotes.length" class="text-muted small">لا توجد ملاحظات داخلية بعد.</div>
          <ul v-else class="list-unstyled mb-0 small">
            <li v-for="note in recentNotes" :key="note.id" class="mb-2 pb-2 border-bottom">
              <span class="text-muted">{{ note.author }} · </span>
              {{ note.content?.slice(0, 80) }}{{ note.content?.length > 80 ? '…' : '' }}
            </li>
          </ul>
        </div>
      </div>
      <div class="col-md-8">
        <div class="row g-3">
          <div class="col-sm-6">
            <div class="admission-adaptive-card p-3 h-100">
              <div class="d-flex align-items-center gap-2 mb-2">
                <i :class="['bi', parentContacted ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-warning']"></i>
                <span class="fw-semibold small">تم التواصل مع ولي الأمر</span>
              </div>
              <div class="small text-muted">{{ parentContacted ? 'بيانات اتصال كافية' : 'أكمل الهاتف والبريد' }}</div>
            </div>
          </div>
          <div class="col-sm-6">
            <div class="admission-adaptive-card p-3 h-100">
              <div class="d-flex align-items-center gap-2 mb-2">
                <i :class="['bi', applicantStatus.ok ? 'bi-check-circle-fill text-success' : 'bi-exclamation-circle text-warning']"></i>
                <span class="fw-semibold small">بيانات المتقدم</span>
              </div>
              <div class="small text-muted">
                {{ applicantStatus.ok ? 'مكتملة' : `${missingCount} حقول ناقصة` }}
              </div>
            </div>
          </div>
          <div class="col-sm-6">
            <div class="admission-adaptive-card p-3 h-100">
              <div class="d-flex align-items-center gap-2 mb-2">
                <i :class="['bi', missingCount === 0 ? 'bi-check-lg text-success' : 'bi-list-check text-warning']"></i>
                <span class="fw-semibold small">المعلومات المطلوبة</span>
              </div>
              <div class="small text-muted">
                {{ missingCount ? applicantStatus.missing.join('، ') : 'لا يوجد نقص' }}
              </div>
            </div>
          </div>
          <div class="col-sm-6">
            <div class="admission-adaptive-card p-3 h-100">
              <div class="d-flex align-items-center gap-2 mb-2">
                <i :class="['bi', visitReadiness.ok ? 'bi-calendar-check text-success' : 'bi-calendar-x text-warning']"></i>
                <span class="fw-semibold small">جاهزية الزيارة</span>
              </div>
              <div class="small text-muted">
                {{ visitReadiness.ok ? 'جاهز لحجز زيارة' : 'أكمل بيانات الاتصال والمتقدم' }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="small text-muted">
      آخر نشاط: {{ formatDateTime(overview.last_activity_at) }}
    </div>
  </div>
</template>
