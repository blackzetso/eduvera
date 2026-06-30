<script setup>
import { computed } from 'vue'
import { useLandingCmsEdit } from '@/composables/useLandingCmsEdit'

const props = defineProps({
  section: { type: Object, required: true },
})

const { sectionEditHref, sectionEditTitle, sectionSettingsHref } = useLandingCmsEdit()

const contentHref = computed(() => sectionEditHref(props.section))
const contentLabel = computed(() => sectionEditTitle(props.section))
const settingsHref = computed(() => sectionSettingsHref(props.section))
</script>

<template>
  <div class="st-cms-section-edit" @click.stop>
    <a
      :href="contentHref"
      class="st-cms-section-edit__btn"
      :title="`تعديل المحتوى: ${contentLabel}`"
      :aria-label="`تعديل المحتوى: ${contentLabel}`"
    >
      <i class="bi bi-pencil" aria-hidden="true"></i>
      <span class="st-cms-section-edit__label">{{ contentLabel }}</span>
    </a>
    <a
      v-if="settingsHref"
      :href="settingsHref"
      class="st-cms-section-edit__btn st-cms-section-edit__btn--secondary"
      title="إعدادات القسم (عنوان، ظهور، خلفية)"
      aria-label="إعدادات القسم"
    >
      <i class="bi bi-sliders" aria-hidden="true"></i>
    </a>
  </div>
</template>
