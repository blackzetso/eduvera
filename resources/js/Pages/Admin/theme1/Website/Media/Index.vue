<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import WebsiteSidebar from '@/Pages/Admin/theme1/Website/Partials/Sidebar.vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { route } from 'ziggy-js'
import WebsiteImageUploadField from '@/Components/Admin/Website/WebsiteImageUploadField.vue'

defineProps({ media: Object })

const upload = useForm({ file: null, alt: '' })
const copiedId = ref(null)

function submitUpload() {
  upload.post(route('admin.website.media.store'), { forceFormData: true, onSuccess: () => upload.reset() })
}

function destroy(id) {
  if (confirm('حذف الملف؟')) router.delete(route('admin.website.media.destroy', id))
}

async function copyLink(item) {
  const text = item.full_url || item.url
  try {
    await navigator.clipboard.writeText(text)
    copiedId.value = item.id
    setTimeout(() => {
      if (copiedId.value === item.id) copiedId.value = null
    }, 2000)
  } catch {
    window.prompt('انسخ الرابط:', text)
  }
}
</script>

<template>
  <Head title="مكتبة الوسائط" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <WebsiteSidebar />
        <div class="col-lg-9">
          <h1 class="h4 mb-3">مكتبة الوسائط</h1>
          <p class="text-muted small mb-3">
            بعد الرفع، انسخ <strong>رابط الصورة</strong> والصقه في حقول «رابط الصورة» داخل إدارة الموقع (مثل نشاط الطلاب، الخلفيات، إلخ).
          </p>
          <form @submit.prevent="submitUpload" class="card card-body mb-4">
            <div class="row g-3 align-items-end">
              <div class="col-lg-8">
                <WebsiteImageUploadField
                  spec-key="media_generic"
                  label="رفع إلى مكتبة الوسائط"
                  @update:model-value="upload.file = $event"
                />
              </div>
              <div class="col-lg-4">
                <input v-model="upload.alt" class="form-control mb-2" placeholder="Alt" />
                <button type="submit" class="btn btn-primary w-100" :disabled="upload.processing">رفع</button>
              </div>
            </div>
          </form>
          <div class="row g-3">
            <div v-for="m in media.data" :key="m.id" class="col-6 col-md-4 col-lg-3">
              <div class="card h-100">
                <img :src="m.url" class="card-img-top" style="height: 120px; object-fit: cover" :alt="m.alt" />
                <div class="card-body p-2 d-flex flex-column">
                  <div class="small text-truncate mb-1" :title="m.filename">{{ m.filename }}</div>
                  <label class="form-label small text-muted mb-0">رابط الصورة</label>
                  <div class="input-group input-group-sm mb-2">
                    <input
                      type="text"
                      class="form-control font-monospace"
                      :value="m.full_url || m.url"
                      readonly
                      dir="ltr"
                      @focus="$event.target.select()"
                    />
                    <button
                      type="button"
                      class="btn btn-outline-secondary"
                      :title="copiedId === m.id ? 'تم النسخ' : 'نسخ الرابط'"
                      @click="copyLink(m)"
                    >
                      <i class="bi" :class="copiedId === m.id ? 'bi-check-lg text-success' : 'bi-clipboard'" />
                    </button>
                  </div>
                  <a
                    :href="m.full_url || m.url"
                    class="btn btn-sm btn-light mb-2"
                    target="_blank"
                    rel="noopener"
                  >
                    فتح الصورة
                  </a>
                  <button type="button" class="btn btn-sm btn-danger-soft mt-auto w-100" @click="destroy(m.id)">
                    حذف
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
