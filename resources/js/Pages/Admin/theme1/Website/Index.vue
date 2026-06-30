<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import WebsiteSidebar from '@/Pages/Admin/theme1/Website/Partials/Sidebar.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

defineProps({ cmsActive: Boolean })

function importDefaults() {
  if (!confirm('استيراد المحتوى الحالي من القالب الافتراضي؟ سيتم استبدال بيانات CMS.')) return
  router.post(route('admin.website.import-defaults'))
}
</script>

<template>
  <Head title="إدارة الموقع" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <WebsiteSidebar />
        <div class="col-lg-9">
          <h1 class="h3 mb-3">إدارة الموقع — School Talent</h1>
          <div class="alert" :class="cmsActive ? 'alert-success' : 'alert-warning'">
            <span v-if="cmsActive">CMS مفعّل — الصفحة الرئيسية تُعرض من قاعدة البيانات.</span>
            <span v-else>CMS غير مفعّل — الموقع يعرض المحتوى الثابت. انقر استيراد لتفعيل الإدارة.</span>
          </div>
          <div class="d-flex flex-wrap gap-2 mb-4">
            <button type="button" class="btn btn-primary" @click="importDefaults">استيراد المحتوى الافتراضي</button>
            <Link :href="route('home')" class="btn btn-outline-secondary" target="_blank">معاينة الموقع</Link>
          </div>
          <p class="text-muted small mb-0">
            عدّل الأقسام من القائمة الجانبية. التصميم الحالي للصفحة الرئيسية يبقى كما هو — يتغيّر النص والصور والترتيب فقط.
          </p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
