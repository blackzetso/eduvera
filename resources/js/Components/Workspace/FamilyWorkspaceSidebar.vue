<script setup>
import { FAMILY_SIDEBAR_LINKS } from '@/composables/useWorkspaceContext'

const props = defineProps({
  activeTab: { type: String, required: true },
})

const emit = defineEmits(['set-tab', 'scroll-timeline'])

const links = FAMILY_SIDEBAR_LINKS

function isActive(link) {
  if (link.id === 'overview-timeline') {
    return props.activeTab === 'overview'
  }
  return props.activeTab === link.id
}

function onClick(link) {
  const tabId = link.tab || link.id
  emit('set-tab', tabId)
  if (link.scrollTimeline) {
    emit('scroll-timeline')
  }
}
</script>

<template>
  <nav class="workspace-sidebar workspace-layout" aria-label="Family workspace navigation">
    <div class="workspace-sidebar__nav">
      <button
        v-for="link in links"
        :key="link.id"
        type="button"
        class="workspace-sidebar__link"
        :class="{ 'workspace-sidebar__link--active': isActive(link) }"
        @click="onClick(link)"
      >
        <i :class="['bi', link.icon]"></i>
        <span>{{ link.label }}</span>
      </button>
    </div>
  </nav>
</template>
