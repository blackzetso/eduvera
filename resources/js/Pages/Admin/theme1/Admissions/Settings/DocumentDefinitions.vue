<script setup>
import { computed } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  definitions: { type: Array, default: () => [] },
  sourceTypes: { type: Object, default: () => ({}) },
})

const form = useForm({
  definitions: (props.definitions || []).map((row, index) => ({
    id: row.id ?? null,
    key: row.key ?? '',
    label_ar: row.label_ar ?? '',
    label_en: row.label_en ?? '',
    required: row.required ?? false,
    enabled: row.enabled ?? true,
    sort_order: row.sort_order ?? (index + 1) * 10,
    source_type: row.source_type ?? 'settings',
    source_ref: row.source_ref ?? null,
  })),
})

const sortedDefinitions = computed(() =>
  [...form.definitions].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0)),
)

function submit() {
  form.put(route('admin.admissions.settings.documents.update'))
}

function addDefinition() {
  const nextOrder = (form.definitions.length + 1) * 10
  form.definitions.push({
    id: null,
    key: '',
    label_ar: '',
    label_en: '',
    required: true,
    enabled: true,
    sort_order: nextOrder,
    source_type: 'settings',
    source_ref: null,
  })
}

function moveDefinition(row, direction) {
  const list = sortedDefinitions.value
  const index = list.indexOf(row)
  const targetIndex = index + direction
  if (targetIndex < 0 || targetIndex >= list.length) {
    return
  }

  const swap = list[targetIndex]
  const currentOrder = row.sort_order
  row.sort_order = swap.sort_order
  swap.sort_order = currentOrder
}

function isFormBuilderManaged(row) {
  return row.source_type === 'form_builder'
}

function sourceLabel(row) {
  return props.sourceTypes?.[row.source_type] ?? row.source_type
}
</script>

<template>
  <Head title="مستندات القبول" />
  <AppLayout>
    <div class="page-content-wrapper border p-4">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
          <h4 class="mb-1 fw-bold">إعدادات القبول — المستندات</h4>
          <p class="text-muted mb-0">
            حدد أنواع المستندات المطلوبة لكل طلب قبول. تُنشأ قائمة المستندات تلقائياً عند إنشاء الطلب.
          </p>
        </div>
        <Link :href="route('admin.admissions.index')" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-arrow-right me-1"></i>
          العودة لصندوق القبول
        </Link>
      </div>

      <form @submit.prevent="submit" class="vstack gap-3">
        <div class="card">
          <div class="card-body p-0">
            <div class="table-responsive d-none d-lg-block">
              <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th style="width: 4rem">#</th>
                    <th>الاسم (عربي)</th>
                    <th>الاسم (إنجليزي)</th>
                    <th style="width: 6rem">إلزامي</th>
                    <th style="width: 6rem">مفعّل</th>
                    <th style="width: 8rem">المصدر</th>
                    <th style="width: 7rem">ترتيب</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, index) in sortedDefinitions" :key="row.id ?? `new-${index}`">
                    <td class="text-muted">{{ index + 1 }}</td>
                    <td>
                      <input
                        v-model="row.label_ar"
                        class="form-control form-control-sm"
                        :disabled="isFormBuilderManaged(row)"
                        required
                      />
                    </td>
                    <td>
                      <input
                        v-model="row.label_en"
                        class="form-control form-control-sm"
                        :disabled="isFormBuilderManaged(row)"
                      />
                    </td>
                    <td class="text-center">
                      <input
                        v-model="row.required"
                        type="checkbox"
                        class="form-check-input"
                        :disabled="isFormBuilderManaged(row)"
                      />
                    </td>
                    <td class="text-center">
                      <input
                        v-model="row.enabled"
                        type="checkbox"
                        class="form-check-input"
                        :disabled="isFormBuilderManaged(row)"
                      />
                    </td>
                    <td>
                      <span class="badge text-bg-light">{{ sourceLabel(row) }}</span>
                    </td>
                    <td>
                      <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" @click="moveDefinition(row, -1)">↑</button>
                        <button type="button" class="btn btn-outline-secondary" @click="moveDefinition(row, 1)">↓</button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="d-lg-none p-3 vstack gap-2">
              <div
                v-for="(row, index) in sortedDefinitions"
                :key="row.id ?? `mobile-${index}`"
                class="border rounded p-3"
              >
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <strong>مستند {{ index + 1 }}</strong>
                  <span class="badge text-bg-light">{{ sourceLabel(row) }}</span>
                </div>
                <label class="form-label small mb-1">الاسم (عربي)</label>
                <input v-model="row.label_ar" class="form-control form-control-sm mb-2" :disabled="isFormBuilderManaged(row)" required />
                <label class="form-label small mb-1">الاسم (إنجليزي)</label>
                <input v-model="row.label_en" class="form-control form-control-sm mb-2" :disabled="isFormBuilderManaged(row)" />
                <div class="d-flex gap-3 mb-2">
                  <label class="form-check small">
                    <input v-model="row.required" type="checkbox" class="form-check-input" :disabled="isFormBuilderManaged(row)" />
                    إلزامي
                  </label>
                  <label class="form-check small">
                    <input v-model="row.enabled" type="checkbox" class="form-check-input" :disabled="isFormBuilderManaged(row)" />
                    مفعّل
                  </label>
                </div>
                <div class="btn-group btn-group-sm">
                  <button type="button" class="btn btn-outline-secondary" @click="moveDefinition(row, -1)">أعلى</button>
                  <button type="button" class="btn btn-outline-secondary" @click="moveDefinition(row, 1)">أسفل</button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
          <button type="button" class="btn btn-outline-primary btn-sm" @click="addDefinition">
            <i class="bi bi-plus-lg me-1"></i>
            إضافة نوع مستند
          </button>
          <button type="submit" class="btn btn-primary btn-sm" :disabled="form.processing">
            حفظ الإعدادات
          </button>
        </div>

        <p class="text-muted small mb-0">
          المستندات الإلزامية فقط تؤثر على جاهزية الطلب وشريط التقدم. المستندات المعطّلة لا تُضاف لطلبات جديدة.
        </p>
      </form>
    </div>
  </AppLayout>
</template>
