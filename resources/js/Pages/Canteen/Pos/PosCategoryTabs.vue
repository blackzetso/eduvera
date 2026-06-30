<script setup>
defineProps({
  categories: Array,
  activeId: String,
  disabled: { type: Boolean, default: false },
})

defineEmits(['select'])
</script>

<template>
  <div class="pos-category-tabs" :class="{ 'pos-category-tabs--disabled': disabled }">
    <div class="pos-category-tabs__scroll">
      <button
        type="button"
        class="pos-category-tab"
        :class="{ 'pos-category-tab--active': !activeId }"
        :disabled="disabled"
        @click="$emit('select', '')"
      >
        الكل
      </button>
      <button
        v-for="c in categories"
        :key="c.id"
        type="button"
        class="pos-category-tab"
        :class="{ 'pos-category-tab--active': activeId === c.id }"
        :disabled="disabled"
        @click="$emit('select', c.id)"
      >
        {{ c.name_ar || c.name }}
      </button>
    </div>
  </div>
</template>
