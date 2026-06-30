<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import WebsiteSidebar from '@/Pages/Admin/theme1/Website/Partials/Sidebar.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
defineProps({ stories: Array })
function destroy(id) {
  if (confirm('حذف؟')) router.delete(route('admin.website.success-stories.destroy', id))
}
</script>
<template>
  <Head title="قصص النجاح" />
  <AppLayout>
    <div class="page-content-wrapper border"><div class="row"><WebsiteSidebar /><div class="col-lg-9">
      <div class="d-flex justify-content-between mb-3">
        <h1 class="h4">قصص النجاح</h1>
        <Link :href="route('admin.website.success-stories.create')" class="btn btn-success-soft btn-round"><i class="bi bi-plus" /></Link>
      </div>
      <div class="table-responsive eduvera-table-wrap">
      <table class="table">
        <tr v-for="s in stories" :key="s.id"><td>{{ s.student_name }}</td><td>{{ s.category }}</td>
          <td class="text-end">
            <Link :href="route('admin.website.success-stories.edit', s.id)" class="btn btn-sm btn-light">تعديل</Link>
            <button type="button" class="btn btn-sm btn-danger-soft ms-1" @click="destroy(s.id)">حذف</button>
          </td></tr>
      </table>
      </div>
    </div></div></div>
  </AppLayout>
</template>
