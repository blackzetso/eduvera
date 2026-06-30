<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import DovaSidebar from '@/Pages/Admin/theme1/DovaKnowledge/Partials/Sidebar.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  source: Object,
  records: Object,
  filters: Object,
})

function filterLocale(locale) {
  router.get(route('admin.dova-knowledge.sources.records', props.source.id), { locale }, { preserveState: true })
}
</script>

<template>
  <Head :title="`سجلات ${source.name}`" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <DovaSidebar />
        <div class="col-lg-9">
          <Link :href="route('admin.dova-knowledge.sources.index')" class="btn btn-sm btn-link px-0 mb-2">← مصادر المعرفة</Link>
          <h1 class="h3 mb-1">سجلات: {{ source.name }}</h1>
          <p class="text-muted small mb-3">{{ source.recordCount }} سجل — آخر تحديث: {{ source.lastSyncedAt }}</p>

          <div class="btn-group btn-group-sm mb-3">
            <button type="button" class="btn btn-outline-secondary" :class="{ active: !filters.locale }" @click="filterLocale('')">الكل</button>
            <button type="button" class="btn btn-outline-secondary" :class="{ active: filters.locale === 'en' }" @click="filterLocale('en')">EN</button>
            <button type="button" class="btn btn-outline-secondary" :class="{ active: filters.locale === 'ar' }" @click="filterLocale('ar')">AR</button>
          </div>

          <div class="table-responsive border rounded">
            <table class="table table-sm mb-0">
              <thead>
                <tr>
                  <th>المفتاح</th>
                  <th>العنوان</th>
                  <th>المحتوى</th>
                  <th>اللغة</th>
                  <th>مفهرس</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in records.data" :key="r.id">
                  <td class="small text-muted">{{ r.recordKey }}</td>
                  <td>{{ r.title || '—' }}</td>
                  <td class="small" style="max-width: 280px">{{ r.content }}</td>
                  <td>{{ r.locale }}</td>
                  <td class="small text-muted">{{ r.indexedAt }}</td>
                </tr>
                <tr v-if="!records.data?.length">
                  <td colspan="5" class="text-center text-muted py-4">لا توجد سجلات — قم بالمزامنة أولاً.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
