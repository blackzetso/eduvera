<script setup>
import { computed } from 'vue'
import SchoolTalentSectionEditChip from '@/Components/Marketing/SchoolTalent/SchoolTalentSectionEditChip.vue'
import { useLandingCmsEdit } from '@/composables/useLandingCmsEdit'

const props = defineProps({
  section: { type: Object, required: true },
  deviceClass: { type: String, default: '' },
  style: { type: Object, default: () => ({}) },
})

const { canEditWebsiteCms } = useLandingCmsEdit()

const hasBackgroundImage = computed(() => Boolean(props.section?.settings?.background_image_url?.trim()))
</script>

<template>
  <div
    :class="[
      'st-landing-section',
      deviceClass,
      { 'st-landing-section--has-bg': hasBackgroundImage, 'st-landing-section--cms-editable': canEditWebsiteCms },
      $attrs.class,
    ]"
    :style="style"
    :data-section-uuid="section.uuid"
    :data-block-type="section.block_type"
  >
    <SchoolTalentSectionEditChip v-if="canEditWebsiteCms" :section="section" />
    <slot />
  </div>
</template>
