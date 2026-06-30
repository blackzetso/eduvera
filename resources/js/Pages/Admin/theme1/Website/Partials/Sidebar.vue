<script setup>
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AdminSectionNav from '@/Components/Admin/AdminSectionNav.vue'

const links = [
  { label: 'نظرة عامة', route: 'admin.website.index', icon: 'bi-grid' },
  { label: 'منشئ الصفحة الرئيسية', route: 'admin.website.landing-builder.index', icon: 'bi-layout-wtf' },
  { label: 'الرأس والتذييل', route: 'admin.website.chrome.edit', icon: 'bi-window' },
  { label: 'روابط القائمة', route: 'admin.website.nav-links.index', icon: 'bi-list' },
  { label: 'شريط الإعلانات', route: 'admin.website.announcements.index', icon: 'bi-megaphone' },
  { label: 'مكتبة الأزرار', route: 'admin.website.ctas.index', icon: 'bi-cursor' },
  { label: 'تسميات الواجهة', route: 'admin.website.ui-labels.edit', icon: 'bi-translate' },
  { label: 'كتل المحتوى', route: 'admin.website.content-blocks.index', icon: 'bi-bricks' },
  { label: 'معرض الصور', route: 'admin.website.gallery.index', icon: 'bi-images' },
  { label: 'قسم البطل', route: 'admin.website.hero', icon: 'bi-stars' },
  { label: 'معلومات المدرسة', route: 'admin.website.school-info', icon: 'bi-building' },
  { label: 'المراحل الدراسية', route: 'admin.website.stages.index', icon: 'bi-layers' },
  { label: 'المرافق', route: 'admin.website.facilities.index', icon: 'bi-building-gear' },
  { label: 'الأخبار', route: 'admin.website.posts.index', params: { type: 'news' }, icon: 'bi-newspaper' },
  { label: 'المدونة', route: 'admin.website.posts.index', params: { type: 'blog' }, icon: 'bi-journal-text' },
  { label: 'الفعاليات', route: 'admin.website.events.index', icon: 'bi-calendar-event' },
  { label: 'آراء العملاء', route: 'admin.website.testimonials.index', icon: 'bi-chat-quote' },
  { label: 'قصص النجاح', route: 'admin.website.success-stories.index', icon: 'bi-trophy' },
  { label: 'الوظائف', route: 'admin.website.careers.index', icon: 'bi-briefcase' },
  { label: 'القبول', route: 'admin.website.admissions', icon: 'bi-door-open' },
  { label: 'التواصل', route: 'admin.website.contact', icon: 'bi-telephone' },
  { label: 'وسائل التواصل', route: 'admin.website.social', icon: 'bi-share' },
  { label: 'SEO', route: 'admin.website.seo', icon: 'bi-search' },
  { label: 'الهوية البصرية', route: 'admin.website.theme', icon: 'bi-palette' },
  { label: 'مكتبة الوسائط', route: 'admin.website.media.index', icon: 'bi-images' },
]
</script>

<template>
  <AdminSectionNav id="adminWebsiteNav" title="إدارة الموقع" breakpoint="lg">
    <div class="list-group list-group-flush border rounded">
      <Link
        v-for="item in links"
        :key="item.route + (item.params?.type || '')"
        :href="route(item.route, item.params || {})"
        class="list-group-item list-group-item-action d-flex align-items-center gap-2"
      >
        <i :class="['bi', item.icon]" />
        {{ item.label }}
      </Link>
    </div>
  </AdminSectionNav>
</template>
