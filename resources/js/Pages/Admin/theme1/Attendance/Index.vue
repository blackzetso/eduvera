<script setup>
import { ref } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  records: Object,
  filters: Object,
  categories: Array,
})

const filterForm = ref({
  date: props.filters?.date || '',
  category_id: props.filters?.category_id || '',
  status: props.filters?.status || '',
})

const uploadForm = useForm({
  file: null,
  attendance_date: '',
  session_type: 'class',
})

function applyFilters() {
  router.get(route('admin.attendances.index'), filterForm.value, { preserveState: true })
}

function onFile(e) {
  uploadForm.file = e.target.files[0]
}

function submitUpload() {
  uploadForm.post(route('admin.attendances.bulk-upload'), { forceFormData: true })
}
</script>

<template>
  <Head title="سجلات الحضور" />
  <AppLayout>
    <div class="container py-4">
      <div class="d-flex justify-content-between mb-3">
        <h1 class="h3">سجلات الحضور</h1>
        <Link :href="route('admin.attendances.dashboard')" class="btn btn-sm btn-outline-secondary">لوحة التحكم</Link>
      </div>

      <div class="card mb-3">
        <div class="card-body row g-2">
          <div class="col-md-3">
            <input v-model="filterForm.date" type="date" class="form-control form-control-sm" />
          </div>
          <div class="col-md-3">
            <select v-model="filterForm.category_id" class="form-select form-select-sm">
              <option value="">كل المراحل</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div class="col-md-3">
            <select v-model="filterForm.status" class="form-select form-select-sm">
              <option value="">كل الحالات</option>
              <option value="present">حاضر</option>
              <option value="absent">غائب</option>
              <option value="late">متأخر</option>
              <option value="excused">معذور</option>
            </select>
          </div>
          <div class="col-md-3">
            <button class="btn btn-primary btn-sm w-100" @click="applyFilters">تصفية</button>
          </div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header">استيراد CSV / Excel</div>
        <div class="card-body row g-2 align-items-end">
          <div class="col-md-4">
            <input type="file" class="form-control form-control-sm" accept=".csv,.txt,.xlsx" @change="onFile" />
          </div>
          <div class="col-md-3">
            <input v-model="uploadForm.attendance_date" type="date" class="form-control form-control-sm" />
          </div>
          <div class="col-md-3">
            <button class="btn btn-success btn-sm" :disabled="uploadForm.processing" @click="submitUpload">رفع ومعاينة</button>
          </div>
        </div>
        <div class="card-footer small text-muted">
          الأعمدة: student_code أو email، attendance_date، status (present|absent|late|excused)
        </div>
      </div>

      <div class="table-responsive card">
        <table class="table table-sm mb-0">
          <thead>
            <tr>
              <th>التاريخ</th>
              <th>الطالب</th>
              <th>المرحلة</th>
              <th>الحالة</th>
              <th>المصدر</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in records.data" :key="r.id">
              <td>{{ r.attendance_date }}</td>
              <td>{{ r.student?.name }}</td>
              <td>{{ r.category?.name }}</td>
              <td>{{ r.status }}</td>
              <td>{{ r.source }}</td>
            </tr>
            <tr v-if="!records.data?.length">
              <td colspan="5" class="text-center text-muted">لا توجد سجلات</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
