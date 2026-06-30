<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import WebsiteSidebar from '@/Pages/Admin/theme1/Website/Partials/Sidebar.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { computed } from 'vue'
import WebsiteImageUploadField from '@/Components/Admin/Website/WebsiteImageUploadField.vue'

const props = defineProps({ theme: Object })

const defaults = {
  primary_color: '#0d6efd',
  primary_hover: '#0b5ed7',
  secondary_color: '#0f172a',
  accent_color: '#22c55e',
  cream_color: '#f8fafc',
  muted_color: '#64748b',
  border_color: '#e2e8f0',
  success_color: '#22c55e',
  warning_color: '#f59e0b',
  danger_color: '#ef4444',
  font_family: "'Inter', system-ui, sans-serif",
  display_font: "'Playfair Display', Georgia, serif",
  radius: '14px',
  radius_lg: '16px',
  button_style: 'outline',
}

const form = useForm({
  theme: { ...defaults, ...(props.theme || {}) },
  logo: null,
  favicon: null,
})

const existingLogo = computed(() => props.theme?.logo_path ?? '')
const existingFavicon = computed(() => props.theme?.favicon_path ?? '')

function submit() {
  form.post(route('admin.website.theme.update'), { forceFormData: true })
}
</script>

<template>
  <Head title="الهوية البصرية" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <WebsiteSidebar />
        <div class="col-lg-9">
          <h1 class="h4 mb-3">الهوية البصرية</h1>
          <p class="text-muted small">تُطبَّق الألوان كمتغيرات CSS (--st-*) دون تغيير تصميم الصفحة.</p>
          <form @submit.prevent="submit" class="vstack gap-3">
            <div class="card card-body row g-3">
              <div class="col-md-4">
                <label class="form-label">اللون الأساسي</label>
                <input v-model="form.theme.primary_color" type="color" class="form-control form-control-color w-100" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Hover أساسي</label>
                <input v-model="form.theme.primary_hover" type="color" class="form-control form-control-color w-100" />
              </div>
              <div class="col-md-4">
                <label class="form-label">اللون الثانوي (Navy)</label>
                <input v-model="form.theme.secondary_color" type="color" class="form-control form-control-color w-100" />
              </div>
              <div class="col-md-4">
                <label class="form-label">لون التمييز</label>
                <input v-model="form.theme.accent_color" type="color" class="form-control form-control-color w-100" />
              </div>
              <div class="col-md-4">
                <label class="form-label">خلفية Cream</label>
                <input v-model="form.theme.cream_color" type="color" class="form-control form-control-color w-100" />
              </div>
              <div class="col-md-4">
                <label class="form-label">نص باهت</label>
                <input v-model="form.theme.muted_color" type="color" class="form-control form-control-color w-100" />
              </div>
              <div class="col-md-4">
                <label class="form-label">حدود</label>
                <input v-model="form.theme.border_color" type="color" class="form-control form-control-color w-100" />
              </div>
              <div class="col-md-4">
                <label class="form-label">نجاح</label>
                <input v-model="form.theme.success_color" type="color" class="form-control form-control-color w-100" />
              </div>
              <div class="col-md-4">
                <label class="form-label">تحذير</label>
                <input v-model="form.theme.warning_color" type="color" class="form-control form-control-color w-100" />
              </div>
              <div class="col-md-4">
                <label class="form-label">خطر</label>
                <input v-model="form.theme.danger_color" type="color" class="form-control form-control-color w-100" />
              </div>
              <div class="col-md-6">
                <label class="form-label">خط النص</label>
                <input v-model="form.theme.font_family" class="form-control" />
              </div>
              <div class="col-md-6">
                <label class="form-label">خط العناوين</label>
                <input v-model="form.theme.display_font" class="form-control" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Radius</label>
                <input v-model="form.theme.radius" class="form-control" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Radius كبير</label>
                <input v-model="form.theme.radius_lg" class="form-control" />
              </div>
              <div class="col-md-4">
                <label class="form-label">نمط الأزرار</label>
                <select v-model="form.theme.button_style" class="form-select">
                  <option value="outline">Outline</option>
                  <option value="filled">Filled</option>
                </select>
              </div>
              <div class="col-md-6">
                <WebsiteImageUploadField
                  spec-key="logo"
                  label="شعار الموقع"
                  :existing-url="existingLogo"
                  input-class="form-control"
                  @update:model-value="form.logo = $event"
                />
              </div>
              <div class="col-md-6">
                <WebsiteImageUploadField
                  spec-key="favicon"
                  label="Favicon"
                  :existing-url="existingFavicon"
                  input-class="form-control"
                  @update:model-value="form.favicon = $event"
                />
              </div>
            </div>
            <button type="submit" class="btn btn-primary" :disabled="form.processing">حفظ</button>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
