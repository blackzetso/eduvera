<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { landingSectionContentSource, resolveAdminLink } from '@/utils/landingCmsEditLinks'

const props = defineProps({
  blockType: { type: String, default: '' },
})

const source = computed(() => landingSectionContentSource(props.blockType))

function href(link) {
  return resolveAdminLink(link)
}
</script>

<template>
  <div v-if="source" class="alert alert-warning small mb-3">
    <strong>{{ source.title }}:</strong> {{ source.body }}
    <span class="d-block mt-1">
      <Link
        v-for="(link, i) in source.links"
        :key="link.route + (link.params || '')"
        :href="href(link)"
        class="me-2"
      >
        {{ link.label }}<span v-if="i < source.links.length - 1"> ·</span>
      </Link>
    </span>
  </div>
</template>
