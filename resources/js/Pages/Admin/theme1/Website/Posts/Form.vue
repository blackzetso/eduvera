<script setup>
import { computed } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import WebsiteSidebar from '@/Pages/Admin/theme1/Website/Partials/Sidebar.vue'
import WebsiteAdminImageSection from '@/Components/Admin/Website/WebsiteAdminImageSection.vue'
import WebsiteBilingualField from '@/Components/Admin/Website/WebsiteBilingualField.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  post: { type: Object, default: null },
  type: { type: String, default: 'news' },
  publicPreviewUrl: { type: String, default: '' },
})

const categorySuggestions = [
  'Achievement',
  'Success',
  'Parent Guides',
  'Admissions',
  'School News',
  'Community',
  'Academics',
]

const form = useForm({
  type: props.type || props.post?.type || 'news',
  title: props.post?.title ?? '',
  title_ar: props.post?.title_ar ?? '',
  slug: props.post?.slug ?? '',
  category: props.post?.category ?? '',
  published_at: props.post?.published_at ?? '',
  summary: props.post?.summary ?? '',
  summary_ar: props.post?.summary_ar ?? '',
  content: props.post?.content ?? '',
  content_ar: props.post?.content_ar ?? '',
  is_featured: props.post?.is_featured ?? false,
  is_active: props.post?.is_active ?? true,
  sort_order: props.post?.sort_order ?? 0,
  image_src: props.post?.image_src ?? '',
  image_alt: props.post?.image_alt ?? '',
  image: null,
})

const pageTitle = computed(() => (props.post ? 'تعديل مقال' : 'مقال جديد'))
const listLabel = computed(() => (form.type === 'blog' ? 'المدونة' : 'الأخبار'))
const previewUrl = computed(() => props.publicPreviewUrl || '')

function submit() {
  const options = { forceFormData: true, preserveScroll: true }
  if (props.post) {
    form.post(route('admin.website.posts.update', props.post.id), { ...options, _method: 'put' })
  } else {
    form.post(route('admin.website.posts.store'), options)
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
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
              <Link
                :href="route('admin.website.posts.index', { type: form.type })"
                class="btn btn-sm btn-link px-0 text-muted"
              >
                ← العودة إلى {{ listLabel }}
              </Link>
              <h1 class="h4 mb-0">{{ pageTitle }}</h1>
            </div>
            <a
              v-if="previewUrl"
              :href="previewUrl"
              target="_blank"
              rel="noopener noreferrer"
              class="btn btn-sm btn-outline-secondary"
            >
              معاينة على الموقع <i class="bi bi-box-arrow-up-right ms-1"></i>
            </a>
          </div>

          <form @submit.prevent="submit" class="vstack gap-3">
            <div class="card card-body">
              <h2 class="h6 mb-3">المعلومات الأساسية</h2>
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label">النوع</label>
                  <select v-model="form.type" class="form-select" :disabled="!!post">
                    <option value="news">خبر (News)</option>
                    <option value="blog">مدونة (Blog)</option>
                  </select>
                  <div v-if="post" class="form-text">لا يمكن تغيير النوع بعد الإنشاء.</div>
                </div>
                <div class="col-12">
                  <WebsiteBilingualField
                    v-model:en="form.title"
                    v-model:ar="form.title_ar"
                    label="العنوان"
                    en-placeholder="English title"
                    ar-placeholder="العنوان بالعربية"
                    required
                  />
                  <div v-if="form.errors.title" class="text-danger small">{{ form.errors.title }}</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">الرابط (Slug)</label>
                  <input v-model="form.slug" class="form-control" placeholder="student-wellbeing" />
                  <div class="form-text">يُستخدم في رابط المقال على الموقع. اتركه فارغاً ليُولَّد تلقائياً من العنوان.</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">التصنيف (Category)</label>
                  <input v-model="form.category" class="form-control" list="post-categories" placeholder="Parent Guides" />
                  <datalist id="post-categories">
                    <option v-for="c in categorySuggestions" :key="c" :value="c" />
                  </datalist>
                  <div class="form-text">يظهر كشارة ملونة على بطاقة الخبر في الصفحة الرئيسية.</div>
                </div>
                <div class="col-md-4">
                  <label class="form-label">تاريخ النشر</label>
                  <input v-model="form.published_at" class="form-control" placeholder="Jan 20, 2026" />
                </div>
                <div class="col-md-4">
                  <label class="form-label">ترتيب العرض</label>
                  <input v-model.number="form.sort_order" type="number" min="0" class="form-control" />
                  <div class="form-text">الأرقام الأصغر تظهر أولاً.</div>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                  <div class="d-flex flex-wrap gap-3">
                    <div class="form-check">
                      <input v-model="form.is_featured" type="checkbox" class="form-check-input" id="feat" />
                      <label for="feat" class="form-check-label">مقال مميز</label>
                    </div>
                    <div class="form-check">
                      <input v-model="form.is_active" type="checkbox" class="form-check-input" id="active" />
                      <label for="active" class="form-check-label">نشط / ظاهر</label>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="card card-body">
              <h2 class="h6 mb-3">المحتوى</h2>
              <WebsiteBilingualField
                v-model:en="form.summary"
                v-model:ar="form.summary_ar"
                class="mb-3"
                label="الملخص (Excerpt)"
                type="textarea"
                :rows="3"
                en-placeholder="Short excerpt for news cards..."
                ar-placeholder="نبذة قصيرة تظهر في بطاقة الخبر..."
              />
              <WebsiteBilingualField
                v-model:en="form.content"
                v-model:ar="form.content_ar"
                label="المحتوى الكامل"
                type="textarea"
                :rows="10"
                en-placeholder="Full article HTML or text"
                ar-placeholder="المحتوى الكامل بالعربية"
              />
            </div>

            <WebsiteAdminImageSection
              spec-key="news_featured"
              title="صورة المقال"
              hint="تظهر في قسم Latest Stories & Updates على الصفحة الرئيسية."
              :existing-url="post?.image_src ?? ''"
              :src="form.image_src"
              :alt="form.image_alt"
              @update:image="form.image = $event"
              @update:src="form.image_src = $event"
              @update:alt="form.image_alt = $event"
            />

            <div class="d-flex flex-wrap gap-2">
              <button type="submit" class="btn btn-primary" :disabled="form.processing">
                {{ form.processing ? 'جاري الحفظ...' : 'حفظ' }}
              </button>
              <Link :href="route('admin.website.posts.index', { type: form.type })" class="btn btn-outline-secondary">
                إلغاء
              </Link>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
