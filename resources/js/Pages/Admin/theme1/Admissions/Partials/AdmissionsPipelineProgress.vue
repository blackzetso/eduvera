<script setup>
defineProps({
  steps: { type: Array, default: () => [] },
})
</script>

<template>
  <div class="card admissions-command-card border-0 shadow-sm">
    <div class="card-body py-2">
      <div class="admissions-pipeline-exec d-none d-md-flex">
        <template v-for="(step, index) in steps" :key="step.key">
          <div class="admissions-pipeline-exec__step" :class="`admissions-pipeline-exec__step--${step.state}`">
            <div class="admissions-pipeline-exec__node">
              <i v-if="step.state === 'completed'" class="bi bi-check-lg"></i>
              <i v-else-if="step.state === 'blocked'" class="bi bi-lock-fill"></i>
              <span v-else>{{ index + 1 }}</span>
            </div>
            <div class="admissions-pipeline-exec__label">{{ step.label }}</div>
          </div>
          <div
            v-if="index < steps.length - 1"
            class="admissions-pipeline-exec__connector"
            :class="{ 'admissions-pipeline-exec__connector--done': step.state === 'completed' }"
          ></div>
        </template>
      </div>

      <div class="d-md-none">
        <div class="d-flex flex-wrap gap-2">
          <span
            v-for="step in steps"
            :key="step.key"
            class="badge rounded-pill"
            :class="{
              'bg-success': step.state === 'completed',
              'bg-primary': step.state === 'active',
              'bg-danger': step.state === 'blocked',
              'bg-light text-dark border': step.state === 'future',
            }"
          >
            {{ step.label }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>
