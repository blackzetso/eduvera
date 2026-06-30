<script setup>
import { onMounted, ref } from 'vue'
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import DovaSidebar from '@/Pages/Admin/theme1/DovaKnowledge/Partials/Sidebar.vue'
import { Head } from '@inertiajs/vue3'
import Chart from 'chart.js/auto'

const props = defineProps({
  overview: Object,
  charts: Object,
})

const trendRef = ref(null)
const sourcesRef = ref(null)
const topicsRef = ref(null)
let trendChart = null
let sourcesChart = null
let topicsChart = null

onMounted(() => {
  if (trendRef.value && props.charts?.unansweredTrend?.length) {
    trendChart = new Chart(trendRef.value, {
      type: 'line',
      data: {
        labels: props.charts.unansweredTrend.map((d) => d.date),
        datasets: [{
          label: 'أسئلة بلا إجابة',
          data: props.charts.unansweredTrend.map((d) => d.count),
          borderColor: 'rgb(220, 53, 69)',
          backgroundColor: 'rgba(220, 53, 69, 0.1)',
          fill: true,
          tension: 0.2,
        }],
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } },
    })
  }

  if (sourcesRef.value && props.charts?.mostUsedSources?.length) {
    sourcesChart = new Chart(sourcesRef.value, {
      type: 'doughnut',
      data: {
        labels: props.charts.mostUsedSources.map((d) => d.source),
        datasets: [{
          data: props.charts.mostUsedSources.map((d) => d.count),
          backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#6f42c1', '#20c997', '#fd7e14', '#6c757d'],
        }],
      },
      options: { responsive: true, maintainAspectRatio: false },
    })
  }

  if (topicsRef.value && props.charts?.mostAskedTopics?.length) {
    topicsChart = new Chart(topicsRef.value, {
      type: 'bar',
      data: {
        labels: props.charts.mostAskedTopics.map((d) => d.topic),
        datasets: [{
          label: 'الأسئلة',
          data: props.charts.mostAskedTopics.map((d) => d.count),
          backgroundColor: 'rgba(13, 110, 253, 0.6)',
        }],
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } },
    })
  }
})
</script>

