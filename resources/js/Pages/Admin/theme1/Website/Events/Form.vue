<script setup>
import { computed } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import WebsiteSidebar from '@/Pages/Admin/theme1/Website/Partials/Sidebar.vue'
import WebsiteAdminImageSection from '@/Components/Admin/Website/WebsiteAdminImageSection.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({ event: { type: Object, default: null } })

const form = useForm({
  title: props.event?.title ?? '',
  slug: props.event?.slug ?? '',
  type: props.event?.type ?? 'Open Day',
  date: props.event?.date ?? '',
  date_short: props.event?.date_short ?? '',
  audience: props.event?.audience ?? '',
  location: props.event?.location ?? '',
  description: props.event?.description ?? '',
  cta: props.event?.cta ?? 'Register',
  href: props.event?.href ?? '#visit',
  is_open_day: props.event?.is_open_day ?? false,
  limited_seats_label: props.event?.limited_seats_label ?? '',
  is_active: props.event?.is_active ?? true,
  sort_order: props.event?.sort_order ?? 0,
  image_src: props.event?.image_src ?? '',
  image_alt: props.event?.image_alt ?? '',
  image: null,
})

const pageTitle = computed(() => (props.event ? 'تعديل فعالية' : 'فعالية جديدة'))

function submit() {
  const options = { forceFormData: true, preserveScroll: true }
  if (props.event) {
    form.post(route('admin.website.events.update', props.event.id), { ...options, _method: 'put' })
  } else {
    form.post(route('admin.website.events.store'), options)
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
            <Link :href="route('admin.website.events.index')" class="btn btn-sm btn-link px-0 text-muted">← العودة إلى الفعاليات</Link>
            <h1 class="h4 mb-0">{{ pageTitle }}</h1>
          </div>

          <form @submit.prevent="submit" class="vstack gap-3">
            <div class="card card-body">
              <h2 class="h6 mb-3">المعلومات الأساسية</h2>
              <div class="row g-3">
                <div class="col-md-8">
                  <label class="form-label">العنوان <span class="text-danger">*</span></label>
                  <input v-model="form.title" class="form-control" required />
                </div>
                <div class="col-md-4">
                  <label class="form-label">النوع</label>
                  <input v-model="form.type" class="form-control" placeholder="Open Day" list="event-types" />
                  <datalist id="event-types">
                    <option value="Open Day" />
                    <option value="Workshop" />
                    <option value="Competition" />
                    <option value="Trip" />
                  </datalist>
                </div>
                <div class="col-md-6">
                  <label class="form-label">الرابط (Slug)</label>
                  <input v-model="form.slug" class="form-control" placeholder="open-day-2026" />
                </div>
                <div class="col-md-3">
                  <label class="form-label">التاريخ</label>
                  <input v-model="form.date" class="form-control" placeholder="Mar 15, 2026" />
                </div>
                <div class="col-md-3">
                  <label class="form-label">تاريخ مختصر</label>
                  <input v-model="form.date_short" class="form-control" placeholder="Mar 15" />
                </div>
                <div class="col-md-6">
                  <label class="form-label">الجمهور</label>
                  <input v-model="form.audience" class="form-control" placeholder="Prospective families" />
                </div>
                <div class="col-md-6">
                  <label class="form-label">المكان</label>
                  <input v-model="form.location" class="form-control" placeholder="Main Campus" />
                </div>
                <div class="col-md-4">
                  <label class="form-label">ترتيب العرض</label>
                  <input v-model.number="form.sort_order" type="number" min="0" class="form-control" />
                </div>
                <div class="col-md-8 d-flex align-items-end flex-wrap gap-3">
                  <div class="form-check">
                    <input v-model="form.is_open_day" type="checkbox" class="form-check-input" id="open-day" />
                    <label for="open-day" class="form-check-label">يوم مفتوح (Open Day)</label>
                  </div>
                  <div class="form-check">
                    <input v-model="form.is_active" type="checkbox" class="form-check-input" id="active" />
                    <label for="active" class="form-check-label">نشط / ظاهر</label>
                  </div>
                </div>
                <div v-if="form.is_open_day" class="col-12">
                  <label class="form-label">نص المقاعد المحدودة</label>
                  <input v-model="form.limited_seats_label" class="form-control" placeholder="Limited Seats Available" />
                </div>
                <div class="col-12">
                  <label class="form-label">وصف (اختياري)</label>
                  <textarea v-model="form.description" class="form-control" rows="3" />
                </div>
              </div>
            </div>

            <div class="card card-body">
              <h2 class="h6 mb-3">زر الإجراء</h2>
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label">نص الزر</label>
                  <input v-model="form.cta" class="form-control" placeholder="Register Now" />
                </div>
                <div class="col-md-8">
                  <label class="form-label">رابط الزر</label>
                  <input v-model="form.href" class="form-control" placeholder="#visit أو https://..." />
                </div>
              </div>
            </div>

            <WebsiteAdminImageSection
              spec-key="event_image"
              title="صورة الفعالية"
              hint="تظهر في كاروسيل Events & Activities على الصفحة الرئيسية."
              :existing-url="event?.image_src ?? ''"
              :src="form.image_src"
              :alt="form.image_alt"
              @update:image="form.image = $event"
              @update:src="form.image_src = $event"
              @update:alt="form.image_alt = $event"
            />

            <div class="d-flex flex-wrap gap-2">
              <button type="submit" class="btn btn-primary" :disabled="form.processing">حفظ</button>
              <Link :href="route('admin.website.events.index')" class="btn btn-outline-secondary">إلغاء</Link>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
