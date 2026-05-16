<script setup>
import { computed } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  categories: { type: Array, default: () => [] }
})

const form = useForm({
  file: null
})

const page = usePage()
const flash = computed(() => page.props.flash || {})

function onFileChange(event) {
  form.file = event.target.files?.[0] || null
}

function submitImport() {
  form.post(route('admin.students.bulk-data.import'), {
    forceFormData: true,
    preserveScroll: true,
  })
}
</script>

<template>
  <Head title="Bulk Data - الطلاب" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="card-body px-1 px-sm-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h4>Bulk Data - الطلاب</h4>
          <Link :href="route('admin.students.index')" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> العودة
          </Link>
        </div>

        <div v-if="flash.success" class="alert alert-success">{{ flash.success }}</div>
        <div v-if="flash.error" class="alert alert-danger">{{ flash.error }}</div>

        <div class="card">
          <div class="card-body">
            <div class="row g-4">
              <div class="col-lg-7">
                <h5 class="mb-3">1) نزّل قالب الشيت</h5>
                <ul class="mb-3">
                  <li>name (الاسم)</li>
                  <li>email (البريد الإلكتروني)</li>
                  <li>password (كلمة المرور)</li>
                  <li>phone (رقم الهاتف)</li>
                  <li>category_id (الفصل ID)</li>
                  <li class="text-muted">guardian_national_id (الرقم القومي لولي الأمر - <em>اختياري</em>)</li>
                </ul>
                <div class="alert alert-info py-2 small mb-3">
                  <i class="bi bi-info-circle me-1"></i>
                  إذا أدخلت الرقم القومي لولي الأمر، سيتم ربط الطالب تلقائيًا بولي الأمر المطابق.
                </div>

                <a :href="route('admin.students.bulk-data.template')" class="btn btn-outline-primary">
                  <i class="bi bi-download me-1"></i>
                  تنزيل قالب CSV
                </a>

                <hr class="my-4" />

                <h5 class="mb-3">2) ارفع الشيت بعد تعبئته</h5>
                <form @submit.prevent="submitImport" class="d-flex flex-column gap-3">
                  <div>
                    <input
                      type="file"
                      class="form-control"
                      accept=".csv,text/csv"
                      @change="onFileChange"
                    />
                    <div v-if="form.errors.file" class="text-danger mt-1">{{ form.errors.file }}</div>
                  </div>

                  <div>
                    <button type="submit" class="btn btn-success" :disabled="form.processing || !form.file">
                      <span v-if="form.processing" class="spinner-border spinner-border-sm me-2"></span>
                      <i v-else class="bi bi-upload me-1"></i>
                      رفع واستيراد الطلاب
                    </button>
                  </div>
                </form>
              </div>

              <div class="col-lg-5">
                <div class="border rounded p-3 bg-light">
                  <h6 class="mb-3">دليل الفصول (category_id)</h6>
                  <div v-if="categories.length === 0" class="text-muted">لا توجد فئات.</div>
                  <ul v-else class="mb-0" style="max-height: 300px; overflow: auto;">
                    <li v-for="cat in categories" :key="cat.id">
                      {{ cat.id }} - {{ cat.path }}
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
