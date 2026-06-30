<script setup>
defineProps({
  funnel: { type: Array, required: true },
  bottleneck: { type: Object, default: null },
  totalApplications: { type: Number, default: 0 },
})
</script>

<template>
  <div class="card admission-pipeline-card admission-dashboard-card mb-4">
    <div class="card-body p-4">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h6 class="mb-1 fw-bold">توزيع الطلبات حسب المرحلة</h6>
          <p class="text-muted small mb-0">
            {{ totalApplications }} طلب ضمن الفلاتر الحالية
            <span v-if="bottleneck?.count">
              — أكبر تركّز: {{ bottleneck.label }} ({{ bottleneck.count }})
            </span>
          </p>
        </div>
      </div>

      <div class="d-none d-md-block">
        <div
          v-for="stage in funnel"
          :key="stage.key"
          class="admission-pipeline-card__row"
        >
          <div class="d-flex justify-content-between align-items-center mb-1 small">
            <span class="fw-semibold">{{ stage.label }}</span>
            <span class="text-muted">
              {{ stage.count }}
              <span class="ms-1">({{ stage.percent }}%)</span>
            </span>
          </div>
          <div class="admission-pipeline-card__bar">
            <div
              class="admission-pipeline-card__fill"
              :class="`bg-${stage.color}`"
              :style="{ width: `${Math.max(stage.percent, stage.count ? 4 : 0)}%` }"
              role="progressbar"
              :aria-valuenow="stage.percent"
              aria-valuemin="0"
              aria-valuemax="100"
            ></div>
          </div>
        </div>
      </div>

      <div class="d-md-none">
        <div
          v-for="stage in funnel"
          :key="`m-${stage.key}`"
          class="admission-pipeline-card__row"
        >
          <div class="d-flex align-items-center gap-3">
            <div
              class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 admission-pipeline-mobile-count"
              :class="`bg-${stage.color} text-white`"
            >
              {{ stage.count }}
            </div>
            <div class="flex-grow-1 min-w-0">
              <div class="fw-semibold small">{{ stage.label }}</div>
              <div class="admission-pipeline-card__bar mt-1">
                <div
                  class="admission-pipeline-card__fill"
                  :class="`bg-${stage.color}`"
                  :style="{ width: `${Math.max(stage.percent, stage.count ? 4 : 0)}%` }"
                ></div>
              </div>
              <div class="text-muted small mt-1">{{ stage.percent }}%</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
