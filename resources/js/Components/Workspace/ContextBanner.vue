<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  context: { type: Object, required: true },
})

const modeClass = computed(() =>
  props.context.mode === 'family'
    ? 'workspace-context-banner--family'
    : 'workspace-context-banner--student',
)

const relatedLabel = computed(() =>
  props.context.related_profile_label_ar
  || props.context.related_profile_label
  || 'Open Related Profile',
)

const returnLabel = computed(() =>
  props.context.return_label_ar
  || props.context.return_label
  || 'Return To Admin',
)
</script>

<template>
  <div
    class="workspace-context-banner workspace-layout mb-3 mb-md-4 p-3 p-md-4"
    :class="modeClass"
  >
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
      <div class="d-flex align-items-center gap-3 min-w-0">
        <span class="workspace-context-banner__icon">
          <i :class="['bi', context.icon || 'bi-layers']"></i>
        </span>
        <div class="min-w-0">
          <div class="workspace-context-banner__mode">
            {{ context.label_ar || context.label }}
          </div>
          <div class="workspace-context-banner__entity text-truncate">
            {{ context.entity_name }}
          </div>
        </div>
      </div>

      <div class="d-flex flex-wrap gap-2">
        <Link
          :href="context.return_url"
          class="btn btn-sm btn-outline-secondary rounded-4"
        >
          <i class="bi bi-arrow-right me-1"></i>
          {{ returnLabel }}
        </Link>
        <Link
          v-if="context.related_profile_url"
          :href="context.related_profile_url"
          class="btn btn-sm btn-primary rounded-4"
        >
          <i class="bi bi-box-arrow-up-right me-1"></i>
          {{ relatedLabel }}
        </Link>
      </div>
    </div>
  </div>
</template>
