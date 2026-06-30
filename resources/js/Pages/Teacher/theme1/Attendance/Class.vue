<script setup>
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  period: Object,
  date: String,
  students: Array,
})

const marks = ref(props.students.map(s => ({ ...s })))

const form = useForm({
  attendance_date: props.date,
  marks: [],
})

function submit() {
  form.attendance_date = props.date
  form.marks = marks.value.map(m => ({
    student_id: m.id,
    status: m.status,
    notes: m.notes || null,
  }))
  form.post(route('teacher.attendances.mark', props.period.id))
}
</script>

<template>
  <Head title="تسجيل حضور الحصة" />
  <div class="container py-4">
    <h1 class="h4">حصة {{ period.period_number }} — {{ period.category?.name }}</h1>
    <table class="table table-sm mt-3">
      <thead><tr><th>طالب</th><th>حالة</th></tr></thead>
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
        </tr>
      </tbody>
    </table>
    <button class="btn btn-primary" :disabled="form.processing" @click="submit">حفظ</button>
  </div>
</template>
