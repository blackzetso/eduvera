<script setup>
import { computed } from 'vue'
import { useWebsiteContent } from '@/composables/useWebsiteContent'
import { useCta } from '@/composables/useCta'
import SchoolTalentMediaImage from '@/Components/Marketing/SchoolTalent/SchoolTalentMediaImage.vue'

const props = defineProps({
  section: { type: Object, required: true },
})

const { imageSrc, admissionsFunnelHref } = useWebsiteContent()
const { resolveCta } = useCta()

function ctaHref(cta) {
  if (typeof cta === 'string') return resolveCta(cta)?.href ?? admissionsFunnelHref.value
  if (cta?.id) return resolveCta(cta.id)?.href ?? cta.href ?? admissionsFunnelHref.value
  return cta?.href ?? admissionsFunnelHref.value
}

function ctaLabel(cta) {
  if (typeof cta === 'string') return resolveCta(cta)?.label ?? cta
  if (cta?.id) return resolveCta(cta.id)?.label ?? cta.label
  return cta?.label ?? ''
}

const subtype = computed(() => props.section.content?.subtype ?? 'text_block')
const items = computed(() => props.section.content?.items ?? [])
const body = computed(() => props.section.content?.body ?? '')
const settings = computed(() => props.section.settings ?? {})
</script>

<template>
  <section class="st-section st-section--white">
    <div class="st-container">
      <div v-if="settings.eyebrow || settings.title" class="st-section__head st-reveal">
        <span v-if="settings.eyebrow" class="st-eyebrow">{{ settings.eyebrow }}</span>
        <h2 v-if="settings.title" class="st-section-title">{{ settings.title }}</h2>
        <p v-if="settings.subtitle" class="text-muted">{{ settings.subtitle }}</p>
      </div>

      <div v-if="subtype === 'text_block'" class="st-reveal">
        <p class="text-muted mb-0" style="white-space: pre-wrap">{{ body }}</p>
      </div>

      <div v-else-if="subtype === 'image_block'" class="text-center st-reveal">
        <SchoolTalentMediaImage
          v-if="section.content?.image"
          :image="section.content.image"
          :alt="section.content.image?.alt || ''"
          img-class="img-fluid rounded"
        />
      </div>

      <div v-else-if="subtype === 'gallery'" class="st-gallery-grid st-reveal">
        <figure v-for="(img, i) in items" :key="i" class="st-gallery-card">
          <SchoolTalentMediaImage :image="img.src ? { src: img.src, alt: img.alt } : img" :alt="img.alt || 'Gallery photo'" />
          <figcaption v-if="img.alt" class="st-gallery-card__caption">{{ img.alt }}</figcaption>
        </figure>
      </div>

      <div v-else-if="subtype === 'video'" class="ratio ratio-16x9 st-reveal">
        <iframe
          v-if="section.content?.video_url"
          :src="section.content.video_url"
          title="Video"
          allowfullscreen
          loading="lazy"
        />
      </div>

      <div v-else-if="subtype === 'cta_banner'" class="st-cta-final st-reveal text-center">
        <h2 v-if="settings.title">{{ settings.title }}</h2>
        <p v-if="settings.subtitle" class="mb-4 opacity-75">{{ settings.subtitle }}</p>
        <div class="d-flex flex-wrap justify-content-center gap-2">
          <a
            v-for="(cta, i) in settings.ctas || []"
            :key="i"
            :href="ctaHref(cta)"
            class="st-btn st-btn--primary st-btn--lift"
          >
            {{ ctaLabel(cta) }}
          </a>
        </div>
      </div>

      <div v-else-if="subtype === 'statistics'" class="st-stats-band st-reveal">
        <div v-for="(item, i) in items" :key="i" class="st-stat-box">
          <strong>{{ item.value }}</strong>
          <span class="small">{{ item.label }}</span>
        </div>
      </div>

      <div v-else-if="subtype === 'cards_grid'" class="st-grid-3 st-reveal">
        <div v-for="(card, i) in items" :key="i" class="st-card">
          <h3>{{ card.title }}</h3>
          <p class="text-muted small mb-0">{{ card.text }}</p>
        </div>
      </div>

      <div v-else-if="subtype === 'accordion' || subtype === 'faq'" class="st-faq mx-auto st-reveal" style="max-width: 720px">
        <details v-for="(item, i) in items" :key="i">
          <summary>{{ item.q || item.title }}</summary>
          <p>{{ item.a || item.text }}</p>
        </details>
      </div>

      <div v-else-if="subtype === 'rich_content'" class="st-reveal" v-html="body" />
    </div>
  </section>
</template>
