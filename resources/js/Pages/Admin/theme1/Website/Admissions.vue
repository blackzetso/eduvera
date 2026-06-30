<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import WebsiteSidebar from '@/Pages/Admin/theme1/Website/Partials/Sidebar.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  admissionSteps: Array,
  admissionsFunnelHref: String,
  visitFormConfig: Object,
  visitCampusReasons: Array,
  campusVisit: Object,
  admissionDocuments: Array,
  admissionDocumentsSettingsUrl: String,
})

const visitCfg = JSON.parse(JSON.stringify(props.visitFormConfig || {}))
if (!visitCfg.fields?.length) {
  visitCfg.fields = []
}
visitCfg.labels = visitCfg.labels || {}
visitCfg.gradeOptions = visitCfg.gradeOptions || []
visitCfg.timeSlots = visitCfg.timeSlots || []

const form = useForm({
  admissionSteps: props.admissionSteps || [],
  admissionsFunnelHref: props.admissionsFunnelHref || '#visit',
  visitFormConfig: visitCfg,
  visitCampusReasons: props.visitCampusReasons || [],
  campusVisit: JSON.parse(JSON.stringify(props.campusVisit || {})),

})

function submit() {
  form.put(route('admin.website.admissions.update'))
}

function addField() {
  form.visitFormConfig.fields.push({
    key: 'field_' + Date.now(),
    name: 'field',
    type: 'text',
    enabled: true,
    required: false,
    sort: (form.visitFormConfig.fields.length + 1) * 10,
    label: 'New field',
    placeholder: '',
  })
}

function moveField(i, dir) {
  const list = form.visitFormConfig.fields
  const j = i + dir
  if (j < 0 || j >= list.length) return
  const sortA = list[i].sort
  list[i].sort = list[j].sort
  list[j].sort = sortA
  list.sort((a, b) => (a.sort ?? 0) - (b.sort ?? 0))
}
</script>

