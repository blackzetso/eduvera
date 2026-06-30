<script setup>
import { computed, watch } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import WebsiteSidebar from '@/Pages/Admin/theme1/Website/Partials/Sidebar.vue'
import WebsiteAdminImageSection from '@/Components/Admin/Website/WebsiteAdminImageSection.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({ testimonial: { type: Object, default: null } })

const roleLabels = {
  parent: 'Parent',
  student: 'Student',
  teacher: 'Teacher',
  alumni: 'Alumni',
}

const form = useForm({
  name: props.testimonial?.name ?? '',
  role: props.testimonial?.role ?? 'Parent',
  role_type: props.testimonial?.role_type ?? 'parent',
  quote: props.testimonial?.quote ?? '',
  is_active: props.testimonial?.is_active ?? true,
  sort_order: props.testimonial?.sort_order ?? 0,
  photo_src: props.testimonial?.photo_src ?? '',
  photo_alt: props.testimonial?.photo_alt ?? '',
  photo: null,
})

const pageTitle = computed(() => (props.testimonial ? 'تعديل رأي' : 'رأي جديد'))

watch(
  () => form.role_type,
  (type) => {
    form.role = roleLabels[type] ?? form.role
  }
)

function submit() {
  const options = { forceFormData: true, preserveScroll: true }
  if (props.testimonial) {
    form.post(route('admin.website.testimonials.update', props.testimonial.id), { ...options, _method: 'put' })
  } else {
    form.post(route('admin.website.testimonials.store'), options)
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
            <Link :href="route('admin.website.testimonials.index')" class="btn btn-sm btn-link px-0 text-muted">← العودة إلى آراء الأهالي</Link>
            <h1 class="h4 mb-0">{{ pageTitle }}</h1>
          </div>

          <form @submit.prevent="submit" class="vstack gap-3">
            <div class="card card-body">
              <h2 class="h6 mb-3">المعلومات الأساسية</h2>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">الاسم <span class="text-danger">*</span></label>
                  <input v-model="form.name" class="form-control" required />
                </div>
                <div class="col-md-6">
                  <label class="form-label">نوع المتحدث</label>
                  <select v-model="form.role_type" class="form-select">
                    <option value="parent">ولي أمر (Parent)</option>
                    <option value="student">طالب (Student)</option>
                    <option value="teacher">معلم (Teacher)</option>
                    <option value="alumni">خريج (Alumni)</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">الدور المعروض</label>
                  <input v-model="form.role" class="form-control" placeholder="Parent of Grade 5" />
                </div>
                <div class="col-md-3">
                  <label class="form-label">ترتيب العرض</label>
                  <input v-model.number="form.sort_order" type="number" min="0" class="form-control" />
                </div>
                <div class="col-md-3 d-flex align-items-end">
                  <div class="form-check">
                    <input v-model="form.is_active" type="checkbox" class="form-check-input" id="active" />
                    <label for="active" class="form-check-label">نشط / ظاهر</label>
                  </div>
                </div>
                <div class="col-12">
                  <label class="form-label">الاقتباس <span class="text-danger">*</span></label>
                  <textarea v-model="form.quote" class="form-control" rows="4" required placeholder="«School Talent changed our daughter's confidence...»" />
                </div>
              </div>
            </div>

            <WebsiteAdminImageSection
              spec-key="testimonial_photo"
              title="صورة الشخص"
              hint="تظهر في قسم What Families Say."
              upload-label="رفع صورة جديدة"
              :existing-url="testimonial?.photo_src ?? ''"
              :src="form.photo_src"
              :alt="form.photo_alt"
              @update:image="form.photo = $event"
              @update:src="form.photo_src = $event"
              @update:alt="form.photo_alt = $event"
            />

            <div class="d-flex flex-wrap gap-2">
              <button type="submit" class="btn btn-primary" :disabled="form.processing">حفظ</button>
              <Link :href="route('admin.website.testimonials.index')" class="btn btn-outline-secondary">إلغاء</Link>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
