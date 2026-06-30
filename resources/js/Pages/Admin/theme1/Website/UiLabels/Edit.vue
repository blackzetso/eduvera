<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import WebsiteSidebar from '@/Pages/Admin/theme1/Website/Partials/Sidebar.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import { route } from 'ziggy-js'

const props = defineProps({ uiLabels: Object, fields: Array })

const initial = JSON.parse(JSON.stringify(props.uiLabels || {}))
initial.global = initial.global || {}
initial.cta = initial.cta || {}
initial.hero = initial.hero || {}
initial.hero.trust_avatars = initial.hero.trust_avatars || []

const form = useForm({ uiLabels: initial })

const globalFields = computed(() => props.fields.filter((f) => f.group === 'global'))
const ctaFields = computed(() => props.fields.filter((f) => f.group === 'cta'))

function submit() {
  form.put(route('admin.website.ui-labels.update'))
}

function addAvatar() {
  form.uiLabels.hero.trust_avatars = form.uiLabels.hero.trust_avatars || []
  form.uiLabels.hero.trust_avatars.push({ mode: 'initial', value: '' })
}

function moveAvatar(i, dir) {
  const list = form.uiLabels.hero.trust_avatars
  const j = i + dir
  if (j < 0 || j >= list.length) return
  ;[list[i], list[j]] = [list[j], list[i]]
}
</script>

<template>
  <Head title="تسميات الواجهة" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <WebsiteSidebar />
        <div class="col-lg-9">
          <h1 class="h4 mb-3">تسميات الواجهة (UI Labels)</h1>
          <p class="text-muted small">نصوص الأزرار والعبارات الشائعة على الصفحة الرئيسية. تسميات الأزرار تُزامَن مع مكتبة CTA.</p>
          <form @submit.prevent="submit" class="vstack gap-3">
            <div class="card card-body">
              <h2 class="h6">عبارات عامة</h2>
              <div v-for="f in globalFields" :key="f.key" class="mb-2">
                <label class="form-label small mb-0">{{ f.label }}</label>
                <input v-model="form.uiLabels.global[f.key]" class="form-control form-control-sm" />
              </div>
            </div>
            <div class="card card-body">
              <h2 class="h6">تسميات الأزرار (CTA)</h2>
              <div v-for="f in ctaFields" :key="f.key" class="mb-2">
                <label class="form-label small mb-0">{{ f.label }}</label>
                <input v-model="form.uiLabels.cta[f.key]" class="form-control form-control-sm" />
              </div>
            </div>
            <div class="card card-body">
              <h2 class="h6">صور/أحرف الثقة في البطل (Hero)</h2>
              <div v-for="(av, i) in form.uiLabels.hero.trust_avatars" :key="i" class="row g-2 mb-2 align-items-center">
                <div class="col-3">
                  <select v-model="av.mode" class="form-select form-select-sm">
                    <option value="initial">حرف/أوليات</option>
                    <option value="image">صورة (رابط)</option>
                  </select>
                </div>
                <div class="col-7">
                  <input v-model="av.value" class="form-control form-control-sm" :placeholder="av.mode === 'image' ? 'https://...' : 'A'" />
                </div>
                <div class="col-2 d-flex gap-1">
                  <button type="button" class="btn btn-sm btn-outline-secondary" @click="moveAvatar(i, -1)">↑</button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" @click="moveAvatar(i, 1)">↓</button>
                  <button type="button" class="btn btn-sm btn-outline-danger" @click="form.uiLabels.hero.trust_avatars.splice(i, 1)">×</button>
                </div>
              </div>
              <button type="button" class="btn btn-sm btn-outline-primary" @click="addAvatar">+ عنصر</button>
            </div>
            <button type="submit" class="btn btn-primary" :disabled="form.processing">حفظ</button>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
