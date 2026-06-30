<script setup>
import { STUDENT_SIDEBAR_LINKS } from '@/composables/useWorkspaceContext'

defineProps({
  activeTab: { type: String, required: true },
})

const emit = defineEmits(['set-tab'])

const links = STUDENT_SIDEBAR_LINKS
</script>

<template>
  <nav class="workspace-sidebar workspace-layout" aria-label="Student workspace navigation">
    <div class="workspace-sidebar__nav">
      <button
        v-for="link in links"
        :key="link.id"
        type="button"
        class="workspace-sidebar__link"
        :class="{ 'workspace-sidebar__link--active': activeTab === link.id }"
        @click="emit('set-tab', link.id)"
      >
        <i :class="['bi', link.icon]"></i>
        <span>{{ link.label }}</span>
      </button>
    </div>
  </nav>
</template>
