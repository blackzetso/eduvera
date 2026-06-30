<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

defineProps({
  thresholds: Array,
  categories: Array,
})

const form = useForm({
  category_id: '',
  academic_year: '',
  period_type: 'year',
  warning_absences: 5,
  critical_absences: 10,
  auto_notify_guardian: true,
  suggest_block_at_critical: true,
})

function submit() {
  form.post(route('admin.attendances.thresholds.store'))
}
</script>

<template>
  <Head title="عتبات الحضور" />
  <AppLayout>
    <div class="container py-4">
      <h1 class="h4 mb-3">إعداد عتبات الغياب</h1>

      <form class="card mb-4" @submit.prevent="submit">
        <div class="card-body row g-2">
          <div class="col-md-4">
            <label class="form-label">المرحلة (فارغ = عام)</label>
            <select v-model="form.category_id" class="form-select form-select-sm">
              <option value="">عام للمدرسة</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">تحذير</label>
            <input v-model.number="form.warning_absences" type="number" class="form-control form-control-sm" min="1" />
          </div>
          <div class="col-md-2">
            <label class="form-label">حرج</label>
            <input v-model.number="form.critical_absences" type="number" class="form-control form-control-sm" min="1" />
          </div>
          <div class="col-md-4 d-flex align-items-end">
            <button type="submit" class="btn btn-primary btn-sm" :disabled="form.processing">حفظ</button>
          </div>
        </div>
      </form>

      <div class="table-responsive eduvera-table-wrap">
      <table class="table table-sm card">
        <thead>
          <tr><th>المرحلة</th><th>تحذير</th><th>حرج</th><th>إشعار ولي الأمر</th></tr>
        </thead>
        <tbody>
          <tr v-for="t in thresholds" :key="t.id">
            <td>{{ t.category?.name || 'عام' }}</td>
            <td>{{ t.warning_absences }}</td>
            <td>{{ t.critical_absences }}</td>
            <td>{{ t.auto_notify_guardian ? 'نعم' : 'لا' }}</td>
          </tr>
        </tbody>
      </table>
      </div>
    </div>
  </AppLayout>
</template>
