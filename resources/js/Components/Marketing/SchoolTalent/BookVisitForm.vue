<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useWebsiteContent } from '@/composables/useWebsiteContent'
import { useCta } from '@/composables/useCta'
import { useUiLabel } from '@/composables/useUiLabel'
import VisitBookingSuccessModal from '@/Components/Marketing/SchoolTalent/VisitBookingSuccessModal.vue'

const { visitFormConfig } = useWebsiteContent()
const { resolveCta } = useCta()
const { l } = useUiLabel()

const emit = defineEmits(['submit', 'success', 'error'])

const submitting = ref(false)
const submitError = ref('')
const showSuccessModal = ref(false)
const successConfirmation = ref(null)

const form = reactive({})

const FIELD_GROUPS = [
  {
    id: 'family',
    titleKey: 'group_family',
    fallback: 'Family details',
    icon: 'bi-people',
    keys: ['parentName', 'studentName', 'currentGrade'],
  },
  {
    id: 'contact',
    titleKey: 'group_contact',
    fallback: 'Contact information',
    icon: 'bi-telephone',
    keys: ['phone', 'email'],
  },
  {
    id: 'schedule',
    titleKey: 'group_schedule',
    fallback: 'Preferred visit',
    icon: 'bi-calendar-event',
    keys: ['visitDate', 'visitTime'],
  },
  {
    id: 'notes',
    titleKey: 'group_notes',
    fallback: 'Additional notes',
    icon: 'bi-chat-left-text',
    keys: ['notes'],
  },
]

const activeFields = computed(() => {
  const fields = visitFormConfig.value?.fields ?? []
  return fields
    .filter((f) => f.enabled !== false)
    .sort((a, b) => (a.sort ?? 0) - (b.sort ?? 0))
})

const fieldByKey = computed(() => {
  const map = new Map()
  for (const field of activeFields.value) {
    map.set(field.key, field)
  }
  return map
})

const formGroups = computed(() => {
  const used = new Set()
  const groups = []

  for (const group of FIELD_GROUPS) {
    const fields = group.keys
      .map((key) => fieldByKey.value.get(key))
      .filter(Boolean)
      .filter((f) => !used.has(f.key))

    if (!fields.length) continue

    for (const f of fields) used.add(f.key)

    groups.push({
      ...group,
      title: labels.value[group.titleKey] || group.fallback,
      rows: buildRows(fields),
    })
  }

  const remaining = activeFields.value.filter((f) => !used.has(f.key))
  if (remaining.length) {
    groups.push({
      id: 'other',
      title: labels.value.group_other || 'Other',
      icon: 'bi-ui-checks',
      rows: buildRows(remaining),
    })
  }

  return groups
})

function buildRows(fields) {
  const rows = []
  const used = new Set()

  for (let i = 0; i < fields.length; i++) {
    if (used.has(i)) continue
    const f = fields[i]
    const pairId = f.rowPair
    if (pairId) {
      const mateIdx = fields.findIndex((x, j) => j > i && !used.has(j) && x.rowPair === pairId)
      if (mateIdx !== -1) {
        rows.push({ type: 'row', fields: [f, fields[mateIdx]] })
        used.add(i)
        used.add(mateIdx)
        continue
      }
    }
    rows.push({ type: 'single', fields: [f] })
    used.add(i)
  }

  return rows
}

watch(
  activeFields,
  (fields) => {
    for (const field of fields) {
      if (!(field.key in form)) {
        form[field.key] = ''
      }
    }
  },
  { immediate: true }
)

const labels = computed(() => visitFormConfig.value?.labels ?? {})
const submitLabel = computed(
  () => labels.value.submit ?? l('global.submit_visit_request', resolveCta('visit')?.label ?? 'Submit Visit Request')
)

function fieldLabel(field) {
  return field.label || labels.value[field.name] || field.key
}

function optionsFor(field) {
  const src = field.optionsSource
  if (!src) return []
  return visitFormConfig.value?.[src] ?? []
}

function closeSuccessModal() {
  showSuccessModal.value = false
  successConfirmation.value = null
}

