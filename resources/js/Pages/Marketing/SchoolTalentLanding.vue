<script setup>
import { computed, ref, provide, watch, onUnmounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import SchoolTalentLayout from '@/Layouts/SchoolTalentLayout.vue'
import StageDetailModal from '@/Components/Marketing/SchoolTalent/StageDetailModal.vue'
import LandingSectionWrapper from '@/Components/Marketing/SchoolTalent/Builder/LandingSectionWrapper.vue'
import SchoolTalentSectionHost from '@/Components/Marketing/SchoolTalent/Builder/SchoolTalentSectionHost.vue'
import { useWebsiteContent } from '@/composables/useWebsiteContent'
import { useLandingSections } from '@/composables/useLandingSections'
import { useLandingCmsEdit } from '@/composables/useLandingCmsEdit'
import SchoolTalentCmsEditBar from '@/Components/Marketing/SchoolTalent/SchoolTalentCmsEditBar.vue'

const props = defineProps({
  websiteContent: { type: Object, default: () => ({}) },
  websiteSeo: { type: Object, default: () => ({}) },
  landingPreview: { type: Boolean, default: false },
  previewDevice: { type: String, default: 'desktop' },
})

provide('websiteContent', computed(() => props.websiteContent))

const { themeCssVars } = useWebsiteContent()
const {
  visibleSections,
  landingPreview,
  previewDevice,
  deviceClass,
  wrapperStyle,
} = useLandingSections()

const { canEditWebsiteCms } = useLandingCmsEdit()

watch(
  canEditWebsiteCms,
  (enabled) => {
    document.body.classList.toggle('st-cms-edit-mode', enabled)
  },
  { immediate: true }
)

onUnmounted(() => {
  document.body.classList.remove('st-cms-edit-mode')
})

const selectedStage = ref(null)
const modalOpen = ref(false)
const activeGalleryFilter = ref('All')

const pageTitle = computed(() => props.websiteSeo?.meta_title || 'School Talent — International School')

const previewFrameClass = computed(() => {
  if (!landingPreview.value) return ''
  return `st-preview-frame st-preview-frame--${previewDevice.value}`
})

/** Never render the legacy card row — hero_stats only controls the inline hero band */
const displaySections = computed(() =>
  visibleSections.value.filter((s) => s.block_type !== 'hero_stats')
)

function openStage(stage) {
  selectedStage.value = stage
  modalOpen.value = true
}

function closeModal() {
  modalOpen.value = false
}
</script>

<template>
  <Head :title="pageTitle">
    <meta v-if="websiteSeo?.meta_description" head-key="description" name="description" :content="websiteSeo.meta_description" />
  </Head>
  <component :is="'style'" v-if="themeCssVars">{{ themeCssVars }}</component>

  <SchoolTalentCmsEditBar v-if="canEditWebsiteCms && !landingPreview" />

  <div v-if="landingPreview" class="st-preview-banner">
    <span><i class="bi bi-eye me-2"></i>Preview mode — draft sections visible</span>
  </div>

  <div :class="previewFrameClass">
    <SchoolTalentLayout meridian-hero>
      <LandingSectionWrapper
        v-for="section in displaySections"
        :key="section.uuid"
        :section="section"
        :device-class="deviceClass(section)"
        :style="wrapperStyle(section)"
      >
        <SchoolTalentSectionHost
          :section="section"
          :active-gallery-filter="activeGalleryFilter"
          @explore-stage="openStage"
          @gallery-filter="activeGalleryFilter = $event"
        />
      </LandingSectionWrapper>

      <StageDetailModal :stage="selectedStage" :open="modalOpen" @close="closeModal" />
    </SchoolTalentLayout>
  </div>
</template>
