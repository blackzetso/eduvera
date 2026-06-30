<script setup>
import {
  timelineEventColor,
  timelineEventIcons,
  engagementStatusBadge,
  engagementChannelBadge,
  engagementTypeEmoji,
} from '@/Shared/admissionsBadges'

defineProps({
  timeline: { type: Array, default: () => [] },
  engagementTimeline: { type: Array, default: () => [] },
  notes: { type: Array, default: () => [] },
  formatDateTime: { type: Function, required: true },
})

const timelineIcon = timelineEventIcons

function eventIcon(event) {
  if (event.is_engagement) {
    return event.icon || timelineIcon.engagement
  }

  return timelineIcon[event.type] || 'bi-circle'
}

function eventEmoji(event) {
  if (!event.is_engagement) return null

  return engagementTypeEmoji[event.engagement_type] || '💬'
}
</script>

<template>
  <div>
    <div class="card border mb-4">
      <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-chat-heart text-info"></i>
        <span class="fw-semibold">سجل التفاعلات</span>
        <span class="badge bg-light text-dark border">{{ engagementTimeline.length }}</span>
      </div>
      <div class="card-body">
        <div v-if="!engagementTimeline.length" class="text-muted">لا توجد تفاعلات مسجّلة بعد.</div>
        <div
          v-for="(event, idx) in engagementTimeline"
          :key="`eng-${event.engagement_id ?? idx}`"
          class="card border border-info border-opacity-25 mb-2"
        >
          <div class="card-body py-2 d-flex gap-3 align-items-start">
            <span
              class="rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0 bg-info bg-opacity-10"
              style="width:2.25rem;height:2.25rem"
            >
              <span class="fs-6" aria-hidden="true">{{ eventEmoji(event) }}</span>
            </span>
            <div class="flex-grow-1">
              <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <span class="fw-semibold">{{ event.title }}</span>
                <span class="badge" :class="engagementChannelBadge(event.channel)">{{ event.channel_label }}</span>
                <span class="badge" :class="engagementStatusBadge(event.status)">{{ event.status_label }}</span>
              </div>
              <div v-if="event.description" class="small">{{ event.description }}</div>
            </div>
            <div class="small text-muted text-end">
              <div>{{ event.performed_by || '—' }}</div>
              <div>{{ formatDateTime(event.occurred_at) }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="mb-2 fw-semibold">الجدول الزمني الكامل</div>
    <div v-if="!timeline.length" class="text-muted">لا توجد أحداث بعد.</div>
    <div
      v-for="(event, idx) in timeline"
      :key="idx"
      class="card border mb-2"
      :class="{
        'border-primary border-opacity-25': event.type?.startsWith('visit'),
        'border-info border-opacity-50': event.is_engagement,
      }"
    >
      <div class="card-body py-2 d-flex gap-3 align-items-start">
        <span
          class="rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0"
          style="width:2.25rem;height:2.25rem"
          :class="event.is_engagement ? 'bg-info bg-opacity-10' : (event.type?.startsWith('visit') ? 'bg-light' : '')"
        >
          <span v-if="event.is_engagement" class="fs-6" aria-hidden="true">{{ eventEmoji(event) }}</span>
          <i
            v-else
            :class="['bi', eventIcon(event), 'fs-5', timelineEventColor(event.is_engagement ? 'engagement' : event.type)]"
          ></i>
        </span>
        <div class="flex-grow-1">
          <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="fw-semibold">{{ event.title }}</span>
            <template v-if="event.is_engagement">
              <span class="badge" :class="engagementChannelBadge(event.channel)">{{ event.channel_label }}</span>
              <span class="badge" :class="engagementStatusBadge(event.status)">{{ event.status_label }}</span>
            </template>
          </div>
          <div>{{ event.description }}</div>
          <div v-if="event.meta" class="small text-muted">{{ event.meta }}</div>
        </div>
        <div class="small text-muted text-end">
          <div>{{ event.performed_by || '—' }}</div>
          <div>{{ formatDateTime(event.occurred_at) }}</div>
        </div>
      </div>
    </div>

    <div class="card border mt-4">
      <div class="card-header">الملاحظات الداخلية</div>
      <div class="card-body">
        <div v-if="!notes.length" class="text-muted">—</div>
        <div v-for="note in notes" :key="note.id" class="mb-3 pb-3 border-bottom">
          <div class="small text-muted">{{ note.author }} · {{ note.visibility_label }} · {{ formatDateTime(note.created_at) }}</div>
          <div>{{ note.content }}</div>
        </div>
      </div>
    </div>
  </div>
</template>