async function onSubmit() {
  submitError.value = ''
  closeSuccessModal()
  submitting.value = true

  const payload = {
    formId: visitFormConfig.value?.formId,
    submittedAt: new Date().toISOString(),
    ...form,
  }

  emit('submit', payload)

  try {
    const csrf = document.head.querySelector('meta[name="csrf-token"]')?.content ?? ''
    const endpoint = visitFormConfig.value?.submitEndpoint || '/api/admissions/intake/visit'
    const { data } = await window.axios.post(endpoint, payload, {
      headers: { 'X-CSRF-TOKEN': csrf },
    })

    successConfirmation.value = data
    showSuccessModal.value = true

    emit('success', data)

    for (const key of Object.keys(form)) {
      form[key] = ''
    }
  } catch (err) {
    const message = err?.response?.data?.message
      || labels.value.error
      || 'Unable to submit your request. Please try again.'
    submitError.value = message
    emit('error', err)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <form class="st-form st-visit-form" :id="visitFormConfig.formId" @submit.prevent="onSubmit">
    <section
      v-for="group in formGroups"
      :key="group.id"
      class="st-visit-form__group"
    >
      <header class="st-visit-form__group-head">
        <i :class="['bi', group.icon]"></i>
        <h4>{{ group.title }}</h4>
      </header>

      <div class="st-visit-form__group-body">
        <template v-for="(row, ri) in group.rows" :key="`${group.id}-${ri}`">
          <div v-if="row.type === 'row'" class="st-form__row">
            <label v-for="field in row.fields" :key="field.key" class="st-visit-form__field">
              <span class="st-visit-form__label">
                {{ fieldLabel(field) }}<template v-if="field.required"> <em>*</em></template>
              </span>
              <input
                v-if="['text', 'tel', 'email', 'date'].includes(field.type)"
                v-model="form[field.key]"
                :type="field.type"
                :name="field.name"
                :required="field.required"
                :placeholder="field.placeholder"
                class="st-visit-form__control"
              />
              <select
                v-else-if="field.type === 'select'"
                v-model="form[field.key]"
                :name="field.name"
                :required="field.required"
                class="st-visit-form__control"
              >
                <option value="">{{ field.placeholder || 'Select' }}</option>
                <option v-for="opt in optionsFor(field)" :key="opt" :value="opt">{{ opt }}</option>
              </select>
            </label>
          </div>
          <template v-else>
            <label v-for="field in row.fields" :key="field.key" class="st-visit-form__field">
              <span class="st-visit-form__label">
                {{ fieldLabel(field) }}<template v-if="field.required"> <em>*</em></template>
              </span>
              <input
                v-if="['text', 'tel', 'email', 'date'].includes(field.type)"
                v-model="form[field.key]"
                :type="field.type"
                :name="field.name"
                :required="field.required"
                :placeholder="field.placeholder"
                class="st-visit-form__control"
              />
              <select
                v-else-if="field.type === 'select'"
                v-model="form[field.key]"
                :name="field.name"
                :required="field.required"
                class="st-visit-form__control"
              >
                <option value="">{{ field.placeholder || 'Select' }}</option>
                <option v-for="opt in optionsFor(field)" :key="opt" :value="opt">{{ opt }}</option>
              </select>
              <textarea
                v-else-if="field.type === 'textarea'"
                v-model="form[field.key]"
                :name="field.name"
                rows="3"
                :required="field.required"
                :placeholder="field.placeholder"
                class="st-visit-form__control"
              />
            </label>
          </template>
        </template>
      </div>
    </section>

    <div class="st-visit-form__submit">
      <button type="submit" class="st-btn st-btn--solid st-btn--lift w-100" :disabled="submitting">
        <i v-if="!submitting" class="bi bi-send me-2"></i>
        <span v-if="submitting" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
        {{ submitting ? (labels.submitting || 'Submitting...') : submitLabel }}
      </button>
      <p v-if="submitError" class="st-form__hint small text-danger mb-0 mt-2">{{ submitError }}</p>
      <p v-if="visitFormConfig.hint && !showSuccessModal" class="st-form__hint small text-muted mb-0 mt-2">
        {{ visitFormConfig.hint }}
      </p>
    </div>
  </form>

  <VisitBookingSuccessModal
    :open="showSuccessModal"
    :confirmation="successConfirmation"
    @close="closeSuccessModal"
  />
</template>
