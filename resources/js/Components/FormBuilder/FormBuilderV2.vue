<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { useFormBuilderEngine } from '@/composables/useFormBuilderEngine'
import SimpleFieldEditor from '@/Components/FormBuilder/SimpleFieldEditor.vue'
import SimpleFieldList from '@/Components/FormBuilder/SimpleFieldList.vue'
import FieldEditorPanel from '@/Components/FormBuilder/FieldEditorPanel.vue'
import SectionBuilder from '@/Components/FormBuilder/SectionBuilder.vue'
import FormPreviewModal from '@/Components/FormBuilder/FormPreviewModal.vue'
import FormSettingsPanel from '@/Components/FormBuilder/FormSettingsPanel.vue'
import LogicBuilderPanel from '@/Components/FormBuilder/LogicBuilderPanel.vue'
import WorkflowBuilderPanel from '@/Components/FormBuilder/WorkflowBuilderPanel.vue'
import TemplatesLibraryModal from '@/Components/FormBuilder/TemplatesLibraryModal.vue'
import Swal from 'sweetalert2'

const props = defineProps({
  mode: { type: String, default: 'create' },
  initialForm: { type: Object, default: () => ({}) },
  formMeta: { type: Object, default: null },
  templates: { type: Array, default: () => [] },
  builderConfig: { type: Object, default: () => ({}) },
})

const showTemplates = ref(false)
const showAdvancedField = ref(false)
const showAdvancedSections = ref(false)
const showAdvancedTabs = ref(false)
const saving = ref(false)

const engine = useFormBuilderEngine(props.initialForm)
const {
  formMeta,
  sections,
  logicRules,
  workflow,
  fieldDraft,
  editingFieldId,
  activeSectionId,
  activeTab,
  previewLocale,
  showPreview,
  draggedSectionIndex,
  draggedField,
  dragOverField,
  totalFields,
  fieldHasOptions,
  fieldTypeLabel,
  resetFieldDraft,
  translateFieldDraftNames,
  addFieldToSection,
  editField,
  duplicateField,
  confirmDeleteField,
  addSection,
  removeSection,
  toggleSectionCollapse,
  onSectionDragStart,
  onSectionDrop,
  onFieldDragStart,
  onFieldDragOver,
  onFieldDrop,
  addLogicRule,
  removeLogicRule,
  addWorkflowStage,
  serializePayload,
  loadTemplate,
  addFieldOption,
  removeFieldOption,
} = engine

const useSectionMode = computed(() => showAdvancedSections.value || sections.length > 1)

function openPreview() {
  showPreview.value = true
}

function saveForm() {
  if (!formMeta.name?.trim()) {
    Swal.fire('تنبيه', 'أدخل عنوان النموذج', 'warning')
    return
  }

  const payload = serializePayload()
  const opts = {
    onSuccess: () => Swal.fire('تم الحفظ!', props.mode === 'edit' ? 'تم تحديث النموذج.' : 'تم إنشاء النموذج.', 'success'),
    onError: () => Swal.fire('خطأ!', 'حدثت مشكلة أثناء الحفظ.', 'error'),
    onFinish: () => { saving.value = false },
  }

  saving.value = true
  if (props.mode === 'edit' && props.formMeta?.id) {
    router.put(route('admin.forms.update', props.formMeta.id), payload, opts)
  } else {
    router.post(route('admin.forms.store'), payload, opts)
  }
}

async function applyTemplate(key) {
  showTemplates.value = false
  await loadTemplate(key, (k) => route('admin.forms.template', k))
  if (sections.length > 1) {
    showAdvancedSections.value = true
  }
}

function enableSectionMode() {
  showAdvancedSections.value = true
  if (sections.length === 0) {
    addSection()
  }
}
</script>

