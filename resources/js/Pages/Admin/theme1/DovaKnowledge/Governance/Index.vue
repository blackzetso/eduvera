<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import DovaSidebar from '@/Pages/Admin/theme1/DovaKnowledge/Partials/Sidebar.vue'
import { Head, Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

defineProps({
  dashboard: Object,
})

function statusBadge(status) {
  return {
    active: 'bg-success',
    needs_review: 'bg-warning text-dark',
    deprecated: 'bg-secondary',
    archived: 'bg-dark',
  }[status] || 'bg-light text-dark'
}
</script>

<template>
  <Head title="حوكمة المعرفة — دوفا" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <DovaSidebar />
        <div class="col-lg-9">
          <h1 class="h3 mb-1">حوكمة المعرفة</h1>
          <p class="text-muted small mb-4">مراقبة جودة المعرفة وملكيتها وجداول المراجعة</p>

          <h2 class="h6 text-muted mb-3">صحة المعرفة</h2>
          <div class="row g-3 mb-4">
            <div class="col-md-4 col-lg-2">
              <div class="card border"><div class="card-body p-3"><div class="small text-muted">إجمالي الأسئلة</div><div class="h4 mb-0">{{ dashboard.health.totalFaqs }}</div></div></div>
            </div>
            <div class="col-md-4 col-lg-2">
              <div class="card border"><div class="card-body p-3"><div class="small text-muted">نشطة</div><div class="h4 mb-0 text-success">{{ dashboard.health.activeFaqs }}</div></div></div>
            </div>
            <div class="col-md-4 col-lg-2">
              <div class="card border"><div class="card-body p-3"><div class="small text-muted">مستحقة للمراجعة</div><div class="h4 mb-0 text-warning">{{ dashboard.health.dueForReview }}</div></div></div>
            </div>
            <div class="col-md-4 col-lg-2">
              <div class="card border"><div class="card-body p-3"><div class="small text-muted">مهملة</div><div class="h4 mb-0">{{ dashboard.health.deprecatedFaqs }}</div></div></div>
            </div>
            <div class="col-md-4 col-lg-2">
              <div class="card border"><div class="card-body p-3"><div class="small text-muted">مؤرشفة</div><div class="h4 mb-0">{{ dashboard.health.archivedFaqs }}</div></div></div>
            </div>
          </div>

          <h2 class="h6 text-muted mb-3">مقاييس المراجعة</h2>
          <div class="row g-3 mb-4">
            <div class="col-md-4">
              <div class="card border"><div class="card-body p-3"><div class="small text-muted">رُوجعت هذا الشهر</div><div class="h4 mb-0 text-primary">{{ dashboard.health.reviewedThisMonth }}</div></div></div>
            </div>
            <div class="col-md-4">
              <div class="card border"><div class="card-body p-3"><div class="small text-muted">مراجعات متأخرة</div><div class="h4 mb-0 text-danger">{{ dashboard.health.overdueReviews }}</div></div></div>
            </div>
            <div class="col-md-4">
              <div class="card border"><div class="card-body p-3"><div class="small text-muted">متوسط عمر السؤال</div><div class="h4 mb-0">{{ dashboard.health.averageFaqAgeDays }} يوم</div></div></div>
            </div>
          </div>

          <div class="row g-4">
            <div class="col-lg-7">
              <div class="card border">
                <div class="card-header fw-semibold d-flex justify-content-between">
                  <span>قائمة المراجعة</span>
                  <Link :href="route('admin.dova-knowledge.faqs.index', { review_filter: 'needs_review' })" class="small">عرض الكل</Link>
                </div>
                <div class="table-responsive">
                  <table class="table table-sm mb-0">
                    <thead>
                      <tr>
                        <th>السؤال</th>
                        <th>المالك</th>
                        <th>المراجعة القادمة</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="item in dashboard.reviewQueue" :key="item.id">
                        <td class="small">{{ item.questionEn }}</td>
                        <td class="small">{{ item.owner || '—' }}</td>
                        <td class="small text-muted">{{ item.nextReview }}</td>
                        <td class="text-end">
                          <Link :href="route('admin.dova-knowledge.faqs.edit', item.id)" class="btn btn-sm btn-outline-primary">مراجعة</Link>
                        </td>
                      </tr>
                      <tr v-if="!dashboard.reviewQueue?.length">
                        <td colspan="4" class="text-center text-muted py-3">لا توجد عناصر في قائمة المراجعة.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <div class="col-lg-5">
              <div class="card border mb-4">
                <div class="card-header fw-semibold">توزيع الملكية</div>
                <ul class="list-group list-group-flush">
                  <li v-for="o in dashboard.ownershipDistribution" :key="o.ownerId" class="list-group-item small d-flex justify-content-between">
                    <span>{{ o.ownerName }}</span>
                    <span class="badge bg-primary">{{ o.total }}</span>
                  </li>
                  <li v-if="!dashboard.ownershipDistribution?.length" class="list-group-item small text-muted">لا يوجد مالكو معرفة محددون بعد.</li>
                </ul>
              </div>

              <div class="card border">
                <div class="card-header fw-semibold">تحليل عمر المعرفة</div>
                <ul class="list-group list-group-flush">
                  <li v-for="bucket in dashboard.agingAnalysis" :key="bucket.label" class="list-group-item small d-flex justify-content-between">
                    <span>{{ bucket.label }}</span>
                    <span class="badge bg-light text-dark">{{ bucket.count }}</span>
                  </li>
                </ul>
              </div>
            </div>
          </div>

          <div class="card border mt-4">
            <div class="card-header fw-semibold">صحة التصنيفات</div>
            <div class="table-responsive">
              <table class="table table-sm mb-0">
                <thead>
                  <tr>
                    <th>التصنيف</th>
                    <th>الإجمالي</th>
                    <th>نشط</th>
                    <th>يحتاج مراجعة</th>
                    <th>مهمل</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="c in dashboard.categoryHealth" :key="c.category">
                    <td>{{ c.category }}</td>
                    <td>{{ c.total }}</td>
                    <td class="text-success">{{ c.active }}</td>
                    <td class="text-warning">{{ c.needsReview }}</td>
                    <td>{{ c.deprecated }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
