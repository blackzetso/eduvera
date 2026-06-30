<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import DovaSidebar from '@/Pages/Admin/theme1/DovaKnowledge/Partials/Sidebar.vue'
import { Head, Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

defineProps({
  overview: Object,
  sources: Array,
})
</script>

<template>
  <Head title="مركز معرفة دوفا" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <DovaSidebar />
        <div class="col-lg-9">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
              <h1 class="h3 mb-1">مركز معرفة دوفا</h1>
              <p class="text-muted small mb-0">إدارة ومراقبة وتحليل معرفة المساعد الذكي</p>
            </div>
            <Link :href="route('admin.dova-knowledge.sync.index')" class="btn btn-primary btn-sm">
              <i class="bi bi-arrow-repeat me-1" /> مزامنة
            </Link>
          </div>

          <div class="row g-3 mb-4">
            <div class="col-md-4 col-lg-3">
              <div class="card border h-100">
                <div class="card-body">
                  <div class="text-muted small">مصادر المعرفة</div>
                  <div class="h4 mb-0">{{ overview.totalSources }}</div>
                  <div class="small text-success">{{ overview.indexedSources }} مفهرس</div>
                </div>
              </div>
            </div>
            <div class="col-md-4 col-lg-3">
              <div class="card border h-100">
                <div class="card-body">
                  <div class="text-muted small">السجلات المفهرسة</div>
                  <div class="h4 mb-0">{{ overview.totalRecords }}</div>
                </div>
              </div>
            </div>
            <div class="col-md-4 col-lg-3">
              <div class="card border h-100">
                <div class="card-body">
                  <div class="text-muted small">آخر مزامنة</div>
                  <div class="h6 mb-0">{{ overview.lastSyncLabel }}</div>
                </div>
              </div>
            </div>
            <div class="col-md-4 col-lg-3">
              <div class="card border h-100 bg-primary bg-opacity-10">
                <div class="card-body">
                  <div class="text-muted small">صحة المعرفة</div>
                  <div class="h3 mb-0 text-primary">{{ overview.healthScore }}<span class="fs-6">/100</span></div>
                </div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="card border h-100">
                <div class="card-body">
                  <div class="text-muted small">إجمالي الأسئلة</div>
                  <div class="h4 mb-0">{{ overview.totalQuestionsAsked }}</div>
                </div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="card border h-100">
                <div class="card-body">
                  <div class="text-muted small">تمت الإجابة</div>
                  <div class="h4 mb-0 text-success">{{ overview.totalAnswered }}</div>
                </div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="card border h-100">
                <div class="card-body">
                  <div class="text-muted small">بدون إجابة</div>
                  <div class="h4 mb-0 text-danger">{{ overview.totalUnanswered }}</div>
                </div>
              </div>
            </div>
          </div>

          <div class="card border">
            <div class="card-header d-flex justify-content-between align-items-center">
              <span class="fw-semibold">مصادر المعرفة</span>
              <Link :href="route('admin.dova-knowledge.sources.index')" class="btn btn-sm btn-link">عرض الكل</Link>
            </div>
            <div class="table-responsive">
              <table class="table table-sm mb-0">
                <thead>
                  <tr>
                    <th>المصدر</th>
                    <th>الحالة</th>
                    <th>السجلات</th>
                    <th>آخر مزامنة</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="s in sources" :key="s.slug">
                    <td>{{ s.name }}</td>
                    <td>
                      <span class="badge" :class="s.status === 'indexed' ? 'bg-success' : 'bg-secondary'">
                        {{ s.statusLabel }}
                      </span>
                    </td>
                    <td>{{ s.recordCount }}</td>
                    <td class="text-muted small">{{ s.lastSyncedLabel }}</td>
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
