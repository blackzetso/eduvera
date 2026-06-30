<script setup>
import { computed } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import WebsiteSidebar from '@/Pages/Admin/theme1/Website/Partials/Sidebar.vue'
import WebsiteAdminImageSection from '@/Components/Admin/Website/WebsiteAdminImageSection.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({ story: { type: Object, default: null } })

const form = useForm({
  student_name: props.story?.student_name ?? '',
  achievement: props.story?.achievement ?? '',
  category: props.story?.category ?? '',
  story: props.story?.story ?? '',
  stat_value: props.story?.stat_value ?? '',
  stat_label: props.story?.stat_label ?? '',
  is_active: props.story?.is_active ?? true,
  sort_order: props.story?.sort_order ?? 0,
  image_src: props.story?.image_src ?? '',
  image_alt: props.story?.image_alt ?? '',
  image: null,
})

const pageTitle = computed(() => (props.story ? 'تعديل قصة نجاح' : 'قصة نجاح جديدة'))

function submit() {
  const options = { forceFormData: true, preserveScroll: true }
  if (props.story) {
    form.post(route('admin.website.success-stories.update', props.story.id), { ...options, _method: 'put' })
  } else {
    form.post(route('admin.website.success-stories.store'), options)
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
            <Link :href="route('admin.website.success-stories.index')" class="btn btn-sm btn-link px-0 text-muted">← العودة إلى قصص النجاح</Link>
            <h1 class="h4 mb-0">{{ pageTitle }}</h1>
          </div>

          <form @submit.prevent="submit" class="vstack gap-3">
            <div class="card card-body">
              <h2 class="h6 mb-3">المعلومات الأساسية</h2>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">اسم الطالب / العنوان الداخلي</label>
                  <input v-model="form.student_name" class="form-control" required />
                </div>
                <div class="col-md-6">
                  <label class="form-label">التصنيف</label>
                  <input v-model="form.category" class="form-control" list="story-categories" placeholder="Scholarships" />
                  <datalist id="story-categories">
                    <option value="Scholarships" />
                    <option value="Competitions" />
                    <option value="University Admissions" />
                    <option value="Student Achievements" />
                  </datalist>
                </div>
                <div class="col-12">
                  <label class="form-label">عنوان الإنجاز (يظهر كعنوان البطاقة)</label>
                  <input v-model="form.achievement" class="form-control" placeholder="National Robotics Champions" />
                </div>
                <div class="col-md-4">
                  <label class="form-label">الرقم / الإحصائية</label>
                  <input v-model="form.stat_value" class="form-control" placeholder="42 أو 98%" />
                </div>
                <div class="col-md-4">
                  <label class="form-label">وصف الإحصائية</label>
                  <input v-model="form.stat_label" class="form-control" placeholder="Scholarships" />
                </div>
                <div class="col-md-4">
                  <label class="form-label">ترتيب العرض</label>
                  <input v-model.number="form.sort_order" type="number" min="0" class="form-control" />
                </div>
                <div class="col-12">
                  <div class="form-check">
                    <input v-model="form.is_active" type="checkbox" class="form-check-input" id="active" />
                    <label for="active" class="form-check-label">نشط / ظاهر</label>
                  </div>
                </div>
                <div class="col-12">
                  <label class="form-label">نص القصة</label>
                  <textarea v-model="form.story" class="form-control" rows="4" />
                </div>
              </div>
            </div>

            <WebsiteAdminImageSection
              spec-key="success_story"
              title="صورة قصة النجاح"
              hint="تظهر في كاروسيل Stories of Excellence."
              :existing-url="story?.image_src ?? ''"
              :src="form.image_src"
              :alt="form.image_alt"
              @update:image="form.image = $event"
              @update:src="form.image_src = $event"
              @update:alt="form.image_alt = $event"
            />

            <div class="d-flex flex-wrap gap-2">
              <button type="submit" class="btn btn-primary" :disabled="form.processing">حفظ</button>
              <Link :href="route('admin.website.success-stories.index')" class="btn btn-outline-secondary">إلغاء</Link>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
