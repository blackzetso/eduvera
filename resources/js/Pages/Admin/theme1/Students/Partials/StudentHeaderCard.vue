<script setup>
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

defineProps({
  profile: { type: Object, required: true },
  formatDate: { type: Function, required: true },
})

const emit = defineEmits(['copy-code'])

const showDetails = ref(false)
</script>

<template>
  <div class="card student-command-card student-header-card border-0 shadow-sm overflow-hidden">
    <div class="card-body">
      <div class="d-flex flex-wrap align-items-center gap-2 gap-md-3">
        <img
          :src="profile.profile_photo_url"
          :alt="profile.name"
          class="rounded-circle border border-2 border-white shadow-sm student-header-avatar flex-shrink-0"
          @error="$event.target.src = '/front/theme1/images/avatar/01.jpg'"
        >

        <div class="flex-grow-1 min-w-0">
          <div class="d-flex flex-wrap align-items-center gap-1 gap-md-2 mb-1">
            <h5 class="mb-0 student-header-name text-truncate">{{ profile.name }}</h5>
            <code class="small text-primary mb-0" :title="'رقم النظام — مرجع الكافتيريا'">#{{ profile.id }}</code>
            <code v-if="profile.student_code" class="small text-muted mb-0">{{ profile.student_code }}</code>
            <button
              v-if="profile.student_code"
              type="button"
              class="btn btn-link btn-sm p-0 text-decoration-none lh-1"
              title="نسخ الكود"
              @click="emit('copy-code')"
            >
              <i class="bi bi-clipboard small"></i>
            </button>
            <span class="badge rounded-pill" :class="profile.status_badge_class">{{ profile.status_label }}</span>
            <span v-if="profile.enrollment_status_label" class="badge rounded-pill bg-light text-dark border small">
              {{ profile.enrollment_status_label }}
            </span>
            <span v-if="profile.active_alert" class="badge bg-warning text-dark small">
              <i class="bi bi-exclamation-triangle me-1"></i>تنبيه
            </span>
          </div>

          <div class="d-flex flex-wrap student-header-meta text-muted">
            <span class="text-truncate" style="max-width: 100%">
              <i class="bi bi-mortarboard me-1"></i>{{ profile.class_path_label || profile.category_name || '—' }}
            </span>
            <span v-if="profile.academic_year"><i class="bi bi-calendar3 me-1"></i>{{ profile.academic_year }}</span>
            <span v-if="profile.primary_guardian_name">
              <i class="bi bi-person-heart me-1"></i>
              <Link
                v-if="profile.primary_guardian_id"
                :href="route('admin.parents.show', profile.primary_guardian_id)"
                class="fw-semibold text-decoration-none"
              >{{ profile.primary_guardian_name }}</Link>
              <template v-else>{{ profile.primary_guardian_name }}</template>
            </span>
            <span><i class="bi bi-people me-1"></i>{{ profile.guardians_count || 0 }} ولي · {{ profile.siblings_count || 0 }} أخ</span>
          </div>
        </div>

        <div v-if="profile.attendance_rate_percent != null" class="student-header-kpi text-center flex-shrink-0">
          <div class="student-header-kpi__value">{{ profile.attendance_rate_percent }}%</div>
          <div class="student-header-kpi__label">حضور</div>
        </div>
      </div>

      <button
        type="button"
        class="btn btn-link btn-sm text-decoration-none px-0 py-0 mt-1 small"
        @click="showDetails = !showDetails"
      >
        <i :class="['bi me-1', showDetails ? 'bi-chevron-up' : 'bi-chevron-down']"></i>
        {{ showDetails ? 'إخفاء' : 'تفاصيل' }}
      </button>

      <div v-show="showDetails" class="row g-2 text-muted small mt-1 pt-1 border-top">
        <div class="col-6 col-md-4 text-truncate"><i class="bi bi-envelope me-1"></i>{{ profile.email }}</div>
        <div class="col-6 col-md-4"><i class="bi bi-telephone me-1"></i>{{ profile.phone || '—' }}</div>
        <div class="col-6 col-md-4"><i class="bi bi-calendar-event me-1"></i>قيد: {{ formatDate(profile.enrollment_date) }}</div>
        <div v-if="profile.source_admission_url" class="col-12">
          <i class="bi bi-file-earmark-person me-1"></i>
          <Link :href="profile.source_admission_url" class="fw-semibold">طلب القبول الأصلي</Link>
        </div>
      </div>
    </div>
  </div>
</template>
