<script setup>
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  profile: { type: Object, required: true },
})

const emit = defineEmits(['open-tab'])
</script>

<template>
  <div class="card family-command-card border-0 shadow-sm mb-4 rounded-4">
    <div class="card-body p-3">
      <h6 class="fw-bold mb-3">
        <i class="bi bi-lightning-charge me-1 text-primary"></i>
        إجراءات سريعة
      </h6>
      <div class="row g-2 family-quick-actions">
        <div class="col-6 col-md-4 col-lg">
          <button type="button" class="btn btn-outline-warning btn-sm w-100 family-quick-action-btn" @click="emit('open-tab', 'finance')">
            <i class="bi bi-cash-coin me-1"></i>المالية
          </button>
        </div>
        <div class="col-6 col-md-4 col-lg">
          <button type="button" class="btn btn-outline-success btn-sm w-100 family-quick-action-btn" @click="emit('open-tab', 'attendance')">
            <i class="bi bi-calendar-check me-1"></i>الحضور
          </button>
        </div>
        <div class="col-6 col-md-4 col-lg">
          <button type="button" class="btn btn-outline-primary btn-sm w-100 family-quick-action-btn" @click="emit('open-tab', 'academic')">
            <i class="bi bi-journal-bookmark me-1"></i>السجلات الأكاديمية
          </button>
        </div>
        <div class="col-6 col-md-4 col-lg">
          <button type="button" class="btn btn-outline-secondary btn-sm w-100 family-quick-action-btn" @click="emit('open-tab', 'documents')">
            <i class="bi bi-folder me-1"></i>مستندات العائلة
          </button>
        </div>
        <div class="col-6 col-md-4 col-lg">
          <a :href="`mailto:${profile.email}`" class="btn btn-outline-info btn-sm w-100 family-quick-action-btn">
            <i class="bi bi-envelope me-1"></i>إرسال رسالة
          </a>
        </div>
        <div class="col-6 col-md-4 col-lg">
          <Link :href="route('admin.parents.edit', profile.id)" class="btn btn-outline-dark btn-sm w-100 family-quick-action-btn">
            <i class="bi bi-pencil me-1"></i>تعديل البيانات
          </Link>
        </div>
      </div>
    </div>
  </div>
</template>
