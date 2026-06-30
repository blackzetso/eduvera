<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import WebsiteSidebar from '@/Pages/Admin/theme1/Website/Partials/Sidebar.vue'
import WebsiteBilingualField from '@/Components/Admin/Website/WebsiteBilingualField.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({ links: Array })

const newLink = useForm({
  label: '',
  label_ar: '',
  href: '#',
  is_active: true,
  sort_order: (props.links?.length || 0) + 1,
})

function add() {
  newLink.post(route('admin.website.nav-links.store'), {
    preserveScroll: true,
    onSuccess: () => newLink.reset('label', 'label_ar'),
  })
}

function update(link) {
  router.put(route('admin.website.nav-links.update', link.id), {
    label: link.label,
    label_ar: link.label_ar,
    href: link.href,
    is_active: link.is_active,
    sort_order: link.sort_order,
  }, { preserveScroll: true })
}

function remove(id) {
  if (confirm('حذف؟')) router.delete(route('admin.website.nav-links.destroy', id))
}
</script>

<template>
  <Head title="روابط القائمة" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <WebsiteSidebar />
        <div class="col-lg-9">
          <h1 class="h4 mb-2">روابط التنقل</h1>
          <p class="text-muted small mb-3">English = عمود إنجليزي، عربي = عمود عربي. يظهر على الموقع حسب لغة الزائر.</p>
          <div class="table-responsive">
          <table class="table table-sm mb-4 align-middle">
            <thead>
              <tr>
                <th>English</th>
                <th>عربي</th>
                <th>الرابط</th>
                <th>ترتيب</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="link in links" :key="link.id">
                <td>
                  <input v-model="link.label" class="form-control form-control-sm" dir="ltr" @change="update(link)" />
                </td>
                <td>
                  <input v-model="link.label_ar" class="form-control form-control-sm" dir="rtl" @change="update(link)" />
                </td>
                <td>
                  <input v-model="link.href" class="form-control form-control-sm" @change="update(link)" />
                </td>
                <td>
                  <input v-model.number="link.sort_order" type="number" class="form-control form-control-sm" style="width:4rem" @change="update(link)" />
                </td>
                <td>
                  <button type="button" class="btn btn-sm btn-danger-soft" @click="remove(link.id)">حذف</button>
                </td>
              </tr>
            </tbody>
          </table>
          </div>
          <div class="card card-body">
            <h2 class="h6">إضافة رابط</h2>
            <WebsiteBilingualField
              v-model:en="newLink.label"
              v-model:ar="newLink.label_ar"
              class="mb-2"
              compact
              en-placeholder="Home"
              ar-placeholder="الرئيسية"
            />
            <input v-model="newLink.href" class="form-control mb-2" placeholder="#section" />
            <button type="button" class="btn btn-primary btn-sm" @click="add">إضافة</button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
