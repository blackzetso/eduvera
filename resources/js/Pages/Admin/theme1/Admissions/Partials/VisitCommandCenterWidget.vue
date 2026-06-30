<script setup>
import { ref } from 'vue'
import { useVisitExecutiveSummary } from '@/composables/useVisitExecutiveSummary'

const props = defineProps({
  visits: { type: Array, default: () => [] },
  application: { type: Object, default: null },
  filterOptions: { type: Object, default: () => ({}) },
  formatDate: { type: Function, required: true },
})

const emit = defineEmits(['open-tab'])

const collapsed = ref(false)

const {
  activeVisit,
  statusMeta,
  health,
  nextAction,
  outcomeSuccess,
  lastVisitDateDisplay,
  followUpRequired,
  hasVisits,
} = useVisitExecutiveSummary(
  () => props.visits,
  () => props.application,
  () => props.filterOptions,
)
</script>

<template>
  <div class="card admissions-command-card admissions-visit-exec border-0 shadow-sm h-100">
    <div class="card-body d-flex flex-column">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
        <div class="d-flex align-items-center gap-2 min-w-0">
          <h6 class="mb-0 fw-bold text-truncate">
            <i class="bi bi-building text-primary me-1"></i>
            زيارة الحرم
          </h6>
          <span
            v-if="hasVisits"
            class="admissions-visit-health small flex-shrink-0"
            :class="health.class"
            :title="health.label"
          >
            <span aria-hidden="true">{{ health.emoji }}</span>
            <span class="admissions-visit-health__label">{{ health.labelAr }}</span>
          </span>
        </div>
        <button
          type="button"
          class="btn btn-sm btn-link text-decoration-none d-md-none flex-shrink-0"
          @click="collapsed = !collapsed"
        >
          <i :class="['bi', collapsed ? 'bi-chevron-down' : 'bi-chevron-up']"></i>
        </button>
      </div>

      <div class="admissions-visit-exec__body flex-grow-1" :class="{ 'd-none d-md-flex': collapsed }">
        <template v-if="hasVisits && activeVisit">
          <div class="admissions-visit-exec__grid small">
            <div class="admissions-visit-exec__field">
              <div class="admissions-visit-exec__label">الحالة الحالية</div>
              <span
                v-if="statusMeta"
                class="badge admissions-visit-exec__status"
                :class="statusMeta.badgeClass"
              >
                {{ statusMeta.label }}
              </span>
            </div>

            <div class="admissions-visit-exec__field">
              <div class="admissions-visit-exec__label">آخر زيارة</div>
              <div class="admissions-visit-exec__value">{{ formatDate(lastVisitDateDisplay) }}</div>
            </div>

            <div class="admissions-visit-exec__field">
              <div class="admissions-visit-exec__label">المسؤول</div>
              <div class="admissions-visit-exec__value text-truncate">
                {{ application?.assigned_to?.name || '—' }}
              </div>
            </div>

            <div class="admissions-visit-exec__field">
              <div class="admissions-visit-exec__label">النتيجة</div>
              <div class="admissions-visit-exec__value">{{ outcomeSuccess || '—' }}</div>
            </div>

            <div class="admissions-visit-exec__field">
              <div class="admissions-visit-exec__label">المتابعة</div>
              <span
                class="badge"
                :class="followUpRequired ? 'bg-warning text-dark' : 'bg-light text-dark border'"
              >
                {{ followUpRequired ? 'مطلوبة' : 'غير مطلوبة' }}
              </span>
            </div>

            <div class="admissions-visit-exec__field admissions-visit-exec__field--action">
              <div class="admissions-visit-exec__label">الإجراء التالي</div>
              <div class="admissions-visit-exec__value fw-semibold" :class="nextAction.key === 'none' ? 'text-muted' : 'text-primary'">
                {{ nextAction.label }}
              </div>
            </div>
          </div>
        </template>

        <div v-else class="admissions-visit-exec__empty text-center flex-grow-1 d-flex flex-column justify-content-center">
          <i class="bi bi-calendar-x text-muted fs-4 mb-2"></i>
          <div class="fw-semibold small">لا توجد زيارات مسجلة</div>
          <div class="text-muted small mb-2">قم بجدولة أول زيارة للمتقدم</div>
        </div>
      </div>

      <div class="admissions-visit-exec__footer mt-auto pt-2">
        <button
          type="button"
          class="btn btn-sm w-100"
          :class="hasVisits ? 'btn-outline-primary' : 'btn-primary'"
          @click="emit('open-tab', 'visits')"
        >
          {{ hasVisits ? 'إدارة الزيارات' : 'فتح مركز الزيارات' }}
        </button>
      </div>
    </div>
  </div>
</template>
