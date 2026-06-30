<script setup>
defineProps({
  workflow: Object,
})

const emit = defineEmits(['add-stage'])
</script>

<template>
  <div class="fb-v2__panel">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="fw-bold mb-0">سير العمل</h5>
      <div class="form-check form-switch mb-0">
        <input v-model="workflow.enabled" class="form-check-input" type="checkbox" id="wfEnabled" />
        <label class="form-check-label" for="wfEnabled">تفعيل</label>
      </div>
    </div>

    <div v-if="workflow.enabled">
      <button type="button" class="btn btn-sm btn-primary-soft mb-3" @click="emit('add-stage')">
        <i class="bi bi-plus-lg ms-1"></i> إضافة مرحلة
      </button>

      <div v-for="(stage, index) in workflow.stages" :key="stage.id" class="border rounded p-3 mb-2">
        <div class="d-flex align-items-center gap-2 mb-2">
          <span class="badge bg-primary">{{ index + 1 }}</span>
          <span v-if="index < workflow.stages.length - 1" class="text-muted">↓</span>
        </div>
        <div class="row g-2">
          <div class="col-md-4">
            <input v-model="stage.name_ar" class="form-control form-control-sm" placeholder="اسم المرحلة AR" />
          </div>
          <div class="col-md-4">
            <input v-model="stage.name_en" class="form-control form-control-sm" placeholder="Stage EN" dir="ltr" />
          </div>
          <div class="col-md-4">
            <input v-model="stage.role" class="form-control form-control-sm" placeholder="الدور" />
          </div>
          <div class="col-md-4">
            <input v-model="stage.department" class="form-control form-control-sm" placeholder="القسم" />
          </div>
          <div class="col-md-8">
            <input v-model="stage.user_id" class="form-control form-control-sm" placeholder="معرف المستخدم (اختياري)" />
          </div>
        </div>
        <div class="small text-muted mt-2">الإجراءات: موافقة · رفض · إرجاع للتعديل</div>
      </div>
    </div>
    <p v-else class="text-muted small mb-0">فعّل سير العمل لإضافة مراحل الموافقة (موارد بشرية، مشتريات، صيانة...)</p>
  </div>
</template>
