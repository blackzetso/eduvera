<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  guardians: { type: Array, default: () => [] },
  siblings: { type: Array, default: () => [] },
  executiveMetrics: { type: Object, default: () => ({}) },
  formatCurrency: { type: Function, default: null },
})

const emit = defineEmits(['open-tab'])

const primaryGuardian = computed(() =>
  props.guardians.find((g) => g.is_primary) || props.guardians[0] || null,
)

const additionalGuardians = computed(() =>
  props.guardians.filter((g) => g.id !== primaryGuardian.value?.id),
)

const guardiansCount = computed(() =>
  props.executiveMetrics.guardiansCount ?? props.guardians.length,
)

const siblingsCount = computed(() =>
  props.executiveMetrics.siblingsCount ?? props.siblings.length,
)

const outstandingBalance = computed(() => props.executiveMetrics.outstandingBalance)
const walletBalance = computed(() => props.executiveMetrics.walletBalance)

function formatMoney(value) {
  if (value == null) return '—'
  if (props.formatCurrency) return props.formatCurrency(value)
  return parseFloat(value).toFixed(2)
}
</script>

<template>
  <div class="card student-command-card border-0 shadow-sm">
    <div class="card-body p-2 p-md-3">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <div class="student-section-title mb-0">
          <i class="bi bi-people-fill me-1 text-info"></i>
          العائلة
        </div>
        <button type="button" class="btn btn-sm btn-outline-info py-0 px-2" style="font-size: 0.72rem" @click="emit('open-tab', 'family')">
          إدارة
        </button>
      </div>

      <div class="row g-2 mb-2 student-cc-row-tight">
        <div class="col-3 col-md-3">
          <div class="student-family-kpi h-100">
            <div class="student-family-kpi__value">{{ siblingsCount }}</div>
            <div class="student-family-kpi__label">إخوة</div>
          </div>
        </div>
        <div class="col-3 col-md-3">
          <div class="student-family-kpi h-100">
            <div class="student-family-kpi__value">{{ guardiansCount }}</div>
            <div class="student-family-kpi__label">أولياء</div>
          </div>
        </div>
        <div v-if="walletBalance != null" class="col-3 col-md-3">
          <div class="student-family-kpi h-100">
            <div class="student-family-kpi__value">{{ formatMoney(walletBalance) }}</div>
            <div class="student-family-kpi__label">المحفظة</div>
          </div>
        </div>
        <div v-if="outstandingBalance != null" class="col-3 col-md-3">
          <div class="student-family-kpi h-100" :class="{ 'border border-danger border-opacity-25': outstandingBalance > 0 }">
            <div class="student-family-kpi__value" :class="{ 'text-danger': outstandingBalance > 0 }">
              {{ formatMoney(outstandingBalance) }}
            </div>
            <div class="student-family-kpi__label">مستحق</div>
          </div>
        </div>
      </div>

      <div class="row g-2 student-cc-row-tight">
        <div class="col-md-6">
          <div class="student-family-card p-2 h-100">
            <div class="text-muted small mb-1" style="font-size: 0.65rem">ولي أساسي</div>
            <template v-if="primaryGuardian">
              <div class="fw-bold small mb-0">
                <Link :href="route('admin.parents.show', primaryGuardian.id)" class="text-decoration-none">
                  {{ primaryGuardian.name }}
                </Link>
              </div>
              <div class="small text-muted">{{ primaryGuardian.relationship_label }} · {{ primaryGuardian.phone || '—' }}</div>
            </template>
            <p v-else class="text-muted small mb-0">لا يوجد</p>
          </div>
        </div>

        <div class="col-md-6">
          <div class="student-family-card p-2 h-100">
            <div class="text-muted small mb-1" style="font-size: 0.65rem">إخوة ({{ siblings.length }})</div>
            <div v-if="siblings.length" class="d-flex flex-wrap gap-1">
              <Link
                v-for="s in siblings.slice(0, 3)"
                :key="s.id"
                :href="s.profile_url || route('admin.students.show', s.id)"
                class="badge bg-light text-dark border text-decoration-none"
              >{{ s.name }}</Link>
              <span v-if="siblings.length > 3" class="badge bg-secondary">+{{ siblings.length - 3 }}</span>
            </div>
            <p v-else class="text-muted small mb-0">لا يوجد</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
