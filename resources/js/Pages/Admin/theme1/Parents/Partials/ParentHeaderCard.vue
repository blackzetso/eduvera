<script setup>
import { ref } from 'vue'

const props = defineProps({
  profile: { type: Object, required: true },
  formatCurrency: { type: Function, required: true },
})

const showDetails = ref(false)
</script>

<template>
  <div class="card family-command-card border-0 shadow-sm mb-3 overflow-hidden rounded-4">
    <div class="card-body p-3 p-md-4">
      <div class="d-flex flex-wrap align-items-center gap-3">
        <img
          :src="profile.profile_photo_url"
          :alt="profile.name"
          class="rounded-circle border border-3 border-white shadow family-header-avatar flex-shrink-0"
          @error="$event.target.src = '/front/theme1/images/avatar/01.jpg'"
        >

        <div class="flex-grow-1 min-w-0">
          <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
            <h4 class="mb-0 fw-bold text-truncate">{{ profile.name }}</h4>
            <code class="small text-muted">{{ profile.parent_code }}</code>
          </div>

          <div class="d-flex flex-wrap align-items-center gap-2 small mb-2">
            <span class="badge rounded-pill" :class="profile.status_badge_class">{{ profile.status_label }}</span>
            <span class="badge rounded-pill bg-primary">{{ profile.role_label }}</span>
            <span class="badge rounded-pill bg-light text-dark border">{{ profile.family_label }}</span>
          </div>

          <div class="d-flex flex-wrap gap-3 small text-muted">
            <span><i class="bi bi-telephone me-1"></i>{{ profile.phone || '—' }}</span>
            <span><i class="bi bi-envelope me-1"></i>{{ profile.email }}</span>
          </div>
        </div>
      </div>

      <div class="d-flex flex-wrap gap-2 mt-3">
        <span class="badge bg-light text-dark border rounded-pill px-3 py-2">
          <i class="bi bi-people me-1"></i>{{ profile.children_count }} أبناء
        </span>
        <span class="badge bg-light text-dark border rounded-pill px-3 py-2">
          <i class="bi bi-person-heart me-1"></i>{{ profile.guardians_count }} أولياء
        </span>
        <span class="badge bg-light text-dark border rounded-pill px-3 py-2">
          <i class="bi bi-wallet2 me-1"></i>{{ formatCurrency(profile.family_wallet_balance) }}
        </span>
        <span
          class="badge rounded-pill px-3 py-2"
          :class="profile.outstanding_balance > 0 ? 'bg-warning text-dark' : 'bg-light text-dark border'"
        >
          <i class="bi bi-cash-stack me-1"></i>مستحق: {{ formatCurrency(profile.outstanding_balance) }}
        </span>
      </div>

      <button
        type="button"
        class="btn btn-link btn-sm text-decoration-none px-0 mt-2"
        @click="showDetails = !showDetails"
      >
        <i :class="['bi me-1', showDetails ? 'bi-chevron-up' : 'bi-chevron-down']"></i>
        {{ showDetails ? 'إخفاء التفاصيل' : 'تفاصيل إضافية' }}
      </button>

      <div v-show="showDetails" class="row g-2 text-muted small mt-2 pt-2 border-top">
        <div class="col-6 col-md-4"><strong>الرقم القومي:</strong> {{ profile.national_id || '—' }}</div>
        <div class="col-6 col-md-4"><strong>الوظيفة:</strong> {{ profile.job_title || '—' }}</div>
        <div class="col-6 col-md-4"><strong>تاريخ التسجيل:</strong> {{ profile.created_at || '—' }}</div>
      </div>
    </div>
  </div>
</template>
