<script setup>
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AdminSectionNav from '@/Components/Admin/AdminSectionNav.vue'

const links = [
  { label: 'لوحة التحكم', route: 'admin.dova-knowledge.dashboard', icon: 'bi-speedometer2' },
  { label: 'إدارة الأسئلة الشائعة', route: 'admin.dova-knowledge.faqs.dashboard', icon: 'bi-patch-question' },
  { label: 'حوكمة المعرفة', route: 'admin.dova-knowledge.governance.index', icon: 'bi-shield-check' },
  { label: 'فجوات المعرفة', route: 'admin.dova-knowledge.gaps.index', icon: 'bi-exclamation-triangle' },
  { label: 'مصادر المعرفة', route: 'admin.dova-knowledge.sources.index', icon: 'bi-database' },
  { label: 'مركز المزامنة', route: 'admin.dova-knowledge.sync.index', icon: 'bi-arrow-repeat' },
  { label: 'مستكشف المعرفة', route: 'admin.dova-knowledge.explorer.index', icon: 'bi-search' },
  { label: 'مركز الاختبار', route: 'admin.dova-knowledge.testing.index', icon: 'bi-chat-square-text' },
  { label: 'استخدام الذكاء الاصطناعي', route: 'admin.dova-knowledge.ai-usage.index', icon: 'bi-cpu' },
  { label: 'أسئلة بلا إجابة', route: 'admin.dova-knowledge.unanswered.index', icon: 'bi-question-circle' },
  { label: 'التحليلات', route: 'admin.dova-knowledge.analytics.index', icon: 'bi-bar-chart-line' },
]
</script>

<template>
  <AdminSectionNav id="adminDovaNav" title="مركز معرفة دوفا" breakpoint="lg">
    <div class="list-group list-group-flush border rounded">
      <div class="list-group-item bg-light fw-semibold small text-muted">مركز معرفة دوفا</div>
      <Link
        v-for="item in links"
        :key="item.route"
        :href="route(item.route)"
        class="list-group-item list-group-item-action d-flex align-items-center gap-2"
      >
        <i :class="['bi', item.icon]" />
        {{ item.label }}
      </Link>
    </div>
  </AdminSectionNav>
</template>
