<script setup>
import { ref, computed, watch } from 'vue'
import { useWebsiteContent } from '@/composables/useWebsiteContent'

const props = defineProps({
  image: { type: [Object, String], default: null },
  alt: { type: String, default: '' },
  imgClass: { type: String, default: '' },
})

const { imageSrc } = useWebsiteContent()
const failed = ref(false)

const resolved = computed(() => imageSrc(props.image))

watch(
  () => props.image,
  () => {
    failed.value = false
  }
)

function onError() {
  failed.value = true
}
</script>

<template>
  <img
    v-if="resolved && !failed"
    :src="resolved"
    :alt="alt"
    :class="imgClass"
    loading="lazy"
    @error="onError"
  />
  <div v-else class="st-media-placeholder" :class="imgClass" aria-hidden="true">
    <i class="bi bi-image"></i>
  </div>
</template>
