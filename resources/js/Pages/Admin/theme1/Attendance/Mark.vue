<script setup>
import { ref, watch } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  period: Object,
  date: String,
  students: Array,
  periods: Array,
})

const selectedPeriod = ref(props.period?.id || '')
const selectedDate = ref(props.date)
const marks = ref((props.students || []).map(s => ({ ...s })))

watch([selectedPeriod, selectedDate], () => {
  router.get(route('admin.attendances.mark.form'), {
    period_id: selectedPeriod.value,
    date: selectedDate.value,
  }, { preserveState: true })
})

const form = useForm({
  timetable_period_id: props.period?.id,
  attendance_date: props.date,
  marks: [],
})

function submit() {
  form.timetable_period_id = selectedPeriod.value
  form.attendance_date = selectedDate.value
  form.marks = marks.value.map(m => ({
    student_id: m.id,
    status: m.status,
    notes: m.notes || null,
  }))
  form.post(route('admin.attendances.mark'))
}
</script>

<template>
  <Head title="تسجيل الحضور" />
  <AppLayout>
    <div class="container py-4">
      <h1 class="h4 mb-3">تسجيل حضور الحصة</h1>
      <div class="row g-2 mb-3">
        <div class="col-md-4">
          <select v-model="selectedPeriod" class="form-select">
            <option value="">اختر الحصة</option>
            <option v-for="p in periods" :key="p.id" :value="p.id">
              {{ p.category?.name }} — حصة {{ p.period_number }}
            </option>
          </select>
        </div>
        <div class="col-md-3">
          <input v-model="selectedDate" type="date" class="form-control" />
        </div>
      </div>

      <table v-if="marks.length" class="table table-sm card">
        <thead><tr><th>الطالب</th><th>الحالة</th><th>ملاحظات</th></tr></thead>
        <tbody>
          <tr v-for="m in marks" :key="m.id">
            <td>{{ m.name }}</td>
            <td>
              <select v-model="m.status" class="form-select form-select-sm">
                <option value="present">حاضر</option>
                <option value="absent">غائب</option>
                <option value="late">متأخر</option>
                <option value="excused">معذور</option>
              </select>
            </td>
            <td><input v-model="m.notes" class="form-control form-control-sm" /></td>
          </tr>
        </tbody>
      </table>

      <button v-if="marks.length" class="btn btn-primary mt-2" :disabled="form.processing" @click="submit">حفظ</button>
    </div>
  </AppLayout>
</template>
