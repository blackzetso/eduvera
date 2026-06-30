<script setup>
import { computed } from 'vue'

const props = defineProps({
  sections: Array,
  fieldTypeLabel: Function,
  draggedField: Object,
  dragOverField: Object,
})

const emit = defineEmits([
  'field-drag-start',
  'field-drag-over',
  'field-drop',
  'edit-field',
  'duplicate-field',
  'delete-field',
])

const primarySection = computed(() => props.sections[0])

function fieldGlobalIndex(sectionId, fIndex) {
  return { sectionId, index: fIndex }
}
</script>

<template>
  <div class="fb-v2__panel fb-v2__panel--fields h-100">
    <h5 class="fw-bold mb-4 text-center">الحقول المضافة</h5>

    <div
      v-if="!primarySection?.fields?.length"
      class="text-center text-muted py-5 border rounded bg-light"
    >
      <i class="bi bi-ui-checks-grid fs-3 d-block mb-2"></i>
      لم تُضف حقول بعد
    </div>

    <div v-else class="fb-v2__fields-list">
      <div
        v-for="(field, fIndex) in primarySection.fields"
        :key="field.id"
        class="fb-v2__field-row fb-v2__field-row--classic"
        :class="{
          'is-dragging': draggedField?.sectionId === primarySection.id && draggedField?.index === fIndex,
          'is-drag-over': dragOverField?.sectionId === primarySection.id && dragOverField?.index === fIndex,
        }"
        draggable="true"
        @dragstart.stop="emit('field-drag-start', primarySection.id, fIndex)"
        @dragenter.prevent="emit('field-drag-over', primarySection.id, fIndex)"
        @dragover.prevent
        @drop.prevent.stop="emit('field-drop', primarySection.id, fIndex)"
      >
        <button
          type="button"
          class="fb-v2__action-btn fb-v2__action-btn--delete"
          title="حذف الحقل"
          @click.stop="emit('delete-field', field.id)"
        >
          <i class="bi bi-trash"></i>
        </button>

        <button
          type="button"
          class="fb-v2__action-btn fb-v2__action-btn--duplicate"
          title="نسخ الحقل"
          @click.stop="emit('duplicate-field', field.id)"
        >
          <i class="bi bi-copy"></i>
        </button>

        <button
          type="button"
          class="fb-v2__action-btn fb-v2__action-btn--edit"
          title="تعديل"
          @click.stop="emit('edit-field', field.id)"
        >
          <i class="bi bi-pencil"></i>
        </button>

        <button type="button" class="fb-v2__drag-btn" tabindex="-1" title="سحب لإعادة الترتيب">
          <i class="bi bi-grip-vertical"></i>
        </button>

        <span class="fb-v2__type-badge">{{ fieldTypeLabel(field.type) }}</span>

        <span class="fb-v2__field-name text-truncate">
          {{ field.name_ar }}
          <span v-if="field.required" class="text-danger">*</span>
        </span>

        <span class="fb-v2__field-order">{{ field.order }}</span>
      </div>

      <div
        class="fb-v2__drop-zone"
        :class="{ 'is-over': dragOverField?.sectionId === primarySection.id && dragOverField?.index === primarySection.fields.length }"
        @dragenter.prevent="emit('field-drag-over', primarySection.id, primarySection.fields.length)"
        @drop.prevent.stop="emit('field-drop', primarySection.id, primarySection.fields.length)"
      >
        أفلت الحقل هنا
      </div>

      <div class="fb-v2__drag-hint mt-3">
        <i class="bi bi-arrows-move"></i>
        اسحب الحقل لتغيير الترتيب
      </div>
    </div>
  </div>
</template>
