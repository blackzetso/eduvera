<script setup>
import { ref, inject, computed, onMounted, onUnmounted } from 'vue'
import { useWebsiteContent } from '@/composables/useWebsiteContent'
import { useCta } from '@/composables/useCta'
import BrandSocialIcon from '@/Components/Marketing/SchoolTalent/BrandSocialIcon.vue'

const { schoolInfo, buildWhatsAppUrl, whatsappQuickActions, floatingChrome } = useWebsiteContent()
const { resolveCta } = useCta()

const SCROLL_COLLAPSE_PX = 100

const navigateTo = inject('stNavigate', (href, e) => {
  if (e && href?.startsWith('#')) {
    e.preventDefault()
    document.querySelector(href)?.scrollIntoView({ behavior: 'smooth' })
  }
})

const expanded = ref(false)
const sheetOpen = ref(false)
let scrollAnchorY = 0

const panelTitle = computed(() => floatingChrome.value?.panel_title ?? 'Admissions')

const panelActions = computed(() => {
  const defs = floatingChrome.value?.panel_actions ?? [
    { cta_id: 'apply', icon: 'bi-pencil-square', variant: 'primary' },
    { cta_id: 'visit', icon: 'bi-calendar-check', variant: 'outline' },
    { cta_id: 'whatsapp', icon: 'bi-whatsapp', variant: 'whatsapp' },
    { cta_id: 'info', icon: 'bi-envelope', variant: 'outline' },
  ]

  return defs.map((def) => {
    const isWa = def.cta_id === 'whatsapp' || def.variant === 'whatsapp'
    const cta = isWa ? null : resolveCta(def.cta_id)
    return {
      label: def.label ?? (isWa ? (floatingChrome.value?.whatsapp_action_label ?? 'WhatsApp Admissions') : cta?.label),
      href: isWa
        ? buildWhatsAppUrl(schoolInfo.value?.contact?.whatsapp, whatsappQuickActions.value?.[0]?.message ?? '')
        : (cta?.href ?? '#visit'),
      icon: def.icon ?? 'bi-link',
      brandIcon: isWa ? 'whatsapp' : null,
      variant: isWa ? 'whatsapp' : (def.variant ?? 'outline'),
      external: isWa,
    }
  })
})

function isOpen() {
  return expanded.value || sheetOpen.value
}

function collapsePanel() {
  expanded.value = false
  sheetOpen.value = false
}

function expandFromTab() {
  expanded.value = true
  sheetOpen.value = false
  scrollAnchorY = window.scrollY
}

function toggleSheet() {
  sheetOpen.value = !sheetOpen.value
  expanded.value = false
  scrollAnchorY = window.scrollY
}

function onPanelEnter() {
  expanded.value = true
  sheetOpen.value = false
}

function onPanelLeave() {
  collapsePanel()
}

function onActionClick(href, event, external) {
  if (!external) navigateTo(href, event)
  collapsePanel()
}

function onDocumentClick(e) {
  if (!isOpen()) return
  const root = e.target.closest?.('.st-float-panel, .st-float-sheet')
  if (!root) collapsePanel()
}

function onScroll() {
  if (!isOpen()) return
  if (Math.abs(window.scrollY - scrollAnchorY) > SCROLL_COLLAPSE_PX) collapsePanel()
}

onMounted(() => {
  document.addEventListener('click', onDocumentClick, true)
  window.addEventListener('scroll', onScroll, { passive: true })
})

onUnmounted(() => {
  document.removeEventListener('click', onDocumentClick, true)
  window.removeEventListener('scroll', onScroll)
})
</script>

<template>
  <!-- Desktop side panel -->
  <div
    class="st-float-panel d-none d-lg-flex"
    :class="{ 'st-float-panel--open': expanded }"
  >
    <div
      class="st-float-panel__inner"
      @mouseenter="onPanelEnter"
      @mouseleave="onPanelLeave"
    >
      <button type="button" class="st-float-panel__tab" :aria-expanded="expanded" @click="expandFromTab">
        <i class="bi bi-mortarboard-fill" aria-hidden="true"></i>
        {{ panelTitle }}
      </button>
      <div class="st-float-panel__menu" role="menu">
        <a
          v-for="(action, i) in panelActions"
          :key="'d-' + i"
          :href="action.href"
          class="st-float-panel__action"
          :class="`st-float-panel__action--${action.variant}`"
          role="menuitem"
          :target="action.external ? '_blank' : undefined"
          :rel="action.external ? 'noopener noreferrer' : undefined"
          @click="onActionClick(action.href, $event, action.external)"
        >
          <BrandSocialIcon v-if="action.brandIcon" :brand="action.brandIcon" size="1.4rem" />
          <i v-else :class="['bi', action.icon]" aria-hidden="true"></i>
          {{ action.label }}
        </a>
      </div>
    </div>
  </div>

  <!-- Mobile FAB + sheet -->
  <div class="st-float-sheet d-lg-none" :class="{ 'st-float-sheet--open': sheetOpen }">
    <button
      type="button"
      class="st-float-sheet__fab"
      :class="{ 'st-float-sheet__fab--expanded': sheetOpen }"
      :aria-expanded="sheetOpen"
      @click="toggleSheet"
    >
      <i class="bi bi-mortarboard-fill" aria-hidden="true"></i>
      <span v-if="!sheetOpen" class="st-float-sheet__fab-label">{{ panelTitle }}</span>
    </button>
    <div class="st-float-sheet__panel" role="menu">
      <p class="st-float-sheet__title">{{ panelTitle }}</p>
      <a
        v-for="(action, i) in panelActions"
        :key="'m-' + i"
        :href="action.href"
        class="st-float-sheet__action"
        :class="`st-float-sheet__action--${action.variant}`"
        role="menuitem"
        :target="action.external ? '_blank' : undefined"
        :rel="action.external ? 'noopener noreferrer' : undefined"
        @click="onActionClick(action.href, $event, action.external)"
      >
        <BrandSocialIcon
          v-if="action.brandIcon"
          :brand="action.brandIcon"
          size="1.25rem"
          variant="glyph"
        />
        <i v-else :class="['bi', action.icon]" aria-hidden="true"></i>
        {{ action.label }}
      </a>
    </div>
  </div>
</template>
