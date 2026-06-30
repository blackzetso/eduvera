<script setup>
import { computed } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import WebsiteSidebar from '@/Pages/Admin/theme1/Website/Partials/Sidebar.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({ career: { type: Object, default: null } })

const form = useForm({
  title: props.career?.title ?? '',
  department: props.career?.department ?? '',
  type: props.career?.type ?? 'teacher',
  description: props.career?.description ?? '',
  apply_url: props.career?.apply_url ?? '',
  is_active: props.career?.is_active ?? true,
  sort_order: props.career?.sort_order ?? 0,
})

const pageTitle = computed(() => (props.career ? 'تعديل وظيفة' : 'وظيفة جديدة'))

function submit() {
  const options = { preserveScroll: true }
  if (props.career) {
    form.put(route('admin.website.careers.update', props.career.id), options)
  } else {
    form.post(route('admin.website.careers.store'), options)
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
            <Link :href="route('admin.website.careers.index')" class="btn btn-sm btn-link px-0 text-muted">← العودة إلى الوظائف</Link>
            <h1 class="h4 mb-0">{{ pageTitle }}</h1>
          </div>

          <form @submit.prevent="submit" class="vstack gap-3">
            <div class="card card-body">
              <h2 class="h6 mb-3">تفاصيل الوظيفة</h2>
              <div class="row g-3">
                <div class="col-md-8">
                  <label class="form-label">المسمى الوظيفي <span class="text-danger">*</span></label>
                  <input v-model="form.title" class="form-control" required placeholder="Secondary Mathematics Teacher" />
                </div>
                <div class="col-md-4">
                  <label class="form-label">القسم</label>
                  <input v-model="form.department" class="form-control" placeholder="Secondary" />
                </div>
                <div class="col-md-4">
                  <label class="form-label">النوع</label>
                  <select v-model="form.type" class="form-select">
                    <option value="teacher">معلم</option>
                    <option value="staff">موظف إداري</option>
                    <option value="leadership">قيادة</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">ترتيب العرض</label>
                  <input v-model.number="form.sort_order" type="number" min="0" class="form-control" />
                </div>
                <div class="col-md-4 d-flex align-items-end">
                  <div class="form-check">
                    <input v-model="form.is_active" type="checkbox" class="form-check-input" id="active" />
                    <label for="active" class="form-check-label">نشط / ظاهر</label>
                  </div>
                </div>
                <div class="col-12">
                  <label class="form-label">الوصف</label>
                  <textarea v-model="form.description" class="form-control" rows="4" />
                </div>
                <div class="col-12">
                  <label class="form-label">رابط التقديم (اختياري)</label>
                  <input v-model="form.apply_url" class="form-control" placeholder="mailto:careers@school.com أو https://..." />
                  <div class="form-text">إن تُرك فارغاً يُستخدم بريد التوظيف من إعدادات المدرسة.</div>
                </div>
              </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
              <button type="submit" class="btn btn-primary" :disabled="form.processing">حفظ</button>
              <Link :href="route('admin.website.careers.index')" class="btn btn-outline-secondary">إلغاء</Link>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
