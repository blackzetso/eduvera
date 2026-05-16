<script setup>
import { computed } from 'vue'

const props = defineProps({
  categories: Array,
  prefix: {
    type: String,
    default: ''
  },
  currentId: {
    type: Number,
    default: null
  },
  level: {
    type: Number,
    default: 0
  }
})

// Calculate the prefix based on level
const displayPrefix = computed(() => {
  if (props.level === 0) return ''
  // For level 1: "- ", level 2: "-- ", level 3: "--- ", etc.
  return '-'.repeat(props.level) + ' '
})
</script>

<template>
  <template v-for="cat in categories" :key="cat.id">
    <option
      :value="cat.id"
      :disabled="cat.id === props.currentId"
    >
      {{ displayPrefix + cat.name }}
    </option>
    <CategoryOptions
      v-if="cat.children && cat.children.length"
      :categories="cat.children"
      :prefix="prefix"
      :current-id="props.currentId"
      :level="level + 1"
    />
  </template>
</template>
