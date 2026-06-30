<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import WebsiteSidebar from '@/Pages/Admin/theme1/Website/Partials/Sidebar.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({ posts: Array, type: String })

function destroy(id) {
  if (confirm('حذف؟')) router.delete(route('admin.website.posts.destroy', id))
}
</script>

<template>
  <Head :title="type === 'blog' ? 'المدونة' : 'الأخبار'" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <WebsiteSidebar />
        <div class="col-lg-9">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <h1 class="h4 mb-1">{{ type === 'blog' ? 'المدونة' : 'الأخبار' }}</h1>
              <p class="text-muted small mb-0">تظهر في قسم Latest Stories & Updates على الصفحة الرئيسية.</p>
            </div>
            <Link :href="route('admin.website.posts.create', { type })" class="btn btn-success-soft btn-round" title="إضافة">
              <i class="bi bi-plus" />
            </Link>
          </div>
          <div class="table-responsive eduvera-table-wrap">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th>العنوان</th>
                <th>التصنيف</th>
                <th>مميز</th>
                <th>نشط</th>
                <th class="text-end">إجراءات</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in posts" :key="p.id">
                <td>{{ p.title }}</td>
                <td class="text-muted">{{ p.category || '—' }}</td>
                <td>{{ p.is_featured ? '✓' : '—' }}</td>
                <td>{{ p.is_active !== false ? '✓' : '—' }}</td>
                <td class="text-end">
                  <Link :href="route('admin.website.posts.edit', p.id)" class="btn btn-sm btn-light">تعديل</Link>
                  <button type="button" class="btn btn-sm btn-danger-soft ms-1" @click="destroy(p.id)">حذف</button>
                </td>
              </tr>
              <tr v-if="!posts?.length">
                <td colspan="5" class="text-muted text-center">لا توجد مقالات بعد.</td>
              </tr>
            </tbody>
          </table>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