<template>
  <Head :title="mode === 'edit' ? 'تعديل نموذج' : 'إضافة نموذج'" />
  <AppLayout>
    <div class="page-content-wrapper border fb-v2" dir="rtl">
      <div class="card-body px-1 px-sm-4">
        <h4 class="mb-1">Add New Form</h4>
        <Link :href="route('admin.forms.index')" class="text-decoration-none">
          <i class="fas fa-arrow-right ms-1"></i>
          Back
        </Link>
        <hr />

        <div class="mb-3">
          <label class="form-label">Form title</label>
          <input v-model="formMeta.name" class="form-control" type="text" placeholder="نموذج تسجيل موظف" />
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 fb-v2__toolbar-compact">
          <button
            type="button"
            class="btn btn-link btn-sm text-decoration-none fb-v2__advanced-toggle p-0"
            @click="showAdvancedTabs = !showAdvancedTabs"
          >
            <i class="bi bi-sliders ms-1"></i>
            {{ showAdvancedTabs ? 'إخفاء الخيارات المتقدمة' : 'خيارات متقدمة (أقسام، منطق، سير عمل)' }}
          </button>
          <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-secondary-soft btn-sm" @click="showTemplates = true">
              <i class="bi bi-collection"></i>
            </button>
            <button type="button" class="btn btn-info-soft btn-sm" @click="openPreview">
              <i class="bi bi-eye ms-1"></i> معاينة
            </button>
          </div>
        </div>

        <ul v-show="showAdvancedTabs" class="nav nav-tabs fb-v2__tabs mb-3">
          <li class="nav-item">
            <button type="button" class="nav-link" :class="{ active: activeTab === 'builder' }" @click="activeTab = 'builder'">البناء</button>
          </li>
          <li class="nav-item">
            <button type="button" class="nav-link" :class="{ active: activeTab === 'settings' }" @click="activeTab = 'settings'">الإعدادات</button>
          </li>
          <li class="nav-item">
            <button type="button" class="nav-link" :class="{ active: activeTab === 'logic' }" @click="activeTab = 'logic'">المنطق</button>
          </li>
          <li class="nav-item">
            <button type="button" class="nav-link" :class="{ active: activeTab === 'workflow' }" @click="activeTab = 'workflow'">سير العمل</button>
          </li>
        </ul>

        <div v-show="!showAdvancedTabs || activeTab === 'builder'">
          <div v-if="!useSectionMode" class="row g-0 form-builder-columns">
            <div class="col-lg-5 mb-4 mb-lg-0">
              <SimpleFieldEditor
                :field-draft="fieldDraft"
                :editing-field-id="editingFieldId"
                :field-has-options="fieldHasOptions"
                @add-option="addFieldOption"
                @remove-option="removeFieldOption"
                @save-field="addFieldToSection"
                @cancel-edit="resetFieldDraft"
                @open-advanced="showAdvancedField = true"
                @translate-names="translateFieldDraftNames"
              />
            </div>
            <div class="col-lg-7">
              <SimpleFieldList
                :sections="sections"
                :field-type-label="fieldTypeLabel"
                :dragged-field="draggedField"
                :drag-over-field="dragOverField"
                @field-drag-start="onFieldDragStart"
                @field-drag-over="onFieldDragOver"
                @field-drop="onFieldDrop"
                @edit-field="editField"
                @duplicate-field="duplicateField"
                @delete-field="confirmDeleteField"
              />
              <div class="text-center mt-2">
                <button type="button" class="btn btn-link btn-sm" @click="enableSectionMode">
                  <i class="bi bi-folder-plus ms-1"></i>
                  تفعيل الأقسام (معلومات شخصية، تواصل، مؤهلات...)
                </button>
              </div>
            </div>
          </div>

          <div v-else class="row g-4">
            <div class="col-lg-5">
              <FieldEditorPanel
                :field-draft="fieldDraft"
                :editing-field-id="editingFieldId"
                :sections="sections"
                :active-section-id="activeSectionId"
                :field-has-options="fieldHasOptions"
                @translate-names="translateFieldDraftNames"
                @update:active-section-id="activeSectionId = $event"
                @add-option="addFieldOption"
                @remove-option="removeFieldOption"
                @save-field="addFieldToSection"
                @cancel-edit="resetFieldDraft"
              />
            </div>
            <div class="col-lg-7">
              <SectionBuilder
                :sections="sections"
                :active-section-id="activeSectionId"
                :field-type-label="fieldTypeLabel"
                :dragged-section-index="draggedSectionIndex"
                :dragged-field="draggedField"
                :drag-over-field="dragOverField"
                @add-section="addSection"
                @remove-section="removeSection"
                @toggle-collapse="toggleSectionCollapse"
                @section-drag-start="onSectionDragStart"
                @section-drop="onSectionDrop"
                @field-drag-start="onFieldDragStart"
                @field-drag-over="onFieldDragOver"
                @field-drop="onFieldDrop"
                @edit-field="editField"
                @duplicate-field="duplicateField"
                @delete-field="confirmDeleteField"
              />
            </div>
          </div>
        </div>

        <div v-show="showAdvancedTabs && activeTab === 'settings'">
          <FormSettingsPanel :form-meta="formMeta" :builder-config="builderConfig" />
        </div>

        <div v-show="showAdvancedTabs && activeTab === 'logic'">
          <LogicBuilderPanel :logic-rules="logicRules" :sections="sections" @add-rule="addLogicRule" @remove-rule="removeLogicRule" />
        </div>

        <div v-show="showAdvancedTabs && activeTab === 'workflow'">
          <WorkflowBuilderPanel :workflow="workflow" @add-stage="addWorkflowStage" />
        </div>

        <div class="d-flex justify-content-end mt-4">
          <button type="button" class="btn btn-primary px-4" :disabled="saving" @click="saveForm">
            Save Form
          </button>
        </div>
      </div>
    </div>

    <FormPreviewModal
      :show="showPreview"
      :locale="previewLocale"
      :form-meta="formMeta"
      :sections="sections"
      :field-has-options="fieldHasOptions"
      @close="showPreview = false"
      @edit="showPreview = false"
      @save="showPreview = false; saveForm()"
    />

    <TemplatesLibraryModal
      :show="showTemplates"
      :templates="templates"
      @close="showTemplates = false"
      @select="applyTemplate"
    />

    <div v-if="showAdvancedField" class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.45)">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content" dir="rtl">
          <div class="modal-header">
            <h5 class="modal-title">إعدادات الحقل المتقدمة</h5>
            <button type="button" class="btn-close" @click="showAdvancedField = false"></button>
          </div>
          <div class="modal-body p-0">
            <FieldEditorPanel
              :field-draft="fieldDraft"
              :editing-field-id="editingFieldId"
              :sections="sections"
              :active-section-id="activeSectionId"
              :field-has-options="fieldHasOptions"
              @update:active-section-id="activeSectionId = $event"
              @add-option="addFieldOption"
              @remove-option="removeFieldOption"
              @translate-names="translateFieldDraftNames"
              @save-field="addFieldToSection(); showAdvancedField = false"
              @cancel-edit="resetFieldDraft(); showAdvancedField = false"
            />
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
