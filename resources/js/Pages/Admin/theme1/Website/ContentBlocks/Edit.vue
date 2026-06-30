<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import WebsiteSidebar from '@/Pages/Admin/theme1/Website/Partials/Sidebar.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import WebsiteImageUrlField from '@/Components/Admin/Website/WebsiteImageUrlField.vue'

const props = defineProps({ block: String, title: String, items: [Array, Object], schema: String })

function ensureStudentLifeImage(item) {
  if (!item.image || typeof item.image !== 'object') {
    item.image = { src: '', alt: '' }
  }
  if (item.image.src == null) item.image.src = ''
  if (item.image.alt == null) item.image.alt = ''
}

function initItems(items, schema) {
  if (schema === 'key_value') {
    return items && typeof items === 'object' && !Array.isArray(items) ? { ...items } : {}
  }
  if (schema === 'stage_modal') {
    const src = items && typeof items === 'object' && !Array.isArray(items) ? items : {}
    return {
      tabs: JSON.parse(JSON.stringify(src.tabs || [])),
      paneTitles: { ...(src.paneTitles || {}) },
      footer: { ...(src.footer || {}) },
    }
  }
  const list = Array.isArray(items) ? JSON.parse(JSON.stringify(items)) : []
  if (schema === 'student_life') {
    list.forEach(ensureStudentLifeImage)
  }
  return list
}

const form = useForm({ items: initItems(props.items, props.schema) })

function submit() {
  form.put(route('admin.website.content-blocks.update', props.block))
}

function addString() {
  form.items.push('')
}

function addObject(template) {
  form.items.push({ ...template })
}

function addFaq() {
  form.items.push({ q: '', a: '', cat: 'General' })
}

function addAccred() {
  form.items.push({ id: 'acc-' + Date.now(), name: '', abbr: '', description: '', benefit: '', verifyUrl: '#' })
}

function addStudentLife() {
  form.items.push({
    id: 'sl-' + Date.now(),
    icon: 'bi-star',
    name: '',
    image: { src: '', alt: '' },
  })
}

function addModalTab() {
  form.items.tabs.push({ id: 'tab-' + Date.now(), label: '' })
}
</script>

