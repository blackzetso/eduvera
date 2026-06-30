<script setup>
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { pipelineBadgeClass, statusBadge, decisionBadge } from '@/Shared/admissionsBadges'
import { formatVisitRelative } from '@/composables/useAdmissionInboxMetrics'

defineProps({
  applications: { type: Array, required: true },
  formatDateTime: { type: Function, required: true },
})

function showUrl(id, tab = null) {
  const base = route('admin.admissions.show', id)
  return tab ? `${base}?tab=${tab}` : base
}
</script>

<template>
  <div class="card admission-dashboard-card admission-inbox-table-wrap">
    <div class="card-header bg-transparent border-0 pt-3 pb-0 px-4">
      <h6 class="mb-0 fw-bold">جميع الطلبات</h6>
    </div>
    <div class="table-responsive eduvera-table-wrap px-2 pb-2">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr class="small text-muted">
            <th>المتقدم</th>
            <th>ولي الأمر</th>
            <th>الصف المستهدف</th>
            <th>الزيارة</th>
            <th>المرحلة</th>
            <th>المسؤول</th>
            <th>الحالة</th>
            <th class="text-end">إجراءات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in applications" :key="row.id">
            <td>
              <div class="d-flex align-items-center gap-2">
                <span class="admission-inbox-avatar">
                  <i class="bi bi-person-fill"></i>
                </span>
                <div class="min-w-0">
                  <Link
                    :href="route('admin.admissions.show', row.id)"
                    class="fw-semibold text-decoration-none d-block text-truncate"
                  >
                    {{ row.student_name || '—' }}
                  </Link>
                  <code class="small text-muted">{{ row.reference_code }}</code>
                </div>
              </div>
            </td>
            <td class="small">{{ row.parent_name || '—' }}</td>
            <td>
              <span
                v-if="row.target_grade"
                class="badge rounded-pill admission-category-badge"
              >
                {{ row.target_grade }}
              </span>
              <span v-else class="text-muted small">—</span>
            </td>
            <td class="small">
              <template v-if="row.visit_date">
                <div class="fw-semibold">{{ formatVisitRelative(row).relative }}</div>
                <div v-if="formatVisitRelative(row).detail" class="text-muted">
                  {{ formatVisitRelative(row).detail }}
                </div>
              </template>
              <span v-else class="text-muted">—</span>
            </td>
            <td>
              <span class="badge rounded-pill" :class="pipelineBadgeClass(row.pipeline_stage)">
                {{ row.pipeline_stage_label }}
              </span>
            </td>
            <td class="small">{{ row.assigned_to?.name || '—' }}</td>
            <td>
              <span class="badge rounded-pill" :class="statusBadge(row.status)">
                {{ row.status_label }}
              </span>
              <span
                v-if="row.decision"
                class="badge rounded-pill ms-1"
                :class="decisionBadge(row.decision)"
              >
                {{ row.decision_label }}
              </span>
            </td>
            <td class="text-end text-nowrap">
              <div class="d-inline-flex flex-wrap justify-content-end gap-1 admission-inbox-actions">
                <Link
                  :href="showUrl(row.id)"
                  class="btn btn-sm btn-primary"
                  title="فتح مساحة العمل"
                >
                  <i class="bi bi-box-arrow-up-left"></i>
                </Link>
                <Link
                  :href="showUrl(row.id, 'overview')"
                  class="btn btn-sm btn-light border"
                  title="نظرة عامة"
                >
                  <i class="bi bi-grid"></i>
                </Link>
                <Link
                  :href="showUrl(row.id, 'timeline')"
                  class="btn btn-sm btn-light border"
                  title="الجدول الزمني"
                >
                  <i class="bi bi-clock-history"></i>
                </Link>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
