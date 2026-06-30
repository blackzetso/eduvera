<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import WebsiteSidebar from '@/Pages/Admin/theme1/Website/Partials/Sidebar.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import WebsiteImageUrlField from '@/Components/Admin/Website/WebsiteImageUrlField.vue'
import WebsiteLandingSectionContentHint from '@/Components/Admin/Website/WebsiteLandingSectionContentHint.vue'

const props = defineProps({
  editing: Object,
  customSubtypes: Object,
})

const form = useForm({
  admin_name: props.editing?.admin_name ?? '',
  anchor_id: props.editing?.anchor_id ?? '',
  is_enabled: props.editing?.is_enabled ?? true,
  is_visible: props.editing?.is_visible ?? true,
  show_desktop: props.editing?.show_desktop ?? true,
  show_tablet: props.editing?.show_tablet ?? true,
  show_mobile: props.editing?.show_mobile ?? true,
  scheduled_starts_at: props.editing?.scheduled_starts_at?.slice(0, 16) ?? '',
  scheduled_ends_at: props.editing?.scheduled_ends_at?.slice(0, 16) ?? '',
  settings: {
    title: props.editing?.settings?.title ?? '',
    subtitle: props.editing?.settings?.subtitle ?? '',
    eyebrow: props.editing?.settings?.eyebrow ?? '',
    background_color: props.editing?.settings?.background_color ?? '',
    background_image_url: props.editing?.settings?.background_image_url ?? '',
    padding_top: props.editing?.settings?.padding_top ?? '',
    padding_bottom: props.editing?.settings?.padding_bottom ?? '',
    animation: props.editing?.settings?.animation ?? 'reveal',
    ctas: props.editing?.settings?.ctas ?? [],
  },
  content: {
    ...(props.editing?.content ?? {}),
  },
})

function submit() {
  form.put(route('admin.website.landing-builder.sections.update', props.editing.id))
}
</script>

