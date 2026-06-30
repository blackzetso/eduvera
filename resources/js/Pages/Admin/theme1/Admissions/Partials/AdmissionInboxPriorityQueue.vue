<script setup>
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { pipelineBadgeClass, decisionBadge } from '@/Shared/admissionsBadges'

defineProps({
  items: { type: Array, default: () => [] },
})
</script>

<template>
  <div class="card admission-dashboard-card mb-4">
    <div class="card-body p-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0 fw-bold">
          <i class="bi bi-lightning-charge-fill text-warning me-1"></i>
          تحتاج متابعة الآن
        </h6>
        <span class="badge bg-warning text-dark rounded-pill">{{ items.length }}</span>
      </div>

      <div v-if="!items.length" class="text-muted small py-2">
        لا توجد طلبات عاجلة ضمن الفلاتر الحالية.
      </div>

      <div v-else class="d-flex flex-column gap-2">
        <Link
          v-for="item in items"
          :key="item.row.id"
          :href="route('admin.admissions.show', item.row.id)"
          class="admission-priority-card card text-decoration-none text-body"
        >
          <div class="card-body py-3 d-flex gap-3 align-items-start">
            <div
              class="admission-priority-card__indicator"
              :class="`admission-priority-card__indicator--${item.level}`"
            ></div>
            <div class="admission-inbox-avatar">
              <i class="bi bi-person-fill"></i>
            </div>
            <div class="flex-grow-1 min-w-0">
              <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <span class="fw-semibold text-truncate">{{ item.row.student_name || '—' }}</span>
                <code class="small text-muted">{{ item.row.reference_code }}</code>
              </div>
              <div class="d-flex flex-wrap gap-1 mb-2">
                <span
                  v-for="tag in item.tags.slice(0, 2)"
                  :key="tag.type"
                  class="badge rounded-pill bg-light text-dark border"
                >
                  {{ tag.label }}
                </span>
              </div>
              <div class="d-flex flex-wrap gap-2 small">
                <span class="badge rounded-pill" :class="pipelineBadgeClass(item.row.pipeline_stage)">
                  {{ item.row.pipeline_stage_label }}
                </span>
                <span
                  v-if="item.row.decision"
                  class="badge rounded-pill"
                  :class="decisionBadge(item.row.decision)"
                >
                  {{ item.row.decision_label }}
                </span>
              </div>
            </div>
            <i class="bi bi-chevron-left text-muted flex-shrink-0"></i>
          </div>
        </Link>
      </div>
    </div>
  </div>
</template>
