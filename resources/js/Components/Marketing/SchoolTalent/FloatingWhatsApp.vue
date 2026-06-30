<script setup>
import { computed } from 'vue'
import { useWebsiteContent } from '@/composables/useWebsiteContent'
import BrandSocialIcon from '@/Components/Marketing/SchoolTalent/BrandSocialIcon.vue'

const { schoolInfo, buildWhatsAppUrl, whatsappQuickActions, floatingChrome } = useWebsiteContent()

const waHref = computed(() =>
  buildWhatsAppUrl(
    schoolInfo.value?.contact?.whatsapp,
    whatsappQuickActions.value?.[0]?.message ??
      'Hello, I would like to contact admissions.'
  )
)

const ariaLabel = computed(() => floatingChrome.value?.whatsapp_label ?? 'WhatsApp Admissions')
</script>

<template>
  <div class="st-float-panel st-float-panel--wa d-none d-lg-flex">
    <a
      :href="waHref"
      class="st-float-panel__tab st-float-panel__tab--whatsapp st-lift"
      target="_blank"
      rel="noopener noreferrer"
      :aria-label="ariaLabel"
      :title="ariaLabel"
    >
      <BrandSocialIcon brand="whatsapp" size="1.1rem" variant="glyph" />
      <span class="st-float-panel__tab-label">WhatsApp</span>
    </a>
  </div>
</template>
