<script setup>
import { Head } from '@inertiajs/vue3'
import GuardianDashboardLayout from '@/Layouts/GuardianDashboardLayout.vue'

defineProps({
  guardian: Object,
  children: Array,
  preferences: Object,
})
</script>

<template>
  <Head title="إعدادات الإشعارات" />
  <GuardianDashboardLayout :guardian="guardian" :children="children" active-menu="notifications">
    <div class="card border">
      <div class="card-header bg-transparent">
        <h5 class="mb-0">تفضيلات إشعارات الحضور</h5>
      </div>
      <div class="card-body">
        <p class="text-muted small">يتم إرسال الإشعارات حسب الإعدادات المحفوظة لكل ابن.</p>
        <div v-for="child in children" :key="child.id" class="border rounded p-3 mb-3">
          <h6 class="mb-2">{{ child.name }}</h6>
          <ul class="list-unstyled small mb-0" v-if="preferences[child.id]">
            <li><i class="bi bi-check2 text-success me-1" /> إشعار الغياب: {{ preferences[child.id].notify_absence ? 'مفعّل' : 'معطّل' }}</li>
            <li><i class="bi bi-check2 text-success me-1" /> إشعار التأخر: {{ preferences[child.id].notify_late ? 'مفعّل' : 'معطّل' }}</li>
            <li><i class="bi bi-whatsapp text-success me-1" /> واتساب: {{ preferences[child.id].notify_whatsapp ? 'مفعّل' : 'معطّل' }}</li>
          </ul>
          <p v-else class="text-muted small mb-0">لم تُضبط تفضيلات بعد (الافتراضي: مفعّل).</p>
        </div>
        <p v-if="!children.length" class="text-muted mb-0">لا يوجد أبناء مرتبطون.</p>
      </div>
    </div>
  </GuardianDashboardLayout>
</template>
