<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import WebsiteSidebar from '@/Pages/Admin/theme1/Website/Partials/Sidebar.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({ ctaLibrary: Array, sectionCtas: Object, sectionKeys: Array })

const form = useForm({
  cta_library: JSON.parse(JSON.stringify(props.ctaLibrary || [])),
  section_ctas: JSON.parse(JSON.stringify(props.sectionCtas || {})),
})

function addCta() {
  form.cta_library.push({ id: 'cta-' + Date.now(), label: 'New CTA', href: '#visit', variant: 'outline' })
}

function submit() {
  form.put(route('admin.website.ctas.update'))
}
</script>

<template>
  <Head title="مكتبة الأزرار" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <WebsiteSidebar />
        <div class="col-lg-9">
          <h1 class="h4 mb-3">مكتبة الأزرار (CTA)</h1>
          <form @submit.prevent="submit">
            <div class="card card-body mb-3">
              <div class="d-flex justify-content-between mb-2">
                <h2 class="h6 mb-0">الأزرار العامة</h2>
                <button type="button" class="btn btn-sm btn-outline-primary" @click="addCta">+</button>
              </div>
              <div v-for="(cta, i) in form.cta_library" :key="i" class="row g-2 mb-2">
                <div class="col-2"><input v-model="cta.id" class="form-control form-control-sm" /></div>
                <div class="col-3"><input v-model="cta.label" class="form-control form-control-sm" /></div>
                <div class="col-4"><input v-model="cta.href" class="form-control form-control-sm" /></div>
                <div class="col-3"><input v-model="cta.variant" class="form-control form-control-sm" /></div>
              </div>
            </div>
            <div v-for="key in sectionKeys" :key="key" class="card card-body mb-2">
              <h2 class="h6">قسم: {{ key }}</h2>
              <input
                :value="(form.section_ctas[key] || []).join(', ')"
                class="form-control form-control-sm"
                placeholder="apply, visit, info"
                @input="form.section_ctas[key] = $event.target.value.split(',').map((s) => s.trim()).filter(Boolean)"
              />
            </div>
            <button type="submit" class="btn btn-primary mt-2" :disabled="form.processing">حفظ</button>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
