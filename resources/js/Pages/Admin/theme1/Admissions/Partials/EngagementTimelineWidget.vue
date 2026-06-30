<script setup>
import { computed, ref } from 'vue'
import {
  engagementStatusBadge,
  engagementChannelBadge,
  engagementTypeEmoji,
} from '@/Shared/admissionsBadges'

const props = defineProps({
  engagements: { type: Array, default: () => [] },
  formatDateTime: { type: Function, required: true },
  limit: { type: Number, default: 5 },
})

const emit = defineEmits(['open-full'])

const collapsed = ref(false)

const preview = computed(() => (props.engagements || []).slice(0, props.limit))
const hasMore = computed(() => (props.engagements || []).length > props.limit)
</script>

<template>
  <div class="card admissions-command-card border-0 shadow-sm h-100">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2 border-0">
      <h6 class="mb-0 fw-bold">
        <i class="bi bi-chat-heart text-info me-1"></i>
        سجل التفاعلات
      </h6>
      <div class="d-flex gap-2">
        <button
          type="button"
          class="btn btn-sm btn-link text-decoration-none d-md-none"
          @click="collapsed = !collapsed"
        >
          <i :class="['bi', collapsed ? 'bi-chevron-down' : 'bi-chevron-up']"></i>
        </button>
        <button type="button" class="btn btn-sm btn-outline-primary" @click="emit('open-full')">
          عرض السجل الكامل
        </button>
      </div>
    </div>

    <div class="card-body pt-0" :class="{ 'd-none d-md-block': collapsed }">
      <div v-if="!preview.length" class="text-muted small text-center py-3">لا توجد تفاعلات مسجّلة.</div>

      <div v-else class="d-flex flex-column gap-2">
        <div
          v-for="(event, idx) in preview"
          :key="event.engagement_id ?? idx"
          class="admissions-engagement-card"
        >
          <div class="d-flex gap-2 align-items-start">
            <span class="flex-shrink-0" aria-hidden="true">{{ engagementTypeEmoji[event.engagement_type] || '💬' }}</span>
            <div class="flex-grow-1 min-w-0">
              <div class="d-flex flex-wrap align-items-center gap-1 mb-1">
                <span class="fw-semibold small">{{ event.title }}</span>
                <span class="badge" :class="engagementChannelBadge(event.channel)">{{ event.channel_label }}</span>
                <span class="badge" :class="engagementStatusBadge(event.status)">{{ event.status_label }}</span>
              </div>
              <div v-if="event.description" class="small text-muted text-truncate">{{ event.description }}</div>
              <div class="small text-muted mt-1">
                {{ event.performed_by || '—' }} · {{ formatDateTime(event.occurred_at) }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="hasMore" class="text-center mt-2">
        <button type="button" class="btn btn-link btn-sm text-decoration-none" @click="emit('open-full')">
          +{{ engagements.length - limit }} تفاعلات أخرى
        </button>
      </div>
    </div>
  </div>
</template>
