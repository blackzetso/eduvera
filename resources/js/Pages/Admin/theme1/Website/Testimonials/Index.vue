<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import WebsiteSidebar from '@/Pages/Admin/theme1/Website/Partials/Sidebar.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
defineProps({ testimonials: Array })
function destroy(id) {
  if (confirm('حذف؟')) router.delete(route('admin.website.testimonials.destroy', id))
}
</script>
<template>
  <Head title="آراء العملاء" />
  <AppLayout>
    <div class="page-content-wrapper border"><div class="row"><WebsiteSidebar /><div class="col-lg-9">
      <div class="d-flex justify-content-between mb-3">
        <h1 class="h4">آراء العملاء</h1>
        <Link :href="route('admin.website.testimonials.create')" class="btn btn-success-soft btn-round"><i class="bi bi-plus" /></Link>
      </div>
      <div class="table-responsive eduvera-table-wrap">
      <table class="table">
        <tr v-for="t in testimonials" :key="t.id"><td>{{ t.name }}</td><td>{{ t.role }}</td>
          <td class="text-end">
            <Link :href="route('admin.website.testimonials.edit', t.id)" class="btn btn-sm btn-light">تعديل</Link>
            <button type="button" class="btn btn-sm btn-danger-soft ms-1" @click="destroy(t.id)">حذف</button>
          </td></tr>
      </table>
      </div>
    </div></div></div>
  </AppLayout>
</template>
