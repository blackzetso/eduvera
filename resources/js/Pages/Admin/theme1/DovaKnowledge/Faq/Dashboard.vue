<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import DovaSidebar from '@/Pages/Admin/theme1/DovaKnowledge/Partials/Sidebar.vue'
import { Head, Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

defineProps({
  stats: Object,
  faqAnalytics: Object,
})
</script>

<template>
  <Head title="لوحة الأسئلة الشائعة — دوفا" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <DovaSidebar />
        <div class="col-lg-9">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">إدارة الأسئلة الشائعة</h1>
            <div class="d-flex gap-2">
              <Link :href="route('admin.dova-knowledge.governance.index')" class="btn btn-outline-secondary btn-sm">حوكمة المعرفة</Link>
              <Link :href="route('admin.dova-knowledge.faqs.create')" class="btn btn-primary btn-sm">إنشاء سؤال</Link>
            </div>
          </div>

          <div class="row g-3 mb-4">
            <div class="col-md-4 col-lg-2">
              <div class="card border"><div class="card-body p-3"><div class="small text-muted">الإجمالي</div><div class="h4 mb-0">{{ stats.total }}</div></div></div>
            </div>
            <div class="col-md-4 col-lg-2">
              <div class="card border"><div class="card-body p-3"><div class="small text-muted">منشور</div><div class="h4 mb-0 text-success">{{ stats.active }}</div></div></div>
            </div>
            <div class="col-md-4 col-lg-2">
              <div class="card border"><div class="card-body p-3"><div class="small text-muted">مسودة</div><div class="h4 mb-0">{{ stats.draft }}</div></div></div>
            </div>
            <div class="col-md-4 col-lg-2">
              <div class="card border"><div class="card-body p-3"><div class="small text-muted">قيد المراجعة</div><div class="h4 mb-0 text-warning">{{ stats.review }}</div></div></div>
            </div>
            <div class="col-md-4 col-lg-2">
              <div class="card border"><div class="card-body p-3"><div class="small text-muted">من فجوات</div><div class="h4 mb-0 text-primary">{{ stats.fromGaps }}</div></div></div>
            </div>
            <div class="col-md-4 col-lg-2">
              <div class="card border"><div class="card-body p-3"><div class="small text-muted">حُلت هذا الشهر</div><div class="h4 mb-0 text-primary">{{ stats.resolution?.resolvedThisMonth ?? 0 }}</div></div></div>
            </div>
          </div>

          <h2 class="h6 text-muted mb-3">صحة المعرفة</h2>
          <div class="row g-3 mb-4">
            <div class="col-md-4 col-lg-2">
              <div class="card border"><div class="card-body p-3"><div class="small text-muted">نشطة</div><div class="h5 mb-0 text-success">{{ stats.governance?.activeFaqs ?? 0 }}</div></div></div>
            </div>
            <div class="col-md-4 col-lg-2">
              <div class="card border"><div class="card-body p-3"><div class="small text-muted">مستحقة للمراجعة</div><div class="h5 mb-0 text-warning">{{ stats.governance?.dueForReview ?? 0 }}</div></div></div>
            </div>
            <div class="col-md-4 col-lg-2">
              <div class="card border"><div class="card-body p-3"><div class="small text-muted">مهملة</div><div class="h5 mb-0">{{ stats.governance?.deprecatedFaqs ?? 0 }}</div></div></div>
            </div>
            <div class="col-md-4 col-lg-2">
              <div class="card border"><div class="card-body p-3"><div class="small text-muted">رُوجعت هذا الشهر</div><div class="h5 mb-0 text-primary">{{ stats.governance?.reviewedThisMonth ?? 0 }}</div></div></div>
            </div>
            <div class="col-md-4 col-lg-2">
              <div class="card border"><div class="card-body p-3"><div class="small text-muted">مراجعات متأخرة</div><div class="h5 mb-0 text-danger">{{ stats.governance?.overdueReviews ?? 0 }}</div></div></div>
            </div>
            <div class="col-md-4 col-lg-2">
              <div class="card border"><div class="card-body p-3"><div class="small text-muted">متوسط العمر</div><div class="h5 mb-0">{{ stats.governance?.averageFaqAgeDays ?? 0 }} يوم</div></div></div>
            </div>
          </div>

          <div class="row g-3 mb-4">
            <div class="col-md-3">
              <div class="card border"><div class="card-body p-3"><div class="small text-muted">أسئلة مفتوحة</div><div class="h5 mb-0 text-danger">{{ stats.resolution?.openQuestions ?? 0 }}</div></div></div>
            </div>
            <div class="col-md-3">
              <div class="card border"><div class="card-body p-3"><div class="small text-muted">تم حلها</div><div class="h5 mb-0 text-success">{{ stats.resolution?.resolvedQuestions ?? 0 }}</div></div></div>
            </div>
            <div class="col-md-3">
              <div class="card border"><div class="card-body p-3"><div class="small text-muted">متجاهلة</div><div class="h5 mb-0">{{ stats.resolution?.ignoredQuestions ?? 0 }}</div></div></div>
            </div>
            <div class="col-md-3">
              <div class="card border"><div class="card-body p-3"><div class="small text-muted">نسبة التحويل</div><div class="h5 mb-0">{{ stats.resolution?.conversionRate ?? 0 }}%</div></div></div>
            </div>
          </div>

          <div class="row g-4">
            <div class="col-lg-6">
              <div class="card border">
                <div class="card-header fw-semibold">الأكثر تكراراً بلا إجابة</div>
                <ul class="list-group list-group-flush">
                  <li v-for="(t, i) in stats.topRepeatedUnanswered" :key="i" class="list-group-item small d-flex justify-content-between">
                    <span>{{ t.topic }}</span>
                    <span class="badge bg-danger">{{ t.frequency }}×</span>
                  </li>
                  <li v-if="!stats.topRepeatedUnanswered?.length" class="list-group-item small text-muted">لا توجد أسئلة مفتوحة.</li>
                </ul>
              </div>
            </div>
            <div class="col-lg-6">
              <div class="card border">
                <div class="card-header fw-semibold">أُضيفت مؤخراً</div>
                <ul class="list-group list-group-flush">
                  <li v-for="f in stats.recent" :key="f.id" class="list-group-item small d-flex justify-content-between">
                    <span>{{ f.questionEn }}</span>
                    <span class="badge bg-light text-dark">{{ f.statusLabel }}</span>
                  </li>
                </ul>
              </div>
            </div>
            <div class="col-lg-6">
              <div class="card border">
                <div class="card-header fw-semibold">الأكثر استخداماً</div>
                <ul class="list-group list-group-flush">
                  <li v-for="f in stats.mostUsed" :key="f.id" class="list-group-item small d-flex justify-content-between">
                    <span>{{ f.question }}</span>
                    <span class="badge bg-primary">{{ f.views }}</span>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
