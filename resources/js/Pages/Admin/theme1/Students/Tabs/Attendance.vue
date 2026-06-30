<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

defineProps({
  student: Object,
  summary: Object,
})
</script>

<template>
  <Head :title="`حضور — ${student.name}`" />
  <AppLayout>
    <div class="container py-4">
      <Link :href="route('admin.students.index')" class="btn btn-sm btn-link mb-2">← الطلاب</Link>
      <h1 class="h4">حضور: {{ student.name }}</h1>
      <div class="row g-2 mb-3">
        <div class="col"><div class="card p-2 text-center">حاضر: {{ summary.present }}</div></div>
        <div class="col"><div class="card p-2 text-center">غائب: {{ summary.absent }}</div></div>
        <div class="col"><div class="card p-2 text-center">متأخر: {{ summary.late }}</div></div>
      </div>
      <table class="table table-sm">
        <thead><tr><th>تاريخ</th><th>نوع</th><th>حالة</th></tr></thead>
        <tbody>
          <tr v-for="r in summary.records" :key="r.id">
            <td>{{ r.attendance_date }}</td>
            <td>{{ r.session_type }}</td>
            <td>{{ r.status }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </AppLayout>
</template>
