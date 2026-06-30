import { reactive, ref, computed } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import { toast } from 'vue3-toastify'
import {
  OPTION_FIELD_TYPES,
  FIELD_TYPE_LABELS,
  DEFAULT_FIELD_SCHEMA,
  DEFAULT_FORM_SETTINGS,
  DEFAULT_WORKFLOW,
} from '@/formBuilder/constants'
import { translateNamePair } from '@/formBuilder/bilingualField'

let sectionIdCounter = 0
let fieldIdCounter = 0

function nextSectionId() {
  return `sec_${++sectionIdCounter}`
}

function nextFieldId() {
  return `fld_${++fieldIdCounter}`
}

function createEmptyFieldDraft() {
  return {
    name_ar: '',
    name_en: '',
    type: 'text',
    required: false,
    options: [],
    schema: DEFAULT_FIELD_SCHEMA(),
  }
}

function createDefaultSection() {
  return {
    id: nextSectionId(),
    title_ar: 'الحقول العامة',
    title_en: 'General Fields',
    description_ar: '',
    description_en: '',
    order: 1,
    collapsed: false,
    fields: [],
  }
}

export function useFormBuilderEngine(initial = {}) {
  const formMeta = reactive({
    name: initial.name ?? '',
    name_en: initial.name_en ?? '',
    description_ar: initial.description_ar ?? '',
    description_en: initial.description_en ?? '',
    template_key: initial.template_key ?? null,
    ...DEFAULT_FORM_SETTINGS(),
    ...(initial.publication_status ? { publication_status: initial.publication_status } : {}),
    visibility: initial.visibility_settings ?? DEFAULT_FORM_SETTINGS().visibility,
    submission: initial.submission_settings ?? DEFAULT_FORM_SETTINGS().submission,
  })

  const sections = reactive([])
  const logicRules = reactive(initial.logic_rules ? [...initial.logic_rules] : [])
  const workflow = reactive(initial.workflow_definition ?? DEFAULT_WORKFLOW())
  const fieldDraft = reactive(createEmptyFieldDraft())
  const editingFieldId = ref(null)
  const activeSectionId = ref(null)
  const activeTab = ref('builder')
  const previewLocale = ref('ar')
  const showPreview = ref(false)

  const draggedSectionIndex = ref(null)
  const draggedField = ref(null)
  const dragOverSectionId = ref(null)
  const dragOverField = ref(null)

  function fieldHasOptions(type) {
    return OPTION_FIELD_TYPES.includes(type)
  }

  function fieldTypeLabel(type) {
    return FIELD_TYPE_LABELS[type] ?? type
  }

  function findSection(sectionId) {
    return sections.find((s) => s.id === sectionId)
  }

  function findFieldLocation(fieldId) {
    for (const section of sections) {
      const index = section.fields.findIndex((f) => f.id === fieldId)
      if (index !== -1) {
        return { section, index }
      }
    }
    return null
  }

  function recalculateOrders() {
    sections.forEach((section, sIndex) => {
      section.order = sIndex + 1
      section.fields.forEach((field, fIndex) => {
        field.order = fIndex + 1
      })
    })
  }

  function initSections(data) {
    sections.splice(0, sections.length)
    const source = data?.sections?.length ? data.sections : null

    if (source) {
      source.forEach((section, sIndex) => {
        const sec = {
          id: section.id ?? nextSectionId(),
          title_ar: section.title_ar ?? 'قسم',
          title_en: section.title_en ?? '',
          description_ar: section.description_ar ?? '',
          description_en: section.description_en ?? '',
          order: section.order ?? sIndex + 1,
          collapsed: section.collapsed ?? false,
          fields: (section.fields ?? []).map((field, fIndex) => normalizeField(field, fIndex)),
        }
        sections.push(sec)
      })
    } else {
      sections.push(createDefaultSection())
    }

    if (!sections.length) sections.push(createDefaultSection())
    activeSectionId.value = sections[0]?.id ?? null
    recalculateOrders()
  }

  function normalizeField(field, index = 0) {
    const schema = { ...DEFAULT_FIELD_SCHEMA(), ...(field.schema ?? {}) }
    schema.label_ar = schema.label_ar || field.name_ar || field.name || ''
    schema.label_en = schema.label_en || field.name_en || field.label_en || ''
    schema.validation.required = field.required ?? schema.validation.required

    return {
      id: field.id ?? nextFieldId(),
      name_ar: field.name_ar ?? field.name ?? schema.label_ar,
      name_en: field.name_en ?? field.label_en ?? schema.label_en,
      type: field.type ?? 'text',
      required: !!schema.validation.required,
      options: fieldHasOptions(field.type) ? (field.options ?? []).map((o) => ({ ...o })) : [],
      placeholder: field.placeholder ?? schema.placeholder_ar ?? '',
      default_value: field.default_value ?? schema.default_value ?? '',
      validation: schema.validation,
      schema,
      order: field.order ?? index + 1,
    }
  }

  function resetFieldDraft() {
    Object.assign(fieldDraft, createEmptyFieldDraft())
    editingFieldId.value = null
  }

  function syncDraftFromField(field) {
    fieldDraft.name_ar = field.name_ar
    fieldDraft.name_en = field.name_en
    fieldDraft.type = field.type
    fieldDraft.required = field.required
    fieldDraft.options = fieldHasOptions(field.type)
      ? field.options.map((o) => ({ ...o }))
      : []
    fieldDraft.schema = JSON.parse(JSON.stringify(field.schema ?? DEFAULT_FIELD_SCHEMA()))
    fieldDraft.schema.label_ar = fieldDraft.name_ar
    fieldDraft.schema.label_en = fieldDraft.name_en
    fieldDraft.schema.validation.required = field.required
  }

  function buildFieldFromDraft() {
    const schema = JSON.parse(JSON.stringify(fieldDraft.schema))
    schema.label_ar = fieldDraft.name_ar.trim()
    schema.label_en = fieldDraft.name_en.trim()
    schema.validation.required = !!fieldDraft.required

    return {
      id: editingFieldId.value ?? nextFieldId(),
      name_ar: fieldDraft.name_ar.trim(),
      name_en: fieldDraft.name_en.trim(),
      type: fieldDraft.type,
      required: !!fieldDraft.required,
      options: fieldHasOptions(fieldDraft.type)
        ? fieldDraft.options.map((o) => ({ ...o, value: o.value ?? '' }))
        : [],
      placeholder: schema.placeholder_ar ?? '',
      default_value: schema.default_value ?? '',
      validation: schema.validation,
      schema,
    }
  }

  function validateFieldDraft() {
    if (!fieldDraft.name_ar?.trim() && !fieldDraft.name_en?.trim()) {
      Swal.fire('تنبيه', 'برجاء إدخال اسم الحقل بلغة واحدة على الأقل', 'warning')
      return false
    }
    const section = findSection(activeSectionId.value) ?? sections[0]
    if (!section) {
      Swal.fire('تنبيه', 'أضف قسماً أولاً', 'warning')
      return false
    }
    activeSectionId.value = section.id
    return true
  }

  async function translateFieldDraftNames() {
    const ar = fieldDraft.name_ar?.trim() ?? ''
    const en = fieldDraft.name_en?.trim() ?? ''

    if ((!ar && !en) || (ar && en)) {
      return
    }

    try {
      const translated = await translateNamePair(ar, en)
      fieldDraft.name_ar = translated.name_ar
      fieldDraft.name_en = translated.name_en
      fieldDraft.schema.label_ar = translated.name_ar
      fieldDraft.schema.label_en = translated.name_en
    } catch {
      // Translation is best-effort; the user can still save with one language filled.
    }
  }

  async function addFieldToSection() {
    if (!validateFieldDraft()) return
    await translateFieldDraftNames()
    const section = findSection(activeSectionId.value) ?? sections[0]
    const field = buildFieldFromDraft()

    if (editingFieldId.value) {
      const loc = findFieldLocation(editingFieldId.value)
      if (loc) {
        field.id = editingFieldId.value
        loc.section.fields.splice(loc.index, 1, field)
        toast.success('تم تحديث الحقل بنجاح', { position: 'top-right', autoClose: 3000 })
      }
    } else {
      section.fields.push(field)
    }

    resetFieldDraft()
    recalculateOrders()
  }

  function editField(fieldId) {
    const loc = findFieldLocation(fieldId)
    if (!loc) return
    editingFieldId.value = fieldId
    activeSectionId.value = loc.section.id
    syncDraftFromField(loc.section.fields[loc.index])
    activeTab.value = 'builder'
  }

  function duplicateField(fieldId) {
    const loc = findFieldLocation(fieldId)
    if (!loc) return
    const source = loc.section.fields[loc.index]
    const duplicate = JSON.parse(JSON.stringify(source))
    duplicate.id = nextFieldId()
    duplicate.name_ar = `${duplicate.name_ar} (نسخة)`.trim()
    duplicate.name_en = `${duplicate.name_en} (Copy)`.trim()
    duplicate.schema.label_ar = duplicate.name_ar
    duplicate.schema.label_en = duplicate.name_en
    loc.section.fields.splice(loc.index + 1, 0, duplicate)
    recalculateOrders()
    toast.success('تم نسخ الحقل بنجاح', { position: 'top-right', autoClose: 3000 })
  }

  function confirmDeleteField(fieldId) {
    Swal.fire({
      html: `
        <div class="form-delete-dialog text-center">
          <div class="form-delete-dialog__icon mb-3"><i class="bi bi-trash"></i></div>
          <p class="form-delete-dialog__title mb-2">هل أنت متأكد من حذف هذا الحقل؟</p>
          <p class="form-delete-dialog__text mb-0">سيتم حذف جميع البيانات المرتبطة به.</p>
        </div>`,
      showCancelButton: true,
      confirmButtonText: 'حذف',
      cancelButtonText: 'إلغاء',
      confirmButtonColor: '#dc3545',
      reverseButtons: true,
      focusCancel: true,
    }).then((r) => r.isConfirmed && deleteField(fieldId))
  }

  function deleteField(fieldId) {
    const loc = findFieldLocation(fieldId)
    if (!loc) return
    if (editingFieldId.value === fieldId) resetFieldDraft()
    loc.section.fields.splice(loc.index, 1)
    recalculateOrders()
    toast.success('تم حذف الحقل بنجاح', { position: 'top-right', autoClose: 3000 })
  }

  function addSection() {
    sections.push({
      id: nextSectionId(),
      title_ar: 'قسم جديد',
      title_en: 'New Section',
      description_ar: '',
      description_en: '',
      order: sections.length + 1,
      collapsed: false,
      fields: [],
    })
    activeSectionId.value = sections[sections.length - 1].id
    recalculateOrders()
  }

  function removeSection(sectionId) {
    if (sections.length <= 1) {
      Swal.fire('تنبيه', 'يجب أن يحتوي النموذج على قسم واحد على الأقل', 'warning')
      return
    }
    const index = sections.findIndex((s) => s.id === sectionId)
    if (index === -1) return
    sections.splice(index, 1)
    activeSectionId.value = sections[0]?.id ?? null
    recalculateOrders()
  }

  function toggleSectionCollapse(sectionId) {
    const section = findSection(sectionId)
    if (section) section.collapsed = !section.collapsed
  }

  function onSectionDragStart(index) {
    draggedSectionIndex.value = index
  }

  function onSectionDrop(targetIndex) {
    if (draggedSectionIndex.value === null || draggedSectionIndex.value === targetIndex) return
    const [moved] = sections.splice(draggedSectionIndex.value, 1)
    sections.splice(targetIndex, 0, moved)
    draggedSectionIndex.value = null
    recalculateOrders()
  }

  function onFieldDragStart(sectionId, index) {
    draggedField.value = { sectionId, index }
  }

  function onFieldDragOver(sectionId, index) {
    if (!draggedField.value) return
    dragOverField.value = { sectionId, index }
  }

  function onFieldDragEnd() {
    draggedField.value = null
    dragOverField.value = null
  }

  function onFieldDrop(targetSectionId, targetIndex) {
    if (!draggedField.value) return
    const sourceSection = findSection(draggedField.value.sectionId)
    const targetSection = findSection(targetSectionId)
    if (!sourceSection || !targetSection) return

    const [moved] = sourceSection.fields.splice(draggedField.value.index, 1)
    targetSection.fields.splice(targetIndex, 0, moved)
    onFieldDragEnd()
    recalculateOrders()
  }

  function addFieldOption() {
    fieldDraft.options.push({ value: '', label_ar: '', label_en: '' })
  }

  function removeFieldOption(index) {
    fieldDraft.options.splice(index, 1)
  }

  function addLogicRule() {
    logicRules.push({
      id: `rule_${logicRules.length + 1}`,
      field_key: '',
      operator: 'equals',
      value: '',
      action: 'show',
      target_field_key: '',
      target_section_id: null,
    })
  }

  function removeLogicRule(index) {
    logicRules.splice(index, 1)
  }

  function addWorkflowStage() {
    if (!workflow.stages) workflow.stages = []
    workflow.stages.push({
      id: `stage_${workflow.stages.length + 1}`,
      name_ar: 'مرحلة جديدة',
      name_en: 'New Stage',
      role: '',
      user_id: null,
      department: '',
      actions: ['approve', 'reject', 'return'],
    })
  }

  function serializePayload() {
    return {
      name: formMeta.name,
      name_en: formMeta.name_en,
      description_ar: formMeta.description_ar,
      description_en: formMeta.description_en,
      publication_status: formMeta.publication_status,
      template_key: formMeta.template_key,
      visibility_settings: formMeta.visibility,
      submission_settings: formMeta.submission,
      workflow_definition: workflow,
      logic_rules: logicRules,
      builder_settings: { version: 2 },
      sections: sections.map((section) => ({
        title_ar: section.title_ar,
        title_en: section.title_en,
        description_ar: section.description_ar,
        description_en: section.description_en,
        order: section.order,
        collapsed: section.collapsed,
        fields: section.fields.map((field) => ({
          name_ar: field.name_ar,
          name_en: field.name_en,
          label_en: field.name_en,
          type: field.type,
          required: field.required,
          options: fieldHasOptions(field.type) ? field.options : [],
          order: field.order,
          schema: field.schema,
          placeholder: field.schema?.placeholder_ar,
          default_value: field.schema?.default_value,
          validation: field.schema?.validation,
          visibility: field.schema?.visibility,
        })),
      })),
    }
  }

  async function loadTemplate(key, routeFn) {
    const { data } = await axios.get(routeFn(key))
    const def = data.template?.definition ?? {}
    formMeta.name = def.name_ar ?? data.template.name_ar
    formMeta.name_en = def.name_en ?? data.template.name_en
    formMeta.template_key = key
    initSections(def)
    toast.success('تم تحميل القالب بنجاح', { position: 'top-right', autoClose: 3000 })
  }

  const totalFields = computed(() =>
    sections.reduce((sum, s) => sum + s.fields.length, 0)
  )

  const previewSections = computed(() => sections)

  initSections(initial)

  return {
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
    dragOverSectionId,
    dragOverField,
    totalFields,
    previewSections,
    fieldHasOptions,
    fieldTypeLabel,
    findSection,
    recalculateOrders,
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
    onFieldDragEnd,
    onFieldDrop,
    addFieldOption,
    removeFieldOption,
    addLogicRule,
    removeLogicRule,
    addWorkflowStage,
    serializePayload,
    loadTemplate,
    initSections,
  }
}
