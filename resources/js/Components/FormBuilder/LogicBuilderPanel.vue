<script setup>
import { LOGIC_ACTIONS } from '@/formBuilder/constants'

defineProps({
  logicRules: Array,
  sections: Array,
})

const emit = defineEmits(['add-rule', 'remove-rule'])
</script>

<template>
  <div class="fb-v2__panel">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="fw-bold mb-0">المنطق الشرطي</h5>
      <button type="button" class="btn btn-sm btn-primary-soft" @click="emit('add-rule')">
        <i class="bi bi-plus-lg ms-1"></i> قاعدة
      </button>
    </div>
    <p class="text-muted small">مثال: إذا نوع الموظف = معلم → أظهر حقول التخصص والخبرة</p>

    <div v-for="(rule, index) in logicRules" :key="rule.id" class="border rounded p-3 mb-2 bg-light">
      <div class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label small">إذا الحقل</label>
          <input v-model="rule.field_key" class="form-control form-control-sm" placeholder="field_key" />
        </div>
        <div class="col-md-2">
          <label class="form-label small">يساوي</label>
          <select v-model="rule.operator" class="form-select form-select-sm">
            <option value="equals">=</option>
            <option value="not_equals">≠</option>
            <option value="contains">يحتوي</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small">القيمة</label>
          <input v-model="rule.value" class="form-control form-control-sm" />
        </div>
        <div class="col-md-2">
          <label class="form-label small">الإجراء</label>
          <select v-model="rule.action" class="form-select form-select-sm">
            <option v-for="a in LOGIC_ACTIONS" :key="a.value" :value="a.value">{{ a.label_ar }}</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small">الهدف</label>
          <input v-model="rule.target_field_key" class="form-control form-control-sm" placeholder="حقل/قسم" />
        </div>
        <div class="col-md-1">
          <button type="button" class="btn btn-danger-soft btn-sm w-100" @click="emit('remove-rule', index)">
            <i class="bi bi-trash"></i>
          </button>
        </div>
      </div>
    </div>
    <div v-if="!logicRules.length" class="text-center text-muted py-4">لا توجد قواعد بعد</div>
  </div>
</template>
