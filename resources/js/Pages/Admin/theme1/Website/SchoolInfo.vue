<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import WebsiteSidebar from '@/Pages/Admin/theme1/Website/Partials/Sidebar.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { computed } from 'vue'
import WebsiteImageUploadField from '@/Components/Admin/Website/WebsiteImageUploadField.vue'

const props = defineProps({ schoolInfo: Object })

function ensureSchoolInfo(info) {
  const copy = JSON.parse(JSON.stringify(info || {}))
  copy.about = {
    eyebrow: '',
    title: '',
    intro: '',
    mission: '',
    vision: '',
    image: { src: '', alt: '' },
    ...(copy.about || {}),
  }
  copy.principal = {
    eyebrow: '',
    title: '',
    message: '',
    image: { src: '', alt: '' },
    ...(copy.principal || {}),
  }
  copy.finalCta = {
    headline: '',
    subheadline: '',
    ...(copy.finalCta || {}),
  }
  return copy
}

const form = useForm({
  schoolInfo: ensureSchoolInfo(props.schoolInfo),
  logo: null,
  about_image: null,
  principal_image: null,
})

const existingLogo = computed(() => props.schoolInfo?.logo?.src ?? '')
const existingAbout = computed(() => props.schoolInfo?.about?.image?.src ?? '')
const existingPrincipal = computed(() => props.schoolInfo?.principal?.image?.src ?? '')

function submit() {
  form.post(route('admin.website.school-info.update'), { forceFormData: true })
}
</script>

<template>
  <Head title="معلومات المدرسة" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <WebsiteSidebar />
        <div class="col-lg-9">
          <h1 class="h4 mb-3">معلومات المدرسة</h1>
          <form @submit.prevent="submit" class="vstack gap-3">
            <div class="card card-body">
              <input v-model="form.schoolInfo.name" class="form-control mb-2" placeholder="اسم المدرسة" />
              <input v-model="form.schoolInfo.tagline" class="form-control mb-2" placeholder="الشعار" />
              <WebsiteImageUploadField
                spec-key="logo"
                label="الشعار (صورة)"
                :existing-url="existingLogo"
                @update:model-value="form.logo = $event"
              />
            </div>
            <div class="card border-secondary">
              <div class="card-header bg-secondary bg-opacity-10">
                <h2 class="h6 mb-0">عن المدرسة — About</h2>
              </div>
              <div class="card-body">
                <div class="mb-3">
                  <label class="form-label fw-semibold">① التسمية العلوية (About School Talent)</label>
                  <input
                    v-model="form.schoolInfo.about.eyebrow"
                    type="text"
                    class="form-control"
                    placeholder="مثال: About School Talent"
                  />
                </div>
                <div class="mb-3">
                  <label class="form-label fw-semibold">② عنوان القسم</label>
                  <input v-model="form.schoolInfo.about.title" type="text" class="form-control" placeholder="مثال: A Legacy of Excellence" />
                </div>
                <div class="mb-3">
                  <label class="form-label fw-semibold">③ المقدمة</label>
                  <textarea v-model="form.schoolInfo.about.intro" class="form-control" rows="3" />
                </div>
                <div class="mb-3">
                  <label class="form-label fw-semibold">④ الرسالة والرؤية</label>
                  <input v-model="form.schoolInfo.about.mission" class="form-control mb-2" placeholder="الرسالة" />
                  <input v-model="form.schoolInfo.about.vision" class="form-control" placeholder="الرؤية" />
                </div>
                <WebsiteImageUploadField
                  spec-key="about_image"
                  label="⑤ صورة قسم عن المدرسة"
                  :existing-url="existingAbout"
                  @update:model-value="form.about_image = $event"
                />
              </div>
            </div>
            <div class="card border-primary">
              <div class="card-header bg-primary bg-opacity-10">
                <h2 class="h6 mb-0">كلمة المدير — Principal Message</h2>
              </div>
              <div class="card-body">
                <p class="small text-muted mb-3">
                  يطابق القسم الظاهر في الصفحة الرئيسية (Leadership + العنوان + النص + الصورة).
                  للإظهار/الإخفاء:
                  <a :href="route('admin.website.landing-builder.index')">منشئ الصفحة</a>.
                </p>
                <div class="mb-3">
                  <label class="form-label fw-semibold">① التسمية العلوية (Leadership)</label>
                  <input
                    v-model="form.schoolInfo.principal.eyebrow"
                    type="text"
                    class="form-control"
                    placeholder="مثال: Leadership"
                  />
                </div>
                <div class="mb-3">
                  <label class="form-label fw-semibold">② عنوان القسم (A Message from the Principal)</label>
                  <input
                    v-model="form.schoolInfo.principal.title"
                    type="text"
                    class="form-control"
                    placeholder="مثال: A Message from the Principal"
                  />
                  <div class="form-text">هذا هو العنوان الكبير تحت التسمية العلوية مباشرة.</div>
                </div>
                <div class="mb-3">
                  <label class="form-label fw-semibold">③ نص الرسالة (الفقرة)</label>
                  <textarea
                    v-model="form.schoolInfo.principal.message"
                    class="form-control"
                    rows="6"
                    placeholder="نص ترحيب المدير..."
                  />
                </div>
                <WebsiteImageUploadField
                  spec-key="principal_image"
                  label="④ صورة المدير"
                  :existing-url="existingPrincipal"
                  @update:model-value="form.principal_image = $event"
                />
              </div>
            </div>
            <div class="card border-success">
              <div class="card-header bg-success bg-opacity-10">
                <h2 class="h6 mb-0">الدعوة الختامية — Final CTA</h2>
              </div>
              <div class="card-body">
                <p class="small text-muted mb-3">
                  النص الظاهر في الشريط الأزرق قبل التذييل (Give Your Child a World-Class Education).
                </p>
                <div class="mb-3">
                  <label class="form-label fw-semibold">① العنوان الرئيسي</label>
                  <input
                    v-model="form.schoolInfo.finalCta.headline"
                    type="text"
                    class="form-control"
                    placeholder="مثال: Give Your Child a World-Class Education"
                  />
                </div>
                <div class="mb-3">
                  <label class="form-label fw-semibold">② النص الفرعي</label>
                  <textarea
                    v-model="form.schoolInfo.finalCta.subheadline"
                    class="form-control"
                    rows="3"
                    placeholder="فقرة قصيرة تحت العنوان..."
                  />
                </div>
              </div>
            </div>
            <button type="submit" class="btn btn-primary" :disabled="form.processing">حفظ</button>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
