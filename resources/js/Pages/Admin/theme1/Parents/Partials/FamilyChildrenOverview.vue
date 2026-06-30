<script setup>
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  children: { type: Array, default: () => [] },
})

function financeIcon(status) {
  return status === 'red' ? 'bi-exclamation-circle text-danger' : status === 'amber' ? 'bi-exclamation-triangle text-warning' : 'bi-check-circle text-success'
}
</script>

<template>
  <div class="card family-command-card border-0 shadow-sm mb-4 rounded-4">
    <div class="card-body p-3 p-md-4">
      <h6 class="fw-bold mb-3">
        <i class="bi bi-people-fill me-1 text-primary"></i>
        نظرة على الأبناء ({{ children.length }})
      </h6>

      <div v-if="!children.length" class="text-center text-muted py-4">
        <i class="bi bi-person-x fs-3 d-block mb-2"></i>
        لا يوجد أبناء مرتبطون بهذا ولي الأمر
      </div>

      <div v-else class="row g-3">
        <div v-for="child in children" :key="child.id" class="col-md-6 col-xl-4">
          <div class="family-child-card p-3 rounded-4">
            <div class="d-flex gap-3 mb-3">
              <img
                :src="child.profile_photo_url"
                :alt="child.name"
                class="rounded-circle family-header-avatar flex-shrink-0"
                style="width:48px;height:48px"
                @error="$event.target.src = '/front/theme1/images/avatar/01.jpg'"
              >
              <div class="min-w-0 flex-grow-1">
                <div class="fw-bold text-truncate">{{ child.name }}</div>
                <code class="small text-muted">{{ child.student_code || '—' }}</code>
                <div class="small text-muted text-truncate">{{ child.grade_label || child.category_name || '—' }}</div>
                <span class="badge mt-1" :class="child.status_badge_class">{{ child.status_label }}</span>
              </div>
            </div>

            <div class="row g-2 small mb-3">
              <div class="col-4 text-center">
                <div class="text-muted">حضور</div>
                <strong>{{ child.attendance?.rate_percent != null ? `${child.attendance.rate_percent}%` : '—' }}</strong>
              </div>
              <div class="col-4 text-center">
                <div class="text-muted">متوسط</div>
                <strong>{{ child.academic?.average_percent != null ? `${child.academic.average_percent}%` : '—' }}</strong>
              </div>
              <div class="col-4 text-center">
                <div class="text-muted">مالية</div>
                <i :class="['bi', financeIcon(child.finance?.finance_status)]"></i>
              </div>
            </div>

            <Link :href="child.profile_url" class="btn btn-sm btn-primary w-100">
              <i class="bi bi-person-badge me-1"></i>فتح ملف الطالب
            </Link>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