<template>
  <Head title="تحليلات المعرفة — دوفا" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <DovaSidebar />
        <div class="col-lg-9">
          <h1 class="h3 mb-4">تحليلات المعرفة</h1>

          <div class="row g-3 mb-4">
            <div class="col-md-3">
              <div class="card border text-center p-3">
                <div class="text-muted small">صحة المعرفة</div>
                <div class="display-6 text-primary">{{ overview.healthScore }}</div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="card border text-center p-3">
                <div class="text-muted small">تغطية الإجابات</div>
                <div class="display-6">{{ charts.knowledgeCoverage }}%</div>
                <div class="small text-muted">{{ charts.totalAnswered }} / {{ charts.totalAsked }}</div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="card border text-center p-3">
                <div class="text-muted small">إجمالي الأسئلة</div>
                <div class="display-6">{{ overview.totalQuestionsAsked }}</div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="card border text-center p-3">
                <div class="text-muted small">بدون إجابة</div>
                <div class="display-6 text-danger">{{ overview.totalUnanswered }}</div>
              </div>
            </div>
          </div>

          <div class="row g-3 mb-4" v-if="charts.voice">
            <div class="col-12">
              <h2 class="h5 mb-0">تحليلات الصوت</h2>
            </div>
            <div class="col-md-3">
              <div class="card border text-center p-3">
                <div class="text-muted small">أسئلة صوتية</div>
                <div class="display-6">{{ charts.voice.voiceQuestions }}</div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="card border text-center p-3">
                <div class="text-muted small">أسئلة نصية</div>
                <div class="display-6">{{ charts.voice.textQuestions }}</div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="card border text-center p-3">
                <div class="text-muted small">نجاح التعرف الصوتي</div>
                <div class="display-6">{{ charts.voice.recognitionSuccessRate }}%</div>
                <div class="small text-muted">{{ charts.voice.successfulRecognitions }} / {{ charts.voice.totalRecognitions }}</div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="card border text-center p-3">
                <div class="text-muted small">متوسط مدة التسجيل</div>
                <div class="display-6">{{ charts.voice.averageRecordingDurationLabel }}</div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="card border text-center p-3">
                <div class="text-muted small">معدل فشل التعرف</div>
                <div class="display-6 text-danger">{{ charts.voice.recognitionFailureRate }}%</div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="card border text-center p-3">
                <div class="text-muted small">عربي vs إنجليزي</div>
                <div class="small mt-2">
                  <span class="badge bg-primary-subtle text-primary border me-1">AR {{ charts.voice.arabicVsEnglish?.arabicPercent }}%</span>
                  <span class="badge bg-secondary-subtle text-secondary border">EN {{ charts.voice.arabicVsEnglish?.englishPercent }}%</span>
                </div>
                <div class="small text-muted mt-1">
                  {{ charts.voice.arabicVsEnglish?.arabic }} / {{ charts.voice.arabicVsEnglish?.english }}
                </div>
              </div>
            </div>
            <div class="col-lg-6">
              <div class="card border">
                <div class="card-header">أكثر الأسئلة الصوتية شيوعاً</div>
                <ul class="list-group list-group-flush">
                  <li
                    v-for="(q, i) in charts.voice.mostCommonVoiceQuestions"
                    :key="i"
                    class="list-group-item d-flex justify-content-between small"
                  >
                    <span>{{ q.question }}</span>
                    <span class="badge bg-info">{{ q.count }}</span>
                  </li>
                  <li v-if="!charts.voice.mostCommonVoiceQuestions?.length" class="list-group-item text-muted text-center">لا توجد بيانات بعد</li>
                </ul>
              </div>
            </div>
            <div class="col-lg-6">
              <div class="card border">
                <div class="card-header">لغات الصوت</div>
                <div class="card-body d-flex flex-wrap gap-2">
                  <span
                    v-for="(lang, i) in charts.voice.languageBreakdown"
                    :key="i"
                    class="badge bg-info-subtle text-info border"
                  >
                    {{ lang.language }} ({{ lang.count }})
                  </span>
                  <span v-if="!charts.voice.languageBreakdown?.length" class="text-muted small">—</span>
                </div>
              </div>
            </div>
          </div>

          <div class="row g-4 mb-4">
            <div class="col-lg-8">
              <div class="card border">
                <div class="card-header">اتجاه الأسئلة بلا إجابة (30 يوم)</div>
                <div class="card-body" style="height: 260px">
                  <canvas ref="trendRef" />
                </div>
              </div>
            </div>
            <div class="col-lg-4">
              <div class="card border">
                <div class="card-header">أكثر المصادر استخداماً</div>
                <div class="card-body" style="height: 260px">
                  <canvas ref="sourcesRef" />
                </div>
              </div>
            </div>
          </div>

          <div class="card border mb-4" v-if="charts.topMissingTopics?.length">
            <div class="card-header">أبرز المواضيع الناقصة</div>
            <div class="card-body">
              <div class="d-flex flex-wrap gap-2">
                <span v-for="(t, i) in charts.topMissingTopics" :key="i" class="badge bg-danger-subtle text-danger border">
                  {{ t.topic }} ({{ t.frequency }})
                </span>
              </div>
            </div>
          </div>

          <div class="row g-4">
            <div class="col-lg-6">
              <div class="card border">
                <div class="card-header">أكثر المواضيع سؤالاً</div>
                <div class="card-body" style="height: 240px">
                  <canvas ref="topicsRef" />
                </div>
              </div>
            </div>
            <div class="col-lg-6">
              <div class="card border">
                <div class="card-header">أبرز أسئلة المستخدمين</div>
                <ul class="list-group list-group-flush">
                  <li v-for="(q, i) in charts.topQuestions" :key="i" class="list-group-item d-flex justify-content-between small">
                    <span>{{ q.question }}</span>
                    <span class="badge bg-primary">{{ q.count }}</span>
                  </li>
                  <li v-if="!charts.topQuestions?.length" class="list-group-item text-muted text-center">لا توجد بيانات بعد</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
