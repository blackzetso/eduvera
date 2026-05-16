<script setup>
const props = defineProps({
  categories: Array,
  prefix: {
    type: String,
    default: ''
  },
  currentId: {
    type: Number,
    default: null
  }
})
</script>

<template>
  <template v-for="cat in categories" :key="cat.id">
    <option :value="cat.id" :disabled="cat.id === props.currentId" >
      {{ prefix + cat.name }}
    </option>
    <CategoryOptions v-if="cat.children && cat.children.length" :categories="cat.children" :prefix="prefix + '_ '" :current-id="props.currentId"
    />
  </template>
</template>
