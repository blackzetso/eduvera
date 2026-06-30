<script setup>
import { computed } from 'vue'
import ContextBanner from '@/Components/Workspace/ContextBanner.vue'
import StudentWorkspaceSidebar from '@/Components/Workspace/StudentWorkspaceSidebar.vue'
import FamilyWorkspaceSidebar from '@/Components/Workspace/FamilyWorkspaceSidebar.vue'
import { useWorkspaceContext, WORKSPACE_MODES } from '@/composables/useWorkspaceContext'

const props = defineProps({
  workspaceContext: { type: Object, default: null },
  activeTab: { type: String, required: true },
  mode: { type: String, default: null },
})

const emit = defineEmits(['set-tab', 'scroll-timeline'])

const { workspaceContext, isStudentWorkspace, isFamilyWorkspace, sidebarLinks } = useWorkspaceContext({
  workspaceContext: props.workspaceContext,
  mode: props.mode,
})

const context = computed(() => props.workspaceContext || workspaceContext.value)

const activeLinkLabel = computed(() => {
  const link = sidebarLinks.value.find((l) => {
    if (l.id === 'overview-timeline') return props.activeTab === 'overview'
    return l.id === props.activeTab
  })
  return link?.label ?? 'القسم'
})

function onMobileSelect(event) {
  const value = event.target.value
  if (!value) return
  if (value === 'overview-timeline') {
    emit('set-tab', 'overview')
    emit('scroll-timeline')
    return
  }
  emit('set-tab', value)
}
</script>

<template>
  <div
    class="workspace-layout"
    :class="{
      'workspace-layout--student': isStudentWorkspace,
      'workspace-layout--family': isFamilyWorkspace,
    }"
  >
    <ContextBanner :context="context" />

    <!-- Mobile context menu (replaces sidebar) -->
    <div class="workspace-mobile-menu d-lg-none mb-3">
      <div class="dropdown w-100">
        <button
          class="btn btn-light border w-100 d-flex justify-content-between align-items-center rounded-4 shadow-sm"
          type="button"
          data-bs-toggle="dropdown"
          aria-expanded="false"
        >
          <span>
            <i class="bi bi-list me-2"></i>
            {{ activeLinkLabel }}
          </span>
          <i class="bi bi-chevron-down"></i>
        </button>
        <ul class="dropdown-menu w-100">
          <li v-for="link in sidebarLinks" :key="link.id">
            <button
              type="button"
              class="dropdown-item workspace-mobile-menu__item"
              :class="{
                active: link.id === 'overview-timeline'
                  ? activeTab === 'overview'
                  : activeTab === link.id,
              }"
              @click="
                link.scrollTimeline
                  ? (emit('set-tab', link.tab || link.id), emit('scroll-timeline'))
                  : emit('set-tab', link.id)
              "
            >
              <i :class="['bi me-2', link.icon]"></i>
              {{ link.label }}
            </button>
          </li>
        </ul>
      </div>
    </div>

    <div class="row g-3 g-lg-4">
      <div v-if="isStudentWorkspace" class="col-lg-3 d-none d-lg-block">
        <StudentWorkspaceSidebar
          :active-tab="activeTab"
          @set-tab="emit('set-tab', $event)"
        />
      </div>
      <div v-else-if="isFamilyWorkspace" class="col-lg-3 d-none d-lg-block">
        <FamilyWorkspaceSidebar
          :active-tab="activeTab"
          @set-tab="emit('set-tab', $event)"
          @scroll-timeline="emit('scroll-timeline')"
        />
      </div>

      <div class="col-lg-9 workspace-content-area">
        <slot />
      </div>
    </div>
  </div>
</template>
