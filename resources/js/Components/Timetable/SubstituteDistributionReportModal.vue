<script setup>
const props = defineProps({
  show: Boolean,
  report: { type: Object, default: null },
  loading: Boolean,
})

const emit = defineEmits(['close'])

function balanceBadgeClass(balance) {
  const n = Number(balance) || 0
  if (n <= 2) return 'tt-coverage-balance--low'
  if (n <= 5) return 'tt-coverage-balance--mid'
  return 'tt-coverage-balance--high'
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="show"
      class="tt-coverage-report-root"
      dir="rtl"
      role="dialog"
      aria-modal="true"
      aria-labelledby="substitute-report-title"
    >
      <div class="tt-coverage-report__overlay" @click.self="emit('close')">
        <div class="modal-dialog modal-lg modal-dialog-scrollable tt-coverage-report__dialog" @click.stop>
          <div class="modal-content shadow-lg">
            <div class="modal-header border-0 tt-coverage-report__header">
              <div>
                <h5 id="substitute-report-title" class="modal-title fw-bold mb-1">
                  <i class="bi bi-clipboard-data ms-2"></i>
                  تقرير توزيع الاحتياط
                </h5>
                <p v-if="report" class="small text-white-50 mb-0">
                  {{ report.day_name }} — {{ report.date }}
                </p>
              </div>
              <button type="button" class="btn-close btn-close-white" @click="emit('close')"></button>
            </div>

            <div class="modal-body">
              <div v-if="loading" class="text-center py-5">
                <div class="spinner-border text-warning"></div>
              </div>

              <template v-else-if="report">
                <div class="row g-3 mb-4">
                  <div class="col-sm-6">
                    <div class="ev-card p-3 text-center h-100">
                      <div class="display-6 fw-bold text-primary">{{ report.summary?.assignments_total ?? 0 }}</div>
                      <div class="small text-muted">حصة احتياطية</div>
                    </div>
                  </div>
                  <div class="col-sm-6">
                    <div class="ev-card p-3 text-center h-100">
                      <div class="display-6 fw-bold text-success">{{ report.summary?.substitute_teachers_count ?? 0 }}</div>
                      <div class="small text-muted">معلم بديل</div>
                    </div>
                  </div>
                </div>

                <h6 class="fw-bold mb-2">توزيع الحمل على المعلمين البدلاء</h6>
                <div v-if="report.summary?.distribution?.length" class="table-responsive mb-4">
                  <table class="table table-sm tt-coverage-plan-table">
                    <thead>
                      <tr>
                        <th>المعلم البديل</th>
                        <th>عدد الحصص</th>
                        <th>الرصيد</th>
                        <th>الحصص</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="row in report.summary.distribution" :key="row.teacher_id">
                        <td class="fw-semibold">{{ row.teacher_name }}</td>
                        <td>{{ row.coverage_count }}</td>
                        <td>
                          <span class="badge" :class="balanceBadgeClass(row.coverage_balance)">
                            {{ row.balance_label }}
                          </span>
                        </td>
                        <td class="small text-muted">
                          <span
                            v-for="(p, pi) in row.periods"
                            :key="pi"
                            class="d-inline-block me-2"
                          >
                            {{ p.period_number }} — {{ p.subject_name }}
                          </span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <p v-else class="small text-muted">لا توجد تعيينات احتياطية مسجّلة لهذا اليوم.</p>

                <h6 class="fw-bold mb-2">تفاصيل التعيينات</h6>
                <div v-if="report.assignments?.length" class="table-responsive">
                  <table class="table table-sm tt-coverage-plan-table">
                    <thead>
                      <tr>
                        <th>الحصة</th>
                        <th>الوقت</th>
                        <th>المادة / الفصل</th>
                        <th>الأصلي</th>
                        <th>البديل</th>
                        <th>الحالة</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(row, i) in report.assignments" :key="i">
                        <td>{{ row.period_number }}</td>
                        <td>{{ row.time }}</td>
                        <td>{{ row.subject_name }} — {{ row.class_name }}</td>
                        <td class="text-danger">{{ row.absent_teacher_name }}</td>
                        <td class="text-primary fw-semibold">{{ row.replacement_teacher_name }}</td>
                        <td>
                          <span
                            class="badge"
                            :class="row.status === 'approved' ? 'bg-success' : 'bg-warning text-dark'"
                          >
                            {{ row.status_label }}
                          </span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </template>

              <p v-else class="text-muted text-center py-4">لا توجد بيانات للتقرير.</p>
            </div>

            <div class="modal-footer border-0">
              <button type="button" class="btn btn-secondary-soft" @click="emit('close')">إغلاق</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
