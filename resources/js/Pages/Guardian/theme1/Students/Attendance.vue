<script setup>
import { Head } from '@inertiajs/vue3'
import GuardianDashboardLayout from '@/Layouts/GuardianDashboardLayout.vue'

const props = defineProps({
  guardian: Object,
  children: Array,
  student: Object,
  summary: Object,
})

const statusLabel = {
  present: 'حاضر',
  absent: 'غائب',
  late: 'متأخر',
  excused: 'معذور',
}
</script>

<template>
  <Head :title="`حضور ${student.name}`" />
  <GuardianDashboardLayout
    :guardian="guardian"
    :children="children"
    :student="student"
    active-menu="attendance"
  >
    <div class="row g-4 mb-4">
      <div class="col-6 col-md-3" v-for="(val, key) in { present: summary.present, absent: summary.absent, late: summary.late, excused: summary.excused }" :key="key">
        <div class="p-3 border rounded text-center">
          <div class="fs-4 fw-bold">{{ val }}</div>
          <small class="text-muted">{{ statusLabel[key] }}</small>
        </div>
      </div>
    </div>

    <div class="card border">
      <div class="card-header bg-transparent">
        <h5 class="mb-0">سجل الحضور والغياب</h5>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>التاريخ</th>
                <th>نوع الجلسة</th>
                <th>الحالة</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in summary.records" :key="r.id">
                <td>{{ r.attendance_date }}</td>
                <td>{{ r.session_type }}</td>
                <td>
                  <span
                    class="badge"
                    :class="{
                      'bg-success': r.status === 'present',
                      'bg-danger': r.status === 'absent',
                      'bg-warning text-dark': r.status === 'late',
                      'bg-info': r.status === 'excused',
                    }"
                  >
                    {{ statusLabel[r.status] ?? r.status }}
                  </span>
                </td>
              </tr>
              <tr v-if="!summary.records?.length">
                <td colspan="3" class="text-center text-muted py-4">لا توجد سجلات</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </GuardianDashboardLayout>
</template>
