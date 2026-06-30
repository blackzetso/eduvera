<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  profile: { type: Object, required: true },
  lifecycle: { type: Object, required: true },
})

const emit = defineEmits(['lifecycle', 'open-tab', 'view-parent'])

const navigationActions = computed(() => [
  {
    id: 'family',
    label: 'العائلة',
    icon: 'bi-people-fill',
    tone: 'info',
    show: !!props.profile.primary_guardian_id,
    onClick: () => emit('view-parent'),
  },
  {
    id: 'finance',
    label: 'المالية',
    icon: 'bi-wallet2',
    tone: 'warning',
    show: true,
    onClick: () => emit('open-tab', 'wallet'),
  },
  {
    id: 'attendance',
    label: 'الحضور',
    icon: 'bi-calendar-check',
    tone: 'primary',
    show: true,
    onClick: () => emit('open-tab', 'attendance'),
  },
  {
    id: 'behavior',
    label: 'السلوك',
    icon: 'bi-emoji-smile',
    tone: 'primary',
    show: true,
    onClick: () => emit('open-tab', 'behavior'),
  },
  {
    id: 'edit',
    label: 'تعديل',
    icon: 'bi-pencil-square',
    tone: 'dark',
    show: true,
    href: route('admin.students.edit', props.profile.id),
  },
])

const lifecycleActions = computed(() => [
  { key: 'promote', label: 'ترقية', icon: 'bi-arrow-up-circle', tone: 'primary', action: 'promote' },
  { key: 'transfer', label: 'نقل', icon: 'bi-arrow-left-right', tone: 'primary', action: 'transfer' },
  { key: 'withdraw', label: 'انسحاب', icon: 'bi-box-arrow-left', tone: 'danger', action: 'withdraw' },
  { key: 're_enroll', label: 'إعادة قيد', icon: 'bi-person-plus', tone: 'success', action: 're_enroll' },
  { key: 'graduate', label: 'تخرج', icon: 'bi-mortarboard', tone: 'info', action: 'graduate' },
].filter((a) => props.lifecycle.actions?.[a.key]))
</script>

<template>
  <div class="card student-command-card border-0 shadow-sm">
    <div class="card-body p-2">
      <div class="student-section-title mb-2">
        <i class="bi bi-lightning-charge-fill me-1 text-warning"></i>
        إجراءات
      </div>

      <div class="row g-2 student-cc-row-tight">
        <div
          v-for="action in navigationActions.filter(a => a.show)"
          :key="action.id"
          class="col-4 col-sm-3 col-md-2"
        >
          <Link
            v-if="action.href"
            :href="action.href"
            class="student-action-tile w-100"
            :class="`student-action-tile--${action.tone}`"
          >
            <i :class="['bi student-action-tile__icon', action.icon]"></i>
            <span class="student-action-tile__label">{{ action.label }}</span>
          </Link>
          <button
            v-else
            type="button"
            class="student-action-tile w-100 border-0"
            :class="`student-action-tile--${action.tone}`"
            @click="action.onClick"
          >
            <i :class="['bi student-action-tile__icon', action.icon]"></i>
            <span class="student-action-tile__label">{{ action.label }}</span>
          </button>
        </div>

        <div
          v-for="action in lifecycleActions"
          :key="action.key"
          class="col-4 col-sm-3 col-md-2"
        >
          <button
            type="button"
            class="student-action-tile w-100 border-0"
            :class="`student-action-tile--${action.tone}`"
            @click="emit('lifecycle', action.action)"
          >
            <i :class="['bi student-action-tile__icon', action.icon]"></i>
            <span class="student-action-tile__label">{{ action.label }}</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
