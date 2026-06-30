<script setup>
import { computed } from 'vue'

const props = defineProps({
  categories: { type: Array, default: () => [] },
  stageId: { type: [Number, null], default: null },
  targetCategoryId: { type: [String, Number], default: '' },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:stageId', 'update:targetCategoryId'])

const stageOptions = computed(() => props.categories)

function collectEnrollmentTargets(node, ancestors) {
  const path = [...ancestors, node.name]
  const children = node.children ?? []

  if (children.length === 0) {
    return [{ id: node.id, label: path.join(' / ') }]
  }

  return children.flatMap((child) => collectEnrollmentTargets(child, path))
}

const targetOptions = computed(() => {
  if (!props.stageId) return []
  const stage = props.categories.find((c) => c.id === props.stageId)
  if (!stage) return []

  return collectEnrollmentTargets(stage, [])
})

function onStageChange(e) {
  const val = e.target.value ? Number(e.target.value) : null
  emit('update:stageId', val)
  emit('update:targetCategoryId', '')
}

function onTargetChange(e) {
  emit('update:targetCategoryId', e.target.value ? Number(e.target.value) : '')
}
</script>

<template>
  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label">المرحلة</label>
      <select
        class="form-select"
        :value="stageId ?? ''"
        :disabled="disabled"
        @change="onStageChange"
      >
        <option value="">— اختر المرحلة —</option>
        <option v-for="stage in stageOptions" :key="stage.id" :value="stage.id">{{ stage.name }}</option>
      </select>
    </div>
    <div class="col-md-6">
      <label class="form-label">الصف / الفئة المستهدفة</label>
      <select
        class="form-select"
        :value="targetCategoryId ?? ''"
        :disabled="disabled || !stageId"
        @change="onTargetChange"
      >
        <option value="">— اختر الصف المستهدف —</option>
        <option v-for="opt in targetOptions" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
      </select>
    </div>
  </div>
</template>
