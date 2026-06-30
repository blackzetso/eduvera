<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import WebsiteSidebar from '@/Pages/Admin/theme1/Website/Partials/Sidebar.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { computed } from 'vue'
import WebsiteImageUploadField from '@/Components/Admin/Website/WebsiteImageUploadField.vue'

const props = defineProps({
  schoolInfo: Object,
  heroStats: Array,
  heroHighlights: Array,
  heroBadges: Array,
  ctaPresets: Object,
})

const form = useForm({
  schoolInfo: JSON.parse(JSON.stringify(props.schoolInfo || {})),
  heroStats: props.heroStats || [],
  heroHighlights: props.heroHighlights || [],
  heroBadges: props.heroBadges || [],
  ctaPresets: props.ctaPresets || {},
  hero_image: null,
  hero_background: null,
})

const existingHeroImage = computed(() => props.schoolInfo?.hero?.image?.src ?? '')
const existingHeroBackground = computed(() => props.schoolInfo?.hero?.backgroundImage?.src ?? '')

function submit() {
  form.post(route('admin.website.hero.update'), { forceFormData: true })
}
</script>

<template>
  <Head title="قسم البطل" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <WebsiteSidebar />
        <div class="col-lg-9">
          <h1 class="h4 mb-3">قسم البطل (Hero)</h1>
          <form @submit.prevent="submit" class="vstack gap-3">
            <div class="card card-body">
              <h2 class="h6">العناوين</h2>
              <input v-model="form.schoolInfo.hero.pill" class="form-control mb-2" placeholder="شارة العلوية" />
              <input v-model="form.schoolInfo.hero.headlineLine1" class="form-control mb-2" />
              <input v-model="form.schoolInfo.hero.headlineAccent" class="form-control mb-2" />
              <input v-model="form.schoolInfo.hero.headlineLine2" class="form-control mb-2" />
              <textarea v-model="form.schoolInfo.hero.subheadline" class="form-control" rows="3" />
              <input v-model="form.schoolInfo.hero.trustLabel" class="form-control mt-2" />
            </div>
            <div class="card card-body">
              <WebsiteImageUploadField
                spec-key="hero_image"
                label="صورة البطل"
                :existing-url="existingHeroImage"
                @update:model-value="form.hero_image = $event"
              />
              <WebsiteImageUploadField
                spec-key="hero_background"
                label="خلفية البطل (اختياري)"
                :existing-url="existingHeroBackground"
                @update:model-value="form.hero_background = $event"
              />
            </div>
            <div class="card card-body">
              <h2 class="h6">إحصائيات</h2>
              <div v-for="(s, i) in form.heroStats" :key="i" class="row g-2 mb-2">
                <div class="col"><input v-model="s.label" class="form-control form-control-sm" /></div>
                <div class="col-2"><input v-model.number="s.end" type="number" class="form-control form-control-sm" /></div>
                <div class="col-1"><input v-model="s.suffix" class="form-control form-control-sm" /></div>
              </div>
            </div>
            <div class="card card-body">
              <h2 class="h6">Hero Highlights</h2>
              <div v-for="(h, i) in form.heroHighlights" :key="i" class="row g-2 mb-1">
                <div class="col-2"><input v-model="h.icon" class="form-control form-control-sm" placeholder="bi-icon" /></div>
                <div class="col"><input v-model="h.text" class="form-control form-control-sm" /></div>
              </div>
              <button type="button" class="btn btn-sm btn-link" @click="form.heroHighlights.push({ icon: 'bi-star', text: '' })">+</button>
            </div>
            <div class="card card-body">
              <h2 class="h6">Hero Badges</h2>
              <div v-for="(b, i) in form.heroBadges" :key="i" class="row g-2 mb-1">
                <div class="col-2"><input v-model="b.icon" class="form-control form-control-sm" /></div>
                <div class="col"><input v-model="b.text" class="form-control form-control-sm" /></div>
                <div class="col-3"><input v-model="b.class" class="form-control form-control-sm" /></div>
              </div>
              <button type="button" class="btn btn-sm btn-link" @click="form.heroBadges.push({ id: 'b'+Date.now(), icon: '★', text: '', class: '' })">+</button>
            </div>
            <button type="submit" class="btn btn-primary" :disabled="form.processing">حفظ</button>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
