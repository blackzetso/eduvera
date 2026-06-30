<script setup>
const props = defineProps({
  sections: Array,
  activeSectionId: String,
  fieldTypeLabel: Function,
  draggedSectionIndex: [Number, null],
  draggedField: Object,
  dragOverField: Object,
})

const emit = defineEmits([
  'add-section',
  'remove-section',
  'toggle-collapse',
  'section-drag-start',
  'section-drop',
  'field-drag-start',
  'field-drop',
  'field-drag-over',
  'edit-field',
  'duplicate-field',
  'delete-field',
])
</script>

<template>
  <div class="fb-v2__panel h-100">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="fw-bold mb-0">الحقول المضافة</h5>
      <button type="button" class="btn btn-sm btn-primary-soft" @click="emit('add-section')">
        <i class="bi bi-folder-plus ms-1"></i> إضافة قسم
      </button>
    </div>

    <div
      v-for="(section, sIndex) in sections"
      :key="section.id"
      class="fb-v2__section-card"
      :class="{ 'is-dragging': draggedSectionIndex === sIndex }"
      draggable="true"
      @dragstart="emit('section-drag-start', sIndex)"
      @dragover.prevent
      @drop.prevent="emit('section-drop', sIndex)"
    >
      <div class="fb-v2__section-header" @click="emit('toggle-collapse', section.id)">
        <i class="bi bi-grip-vertical text-muted"></i>
        <i :class="section.collapsed ? 'bi-chevron-left' : 'bi-chevron-down'" class="text-muted"></i>
        <span class="fw-bold flex-grow-1 text-truncate">📁 {{ section.title_ar }}</span>
        <span class="badge bg-light border text-secondary">{{ section.fields.length }}</span>
        <button
          v-if="sections.length > 1"
          type="button"
          class="btn btn-sm btn-danger-soft btn-round"
          title="حذف القسم"
          @click.stop="emit('remove-section', section.id)"
        >
          <i class="bi bi-trash"></i>
        </button>
      </div>

      <div v-show="!section.collapsed" class="fb-v2__section-body">
        <div class="row g-2 mb-2">
          <div class="col-md-6">
            <input v-model="section.title_ar" class="form-control form-control-sm" placeholder="عنوان القسم بالعربية" @click.stop />
          </div>
          <div class="col-md-6">
            <input v-model="section.title_en" class="form-control form-control-sm text-start" dir="ltr" placeholder="Section title EN" @click.stop />
          </div>
          <div class="col-12">
            <input v-model="section.description_ar" class="form-control form-control-sm" placeholder="وصف القسم بالعربية" @click.stop />
          </div>
        </div>

        <div
          v-for="(field, fIndex) in section.fields"
          :key="field.id"
          class="fb-v2__field-row"
          :class="{
            'is-dragging': draggedField?.sectionId === section.id && draggedField?.index === fIndex,
            'is-drag-over': dragOverField?.sectionId === section.id && dragOverField?.index === fIndex,
          }"
          draggable="true"
          @dragstart.stop="emit('field-drag-start', section.id, fIndex)"
          @dragenter.prevent="emit('field-drag-over', section.id, fIndex)"
          @dragover.prevent
          @drop.prevent.stop="emit('field-drop', section.id, fIndex)"
        >
          <button type="button" class="btn btn-sm btn-danger-soft btn-round field-delete-btn" title="حذف الحقل" @click.stop="emit('delete-field', field.id)">
            <i class="bi bi-trash text-danger"></i>
          </button>
          <button type="button" class="btn btn-sm btn-secondary-soft btn-round field-duplicate-btn" title="نسخ الحقل" @click.stop="emit('duplicate-field', field.id)">
            <i class="bi bi-copy"></i>
          </button>
          <button type="button" class="btn btn-sm btn-primary-soft btn-round field-edit-btn" title="تعديل" @click.stop="emit('edit-field', field.id)">
            <i class="bi bi-pencil"></i>
          </button>
          <button type="button" class="btn btn-sm btn-light border-0" tabindex="-1"><i class="bi bi-grip-vertical text-muted"></i></button>
          <span class="fb-v2__type-badge">{{ fieldTypeLabel(field.type) }}</span>
          <span class="fb-v2__field-name text-truncate">{{ field.name_ar }}<span v-if="field.required" class="text-danger">*</span></span>
          <span class="fb-v2__field-order">{{ field.order }}</span>
        </div>

        <div
          class="fb-v2__drop-zone"
          :class="{ 'is-over': dragOverField?.sectionId === section.id && dragOverField?.index === section.fields.length }"
          @dragenter.prevent="emit('field-drag-over', section.id, section.fields.length)"
          @drop.prevent.stop="emit('field-drop', section.id, section.fields.length)"
        >
          أفلت الحقل هنا
        </div>
      </div>
    </div>

    <div v-if="sections.some((s) => s.fields.length)" class="fb-v2__drag-hint mt-3">
      <i class="bi bi-arrows-move"></i>
      اسحب الحقل أو القسم لتغيير الترتيب
    </div>
  </div>
</template>
