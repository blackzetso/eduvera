<script setup>
import { computed } from 'vue'
import { FIELD_TYPE_GROUPS } from '@/formBuilder/constants'

const props = defineProps({
  show: Boolean,
  locale: { type: String, default: 'ar' },
  formMeta: Object,
  sections: Array,
  fieldHasOptions: Function,
})

const emit = defineEmits(['close', 'edit', 'save'])

const isAr = computed(() => props.locale === 'ar')

function label(field) {
  return isAr.value ? field.name_ar : (field.name_en || field.name_ar)
}

function sectionTitle(section) {
  return isAr.value ? section.title_ar : (section.title_en || section.title_ar)
}

function sectionDesc(section) {
  return isAr.value ? section.description_ar : (section.description_en || section.description_ar)
}

function placeholder(field) {
  const s = field.schema ?? {}
  return isAr.value ? s.placeholder_ar : (s.placeholder_en || s.placeholder_ar)
}

function helpText(field) {
  const s = field.schema ?? {}
  return isAr.value ? s.help_ar : (s.help_en || s.help_ar)
}

function visibleFields(section) {
  return (section.fields ?? []).filter((f) => f.schema?.visibility?.mode !== 'hidden')
}
</script>

<template>
  <div v-if="show" class="modal fade show d-block fb-v2__preview-modal" tabindex="-1" style="background: rgba(0,0,0,.45)">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content" :dir="isAr ? 'rtl' : 'ltr'">
        <div class="modal-header">
          <h5 class="modal-title">
            {{ isAr ? 'معاينة النموذج' : 'Form Preview' }}
            <span class="badge bg-primary-soft text-primary ms-2">{{ isAr ? 'عربي' : 'English' }}</span>
          </h5>
          <button type="button" class="btn-close" @click="emit('close')"></button>
        </div>
        <div class="modal-body fb-v2__preview-form">
          <h4 class="mb-1">{{ isAr ? formMeta.name : (formMeta.name_en || formMeta.name) }}</h4>
          <p v-if="(isAr ? formMeta.description_ar : formMeta.description_en)" class="text-muted small mb-4">
            {{ isAr ? formMeta.description_ar : formMeta.description_en }}
          </p>

          <div v-for="section in sections" :key="section.id" class="fb-v2__section-preview">
            <h5 class="fw-bold mb-1">{{ sectionTitle(section) }}</h5>
            <p v-if="sectionDesc(section)" class="text-muted small mb-3">{{ sectionDesc(section) }}</p>

            <div v-for="field in visibleFields(section)" :key="field.id" class="mb-3">
              <label class="form-label fw-semibold">
                {{ label(field) }}
                <span v-if="field.required" class="text-danger">*</span>
              </label>
              <p v-if="helpText(field)" class="small text-muted mb-1">{{ helpText(field) }}</p>

              <input
                v-if="['text','email','phone','url','number','date','time','academic_year','grade','class','subject','teacher_selector','color'].includes(field.type)"
                :type="field.type === 'number' ? 'number' : field.type === 'date' ? 'date' : field.type === 'time' ? 'time' : field.type === 'email' ? 'email' : field.type === 'url' ? 'url' : 'text'"
                class="form-control"
                :placeholder="placeholder(field)"
                :readonly="field.schema?.visibility?.mode === 'readonly'"
                disabled
              />
              <textarea
                v-else-if="field.type === 'textarea'"
                class="form-control"
                rows="3"
                :placeholder="placeholder(field)"
                disabled
              ></textarea>
              <select v-else-if="['select','multi_select','grade','class','subject'].includes(field.type)" class="form-select" disabled>
                <option v-for="(opt, i) in field.options" :key="i">{{ opt.label_ar || opt.value }}</option>
              </select>
              <div v-else-if="field.type === 'checkbox'">
                <div v-for="(opt, i) in field.options" :key="i" class="form-check">
                  <input type="checkbox" class="form-check-input" disabled />
                  <label class="form-check-label">{{ opt.label_ar || opt.value }}</label>
                </div>
              </div>
              <div v-else-if="field.type === 'radio'">
                <div v-for="(opt, i) in field.options" :key="i" class="form-check">
                  <input type="radio" class="form-check-input" disabled />
                  <label class="form-check-label">{{ opt.label_ar || opt.value }}</label>
                </div>
              </div>
              <input v-else-if="['file','image'].includes(field.type)" type="file" class="form-control" disabled />
              <div v-else-if="field.type === 'signature'" class="border rounded p-4 text-center text-muted bg-light">توقيع</div>
              <div v-else-if="field.type === 'rating'" class="text-warning fs-4">★★★★☆</div>
              <input v-else-if="field.type === 'slider'" type="range" class="form-range" disabled />
              <input v-else class="form-control" :placeholder="placeholder(field)" disabled />
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary-soft" @click="emit('close')">إغلاق</button>
          <button type="button" class="btn btn-primary-soft" @click="emit('edit')">تعديل</button>
          <button type="button" class="btn btn-primary" @click="emit('save')">حفظ</button>
        </div>
      </div>
    </div>
  </div>
</template>
