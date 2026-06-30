<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import WebsiteSidebar from '@/Pages/Admin/theme1/Website/Partials/Sidebar.vue'
import WebsiteBilingualField from '@/Components/Admin/Website/WebsiteBilingualField.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  announcements: Array,
  announcementBadge: String,
  announcementBadgeAr: String,
})

const badgeForm = useForm({
  announcement_badge: props.announcementBadge ?? 'New',
  announcement_badge_ar: props.announcementBadgeAr ?? '',
})

const newItem = useForm({
  text: '',
  text_ar: '',
  href: '#',
  is_active: true,
  sort_order: (props.announcements?.length || 0) + 1,
})

function saveBadge() {
  badgeForm.put(route('admin.website.announcements.badge'), { preserveScroll: true })
}

function add() {
  newItem.post(route('admin.website.announcements.store'), {
    preserveScroll: true,
    onSuccess: () => newItem.reset('text', 'text_ar'),
  })
}

function update(row) {
  router.put(route('admin.website.announcements.update', row.id), row, { preserveScroll: true })
}

function remove(id) {
  if (confirm('حذف؟')) router.delete(route('admin.website.announcements.destroy', id))
}
</script>

<template>
  <Head title="شريط الإعلانات" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <WebsiteSidebar />
        <div class="col-lg-9">
          <h1 class="h4 mb-2">شريط الإعلانات</h1>
          <p class="text-muted small mb-3">English + عربي — يظهر حسب لغة الزائر.</p>

          <form class="card card-body mb-4" @submit.prevent="saveBadge">
            <h2 class="h6">نص الشارة (Badge)</h2>
            <WebsiteBilingualField
              v-model:en="badgeForm.announcement_badge"
              v-model:ar="badgeForm.announcement_badge_ar"
              compact
              en-placeholder="New"
              ar-placeholder="جديد"
            />
            <button class="btn btn-outline-primary btn-sm mt-2">حفظ الشارة</button>
          </form>

          <div class="table-responsive eduvera-table-wrap">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th>English</th>
                <th>عربي</th>
                <th>الرابط</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="a in announcements" :key="a.id">
                <td>
                  <input v-model="a.text" class="form-control form-control-sm" dir="ltr" @change="update(a)" />
                </td>
                <td>
                  <input v-model="a.text_ar" class="form-control form-control-sm" dir="rtl" @change="update(a)" />
                </td>
                <td>
                  <input v-model="a.href" class="form-control form-control-sm" @change="update(a)" />
                </td>
                <td>
                  <button type="button" class="btn btn-sm btn-danger-soft" @click="remove(a.id)">حذف</button>
                </td>
              </tr>
            </tbody>
          </table>
          </div>

          <div class="card card-body mt-3">
            <h2 class="h6">إضافة إعلان</h2>
            <WebsiteBilingualField
              v-model:en="newItem.text"
              v-model:ar="newItem.text_ar"
              class="mb-2"
              compact
              en-placeholder="Admissions Open"
              ar-placeholder="التسجيل مفتوح"
            />
            <input v-model="newItem.href" class="form-control mb-2" placeholder="#href" />
            <button type="button" class="btn btn-primary btn-sm" @click="add">إضافة</button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
