<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import WebsiteSidebar from '@/Pages/Admin/theme1/Website/Partials/Sidebar.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({ landingSections: Array })

const form = useForm({
  landingSections: [...(props.landingSections || [])],
})

function submit() {
  form.put(route('admin.website.landing.update'))
}
</script>

<template>
  <Head title="إعدادات الأقسام" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <WebsiteSidebar />
        <div class="col-lg-9">
          <h1 class="h4 mb-3">إعدادات الصفحة الرئيسية</h1>
          <form @submit.prevent="submit">
            <div v-for="(section, i) in form.landingSections" :key="section.key" class="card mb-2">
              <div class="card-body d-flex align-items-center gap-3">
                <div class="form-check form-switch mb-0">
                  <input v-model="section.enabled" class="form-check-input" type="checkbox" :id="'sec-' + section.key" />
                </div>
                <div class="flex-grow-1">
                  <label class="fw-bold" :for="'sec-' + section.key">{{ section.label }}</label>
                  <div class="text-muted small">{{ section.key }}</div>
                </div>
                <input v-model.number="section.sort_order" type="number" class="form-control form-control-sm" style="width: 5rem" />
              </div>
            </div>
            <button type="submit" class="btn btn-primary" :disabled="form.processing">حفظ</button>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
