<script setup>
import { ref } from 'vue'
import { FIELD_TYPE_GROUPS } from '@/formBuilder/constants'

const props = defineProps({
  fieldDraft: Object,
  editingFieldId: [String, null],
  sections: Array,
  activeSectionId: String,
  fieldHasOptions: Function,
})

const emit = defineEmits([
  'update:activeSectionId',
  'add-option',
  'remove-option',
  'save-field',
  'cancel-edit',
  'translate-names',
])

const settingsTab = ref('general')
</script>

<template>
  <div class="fb-v2__panel h-100">
    <h5 class="fw-bold mb-3">إضافة حقل جديد</h5>
    <p v-if="editingFieldId" class="small text-primary mb-2">
      <i class="bi bi-pencil-square ms-1"></i> جاري تعديل حقل
    </p>

    <div class="mb-3">
      <label class="form-label">القسم</label>
      <select
        class="form-select"
        :value="activeSectionId"
        @change="emit('update:activeSectionId', $event.target.value)"
      >
        <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.title_ar }}</option>
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label">نوع الحقل</label>
      <select v-model="fieldDraft.type" class="form-select">
        <optgroup v-for="(items, group) in FIELD_TYPE_GROUPS" :key="group" :label="group">
          <option v-for="t in items" :key="t.value" :value="t.value">{{ t.label_ar }} — {{ t.label }}</option>
        </optgroup>
      </select>
    </div>

    <ul class="nav nav-pills nav-fill mb-3 fb-v2__tabs">
      <li class="nav-item">
        <button type="button" class="nav-link" :class="{ active: settingsTab === 'general' }" @click="settingsTab = 'general'">عام</button>
      </li>
      <li class="nav-item">
        <button type="button" class="nav-link" :class="{ active: settingsTab === 'validation' }" @click="settingsTab = 'validation'">التحقق</button>
      </li>
      <li class="nav-item">
        <button type="button" class="nav-link" :class="{ active: settingsTab === 'visibility' }" @click="settingsTab = 'visibility'">الظهور</button>
      </li>
    </ul>

    <div v-show="settingsTab === 'general'">
      <p class="small text-muted mb-3">أدخل الاسم بأي لغة — ستُترجم الأخرى تلقائياً ويمكن تعديلها.</p>
      <div class="mb-3">
        <label class="form-label">اسم الحقل بالعربية</label>
        <input
          v-model="fieldDraft.name_ar"
          type="text"
          class="form-control"
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
          @input="fieldDraft.schema.label_en = fieldDraft.name_en"
          @blur="emit('translate-names')"
        />
      </div>
      <div class="mb-3">
        <label class="form-label">Placeholder AR</label>
        <input v-model="fieldDraft.schema.placeholder_ar" type="text" class="form-control" />
      </div>
      <div class="mb-3">
        <label class="form-label">Placeholder EN</label>
        <input v-model="fieldDraft.schema.placeholder_en" type="text" class="form-control text-start" dir="ltr" />
      </div>
      <div class="mb-3">
        <label class="form-label">نص المساعدة AR</label>
        <input v-model="fieldDraft.schema.help_ar" type="text" class="form-control" />
      </div>
      <div class="mb-3">
        <label class="form-label">نص المساعدة EN</label>
        <input v-model="fieldDraft.schema.help_en" type="text" class="form-control text-start" dir="ltr" />
      </div>
      <div class="mb-3 d-flex justify-content-between align-items-center">
        <label class="form-label mb-0">الحقل مطلوب</label>
        <div class="form-check form-switch mb-0">
          <input v-model="fieldDraft.required" class="form-check-input" type="checkbox" @change="fieldDraft.schema.validation.required = fieldDraft.required" />
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label">القيمة الافتراضية</label>
        <input v-model="fieldDraft.schema.default_value" type="text" class="form-control" />
      </div>
      <div class="mb-3">
        <label class="form-label">نوع القيمة الافتراضية</label>
        <select v-model="fieldDraft.schema.default_mode" class="form-select">
          <option value="static">ثابتة</option>
          <option value="dynamic">ديناميكية</option>
        </select>
      </div>
    </div>

    <div v-show="settingsTab === 'validation'">
      <div class="row g-2">
        <div class="col-6">
          <label class="form-label">الحد الأدنى للطول</label>
          <input v-model.number="fieldDraft.schema.validation.min_length" type="number" class="form-control" min="0" />
        </div>
        <div class="col-6">
          <label class="form-label">الحد الأقصى للطول</label>
          <input v-model.number="fieldDraft.schema.validation.max_length" type="number" class="form-control" min="0" />
        </div>
        <div class="col-6">
          <label class="form-label">الحد الأدنى للقيمة</label>
          <input v-model.number="fieldDraft.schema.validation.min_value" type="number" class="form-control" />
        </div>
        <div class="col-6">
          <label class="form-label">الحد الأقصى للقيمة</label>
          <input v-model.number="fieldDraft.schema.validation.max_value" type="number" class="form-control" />
        </div>
        <div class="col-12">
          <label class="form-label">Regex</label>
          <input v-model="fieldDraft.schema.validation.regex" type="text" class="form-control text-start" dir="ltr" />
        </div>
        <div class="col-6">
          <div class="form-check">
            <input v-model="fieldDraft.schema.validation.email" class="form-check-input" type="checkbox" id="valEmail" />
            <label class="form-check-label" for="valEmail">تحقق بريد</label>
          </div>
        </div>
        <div class="col-6">
          <div class="form-check">
            <input v-model="fieldDraft.schema.validation.phone" class="form-check-input" type="checkbox" id="valPhone" />
            <label class="form-check-label" for="valPhone">تحقق هاتف</label>
          </div>
        </div>
      </div>
    </div>

    <div v-show="settingsTab === 'visibility'">
      <label class="form-label">وضع الظهور</label>
      <select v-model="fieldDraft.schema.visibility.mode" class="form-select">
        <option value="visible">ظاهر</option>
        <option value="hidden">مخفي</option>
        <option value="readonly">للقراءة فقط</option>
      </select>
    </div>

    <div v-if="fieldHasOptions(fieldDraft.type)" class="mb-3 mt-3">
      <label class="form-label">خيارات الحقل</label>
      <div v-for="(option, index) in fieldDraft.options" :key="index" class="d-flex gap-2 mb-2">
        <input v-model="option.value" type="text" class="form-control" placeholder="قيمة" />
        <input v-model="option.label_ar" type="text" class="form-control" placeholder="عربي" />
        <button type="button" class="btn btn-danger-soft btn-sm" @click="emit('remove-option', index)">حذف</button>
      </div>
      <button type="button" class="btn btn-primary-soft btn-sm" @click="emit('add-option')">+ أضف خيار</button>
    </div>

    <div class="d-flex flex-column gap-2 mt-3">
      <button type="button" class="btn btn-primary w-100" @click="emit('save-field')">
        {{ editingFieldId ? 'حفظ التعديل' : 'إضافة الحقل' }}
      </button>
      <button v-if="editingFieldId" type="button" class="btn btn-secondary-soft w-100" @click="emit('cancel-edit')">إلغاء التعديل</button>
    </div>
  </div>
</template>
