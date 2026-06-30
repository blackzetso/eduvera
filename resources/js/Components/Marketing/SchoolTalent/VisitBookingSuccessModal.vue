<script setup>
import { computed } from 'vue'
import { useWebsiteContent } from '@/composables/useWebsiteContent'

const props = defineProps({
  open: { type: Boolean, default: false },
  confirmation: { type: Object, default: null },
})

const emit = defineEmits(['close'])

const { visitFormConfig } = useWebsiteContent()

const labels = computed(() => visitFormConfig.value?.labels ?? {})

const visit = computed(() => props.confirmation?.visit ?? {})
const contact = computed(() => props.confirmation?.contact ?? {})
const confirmMeta = computed(() => props.confirmation?.confirmation ?? {})

function formatDate(value) {
  if (!value) return '—'
  try {
    return new Date(value + 'T12:00:00').toLocaleDateString(undefined, {
      weekday: 'short',
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    })
  } catch {
    return value
  }
}

function onBackdrop(e) {
  if (e.target === e.currentTarget) {
    emit('close')
  }
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open && confirmation"
      class="st-modal st-visit-success-modal"
      role="dialog"
      aria-modal="true"
      :aria-label="labels.success_modal_title || 'Visit booking confirmation'"
      @click="onBackdrop"
    >
      <div class="st-modal__panel st-visit-success-modal__panel">
        <button type="button" class="st-modal__close" :aria-label="labels.close || 'Close'" @click="emit('close')">
          <i class="bi bi-x-lg"></i>
        </button>

        <div class="st-visit-success-modal__header">
          <div class="st-visit-success-modal__icon">
            <i class="bi bi-calendar-check"></i>
          </div>
          <h2 class="h4 mb-2">{{ labels.success_modal_title || 'Visit Request Received' }}</h2>
          <p class="text-muted mb-0">
            {{ labels.success_modal_lead || 'Thank you! Here are your visit details:' }}
          </p>
        </div>

        <div class="st-visit-success-modal__body">
          <div class="st-visit-success-modal__ref">
            <span class="text-muted small">{{ labels.success_reference || 'Reference' }}</span>
            <code class="fs-5 fw-bold">{{ confirmation.reference_code }}</code>
          </div>

          <dl class="st-visit-success-modal__details">
            <div v-if="contact.parent_name">
              <dt>{{ labels.parent_name || 'Parent' }}</dt>
              <dd>{{ contact.parent_name }}</dd>
            </div>
            <div v-if="contact.student_name">
              <dt>{{ labels.student_name || 'Student' }}</dt>
              <dd>{{ contact.student_name }}</dd>
            </div>
            <div v-if="contact.current_grade">
              <dt>{{ labels.current_grade || 'Grade' }}</dt>
              <dd>{{ contact.current_grade }}</dd>
            </div>
            <div v-if="visit.scheduled_date">
              <dt>{{ labels.visit_date || 'Visit date' }}</dt>
              <dd>{{ formatDate(visit.scheduled_date) }}</dd>
            </div>
            <div v-if="visit.scheduled_time">
              <dt>{{ labels.visit_time || 'Visit time' }}</dt>
              <dd>{{ visit.scheduled_time }}</dd>
            </div>
            <div v-if="contact.phone">
              <dt>{{ labels.phone || 'Phone' }}</dt>
              <dd>{{ contact.phone }}</dd>
            </div>
          </dl>

          <div v-if="confirmMeta.sent_to" class="st-visit-success-modal__email alert alert-success mb-0">
            <i class="bi bi-envelope-check me-2"></i>
            <div>
              <div class="fw-semibold">
                {{ labels.success_modal_email_note || 'A confirmation email has been sent to:' }}
              </div>
              <div class="text-break">{{ confirmMeta.sent_to }}</div>
              <div v-if="!confirmMeta.email_sent" class="small mt-1 text-muted">
                {{ labels.success_modal_email_pending || 'We will send your confirmation shortly.' }}
              </div>
            </div>
          </div>

          <p v-if="confirmMeta.school_receiver_email" class="small text-muted mb-0 mt-3">
            {{ labels.success_modal_school_note || 'Our admissions team' }}
            (<a :href="`mailto:${confirmMeta.school_receiver_email}`">{{ confirmMeta.school_receiver_email }}</a>)
            {{ labels.success_modal_school_suffix || 'will review your request.' }}
          </p>
        </div>

        <div class="st-visit-success-modal__footer">
          <button type="button" class="st-btn st-btn--solid w-100" @click="emit('close')">
            {{ labels.success_modal_close || 'Got it' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
