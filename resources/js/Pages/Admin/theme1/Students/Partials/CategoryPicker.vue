<script setup>
import { computed } from 'vue'

const props = defineProps({
  categories: { type: Array, default: () => [] },
  topId: { type: [Number, null], default: null },
  midId: { type: [Number, null], default: null },
  sectionId: { type: [Number, null], default: null },
  categoryId: { type: [String, Number], default: '' },
})

const emit = defineEmits(['update:topId', 'update:midId', 'update:sectionId', 'update:categoryId', 'top-change', 'mid-change'])

const yearCategories = computed(() => {
  if (!props.topId) return []
  return props.categories.find(c => c.id === props.topId)?.children ?? []
})

const sectionCategories = computed(() => {
  if (!props.midId) return []
  return yearCategories.value.find(c => c.id === props.midId)?.children ?? []
})

const subSectionCategories = computed(() => {
  if (!props.sectionId) return []
  return sectionCategories.value.find(c => c.id === props.sectionId)?.children ?? []
})

function onTopChange(e) {
  const val = e.target.value ? Number(e.target.value) : null
  emit('update:topId', val)
  emit('update:midId', null)
  emit('update:sectionId', null)
  emit('update:categoryId', '')
  emit('top-change')
}

function onMidChange(e) {
  const val = e.target.value ? Number(e.target.value) : null
  emit('update:midId', val)
  emit('update:sectionId', null)
  emit('update:categoryId', val || '')
  emit('mid-change')
}

function onSectionChange(e) {
  const val = e.target.value ? Number(e.target.value) : null
  emit('update:sectionId', val)
  if (val && subSectionCategories.value.length === 0) {
    emit('update:categoryId', val)
  } else {
    emit('update:categoryId', '')
  }
}

function onLeafChange(e) {
  emit('update:categoryId', e.target.value)
}
</script>

<template>
  <div class="row g-2">
    <div v-if="categories.length" class="col-md-3">
      <label class="form-label small">الشعبة</label>
      <select class="form-select form-select-sm" :value="topId ?? ''" @change="onTopChange">
        <option value="">— اختر —</option>
        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>
    </div>
    <div v-if="topId && yearCategories.length" class="col-md-3">
      <label class="form-label small">المرحلة</label>
      <select class="form-select form-select-sm" :value="midId ?? ''" @change="onMidChange">
        <option value="">— اختر —</option>
        <option v-for="y in yearCategories" :key="y.id" :value="y.id">{{ y.name }}</option>
      </select>
    </div>
    <div v-if="midId && sectionCategories.length" class="col-md-3">
      <label class="form-label small">الفصل</label>
      <select class="form-select form-select-sm" :value="sectionId ?? ''" @change="onSectionChange">
        <option value="">— اختر —</option>
        <option v-for="s in sectionCategories" :key="s.id" :value="s.id">{{ s.name }}</option>
      </select>
    </div>
    <div v-if="sectionId && subSectionCategories.length" class="col-md-3">
      <label class="form-label small">القسم</label>
      <select class="form-select form-select-sm" :value="categoryId" @change="onLeafChange">
        <option value="">— اختر —</option>
        <option v-for="sub in subSectionCategories" :key="sub.id" :value="sub.id">{{ sub.name }}</option>
      </select>
    </div>
  </div>
</template>
