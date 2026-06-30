<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import WebsiteSidebar from '@/Pages/Admin/theme1/Website/Partials/Sidebar.vue'
import { Head, Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { computed } from 'vue'

const props = defineProps({
  device: { type: String, default: 'desktop' },
  previewUrl: { type: String, required: true },
})

const devices = [
  { key: 'desktop', label: 'Desktop', icon: 'bi-display' },
  { key: 'tablet', label: 'Tablet', icon: 'bi-tablet' },
  { key: 'mobile', label: 'Mobile', icon: 'bi-phone' },
]

const iframeSrc = computed(() => {
  const base = props.previewUrl
  const sep = base.includes('?') ? '&' : '?'
  return `${base}${sep}device=${props.device}`
})
</script>

<template>
  <Head title="معاينة الموقع" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <WebsiteSidebar />
        <div class="col-lg-9">
          <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
              <h1 class="h4 mb-1">معاينة الموقع</h1>
              <p class="text-muted small mb-0">معاينة المسودة قبل النشر</p>
            </div>
            <Link :href="route('admin.website.landing-builder.index')" class="btn btn-sm btn-outline-secondary">← المنشئ</Link>
          </div>

          <div class="btn-group mb-3">
            <Link
              v-for="d in devices"
              :key="d.key"
              :href="route('admin.website.landing-builder.preview', { device: d.key })"
              class="btn btn-sm"
              :class="device === d.key ? 'btn-primary' : 'btn-outline-primary'"
            >
              <i :class="['bi', d.icon, 'me-1']"></i>{{ d.label }}
            </Link>
          </div>

          <div
            class="border rounded overflow-hidden bg-light mx-auto"
            :style="{
              maxWidth: device === 'mobile' ? '390px' : device === 'tablet' ? '768px' : '100%',
              minHeight: '70vh',
            }"
          >
            <iframe :src="iframeSrc" title="Landing preview" class="w-100 border-0" style="min-height: 70vh" />
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
