<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  batch: Object,
  errors: Array,
  sample: Array,
})

const form = useForm({})

function confirmImport() {
  form.post(route('admin.attendances.import.confirm', props.batch.id))
}
</script>

<template>
  <Head title="معاينة استيراد الحضور" />
  <AppLayout>
    <div class="container py-4">
      <h1 class="h4 mb-3">معاينة الاستيراد</h1>
      <p>الملف: {{ batch.original_file_name }} — صفوف صالحة: {{ batch.success_rows }} — أخطاء: {{ batch.error_rows }}</p>

      <div v-if="errors?.length" class="alert alert-warning">
        <ul class="mb-0">
          <li v-for="(e, i) in errors" :key="i">سطر {{ e.line }}: {{ e.errors?.join(', ') }}</li>
        </ul>
      </div>

      <table v-if="sample?.length" class="table table-sm card mb-3">
        <thead><tr><th>طالب</th><th>تاريخ</th><th>حالة</th></tr></thead>
        <tbody>
          <tr v-for="(row, i) in sample" :key="i">
            <td>{{ row.student_id }}</td>
            <td>{{ row.attendance_date }}</td>
            <td>{{ row.status }}</td>
          </tr>
        </tbody>
      </table>

      <div class="d-flex gap-2">
        <button
          v-if="batch.status === 'validated'"
          class="btn btn-primary"
          :disabled="form.processing"
          @click="confirmImport"
        >تأكيد الاستيراد</button>
        <Link :href="route('admin.attendances.index')" class="btn btn-secondary">إلغاء</Link>
      </div>
    </div>
  </AppLayout>
</template>