<template>
  <Head :title="title" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <WebsiteSidebar />
        <div class="col-lg-9">
          <Link :href="route('admin.website.content-blocks.index')" class="btn btn-sm btn-link">← كتل المحتوى</Link>
          <h1 class="h4 mb-3">{{ title }}</h1>
          <form @submit.prevent="submit" class="vstack gap-2">
            <template v-if="schema === 'string_list'">
              <div v-for="(item, i) in form.items" :key="i" class="input-group">
                <input v-model="form.items[i]" class="form-control" />
                <button type="button" class="btn btn-outline-danger" @click="form.items.splice(i, 1)">×</button>
              </div>
              <button type="button" class="btn btn-sm btn-outline-primary" @click="addString">+ إضافة</button>
            </template>

            <template v-else-if="schema === 'key_value'">
              <div v-for="(val, key) in form.items" :key="key" class="row g-2 mb-2">
                <div class="col-4"><input :value="key" class="form-control form-control-sm" disabled /></div>
                <div class="col-8"><input v-model="form.items[key]" class="form-control form-control-sm" /></div>
              </div>
            </template>

            <template v-else-if="schema === 'stage_modal'">
              <div class="card card-body">
                <h2 class="h6">تبويبات النافذة</h2>
                <div v-for="(tab, i) in form.items.tabs" :key="i" class="row g-2 mb-2">
                  <div class="col-4"><input v-model="tab.id" class="form-control form-control-sm" placeholder="id" /></div>
                  <div class="col-7"><input v-model="tab.label" class="form-control form-control-sm" /></div>
                  <div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger" @click="form.items.tabs.splice(i, 1)">×</button></div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" @click="addModalTab">+ تبويب</button>
              </div>
              <div class="card card-body">
                <h2 class="h6">عناوين الأقسام</h2>
                <div v-for="(val, key) in form.items.paneTitles" :key="key" class="row g-2 mb-2">
                  <div class="col-4"><small class="text-muted">{{ key }}</small></div>
                  <div class="col-8"><input v-model="form.items.paneTitles[key]" class="form-control form-control-sm" /></div>
                </div>
              </div>
              <div class="card card-body">
                <h2 class="h6">التذييل</h2>
                <input v-model="form.items.footer.applyCtaId" class="form-control form-control-sm mb-2" placeholder="معرّف CTA للتقديم" />
                <input v-model="form.items.footer.visitCtaId" class="form-control form-control-sm mb-2" placeholder="معرّف CTA للزيارة" />
                <input v-model="form.items.footer.applyLabel" class="form-control form-control-sm mb-2" />
                <input v-model="form.items.footer.closeLabel" class="form-control form-control-sm" />
              </div>
            </template>

            <template v-else-if="schema === 'student_life'">
              <div v-for="(item, i) in form.items" :key="i" class="card card-body p-2 mb-2">
                <input v-model="item.name" class="form-control form-control-sm mb-1" placeholder="الاسم" />
                <input v-model="item.icon" class="form-control form-control-sm mb-1" placeholder="bi-icon" />
                <WebsiteImageUrlField
                  v-model="item.image.src"
                  spec-key="student_life_tile"
                  label="رابط الصورة"
                />
                <input v-model="item.image.alt" class="form-control form-control-sm mt-2" placeholder="وصف الصورة" />
              </div>
              <button type="button" class="btn btn-sm btn-outline-primary" @click="addStudentLife">+ نشاط</button>
            </template>

            <template v-else-if="schema === 'faq_list'">
              <div v-for="(item, i) in form.items" :key="i" class="card card-body p-2 mb-2">
                <input v-model="item.q" class="form-control form-control-sm mb-1" placeholder="Question" />
                <textarea v-model="item.a" class="form-control form-control-sm mb-1" rows="2" />
                <input v-model="item.cat" class="form-control form-control-sm" placeholder="Category" />
              </div>
              <button type="button" class="btn btn-sm btn-outline-primary" @click="addFaq">+ FAQ</button>
            </template>

            <template v-else-if="schema === 'accreditation_list'">
              <div v-for="(item, i) in form.items" :key="i" class="card card-body p-2 mb-2">
                <input v-model="item.name" class="form-control form-control-sm mb-1" placeholder="Name" />
                <input v-model="item.abbr" class="form-control form-control-sm mb-1" placeholder="Abbr" />
                <textarea v-model="item.description" class="form-control form-control-sm mb-1" rows="2" />
                <input v-model="item.verifyUrl" class="form-control form-control-sm" placeholder="Verify URL" />
              </div>
              <button type="button" class="btn btn-sm btn-outline-primary" @click="addAccred">+ شريك</button>
            </template>

            <template v-else>
              <div v-for="(item, i) in form.items" :key="i" class="card card-body p-2 mb-2">
                <input v-if="'title' in item" v-model="item.title" class="form-control form-control-sm mb-1" placeholder="title" />
                <input v-if="'text' in item || block === 'why-choose'" v-model="item.text" class="form-control form-control-sm mb-1" placeholder="text" />
                <input v-if="'icon' in item" v-model="item.icon" class="form-control form-control-sm mb-1" placeholder="bi-icon" />
                <input v-if="'label' in item && 'icon' in item" v-model="item.label" class="form-control form-control-sm mb-1" />
                <input v-if="'value' in item" v-model="item.value" class="form-control form-control-sm mb-1" />
                <input v-if="'end' in item" v-model.number="item.end" type="number" class="form-control form-control-sm mb-1" />
                <input v-if="'class' in item" v-model="item.class" class="form-control form-control-sm mb-1" />
              </div>
              <button
                type="button"
                class="btn btn-sm btn-outline-primary"
                @click="addObject(block === 'achievements' ? { id: 'a'+Date.now(), value: '', label: '' } : block === 'hero-badges' ? { id: 'b'+Date.now(), icon: '★', text: '', class: '' } : { icon: 'bi-star', title: '', text: '' })"
              >
                + إضافة
              </button>
            </template>

            <button type="submit" class="btn btn-primary mt-3" :disabled="form.processing">حفظ</button>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
