<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import WebsiteSidebar from '@/Pages/Admin/theme1/Website/Partials/Sidebar.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import WebsiteImageUploadField from '@/Components/Admin/Website/WebsiteImageUploadField.vue'

const props = defineProps({ seo: Object })

const form = useForm({
  seo: { ...(props.seo || {}) },
  og_image: null,
})

function submit() {
  form.post(route('admin.website.seo.update'), { forceFormData: true })
}
</script>

<template>
  <Head title="SEO" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <WebsiteSidebar />
        <div class="col-lg-9">
          <h1 class="h4 mb-3">إعدادات SEO</h1>
          <form @submit.prevent="submit" class="vstack gap-3">
            <input v-model="form.seo.meta_title" class="form-control" placeholder="Meta Title" />
            <textarea v-model="form.seo.meta_description" class="form-control" rows="3" placeholder="Meta Description" />
            <input v-model="form.seo.keywords" class="form-control" placeholder="Keywords" />
            <WebsiteImageUploadField
              spec-key="og_image"
              label="صورة Open Graph"
              :existing-url="seo?.og_image_path ?? ''"
              @update:model-value="form.og_image = $event"
            />
            <button type="submit" class="btn btn-primary" :disabled="form.processing">حفظ</button>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
