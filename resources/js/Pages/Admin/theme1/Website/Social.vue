<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import WebsiteSidebar from '@/Pages/Admin/theme1/Website/Partials/Sidebar.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({ social: Object, whatsappQuickActions: Array })

const form = useForm({
  social: { facebook: '', instagram: '', youtube: '', tiktok: '', linkedin: '', x: '', ...(props.social || {}) },
  whatsappQuickActions: JSON.parse(JSON.stringify(props.whatsappQuickActions || [])),
})

function submit() {
  form.put(route('admin.website.social.update'))
}

function addWhatsAppAction() {
  form.whatsappQuickActions.push({ label: '', message: '' })
}
</script>

<template>
  <Head title="وسائل التواصل" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <WebsiteSidebar />
        <div class="col-lg-9">
          <h1 class="h4 mb-3">وسائل التواصل</h1>
          <form @submit.prevent="submit" class="vstack gap-3">
            <div class="card card-body">
              <h2 class="h6">روابط الشبكات</h2>
              <input v-model="form.social.facebook" class="form-control mb-2" placeholder="Facebook URL" />
              <input v-model="form.social.instagram" class="form-control mb-2" placeholder="Instagram" />
              <input v-model="form.social.youtube" class="form-control mb-2" placeholder="YouTube" />
              <input v-model="form.social.tiktok" class="form-control mb-2" placeholder="TikTok" />
              <input v-model="form.social.linkedin" class="form-control mb-2" placeholder="LinkedIn" />
              <input v-model="form.social.x" class="form-control mb-2" placeholder="X / Twitter" />
            </div>
            <div class="card card-body">
              <h2 class="h6">رسائل واتساب السريعة</h2>
              <div v-for="(a, i) in form.whatsappQuickActions" :key="i" class="row g-2 mb-2">
                <div class="col-4"><input v-model="a.label" class="form-control form-control-sm" placeholder="التسمية" /></div>
                <div class="col-7"><input v-model="a.message" class="form-control form-control-sm" placeholder="نص الرسالة" /></div>
                <div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger" @click="form.whatsappQuickActions.splice(i, 1)">×</button></div>
              </div>
              <button type="button" class="btn btn-sm btn-outline-primary" @click="addWhatsAppAction">+ رسالة</button>
            </div>
            <button type="submit" class="btn btn-primary">حفظ</button>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
