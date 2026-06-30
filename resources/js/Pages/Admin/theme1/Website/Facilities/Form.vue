<script setup>
import { computed } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import WebsiteSidebar from '@/Pages/Admin/theme1/Website/Partials/Sidebar.vue'
import WebsiteAdminImageSection from '@/Components/Admin/Website/WebsiteAdminImageSection.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({ facility: { type: Object, default: null } })

const form = useForm({
  icon: props.facility?.icon ?? 'bi-building',
  name: props.facility?.name ?? '',
  description: props.facility?.description ?? '',
  benefit: props.facility?.benefit ?? '',
  is_active: props.facility?.is_active ?? true,
  sort_order: props.facility?.sort_order ?? 0,
  image_src: props.facility?.image_src ?? '',
  image_alt: props.facility?.image_alt ?? '',
  image: null,
})

const pageTitle = computed(() => (props.facility ? 'تعديل مرفق' : 'مرفق جديد'))

function submit() {
  const options = { forceFormData: true, preserveScroll: true }
  if (props.facility) {
    form.post(route('admin.website.facilities.update', props.facility.id), { ...options, _method: 'put' })
  } else {
    form.post(route('admin.website.facilities.store'), options)
  }
}
</script>

<template>
  <Head :title="pageTitle" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <WebsiteSidebar />
        <div class="col-lg-9">
          <div class="mb-3">
            <Link :href="route('admin.website.facilities.index')" class="btn btn-sm btn-link px-0 text-muted">← العودة إلى المرافق</Link>
            <h1 class="h4 mb-0">{{ pageTitle }}</h1>
          </div>

          <form @submit.prevent="submit" class="vstack gap-3">
            <div class="card card-body">
              <h2 class="h6 mb-3">المعلومات الأساسية</h2>
              <div class="row g-3">
                <div class="col-md-8">
                  <label class="form-label">اسم المرفق <span class="text-danger">*</span></label>
                  <input v-model="form.name" class="form-control" required />
                </div>
                <div class="col-md-4">
                  <label class="form-label">أيقونة Bootstrap</label>
                  <input v-model="form.icon" class="form-control" placeholder="bi-building" />
                  <div class="form-text">مثال: bi-building, bi-flask, bi-palette</div>
                </div>
                <div class="col-md-4">
                  <label class="form-label">ترتيب العرض</label>
                  <input v-model.number="form.sort_order" type="number" min="0" class="form-control" />
                </div>
                <div class="col-md-8 d-flex align-items-end">
                  <div class="form-check">
                    <input v-model="form.is_active" type="checkbox" class="form-check-input" id="active" />
                    <label for="active" class="form-check-label">نشط / ظاهر</label>
                  </div>
                </div>
                <div class="col-12">
                  <label class="form-label">الوصف</label>
                  <textarea v-model="form.description" class="form-control" rows="3" />
                </div>
                <div class="col-12">
                  <label class="form-label">الفائدة / الميزة</label>
                  <textarea v-model="form.benefit" class="form-control" rows="2" placeholder="Hands-on labs for every secondary student." />
                </div>
              </div>
            </div>

            <WebsiteAdminImageSection
              spec-key="facility_image"
              title="صورة المرفق (اختياري)"
              hint="اختيارية — البطاقة تعتمد أساساً على الأيقونة والنص."
              :existing-url="facility?.image_src ?? ''"
              :src="form.image_src"
              :alt="form.image_alt"
              @update:image="form.image = $event"
              @update:src="form.image_src = $event"
              @update:alt="form.image_alt = $event"
            />

            <div class="d-flex flex-wrap gap-2">
              <button type="submit" class="btn btn-primary" :disabled="form.processing">حفظ</button>
              <Link :href="route('admin.website.facilities.index')" class="btn btn-outline-secondary">إلغاء</Link>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
