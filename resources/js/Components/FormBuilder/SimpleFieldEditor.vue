<script setup>
import { ref } from 'vue'
import { FIELD_TYPE_GROUPS } from '@/formBuilder/constants'

defineProps({
  fieldDraft: Object,
  editingFieldId: [String, null],
  fieldHasOptions: Function,
})

const emit = defineEmits(['add-option', 'remove-option', 'save-field', 'cancel-edit', 'open-advanced', 'translate-names'])

const showAdvanced = ref(false)
</script>

<template>
  <div class="fb-v2__panel fb-v2__panel--editor h-100" style="border-right: 2px dashed #dedede">
    <h5 class="fw-bold mb-4">إضافة حقل جديد</h5>

    <p v-if="editingFieldId" class="small text-primary mb-3">
      <i class="bi bi-pencil-square ms-1"></i>
      جاري تعديل حقل
    </p>

    <div class="mb-3">
      <label class="form-label">نوع الحقل</label>
      <select v-model="fieldDraft.type" class="form-select">
        <optgroup v-for="(items, group) in FIELD_TYPE_GROUPS" :key="group" :label="group">
          <option v-for="t in items" :key="t.value" :value="t.value">{{ t.label }}</option>
        </optgroup>
      </select>
    </div>

    <p class="small text-muted mb-3">أدخل الاسم بأي لغة — ستُترجم الأخرى تلقائياً ويمكن تعديلها.</p>

    <div class="mb-3">
      <label class="form-label">اسم الحقل بالعربية</label>
      <input
        v-model="fieldDraft.name_ar"
        type="text"
        class="form-control"
        placeholder="مثال: الاسم الرباعي بالعربية"
        @input="fieldDraft.schema.label_ar = fieldDraft.name_ar"
        @blur="emit('translate-names')"
      />
    </div>

    <div class="mb-3">
      <label class="form-label">اسم الحقل بالإنجليزية</label>
      <input
        v-model="fieldDraft.name_en"
        type="text"
        class="form-control text-start"
        dir="ltr"
        placeholder="Example: Full name"
        @input="fieldDraft.schema.label_en = fieldDraft.name_en"
        @blur="emit('translate-names')"
      />
    </div>

    <div class="mb-3 d-flex align-items-center justify-content-between">
      <label class="form-label mb-0">الحقل مطلوب</label>
      <div class="form-check form-switch mb-0">
        <input
          v-model="fieldDraft.required"
          class="form-check-input"
          type="checkbox"
          role="switch"
          @change="fieldDraft.schema.validation.required = fieldDraft.required"
        />
      </div>
    </div>

    <div v-if="fieldHasOptions(fieldDraft.type)" class="mb-3">
      <label class="form-label">خيارات الحقل</label>
      <div v-for="(option, index) in fieldDraft.options" :key="index" class="d-flex gap-2 mb-2">
        <input v-model="option.value" type="text" class="form-control" placeholder="نص الخيار" />
        <button type="button" class="btn btn-danger-soft btn-sm" @click="emit('remove-option', index)">حذف</button>
      </div>
      <button type="button" class="btn btn-primary-soft btn-sm" @click="emit('add-option')">+ أضف خيار</button>
    </div>

    <button type="button" class="btn btn-link btn-sm p-0 mb-3 text-decoration-none" @click="emit('open-advanced')">
      إعدادات متقدمة للحقل
    </button>

    <button type="button" class="btn btn-primary w-100 py-2" @click="emit('save-field')">
      {{ editingFieldId ? 'حفظ التعديل' : 'إضافة الحقل' }}
    </button>
    <button
      v-if="editingFieldId"
      type="button"
      class="btn btn-secondary-soft w-100 mt-2"
      @click="emit('cancel-edit')"
    >
      إلغاء التعديل
    </button>
  </div>
</template>
