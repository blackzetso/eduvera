<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

defineProps({
  alerts: Object,
})

function acknowledge(alertId) {
  const form = useForm({ action_taken: 'warning_sent', notes: '' })
  form.post(route('admin.attendances.alerts.acknowledge', alertId))
}
</script>

<template>
  <Head title="تنبيهات الحضور" />
  <AppLayout>
    <div class="container py-4">
      <h1 class="h4 mb-3">تنبيهات الغياب</h1>
      <div class="table-responsive eduvera-table-wrap">
      <table class="table table-sm card">
        <thead>
          <tr><th>الطالب</th><th>المستوى</th><th>غيابات</th><th>التاريخ</th><th></th></tr>
        </thead>
        <tbody>
          <tr v-for="a in alerts.data" :key="a.id">
            <td>{{ a.student?.name }}</td>
            <td><span :class="a.level === 'critical' ? 'text-danger' : 'text-warning'">{{ a.level }}</span></td>
            <td>{{ a.absences_count }}</td>
            <td>{{ a.triggered_at }}</td>
            <td>
              <button v-if="!a.acknowledged_at" class="btn btn-xs btn-outline-primary btn-sm" @click="acknowledge(a.id)">
                تأكيد
              </button>
            </td>
          </tr>
        </tbody>
      </table>
      </div>
    </div>
  </AppLayout>
</template>
