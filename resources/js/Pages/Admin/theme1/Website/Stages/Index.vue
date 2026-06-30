<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import WebsiteSidebar from '@/Pages/Admin/theme1/Website/Partials/Sidebar.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

defineProps({ stages: Array })

function destroy(id) {
  if (confirm('حذف هذه المرحلة؟')) router.delete(route('admin.website.stages.destroy', id))
}
</script>

<template>
  <Head title="المراحل الدراسية" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <WebsiteSidebar />
        <div class="col-lg-9">
          <div class="d-flex justify-content-between mb-3">
            <h1 class="h4 mb-0">المراحل الدراسية</h1>
            <Link :href="route('admin.website.stages.create')" class="btn btn-success-soft btn-round"><i class="bi bi-plus" /></Link>
          </div>
          <div class="table-responsive eduvera-table-wrap">
          <table class="table table-hover">
            <thead><tr><th>العنوان</th><th>Slug</th><th>ترتيب</th><th></th></tr></thead>
            <tbody>
              <tr v-for="s in stages" :key="s.id">
                <td>{{ s.title }}</td>
                <td>{{ s.slug }}</td>
                <td>{{ s.sort_order }}</td>
                <td class="text-end">
                  <Link :href="route('admin.website.stages.edit', s.id)" class="btn btn-sm btn-light me-1">تعديل</Link>
                  <button type="button" class="btn btn-sm btn-danger-soft" @click="destroy(s.id)">حذف</button>
                </td>
              </tr>
            </tbody>
          </table>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
