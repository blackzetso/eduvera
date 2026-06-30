<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import WebsiteSidebar from '@/Pages/Admin/theme1/Website/Partials/Sidebar.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import { route } from 'ziggy-js'
import WebsiteImageUploadField from '@/Components/Admin/Website/WebsiteImageUploadField.vue'

const props = defineProps({ headerChrome: Object, footerChrome: Object, schoolInfo: Object, theme: Object })

const existingLogo = computed(() => props.theme?.logo_path ?? props.schoolInfo?.logo?.src ?? '')
const existingFavicon = computed(() => props.theme?.favicon_path ?? '')

const footer = JSON.parse(JSON.stringify(props.footerChrome || {}))
footer.legal_links = footer.legal_links || []
footer.columns = footer.columns || []
footer.newsletter = footer.newsletter || { enabled: false }

const form = useForm({
  header_chrome: JSON.parse(JSON.stringify(props.headerChrome || {})),
  footer_chrome: footer,
  school_info: { name: props.schoolInfo?.name, tagline: props.schoolInfo?.tagline, topBar: { ...props.schoolInfo?.topBar } },
  theme: { ...(props.theme || {}) },
  logo: null,
  favicon: null,
})

function isBadHref(href) {
  const h = String(href || '').trim()
  return h === '' || h === '#'
}

const linkWarnings = computed(() => {
  const warnings = []
  for (const col of form.footer_chrome.columns || []) {
    for (const link of col.links || []) {
      if (isBadHref(link.href)) {
        warnings.push(`عمود «${col.title}»: الرابط «${link.label}» يحتاج URL صالحاً (ليس #).`)
      }
    }
  }
  for (const link of form.footer_chrome.legal_links || []) {
    if (isBadHref(link.href)) {
      warnings.push(`رابط قانوني «${link.label}» يحتاج URL صالحاً.`)
    }
  }
  for (const cta of form.header_chrome.header_ctas || []) {
    if (isBadHref(cta.href)) {
      warnings.push(`زر الرأس «${cta.label}» يحتاج URL صالحاً.`)
    }
  }
  if (isBadHref(form.header_chrome.login?.href)) {
    warnings.push('رابط تسجيل الدخول يحتاج URL صالحاً.')
  }
  return warnings
})

function addFooterColumn() {
  form.footer_chrome.columns = form.footer_chrome.columns || []
  form.footer_chrome.columns.push({ title: 'New Column', links: [{ label: 'Link', href: '#contact' }] })
}

function addHeaderCta() {
  form.header_chrome.header_ctas = form.header_chrome.header_ctas || []
  form.header_chrome.header_ctas.push({ id: 'cta-' + Date.now(), label: 'Button', href: '#visit', variant: 'outline' })
}

function addLegalLink() {
  form.footer_chrome.legal_links = form.footer_chrome.legal_links || []
  form.footer_chrome.legal_links.push({ label: 'Link', href: '/page' })
}

function submit() {
  form.post(route('admin.website.chrome.update'), { forceFormData: true })
}
</script>

