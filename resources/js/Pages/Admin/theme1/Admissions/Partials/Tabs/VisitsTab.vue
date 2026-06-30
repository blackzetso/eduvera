<script setup>
import VisitOutcomeBadge from '../../Visits/Partials/VisitOutcomeBadge.vue'

defineProps({
  visits: { type: Array, default: () => [] },
  visitForms: { type: Object, required: true },
  filterOptions: { type: Object, default: () => ({}) },
  isReadOnly: { type: Boolean, default: false },
})

const emit = defineEmits(['save'])
</script>

<template>
  <div>
    <div v-if="isReadOnly" class="alert alert-secondary mb-3 py-2 small">الطلب محوّل — التعديل معطّل.</div>
    <div v-if="!visits.length" class="text-muted">لا توجد زيارات.</div>
    <div v-for="visit in visits" :key="visit.id" class="card border mb-3">
      <div class="card-header bg-transparent d-flex flex-wrap align-items-center gap-2 py-2">
        <span class="fw-semibold small">{{ visit.scheduled_date }} {{ visit.scheduled_time || '' }}</span>
        <VisitOutcomeBadge
          v-if="visit.outcome"
          :outcome="visit.outcome"
          :label="filterOptions.visit_outcomes?.find(o => o.value === visit.outcome)?.label || visit.outcome"
        />
      </div>
      <form v-if="visitForms[visit.id]" class="card-body row g-3" @submit.prevent="emit('save', visit.id)">
        <div class="col-md-4"><label class="form-label">التاريخ</label><input v-model="visitForms[visit.id].scheduled_date" type="date" class="form-control" :disabled="isReadOnly" /></div>
        <div class="col-md-4"><label class="form-label">الوقت</label><input v-model="visitForms[visit.id].scheduled_time" class="form-control" :disabled="isReadOnly" /></div>
        <div class="col-md-4"><label class="form-label">الحالة</label><select v-model="visitForms[visit.id].status" class="form-select" :disabled="isReadOnly"><option v-for="o in filterOptions.visit_statuses" :key="o.value" :value="o.value">{{ o.label }}</option></select></div>
        <div class="col-md-4"><label class="form-label">النتيجة</label><select v-model="visitForms[visit.id].outcome" class="form-select" :disabled="isReadOnly"><option value="">—</option><option v-for="o in filterOptions.visit_outcomes" :key="o.value" :value="o.value">{{ o.label }}</option></select></div>
        <div class="col-md-4"><label class="form-label">حضور الزيارة</label><select v-model="visitForms[visit.id].attendance_status" class="form-select" :disabled="isReadOnly"><option value="">—</option><option v-for="o in filterOptions.visit_attendance_statuses" :key="o.value" :value="o.value">{{ o.label }}</option></select></div>
        <div class="col-12"><label class="form-label">ملاحظات</label><textarea v-model="visitForms[visit.id].notes" class="form-control" rows="2" :disabled="isReadOnly" /></div>
        <div class="col-12"><label class="form-label">متابعة</label><textarea v-model="visitForms[visit.id].follow_up_notes" class="form-control" rows="2" :disabled="isReadOnly" /></div>
        <div class="col-12"><button type="submit" class="btn btn-primary btn-sm" :disabled="isReadOnly">حفظ الزيارة</button></div>
      </form>
    </div>
  </div>
</template>
