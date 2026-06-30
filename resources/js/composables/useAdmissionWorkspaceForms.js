import { ref, watch, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'

function buildVisitForms(visits) {
  return Object.fromEntries(
    (visits || []).map((v) => [v.id, {
      scheduled_date: v.scheduled_date || '',
      scheduled_time: v.scheduled_time || '',
      status: v.status || '',
      outcome: v.outcome || '',
      attendance_status: v.attendance_status || '',
      notes: v.notes || '',
      follow_up_notes: v.follow_up_notes || '',
    }]),
  )
}

function buildDocumentForms(documents) {
  return Object.fromEntries(
    (documents || []).map((d) => [d.id, {
      status: d.status,
      notes: d.notes || '',
      file_path: d.file_path || '',
    }]),
  )
}

export function useAdmissionWorkspaceForms(workspaceRef, categoriesRef) {
  const primaryApplicant = computed(() => workspaceRef.value?.applicants?.[0] || null)
  const primaryContact = computed(() =>
    workspaceRef.value?.contacts?.find((c) => c.is_primary)
    || workspaceRef.value?.contacts?.[0]
    || null,
  )
  const app = computed(() => workspaceRef.value?.application)

  const applicantForm = useForm({
    first_name: '',
    father_name: '',
    grandfather_name: '',
    gender: '',
    date_of_birth: '',
    current_grade_label: '',
    target_category_id: '',
    national_id: '',
    notes: '',
  })

  const contactForm = useForm({
    name: '',
    email: '',
    phone: '',
    national_id: '',
    address: '',
    relationship_type: 'guardian',
    communication_preferences: {},
  })

  const visitForms = ref({})
  const documentForms = ref({})
  const selectedStageId = ref(null)

  function findStageIdForCategory(categoryId, categories) {
    if (!categoryId) return null
    for (const stage of categories || []) {
      if (categoryInTree(Number(categoryId), [stage])) {
        return stage.id
      }
    }
    return null
  }

  function categoryInTree(categoryId, nodes) {
    for (const node of nodes) {
      if (node.id === categoryId) return true
      if (node.children?.length && categoryInTree(categoryId, node.children)) return true
    }
    return false
  }

  function syncApplicantForm() {
    const a = primaryApplicant.value
    if (!a) return

    applicantForm.defaults({
      first_name: a.first_name || '',
      father_name: a.father_name || '',
      grandfather_name: a.grandfather_name || '',
      gender: a.gender || '',
      date_of_birth: a.date_of_birth || '',
      current_grade_label: a.current_grade_label || '',
      target_category_id: a.target_category_id || app.value?.target_category?.id || '',
      national_id: a.national_id || '',
      notes: a.notes || '',
    }).reset()

    const categoryId = a.target_category_id || app.value?.target_category?.id
    selectedStageId.value = findStageIdForCategory(categoryId, categoriesRef.value)
  }

  function syncContactForm() {
    const c = primaryContact.value
    if (!c) return

    contactForm.defaults({
      name: c.name || '',
      email: c.email || '',
      phone: c.phone || '',
      national_id: c.national_id || '',
      address: c.address || '',
      relationship_type: c.relationship_type || 'guardian',
      communication_preferences: { ...(c.communication_preferences || {}) },
    }).reset()
  }

  function syncVisitForms() {
    visitForms.value = buildVisitForms(workspaceRef.value?.visits)
  }

  function syncDocumentForms() {
    documentForms.value = buildDocumentForms(workspaceRef.value?.documents)
  }

  function syncAllForms() {
    syncApplicantForm()
    syncContactForm()
    syncVisitForms()
    syncDocumentForms()
  }

  watch(workspaceRef, syncAllForms, { deep: true, immediate: true })
  watch(categoriesRef, () => {
    const categoryId = primaryApplicant.value?.target_category_id || app.value?.target_category?.id
    selectedStageId.value = findStageIdForCategory(categoryId, categoriesRef.value)
  })

  return {
    primaryApplicant,
    primaryContact,
    app,
    applicantForm,
    contactForm,
    visitForms,
    documentForms,
    selectedStageId,
    syncAllForms,
  }
}
