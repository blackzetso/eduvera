<script setup>
import { ref, watch } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  form: Object,
  submissions: Object,
  filters: Object,
})

const search = ref(props.filters?.search ?? '')
const status = ref(props.filters?.status ?? '')

watch([search, status], () => {
  router.get(route('admin.forms.submissions.index', props.form.id), {
    search: search.value || undefined,
    status: status.value || undefined,
  }, { preserveState: true, replace: true })
})
</script>

<template>
  <Head :title="`إرسالات — ${form.name}`" />
  <AppLayout>
    <div class="page-content-wrapper border" dir="rtl">
      <div class="card-body px-1 px-sm-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h4 class="mb-1">إرسالات النموذج</h4>
            <p class="text-muted mb-0">{{ form.name }}</p>
          </div>
          <Link :href="route('admin.forms.index')" class="btn btn-secondary-soft btn-sm">رجوع</Link>
        </div>

        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <input v-model="search" class="form-control" placeholder="بحث..." />
          </div>
          <div class="col-md-3">
            <select v-model="status" class="form-select">
              <option value="">كل الحالات</option>
              <option value="submitted">مُرسل</option>
              <option value="approved">موافق عليه</option>
              <option value="rejected">مرفوض</option>
            </select>
          </div>
          <div class="col-md-3 text-end">
            <button type="button" class="btn btn-success-soft btn-sm me-1" disabled title="قريباً">Excel</button>
            <button type="button" class="btn btn-danger-soft btn-sm" disabled title="قريباً">PDF</button>
          </div>
        </div>

        <div class="table-responsive border rounded">
          <table class="table table-hover mb-0">
            <thead>
              <tr class="text-center">
                <th>#</th>
                <th>المستخدم</th>
                <th>الحالة</th>
                <th>مرحلة سير العمل</th>
                <th>التاريخ</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(sub, i) in submissions.data" :key="sub.id" class="text-center">
                <td>{{ submissions.from + i }}</td>
                <td>{{ sub.user?.name ?? '—' }}</td>
                <td><span class="badge bg-light border">{{ sub.status }}</span></td>
                <td>{{ sub.workflow_stage ?? '—' }}</td>
                <td>{{ new Date(sub.created_at).toLocaleString('ar-EG') }}</td>
              </tr>
              <tr v-if="!submissions.data?.length">
                <td colspan="5" class="text-muted py-4">لا توجد إرسالات</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