<template>
  <Head :title="`إعدادات: ${editing?.admin_name}`" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <WebsiteSidebar />
        <div class="col-lg-9">
          <div class="mb-3">
            <Link :href="route('admin.website.landing-builder.index')" class="btn btn-sm btn-link">← العودة للمنشئ</Link>
          </div>
          <h1 class="h4 mb-1">{{ editing?.admin_name }}</h1>
          <p class="text-muted small">{{ editing?.library_label }} ({{ editing?.block_type }})</p>

          <WebsiteLandingSectionContentHint :block-type="editing?.block_type" />
          <div class="alert alert-info small mb-3">
            <strong>ملاحظة:</strong> معاينة «Usage &amp; crop preview» أسفل حقل الصورة <strong>إرشادية فقط</strong> (تُظهر كيف قد يُقصّ المحتوى على الجوال/الديسكتوب) — لا تُغيّر الملف ولا تُطبَّق وحدها على الموقع.
            بعد التعديل اضغط <strong>حفظ الإعدادات</strong>، ثم من <Link :href="route('admin.website.landing-builder.index')">منشئ الصفحة</Link> تأكد أن الصفحة <strong>منشورة</strong> والقسم <strong>مفعّل وظاهر</strong>.
            بعض الأقسام (مثل Hero) تستخدم أيضاً صورة من <Link :href="route('admin.website.hero')">إعدادات Hero</Link> إذا لم تُحدَّد خلفية هنا.
          </div>

          <form @submit.prevent="submit" class="mt-4">
            <div class="card mb-3">
              <div class="card-header">عام</div>
              <div class="card-body row g-3">
                <div class="col-md-6">
                  <label class="form-label">الاسم الداخلي</label>
                  <input v-model="form.admin_name" type="text" class="form-control" required />
                </div>
                <div class="col-md-6">
                  <label class="form-label">معرّف الربط (anchor)</label>
                  <input v-model="form.anchor_id" type="text" class="form-control" placeholder="about" />
                </div>
                <div class="col-md-4">
                  <div class="form-check form-switch">
                    <input v-model="form.is_enabled" class="form-check-input" type="checkbox" id="en" />
                    <label class="form-check-label" for="en">مفعّل</label>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-check form-switch">
                    <input v-model="form.is_visible" class="form-check-input" type="checkbox" id="vis" />
                    <label class="form-check-label" for="vis">ظاهر</label>
                  </div>
                </div>
              </div>
            </div>

            <div class="card mb-3">
              <div class="card-header">إعدادات العرض</div>
              <div class="card-body row g-3">
                <div class="col-md-4">
                  <label class="form-label">العنوان</label>
                  <input v-model="form.settings.title" type="text" class="form-control" />
                </div>
                <div class="col-md-4">
                  <label class="form-label">العنوان الفرعي</label>
                  <input v-model="form.settings.subtitle" type="text" class="form-control" />
                </div>
                <div class="col-md-4">
                  <label class="form-label">Eyebrow</label>
                  <input v-model="form.settings.eyebrow" type="text" class="form-control" />
                </div>
                <div class="col-md-4">
                  <label class="form-label">لون الخلفية</label>
                  <input v-model="form.settings.background_color" type="text" class="form-control" placeholder="#F8FAFC" />
                </div>
                <div class="col-md-12">
                  <WebsiteImageUrlField
                    v-model="form.settings.background_image_url"
                    spec-key="landing_section_background"
                    label="صورة الخلفية (URL)"
                    :hint="'انسخ الرابط من مكتبة الوسائط بعد الرفع'"
                  />
                  <div class="form-text">
                    <Link :href="route('admin.website.media.index')" target="_blank">مكتبة الوسائط</Link>
                  </div>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Padding top</label>
                  <input v-model="form.settings.padding_top" type="text" class="form-control" placeholder="4rem" />
                </div>
                <div class="col-md-3">
                  <label class="form-label">Padding bottom</label>
                  <input v-model="form.settings.padding_bottom" type="text" class="form-control" />
                </div>
                <div class="col-md-3">
                  <label class="form-label">Animation</label>
                  <select v-model="form.settings.animation" class="form-select">
                    <option value="reveal">Reveal</option>
                    <option value="none">None</option>
                  </select>
                </div>
              </div>
            </div>

            <div class="card mb-3">
              <div class="card-header">الظهور حسب الجهاز</div>
              <div class="card-body d-flex gap-4">
                <div class="form-check form-switch">
                  <input v-model="form.show_desktop" class="form-check-input" type="checkbox" id="d" />
                  <label class="form-check-label" for="d">Desktop</label>
                </div>
                <div class="form-check form-switch">
                  <input v-model="form.show_tablet" class="form-check-input" type="checkbox" id="t" />
                  <label class="form-check-label" for="t">Tablet</label>
                </div>
                <div class="form-check form-switch">
                  <input v-model="form.show_mobile" class="form-check-input" type="checkbox" id="m" />
                  <label class="form-check-label" for="m">Mobile</label>
                </div>
              </div>
            </div>

            <div class="card mb-3">
              <div class="card-header">الجدولة</div>
              <div class="card-body row g-3">
                <div class="col-md-6">
                  <label class="form-label">يبدأ في</label>
                  <input v-model="form.scheduled_starts_at" type="datetime-local" class="form-control" />
                </div>
                <div class="col-md-6">
                  <label class="form-label">ينتهي في</label>
                  <input v-model="form.scheduled_ends_at" type="datetime-local" class="form-control" />
                </div>
              </div>
            </div>

            <div v-if="editing?.block_type === 'testimonials'" class="card mb-3">
              <div class="card-header">تصفية الآراء (للنسخ المكررة)</div>
              <div class="card-body">
                <label class="form-label">أدوار (مفصولة بفاصلة) — مثال: Parent, Student, Alumni</label>
                <input
                  :value="(form.content.roles || []).join(', ')"
                  type="text"
                  class="form-control"
                  @input="form.content.roles = $event.target.value.split(',').map((s) => s.trim()).filter(Boolean)"
                />
              </div>
            </div>

            <div v-if="editing?.block_type === 'custom'" class="card mb-3">
              <div class="card-header">محتوى مخصص</div>
              <div class="card-body row g-3">
                <div class="col-md-4">
                  <label class="form-label">النوع</label>
                  <select v-model="form.content.subtype" class="form-select">
                    <option v-for="(label, key) in customSubtypes" :key="key" :value="key">{{ label }}</option>
                  </select>
                </div>
                <div class="col-12">
                  <label class="form-label">نص / HTML</label>
                  <textarea v-model="form.content.body" class="form-control" rows="5"></textarea>
                </div>
                <div class="col-12" v-if="form.content.subtype === 'video'">
                  <label class="form-label">رابط الفيديو (embed)</label>
                  <input v-model="form.content.video_url" type="url" class="form-control" />
                </div>
              </div>
            </div>

            <button type="submit" class="btn btn-primary" :disabled="form.processing">حفظ الإعدادات</button>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
