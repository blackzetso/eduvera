<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import WebsiteSidebar from '@/Pages/Admin/theme1/Website/Partials/Sidebar.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { computed, ref, watch } from 'vue'
import WebsiteImageUploadField from '@/Components/Admin/Website/WebsiteImageUploadField.vue'
import WebsiteImageGuidance from '@/Components/Admin/Website/WebsiteImageGuidance.vue'
import WebsiteImageUsagePreviewPanel from '@/Components/Admin/Website/WebsiteImageUsagePreviewPanel.vue'
import { analyzeImageUrl } from '@/utils/imageDimensionCheck'

const props = defineProps({ stage: Object })

const payload = props.stage?.payload ?? {}

const form = useForm({
  slug: props.stage?.slug ?? '',
  title: props.stage?.title ?? '',
  subtitle: props.stage?.subtitle ?? '',
  age_range: props.stage?.age_range ?? '',
  tagline: props.stage?.tagline ?? '',
  tone: props.stage?.tone ?? '',
  student_count: props.stage?.student_count ?? null,
  class_size: props.stage?.class_size ?? null,
  key_skills: props.stage?.key_skills ?? [],
  is_active: props.stage?.is_active ?? true,
  sort_order: props.stage?.sort_order ?? 0,
  image: null,
  overview: payload.overview ?? '',
  curriculum: payload.curriculum ?? [],
  activities: payload.activities ?? [],
  schedule: payload.schedule ?? [],
  learningOutcomes: payload.learningOutcomes ?? [],
  gallery: payload.gallery ?? [],
  teachers: payload.teachers ?? '',
  parentFaq: payload.parentFaq ?? [],
  admission: payload.admission ?? [],
})

function lineToList(text) {
  return String(text || '').split('\n').map((s) => s.trim()).filter(Boolean)
}

function listToLines(arr) {
  return (arr || []).join('\n')
}

const firstGalleryUrl = computed(() => (form.gallery || [])[0] || '')
const galleryPreviewDims = ref(null)

watch(
  firstGalleryUrl,
  async (url) => {
    if (!url?.trim()) {
      galleryPreviewDims.value = null
      return
    }
    const result = await analyzeImageUrl(url.trim(), 'stage_gallery')
    galleryPreviewDims.value = result.dims
  },
  { immediate: true }
)

function submit() {
  const data = {
    ...form.data(),
    payload: {
      overview: form.overview,
      curriculum: form.curriculum,
      activities: form.activities,
      schedule: form.schedule,
      learningOutcomes: form.learningOutcomes,
      gallery: form.gallery,
      teachers: form.teachers,
      parentFaq: form.parentFaq,
      admission: form.admission,
    },
  }
  if (props.stage) {
    form.transform(() => data).post(route('admin.website.stages.update', props.stage.id), { forceFormData: true, _method: 'put' })
  } else {
    form.transform(() => data).post(route('admin.website.stages.store'), { forceFormData: true })
  }
}
</script>

<template>
  <Head :title="stage ? 'تعديل مرحلة' : 'مرحلة جديدة'" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <WebsiteSidebar />
        <div class="col-lg-9">
          <h1 class="h4 mb-3">{{ stage ? 'تعديل' : 'إضافة' }} مرحلة</h1>
          <form @submit.prevent="submit" class="vstack gap-3">
            <div class="card card-body row g-2">
              <div class="col-md-6"><input v-model="form.slug" class="form-control" placeholder="slug" required /></div>
              <div class="col-md-6"><input v-model="form.title" class="form-control" placeholder="العنوان" required /></div>
              <div class="col-md-6"><input v-model="form.subtitle" class="form-control" /></div>
              <div class="col-md-6"><input v-model="form.tagline" class="form-control" /></div>
              <div class="col-12">
                <WebsiteImageUploadField
                  spec-key="stage_card"
                  label="صورة المرحلة"
                  :existing-url="stage?.image_src ?? ''"
                  @update:model-value="form.image = $event"
                />
              </div>
            </div>
            <div class="card card-body">
              <h2 class="h6">نظرة عامة</h2>
              <textarea v-model="form.overview" class="form-control" rows="4" />
            </div>
            <div class="card card-body">
              <h2 class="h6">المنهج (سطر لكل بند)</h2>
              <textarea :value="listToLines(form.curriculum)" class="form-control" rows="4" @input="form.curriculum = lineToList($event.target.value)" />
            </div>
            <div class="card card-body">
              <h2 class="h6">الأنشطة</h2>
              <textarea :value="listToLines(form.activities)" class="form-control" rows="4" @input="form.activities = lineToList($event.target.value)" />
            </div>
            <div class="card card-body">
              <h2 class="h6">الجدول اليومي (وقت | نشاط — سطر لكل صف: 08:00 | Assembly)</h2>
              <textarea
                :value="(form.schedule || []).map((r) => `${r.time} | ${r.activity}`).join('\n')"
                class="form-control"
                rows="5"
                @input="form.schedule = String($event.target.value).split('\n').map((line) => { const p = line.split('|'); return { time: (p[0] || '').trim(), activity: (p[1] || '').trim() } }).filter((r) => r.time || r.activity)"
              />
            </div>
            <div class="card card-body">
              <h2 class="h6">معرض الصور (رابط لكل سطر)</h2>
              <WebsiteImageGuidance spec-key="stage_gallery" class="mb-2" />
              <textarea :value="listToLines(form.gallery)" class="form-control" rows="4" @input="form.gallery = lineToList($event.target.value)" />
              <WebsiteImageUsagePreviewPanel
                v-if="firstGalleryUrl"
                :image-url="firstGalleryUrl"
                spec-key="stage_gallery"
                :image-dims="galleryPreviewDims"
              />
              <p v-if="(form.gallery || []).length > 1" class="small text-muted mt-2 mb-0">
                Crop preview uses the first gallery URL only.
              </p>
            </div>
            <div class="card card-body">
              <h2 class="h6">نتائج التعلم</h2>
              <textarea :value="listToLines(form.learningOutcomes)" class="form-control" rows="4" @input="form.learningOutcomes = lineToList($event.target.value)" />
            </div>
            <div class="card card-body">
              <h2 class="h6">متطلبات القبول</h2>
              <textarea :value="listToLines(form.admission)" class="form-control" rows="4" @input="form.admission = lineToList($event.target.value)" />
            </div>
            <div class="card card-body">
              <h2 class="h6">المعلمون (نص)</h2>
              <textarea v-model="form.teachers" class="form-control" rows="3" />
            </div>
            <div class="card card-body">
              <h2 class="h6">FAQ للأهل</h2>
              <div v-for="(f, i) in form.parentFaq" :key="i" class="mb-2 border-bottom pb-2">
                <input v-model="f.q" class="form-control form-control-sm mb-1" placeholder="سؤال" />
                <textarea v-model="f.a" class="form-control form-control-sm" rows="2" />
              </div>
              <button type="button" class="btn btn-sm btn-outline-primary" @click="form.parentFaq.push({ q: '', a: '' })">+ سؤال</button>
            </div>
            <button type="submit" class="btn btn-primary" :disabled="form.processing">حفظ</button>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
