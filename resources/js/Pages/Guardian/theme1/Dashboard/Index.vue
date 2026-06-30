<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import GuardianDashboardLayout from '@/Layouts/GuardianDashboardLayout.vue'

defineProps({
  guardian: Object,
  children: Array,
  summaries: Object,
})
</script>

<template>
  <Head title="لوحة ولي الأمر" />
  <GuardianDashboardLayout :guardian="guardian" :children="children" active-menu="dashboard">
    <div class="row g-4 mb-4">
      <div class="col-sm-6 col-lg-4">
        <div class="d-flex justify-content-center align-items-center p-4 bg-primary bg-opacity-10 rounded-3">
          <span class="display-6 text-primary mb-0"><i class="bi bi-people fa-fw" /></span>
          <div class="ms-4">
            <h5 class="mb-0 fw-bold">{{ children.length }}</h5>
            <span class="mb-0 h6 fw-light">عدد الأبناء</span>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-4">
        <div class="d-flex justify-content-center align-items-center p-4 bg-warning bg-opacity-15 rounded-3">
          <span class="display-6 text-warning mb-0"><i class="bi bi-calendar-x fa-fw" /></span>
          <div class="ms-4">
            <h5 class="mb-0 fw-bold">
              {{ Object.values(summaries).reduce((a, s) => a + (s.attendance?.absent ?? 0), 0) }}
            </h5>
            <span class="mb-0 h6 fw-light">إجمالي الغياب (السنة)</span>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-4">
        <div class="d-flex justify-content-center align-items-center p-4 bg-success bg-opacity-10 rounded-3">
          <span class="display-6 text-success mb-0"><i class="bi bi-journal-check fa-fw" /></span>
          <div class="ms-4">
            <h5 class="mb-0 fw-bold">{{ Object.values(summaries).reduce((a, s) => a + (s.grades_count ?? 0), 0) }}</h5>
            <span class="mb-0 h6 fw-light">تقييمات مسجلة</span>
          </div>
        </div>
      </div>
    </div>

    <h3 class="mb-3">أبنائي</h3>
    <div class="row g-4">
      <div v-for="child in children" :key="child.id" class="col-md-6">
        <div class="card border h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-3">
              <div>
                <h5 class="card-title mb-1">{{ child.name }}</h5>
                <p class="small text-muted mb-0">{{ child.category?.name ?? '—' }}</p>
                <p v-if="child.student_code" class="small text-muted mb-0">كود: {{ child.student_code }}</p>
              </div>
              <span
                v-if="summaries[child.id]?.attendance_alert"
                class="badge"
                :class="summaries[child.id].attendance_alert.level === 'critical' ? 'bg-danger' : 'bg-warning text-dark'"
              >
                تنبيه حضور
              </span>
            </div>

            <div class="row g-2 text-center mb-3">
              <div class="col-4">
                <div class="p-2 bg-light rounded">
                  <div class="fw-bold text-success">{{ summaries[child.id]?.attendance?.present ?? 0 }}</div>
                  <small class="text-muted">حاضر</small>
                </div>
              </div>
              <div class="col-4">
                <div class="p-2 bg-light rounded">
                  <div class="fw-bold text-danger">{{ summaries[child.id]?.attendance?.absent ?? 0 }}</div>
                  <small class="text-muted">غائب</small>
                </div>
              </div>
              <div class="col-4">
                <div class="p-2 bg-light rounded">
                  <div class="fw-bold text-primary">
                    {{ summaries[child.id]?.grades_average != null ? summaries[child.id].grades_average + '%' : '—' }}
                  </div>
                  <small class="text-muted">متوسط الدرجات</small>
                </div>
              </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
              <Link :href="route('guardian.students.overview', child.id)" class="btn btn-sm btn-primary">نظرة عامة</Link>
              <Link :href="route('guardian.students.attendance', child.id)" class="btn btn-sm btn-outline-primary">الحضور</Link>
              <Link :href="route('guardian.students.grades', child.id)" class="btn btn-sm btn-outline-secondary">الدرجات</Link>
              <Link :href="route('guardian.students.behavior', child.id)" class="btn btn-sm btn-outline-secondary">السلوك</Link>
            </div>
          </div>
        </div>
      </div>
    </div>

    <p v-if="!children.length" class="text-muted">لا يوجد أبناء مرتبطون بهذا الحساب. تواصل مع إدارة المدرسة.</p>
  </GuardianDashboardLayout>
</template>