<template>
  <Head title="الرأس والتذييل" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <WebsiteSidebar />
        <div class="col-lg-9">
          <h1 class="h4 mb-3">الرأس والتذييل</h1>
          <div v-if="linkWarnings.length" class="alert alert-warning">
            <strong>تحذيرات الروابط:</strong>
            <ul class="mb-0 small">
              <li v-for="(w, i) in linkWarnings" :key="i">{{ w }}</li>
            </ul>
          </div>
          <form @submit.prevent="submit" class="vstack gap-3">
            <div class="card card-body">
              <h2 class="h6">الهوية</h2>
              <input v-model="form.school_info.name" class="form-control mb-2" placeholder="اسم المدرسة" />
              <input v-model="form.school_info.tagline" class="form-control mb-2" placeholder="الشعار النصي" />
              <input v-model="form.school_info.topBar.phone" class="form-control mb-2" placeholder="هاتف الشريط العلوي" />
              <input v-model="form.school_info.topBar.email" class="form-control mb-2" placeholder="بريد الشريط العلوي" />
              <WebsiteImageUploadField
                spec-key="logo"
                label="شعار (صورة)"
                :existing-url="existingLogo"
                @update:model-value="form.logo = $event"
              />
              <WebsiteImageUploadField
                spec-key="favicon"
                label="Favicon"
                :existing-url="existingFavicon"
                @update:model-value="form.favicon = $event"
              />
            </div>

            <div class="card card-body">
              <h2 class="h6">شريط الإعلانات</h2>
              <input v-model="form.header_chrome.announcement_badge" class="form-control" placeholder="Badge text" />
            </div>

            <div class="card card-body">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 mb-0">أزرار الرأس</h2>
                <button type="button" class="btn btn-sm btn-outline-primary" @click="addHeaderCta">+</button>
              </div>
              <div v-for="(cta, i) in form.header_chrome.header_ctas" :key="i" class="row g-2 mb-2">
                <div class="col-md-3"><input v-model="cta.id" class="form-control form-control-sm" placeholder="id" /></div>
                <div class="col-md-3"><input v-model="cta.label" class="form-control form-control-sm" /></div>
                <div class="col-md-4">
                  <input v-model="cta.href" class="form-control form-control-sm" :class="{ 'border-warning': isBadHref(cta.href) }" />
                </div>
                <div class="col-md-2">
                  <select v-model="cta.variant" class="form-select form-select-sm">
                    <option value="primary">primary</option>
                    <option value="outline">outline</option>
                  </select>
                </div>
              </div>
              <div class="row g-2">
                <div class="col-md-4"><input v-model="form.header_chrome.login.label" class="form-control form-control-sm" placeholder="Login label" /></div>
                <div class="col-md-4">
                  <input v-model="form.header_chrome.login.href" class="form-control form-control-sm" :class="{ 'border-warning': isBadHref(form.header_chrome.login?.href) }" />
                </div>
              </div>
            </div>

            <div class="card card-body">
              <h2 class="h6">التذييل</h2>
              <textarea v-model="form.footer_chrome.tagline" class="form-control mb-2" rows="2" />
              <input v-model="form.footer_chrome.copyright" class="form-control mb-3" placeholder="© {year} {school_name}..." />
              <button type="button" class="btn btn-sm btn-outline-secondary mb-2" @click="addFooterColumn">إضافة عمود</button>
              <div v-for="(col, ci) in form.footer_chrome.columns" :key="ci" class="border rounded p-2 mb-2">
                <input v-model="col.title" class="form-control form-control-sm mb-2" />
                <div v-for="(link, li) in col.links" :key="li" class="row g-1 mb-1">
                  <div class="col-5"><input v-model="link.label" class="form-control form-control-sm" /></div>
                  <div class="col-7">
                    <input v-model="link.href" class="form-control form-control-sm" :class="{ 'border-warning': isBadHref(link.href) }" />
                  </div>
                </div>
                <button type="button" class="btn btn-sm btn-link" @click="col.links.push({ label: 'Link', href: '#contact' })">+ رابط</button>
              </div>
              <h3 class="h6 mt-3">روابط قانونية</h3>
              <div v-for="(link, li) in form.footer_chrome.legal_links" :key="'legal-' + li" class="row g-1 mb-1">
                <div class="col-5"><input v-model="link.label" class="form-control form-control-sm" /></div>
                <div class="col-7">
                  <input v-model="link.href" class="form-control form-control-sm" :class="{ 'border-warning': isBadHref(link.href) }" />
                </div>
              </div>
              <button type="button" class="btn btn-sm btn-link" @click="addLegalLink">+ رابط قانوني</button>
              <h3 class="h6 mt-3">النشرة البريدية</h3>
              <div class="form-check form-switch mb-2">
                <input v-model="form.footer_chrome.newsletter.enabled" class="form-check-input" type="checkbox" id="nl" />
                <label class="form-check-label" for="nl">مفعّلة</label>
              </div>
              <input v-model="form.footer_chrome.newsletter.title" class="form-control form-control-sm mb-1" />
              <input v-model="form.footer_chrome.newsletter.description" class="form-control form-control-sm mb-1" />
              <input v-model="form.footer_chrome.newsletter.placeholder" class="form-control form-control-sm mb-1" />
              <input v-model="form.footer_chrome.newsletter.button_label" class="form-control form-control-sm mb-1" />
              <input v-model="form.footer_chrome.newsletter.submit_url" class="form-control form-control-sm" placeholder="Submit URL (optional)" />
            </div>

            <button type="submit" class="btn btn-primary" :disabled="form.processing">حفظ</button>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
