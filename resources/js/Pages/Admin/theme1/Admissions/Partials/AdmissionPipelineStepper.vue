<script setup>
import { computed } from 'vue'
import { PIPELINE_STAGES } from '@/Shared/admissionsBadges'

const props = defineProps({
  currentStage: { type: String, required: true },
  stageOptions: { type: Array, default: () => [] },
})

const stages = computed(() => {
  const labelMap = Object.fromEntries(
    (props.stageOptions || []).map((o) => [o.value, o.label]),
  )

  const fallbackLabels = {
    lead: 'عميل محتمل',
    inquiry: 'استفسار',
    campus_visit: 'زيارة الحرم',
    application: 'طلب التقديم',
  }

  const currentIndex = PIPELINE_STAGES.indexOf(props.currentStage)

  return PIPELINE_STAGES.map((value, index) => {
    let state = 'future'
    if (index < currentIndex) state = 'completed'
    else if (index === currentIndex) state = 'current'

    return {
      value,
      label: labelMap[value] || fallbackLabels[value] || value,
      state,
    }
  })
})
</script>

<template>
  <div class="card admission-command-card border-0 shadow-sm mb-4">
    <div class="card-body p-3 p-md-4">
      <div class="admission-pipeline admission-pipeline--horizontal d-none d-md-flex">
        <template v-for="(stage, index) in stages" :key="stage.value">
          <div class="admission-pipeline__step" :class="`admission-pipeline__step--${stage.state}`">
            <div class="admission-pipeline__node">
              <i v-if="stage.state === 'completed'" class="bi bi-check-lg"></i>
              <span v-else class="admission-pipeline__number">{{ index + 1 }}</span>
            </div>
            <div class="admission-pipeline__label">{{ stage.label }}</div>
          </div>
          <div
            v-if="index < stages.length - 1"
            class="admission-pipeline__connector"
            :class="{ 'admission-pipeline__connector--done': stage.state === 'completed' }"
          ></div>
        </template>
      </div>

      <div class="admission-pipeline admission-pipeline--stacked d-md-none">
        <div
          v-for="(stage, index) in stages"
          :key="stage.value"
          class="admission-pipeline__stack-item"
          :class="`admission-pipeline__step--${stage.state}`"
        >
          <div class="admission-pipeline__node">
            <i v-if="stage.state === 'completed'" class="bi bi-check-lg"></i>
            <span v-else class="admission-pipeline__number">{{ index + 1 }}</span>
          </div>
          <div class="admission-pipeline__label">{{ stage.label }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.admission-pipeline--horizontal {
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.25rem;
}

.admission-pipeline__step {
  flex: 1;
  text-align: center;
  min-width: 0;
}

.admission-pipeline__node {
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.875rem;
  font-weight: 600;
  margin-bottom: 0.5rem;
  border: 2px solid var(--bs-border-color);
  background: var(--bs-body-bg);
  color: var(--bs-secondary-color);
}

.admission-pipeline__step--completed .admission-pipeline__node {
  background: var(--bs-success);
  border-color: var(--bs-success);
  color: #fff;
}

.admission-pipeline__step--current .admission-pipeline__node {
  background: var(--bs-primary);
  border-color: var(--bs-primary);
  color: #fff;
  box-shadow: 0 0 0 4px rgba(var(--bs-primary-rgb), 0.2);
}

.admission-pipeline__step--future .admission-pipeline__node {
  background: var(--bs-light);
}

.admission-pipeline__label {
  font-size: 0.8rem;
  font-weight: 500;
  color: var(--bs-secondary-color);
  line-height: 1.3;
}

.admission-pipeline__step--current .admission-pipeline__label {
  color: var(--bs-primary);
  font-weight: 700;
}

.admission-pipeline__step--completed .admission-pipeline__label {
  color: var(--bs-success);
}

.admission-pipeline__connector {
  flex: 1;
  height: 2px;
  background: var(--bs-border-color);
  margin-top: 1.125rem;
  min-width: 1rem;
}

.admission-pipeline__connector--done {
  background: var(--bs-success);
}

.admission-pipeline--stacked {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.admission-pipeline__stack-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.admission-pipeline__stack-item .admission-pipeline__node {
  margin-bottom: 0;
  flex-shrink: 0;
}
</style>
