<script setup>
import { computed } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import WebsiteSidebar from '@/Pages/Admin/theme1/Website/Partials/Sidebar.vue'
import WebsiteImageUploadField from '@/Components/Admin/Website/WebsiteImageUploadField.vue'
import WebsiteImageUrlField from '@/Components/Admin/Website/WebsiteImageUrlField.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({ careers: Array, teacherRecruitment: Object })

const recruitment = computed(() => props.teacherRecruitment ?? {})

const blockForm = useForm({
  teacherRecruitment: JSON.parse(JSON.stringify(props.teacherRecruitment || {
    eyebrow: 'Careers',
    title: '',
    intro: '',
    image: { src: '', alt: '' },
    benefits: [],
    applyLabel: 'Apply as Teacher',
    positionsLabel: 'View Open Positions',
  })),
  recruitment_image: null,
})

if (!blockForm.teacherRecruitment.image) {
  blockForm.teacherRecruitment.image = { src: '', alt: '' }
}

const benefitsText = computed({
  get: () => (blockForm.teacherRecruitment.benefits || []).join('\n'),
  set: (v) => {
    blockForm.teacherRecruitment.benefits = String(v || '').split('\n').map((s) => s.trim()).filter(Boolean)
  },
})

function saveBlock() {
  blockForm.post(route('admin.website.careers.recruitment'), { forceFormData: true, _method: 'put', preserveScroll: true })
}

function destroy(id) {
  if (confirm('حذف؟')) router.delete(route('admin.website.careers.destroy', id))
}
</script>

<template>
  <Head title="الوظائف" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <WebsiteSidebar />
        <div class="col-lg-9">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 mb-0">الوظائف</h1>
            <Link :href="route('admin.website.careers.create')" class="btn btn-success-soft btn-round" title="إضافة وظيفة">
              <i class="bi bi-plus" />
            </Link>
          </div>

          <div class="card card-body mb-4">
            <h2 class="h6 mb-3">الوظائف المفتوحة (قائمة)</h2>
            <p class="text-muted small">تظهر في قسم التوظيف كـ «Open positions».</p>
            <div class="table-responsive eduvera-table-wrap">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>المسمى</th>
                  <th>القسم</th>
                  <th class="text-end">إجراءات</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="c in careers" :key="c.id">
                  <td>{{ c.title }}</td>
                  <td class="text-muted">{{ c.department || '—' }}</td>
                  <td class="text-end">
                    <Link :href="route('admin.website.careers.edit', c.id)" class="btn btn-sm btn-light">تعديل</Link>
                    <button type="button" class="btn btn-sm btn-danger-soft ms-1" @click="destroy(c.id)">حذف</button>
                  </td>
                </tr>
                <tr v-if="!careers?.length">
                  <td colspan="3" class="text-muted text-center">لا توجد وظائف بعد.</td>
                </tr>
              </tbody>
            </table>
            </div>
          </div>

          <form class="card card-body vstack gap-3" @submit.prevent="saveBlock">
            <h2 class="h6 mb-0">قسم التوظيف (Become a Teacher)</h2>
            <p class="text-muted small mb-0">النص والصورة في قسم Careers على الصفحة الرئيسية.</p>

            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Eyebrow</label>
                <input v-model="blockForm.teacherRecruitment.eyebrow" class="form-control" />
              </div>
              <div class="col-md-8">
                <label class="form-label">العنوان</label>
                <input v-model="blockForm.teacherRecruitment.title" class="form-control" />
              </div>
              <div class="col-12">
                <label class="form-label">المقدمة</label>
                <textarea v-model="blockForm.teacherRecruitment.intro" class="form-control" rows="3" />
              </div>
              <div class="col-md-6">
                <label class="form-label">نص زر التقديم</label>
                <input v-model="blockForm.teacherRecruitment.applyLabel" class="form-control" />
              </div>
              <div class="col-md-6">
                <label class="form-label">نص زر الوظائف</label>
                <input v-model="blockForm.teacherRecruitment.positionsLabel" class="form-control" />
              </div>
              <div class="col-12">
                <label class="form-label">المزايا (سطر لكل بند)</label>
                <textarea v-model="benefitsText" class="form-control" rows="4" />
              </div>
            </div>

            <div>
              <label class="form-label">نص بديل للصورة</label>
              <input v-model="blockForm.teacherRecruitment.image.alt" class="form-control mb-3" />
              <WebsiteImageUploadField
                spec-key="teacher_photo"
                label="رفع صورة جديدة"
                :existing-url="recruitment?.image?.src ?? ''"
                @update:model-value="blockForm.recruitment_image = $event"
              />
              <div class="text-center text-muted small my-2">— أو —</div>
              <WebsiteImageUrlField
                spec-key="teacher_photo"
                v-model="blockForm.teacherRecruitment.image.src"
                label="رابط صورة (URL)"
              />
            </div>

            <button type="submit" class="btn btn-primary align-self-start" :disabled="blockForm.processing">حفظ قسم التوظيف</button>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
