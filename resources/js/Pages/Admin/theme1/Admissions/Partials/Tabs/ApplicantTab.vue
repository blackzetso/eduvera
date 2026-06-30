<script setup>
import AdmissionTargetCategoryPicker from '../AdmissionTargetCategoryPicker.vue'

defineProps({
  primaryApplicant: { type: Object, default: null },
  applicantForm: { type: Object, required: true },
  categories: { type: Array, default: () => [] },
  isReadOnly: { type: Boolean, default: false },
})

const selectedStageId = defineModel('selectedStageId', { type: [Number, null], default: null })

const emit = defineEmits(['save'])
</script>

<template>
  <div>
    <div v-if="!primaryApplicant" class="text-muted">لا يوجد متقدم.</div>
    <form v-else class="card border" @submit.prevent="emit('save')">
      <div v-if="isReadOnly" class="alert alert-secondary m-3 mb-0 py-2 small">الطلب محوّل — التعديل معطّل.</div>
      <div class="card-body row g-3">
        <div class="col-md-4"><label class="form-label">الاسم الأول</label><input v-model="applicantForm.first_name" class="form-control" :disabled="isReadOnly" /></div>
        <div class="col-md-4"><label class="form-label">اسم الأب</label><input v-model="applicantForm.father_name" class="form-control" :disabled="isReadOnly" /></div>
        <div class="col-md-4"><label class="form-label">اسم الجد</label><input v-model="applicantForm.grandfather_name" class="form-control" :disabled="isReadOnly" /></div>
        <div class="col-md-4"><label class="form-label">الجنس</label><select v-model="applicantForm.gender" class="form-select" :disabled="isReadOnly"><option value="">—</option><option value="male">ذكر</option><option value="female">أنثى</option></select></div>
        <div class="col-md-4"><label class="form-label">تاريخ الميلاد</label><input v-model="applicantForm.date_of_birth" type="date" class="form-control" :disabled="isReadOnly" /></div>
        <div class="col-md-4"><label class="form-label">الصف الحالي</label><input v-model="applicantForm.current_grade_label" class="form-control" :disabled="isReadOnly" /></div>
        <div class="col-12">
          <AdmissionTargetCategoryPicker
            :categories="categories"
            v-model:stage-id="selectedStageId"
            v-model:target-category-id="applicantForm.target_category_id"
            :disabled="isReadOnly"
          />
          <div v-if="applicantForm.errors.target_category_id" class="text-danger small mt-1">{{ applicantForm.errors.target_category_id }}</div>
          <div v-if="primaryApplicant?.target_stage_label" class="form-text">
            المرحلة (مُشتقة): {{ primaryApplicant.target_stage_label }}
          </div>
        </div>
        <div class="col-md-4"><label class="form-label">الرقم القومي</label><input v-model="applicantForm.national_id" class="form-control" :disabled="isReadOnly" /></div>
        <div class="col-12"><label class="form-label">ملاحظات</label><textarea v-model="applicantForm.notes" class="form-control" rows="3" :disabled="isReadOnly" /></div>
        <div class="col-12"><button type="submit" class="btn btn-primary" :disabled="applicantForm.processing || isReadOnly">حفظ</button></div>
      </div>
    </form>
  </div>
</template>
