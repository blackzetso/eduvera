<script setup>
import { onMounted, ref } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import DovaSidebar from '@/Pages/Admin/theme1/DovaKnowledge/Partials/Sidebar.vue'
import { Head } from '@inertiajs/vue3'
import Chart from 'chart.js/auto'

const props = defineProps({ stats: Object })

const trendRef = ref(null)
let trendChart = null

onMounted(() => {
  if (!trendRef.value || !props.stats?.trend?.length) return

  trendChart = new Chart(trendRef.value, {
    type: 'line',
    data: {
      labels: props.stats.trend.map((d) => d.date),
      datasets: [
        {
          label: 'طلبات AI',
          data: props.stats.trend.map((d) => d.requests),
          borderColor: '#0d6efd',
          yAxisID: 'y',
          tension: 0.2,
        },
        {
          label: 'التكلفة ($)',
          data: props.stats.trend.map((d) => d.cost),
          borderColor: '#198754',
          yAxisID: 'y1',
          tension: 0.2,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: { beginAtZero: true, position: 'left' },
        y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false } },
      },
    },
  })
})
</script>

<template>
  <Head title="استخدام الذكاء الاصطناعي — دوفا" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <DovaSidebar />
        <div class="col-lg-9">
          <h1 class="h3 mb-2">استخدام الذكاء الاصطناعي</h1>
          <p class="text-muted small mb-4">
            النموذج: {{ stats.model }}
            <span class="badge ms-2" :class="stats.aiEnabled ? 'bg-success' : 'bg-secondary'">
              {{ stats.aiEnabled ? 'مفعّل' : 'معطّل' }}
            </span>
          </p>

          <div class="row g-3 mb-4">
            <div class="col-md-3">
              <div class="card border p-3"><div class="small text-muted">إجمالي الطلبات</div><div class="h4 mb-0">{{ stats.totalRequests }}</div></div>
            </div>
            <div class="col-md-3">
              <div class="card border p-3"><div class="small text-muted">متوسط الرموز</div><div class="h4 mb-0">{{ stats.averageTokens }}</div></div>
            </div>
            <div class="col-md-3">
              <div class="card border p-3"><div class="small text-muted">متوسط الزمن</div><div class="h4 mb-0">{{ stats.averageResponseMs }} ms</div></div>
            </div>
            <div class="col-md-3">
              <div class="card border p-3"><div class="small text-muted">التكلفة التقديرية</div><div class="h4 mb-0">${{ stats.estimatedCost }}</div></div>
            </div>
          </div>

          <div class="row g-4 mb-4">
            <div class="col-lg-8">
              <div class="card border">
                <div class="card-header">اتجاه الاستخدام (30 يوم)</div>
                <div class="card-body" style="height: 260px"><canvas ref="trendRef" /></div>
              </div>
            </div>
            <div class="col-lg-4">
              <div class="card border">
                <div class="card-header">حسب النوع</div>
                <ul class="list-group list-group-flush">
                  <li v-for="(count, type) in stats.byType" :key="type" class="list-group-item d-flex justify-content-between small">
                    <span>{{ type }}</span><span class="badge bg-primary">{{ count }}</span>
                  </li>
                  <li v-if="!Object.keys(stats.byType || {}).length" class="list-group-item text-muted text-center">لا توجد بيانات بعد</li>
                </ul>
              </div>
            </div>
          </div>

          <div class="card border">
            <div class="card-header">أكثر المواضيع سؤالاً</div>
            <ul class="list-group list-group-flush">
              <li v-for="(t, i) in stats.topTopics" :key="i" class="list-group-item d-flex justify-content-between small">
                <span>{{ t.question }}</span><span class="badge bg-light text-dark">{{ t.count }}</span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
