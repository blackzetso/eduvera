<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import WebsiteSidebar from '@/Pages/Admin/theme1/Website/Partials/Sidebar.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
defineProps({ facilities: Array })
function destroy(id) {
  if (confirm('حذف؟')) router.delete(route('admin.website.facilities.destroy', id))
}
</script>
<template>
  <Head title="المرافق" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row"><WebsiteSidebar /><div class="col-lg-9">
        <div class="d-flex justify-content-between mb-3">
          <h1 class="h4">المرافق</h1>
          <Link :href="route('admin.website.facilities.create')" class="btn btn-success-soft btn-round"><i class="bi bi-plus" /></Link>
        </div>
        <div class="table-responsive eduvera-table-wrap">
        <table class="table">
          <tr v-for="f in facilities" :key="f.id"><td>{{ f.name }}</td>
            <td class="text-end">
              <Link :href="route('admin.website.facilities.edit', f.id)" class="btn btn-sm btn-light">تعديل</Link>
              <button type="button" class="btn btn-sm btn-danger-soft ms-1" @click="destroy(f.id)">حذف</button>
            </td></tr>
        </table>
        </div>
      </div></div>
    </div>
  </AppLayout>
</template>