<template>
  <Head title="القبول" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <WebsiteSidebar />
        <div class="col-lg-9">
          <h1 class="h4 mb-3">إدارة القبول</h1>
          <form @submit.prevent="submit" class="vstack gap-3">
            <div class="card card-body">
              <label class="form-label">رابط القبول الرئيسي</label>
              <input v-model="form.admissionsFunnelHref" class="form-control" />
            </div>
            <div class="card card-body">
              <h2 class="h6">خطوات القبول</h2>
              <div v-for="(s, i) in form.admissionSteps" :key="i" class="row g-2 mb-2">
                <div class="col-1"><input v-model.number="s.step" type="number" class="form-control form-control-sm" /></div>
                <div class="col-4"><input v-model="s.title" class="form-control form-control-sm" /></div>
                <div class="col-7"><input v-model="s.text" class="form-control form-control-sm" /></div>
              </div>
              <button type="button" class="btn btn-sm btn-outline-primary" @click="form.admissionSteps.push({ step: form.admissionSteps.length + 1, title: '', text: '' })">+ خطوة</button>
            </div>
            <div class="card card-body">
              <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <h2 class="h6 mb-0">المستندات المطلوبة</h2>
                <a
                  v-if="admissionDocumentsSettingsUrl"
                  :href="admissionDocumentsSettingsUrl"
                  class="btn btn-sm btn-outline-primary"
                >
                  إدارة المستندات
                </a>
              </div>
              <p class="text-muted small">
                تُدار المستندات من إعدادات القبول. القائمة أدناه للعرض على الموقع فقط.
              </p>
              <ul class="list-group list-group-flush">
                <li
                  v-for="(d, i) in admissionDocuments"
                  :key="d.key || i"
                  class="list-group-item d-flex justify-content-between align-items-center px-0"
                >
                  <span>{{ d.label_ar || d.label }}</span>
                  <span class="badge" :class="d.required ? 'text-bg-primary' : 'text-bg-secondary'">
                    {{ d.required ? 'إلزامي' : 'اختياري' }}
                  </span>
                </li>
              </ul>
            </div>
            <div class="card card-body">
              <h2 class="h6">زيارة الحرم</h2>
              <input v-model="form.campusVisit.title" class="form-control mb-2" />
              <textarea v-model="form.campusVisit.lead" class="form-control mb-2" rows="2" />
              <input v-model="form.campusVisit.book_button_label" class="form-control mb-2" />
              <input v-model="form.campusVisit.form_title" class="form-control mb-2" />
            </div>
            <div class="card card-body">
              <h2 class="h6">أسباب الزيارة</h2>
              <div v-for="(r, i) in form.visitCampusReasons" :key="i" class="row g-2 mb-2">
                <div class="col-3"><input v-model="r.icon" class="form-control form-control-sm" /></div>
                <div class="col-8"><input v-model="r.text" class="form-control form-control-sm" /></div>
                <div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger" @click="form.visitCampusReasons.splice(i, 1)">×</button></div>
              </div>
              <button type="button" class="btn btn-sm btn-outline-primary" @click="form.visitCampusReasons.push({ icon: 'bi-check-circle', text: '' })">+ سبب</button>
            </div>

            <div class="card card-body">
              <h2 class="h6">منشئ نموذج الزيارة</h2>
              <input v-model="form.visitFormConfig.formId" class="form-control form-control-sm mb-2" placeholder="form id" />
              <input v-model="form.visitFormConfig.labels.submit" class="form-control form-control-sm mb-2" placeholder="زر الإرسال" />
              <textarea v-model="form.visitFormConfig.hint" class="form-control form-control-sm mb-3" rows="2" placeholder="تلميح أسفل النموذج" />
              <div v-for="(field, i) in form.visitFormConfig.fields" :key="field.key" class="border rounded p-2 mb-2">
                <div class="row g-2">
                  <div class="col-md-2">
                    <div class="form-check">
                      <input v-model="field.enabled" class="form-check-input" type="checkbox" :id="'en-' + i" />
                      <label class="form-check-label small" :for="'en-' + i">مفعّل</label>
                    </div>
                  </div>
                  <div class="col-md-2">
                    <div class="form-check">
                      <input v-model="field.required" class="form-check-input" type="checkbox" :id="'req-' + i" />
                      <label class="form-check-label small" :for="'req-' + i">مطلوب</label>
                    </div>
                  </div>
                  <div class="col-md-2"><input v-model.number="field.sort" type="number" class="form-control form-control-sm" placeholder="ترتيب" /></div>
                  <div class="col-md-3">
                    <select v-model="field.type" class="form-select form-select-sm">
                      <option value="text">نص</option>
                      <option value="tel">هاتف</option>
                      <option value="email">بريد</option>
                      <option value="date">تاريخ</option>
                      <option value="select">قائمة</option>
                      <option value="textarea">نص طويل</option>
                    </select>
                  </div>
                  <div class="col-md-3 d-flex gap-1">
                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="moveField(i, -1)">↑</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="moveField(i, 1)">↓</button>
                  </div>
                  <div class="col-md-6"><input v-model="field.label" class="form-control form-control-sm" placeholder="التسمية" /></div>
                  <div class="col-md-6"><input v-model="field.placeholder" class="form-control form-control-sm" placeholder="Placeholder" /></div>
                  <div class="col-md-4"><input v-model="field.key" class="form-control form-control-sm" placeholder="key" /></div>
                  <div class="col-md-4"><input v-model="field.name" class="form-control form-control-sm" placeholder="name" /></div>
                  <div class="col-md-4" v-if="field.type === 'select'">
                    <input v-model="field.optionsSource" class="form-control form-control-sm" placeholder="gradeOptions / timeSlots" />
                  </div>
                  <div class="col-md-4"><input v-model="field.rowPair" class="form-control form-control-sm" placeholder="rowPair (اختياري)" /></div>
                </div>
              </div>
              <button type="button" class="btn btn-sm btn-outline-primary" @click="addField">+ حقل</button>
              <h3 class="h6 mt-3">خيارات الصفوف (سطر لكل خيار)</h3>
              <label class="small text-muted">الصفوف الدراسية</label>
              <textarea
                :value="(form.visitFormConfig.gradeOptions || []).join('\n')"
                class="form-control form-control-sm mb-2"
                rows="4"
                @input="form.visitFormConfig.gradeOptions = $event.target.value.split('\n').map((s) => s.trim()).filter(Boolean)"
              />
              <label class="small text-muted">أوقات الزيارة</label>
              <textarea
                :value="(form.visitFormConfig.timeSlots || []).join('\n')"
                class="form-control form-control-sm"
                rows="3"
                @input="form.visitFormConfig.timeSlots = $event.target.value.split('\n').map((s) => s.trim()).filter(Boolean)"
              />
            </div>

            <button type="submit" class="btn btn-primary">حفظ</button>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
