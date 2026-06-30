<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import WebsiteSidebar from '@/Pages/Admin/theme1/Website/Partials/Sidebar.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { normalizeMapEmbedUrl } from '@/utils/normalizeMapEmbedUrl'

const props = defineProps({ schoolInfo: Object, visitCampusReasons: Array })

const form = useForm({
  schoolInfo: JSON.parse(JSON.stringify(props.schoolInfo || {})),
  visitCampusReasons: JSON.parse(JSON.stringify(props.visitCampusReasons || [])),
})

function submit() {
  if (form.schoolInfo?.contact?.mapEmbedUrl) {
    form.schoolInfo.contact.mapEmbedUrl = normalizeMapEmbedUrl(form.schoolInfo.contact.mapEmbedUrl)
  }
  form.put(route('admin.website.contact.update'))
}

function addReason() {
  form.visitCampusReasons.push({ icon: 'bi-check-circle', text: '' })
}
</script>

<template>
  <Head title="التواصل" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <WebsiteSidebar />
        <div class="col-lg-9">
          <h1 class="h4 mb-3">التواصل والزيارة</h1>
          <form @submit.prevent="submit" class="vstack gap-3">
            <div class="card card-body">
              <h2 class="h6">بيانات التواصل</h2>
              <input v-model="form.schoolInfo.contact.address" class="form-control mb-2" placeholder="العنوان" />
              <input v-model="form.schoolInfo.contact.phone" class="form-control mb-2" placeholder="الهاتف" />
              <input v-model="form.schoolInfo.contact.email" class="form-control mb-2" placeholder="البريد" />
              <input v-model="form.schoolInfo.contact.whatsapp" class="form-control mb-2" placeholder="واتساب" />
              <input v-model="form.schoolInfo.contact.hours" class="form-control mb-2" placeholder="ساعات العمل" />
              <input v-model="form.schoolInfo.contact.mapTitle" class="form-control mb-2" placeholder="عنوان الخريطة" />
              <label class="form-label small text-muted mb-1">رابط تضمين الخريطة (يمكن لصق رابط <code>src</code> فقط أو كود iframe كامل من Google)</label>
              <textarea v-model="form.schoolInfo.contact.mapEmbedUrl" class="form-control font-monospace" rows="3" placeholder="https://www.google.com/maps/embed?pb=..." />
            </div>
            <div class="card card-body">
              <h2 class="h6">أسباب زيارة الحرم</h2>
              <div v-for="(r, i) in form.visitCampusReasons" :key="i" class="row g-2 mb-2">
                <div class="col-3"><input v-model="r.icon" class="form-control form-control-sm" placeholder="bi-icon" /></div>
                <div class="col-8"><input v-model="r.text" class="form-control form-control-sm" /></div>
                <div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger" @click="form.visitCampusReasons.splice(i, 1)">×</button></div>
              </div>
              <button type="button" class="btn btn-sm btn-outline-primary" @click="addReason">+ سبب</button>
            </div>
            <button type="submit" class="btn btn-primary">حفظ</button>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
