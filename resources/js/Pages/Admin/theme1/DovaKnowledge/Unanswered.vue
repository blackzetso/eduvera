<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import DovaSidebar from '@/Pages/Admin/theme1/DovaKnowledge/Partials/Sidebar.vue'
import AnswerGapModal from '@/Pages/Admin/theme1/DovaKnowledge/Partials/AnswerGapModal.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { ref } from 'vue'
import axios from 'axios'

const props = defineProps({
  questions: Array,
  stats: Object,
  categories: Array,
  filters: Object,
})

const portal = ref(props.filters?.portal || '')
const priority = ref(props.filters?.priority || '')
const status = ref(props.filters?.status || '')

const modalOpen = ref(false)
const modalLoading = ref(false)
const activeGap = ref(null)

function applyFilters() {
  router.get(route('admin.dova-knowledge.unanswered.index'), {
    portal: portal.value || undefined,
    priority: priority.value || undefined,
    status: status.value || undefined,
  }, { preserveState: true })
}

function syncQuestions() {
  router.post(route('admin.dova-knowledge.unanswered.sync'))
}

async function openAnswerModal(question) {
  modalOpen.value = true
  modalLoading.value = true
  activeGap.value = null

  try {
    const { data } = await axios.get(route('admin.dova-knowledge.unanswered.show', question.id))
    activeGap.value = data
  } finally {
    modalLoading.value = false
  }
}

function closeModal() {
  modalOpen.value = false
  activeGap.value = null
}

function onSaved() {
  router.reload({ only: ['questions', 'stats'] })
}

function ignoreQuestion(id) {
  if (!confirm('تجاهل هذا السؤال؟ لن يظهر في قائمة الأسئلة المفتوحة.')) return
  router.post(route('admin.dova-knowledge.unanswered.ignore', id), {}, {
    preserveScroll: true,
    onSuccess: () => router.reload({ only: ['questions', 'stats'] }),
  })
}

function createFaqLink(id) {
  return route('admin.dova-knowledge.gaps.create-faq', id)
}

function statusBadge(s) {
  return {
    unanswered: 'bg-danger',
    draft: 'bg-secondary',
    pending_review: 'bg-warning text-dark',
    published: 'bg-success',
    ignored: 'bg-dark',
  }[s] || 'bg-light text-dark'
}

function priorityBadge(p) {
  return { high: 'bg-danger', medium: 'bg-warning text-dark', low: 'bg-secondary' }[p] || 'bg-light text-dark'
}
</script>

<template>
  <Head title="أسئلة بلا إجابة — دوفا" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <DovaSidebar />
        <div class="col-lg-9">
          <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
              <h1 class="h3 mb-1">أسئلة بلا إجابة</h1>
              <p class="text-muted small mb-0">حوّل كل سؤال بلا إجابة إلى معرفة قابلة لإعادة الاستخدام</p>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm" @click="syncQuestions">
              <i class="bi bi-arrow-repeat me-1" /> تحديث القائمة
            </button>
          </div>

          <div class="row g-3 mb-4">
            <div class="col-md-4 col-lg-2">
              <div class="card border"><div class="card-body p-3"><div class="small text-muted">مفتوحة</div><div class="h4 mb-0 text-danger">{{ stats?.openQuestions ?? 0 }}</div></div></div>
            </div>
            <div class="col-md-4 col-lg-2">
              <div class="card border"><div class="card-body p-3"><div class="small text-muted">تم حلها</div><div class="h4 mb-0 text-success">{{ stats?.resolvedQuestions ?? 0 }}</div></div></div>
            </div>
            <div class="col-md-4 col-lg-2">
              <div class="card border"><div class="card-body p-3"><div class="small text-muted">متجاهلة</div><div class="h4 mb-0">{{ stats?.ignoredQuestions ?? 0 }}</div></div></div>
            </div>
            <div class="col-md-4 col-lg-2">
              <div class="card border"><div class="card-body p-3"><div class="small text-muted">حُلت هذا الشهر</div><div class="h4 mb-0 text-primary">{{ stats?.resolvedThisMonth ?? 0 }}</div></div></div>
            </div>
            <div class="col-md-4 col-lg-2">
              <div class="card border"><div class="card-body p-3"><div class="small text-muted">نسبة التحويل</div><div class="h4 mb-0">{{ stats?.conversionRate ?? 0 }}%</div></div></div>
            </div>
            <div class="col-md-4 col-lg-2">
              <div class="card border"><div class="card-body p-3"><div class="small text-muted">استفسارات خام</div><div class="h4 mb-0">{{ stats?.totalUnansweredQueries ?? 0 }}</div></div></div>
            </div>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-md-3">
              <select v-model="portal" class="form-select form-select-sm" @change="applyFilters">
                <option value="">كل البوابات</option>
                <option value="public">الموقع</option>
                <option value="admin">الإدارة</option>
                <option value="guardian">أولياء الأمور</option>
              </select>
            </div>
            <div class="col-md-3">
              <select v-model="priority" class="form-select form-select-sm" @change="applyFilters">
                <option value="">كل الأولويات</option>
                <option value="high">عالية</option>
                <option value="medium">متوسطة</option>
                <option value="low">منخفضة</option>
              </select>
            </div>
            <div class="col-md-3">
              <select v-model="status" class="form-select form-select-sm" @change="applyFilters">
                <option value="">كل الحالات</option>
                <option value="unanswered">بلا إجابة</option>
                <option value="draft">مسودة</option>
                <option value="pending_review">قيد المراجعة</option>
              </select>
            </div>
          </div>

          <div class="table-responsive border rounded">
            <table class="table table-sm mb-0 align-middle">
              <thead>
                <tr>
                  <th>السؤال</th>
                  <th>المصدر</th>
                  <th>التكرار</th>
                  <th>أول ظهور</th>
                  <th>آخر ظهور</th>
                  <th>الحالة</th>
                  <th>الأولوية</th>
                  <th class="text-end">إجراءات</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="q in questions" :key="q.id">
                  <td>
                    <div class="fw-semibold">{{ q.question }}</div>
                    <div v-for="(sample, i) in (q.sampleQuestions || []).slice(1, 3)" :key="i" class="small text-muted">«{{ sample }}»</div>
                  </td>
                  <td><span class="badge bg-light text-dark">{{ q.portalLabel }}</span></td>
                  <td><strong>{{ q.frequency }}</strong></td>
                  <td class="small text-muted">{{ q.firstSeen }}</td>
                  <td class="small text-muted">{{ q.lastSeen }}</td>
                  <td><span class="badge" :class="statusBadge(q.status)">{{ q.statusLabel }}</span></td>
                  <td><span class="badge" :class="priorityBadge(q.priority)">{{ q.priority }}</span></td>
                  <td class="text-end text-nowrap">
                    <button type="button" class="btn btn-sm btn-primary" @click="openAnswerModal(q)">
                      الإجابة على السؤال
                    </button>
                    <Link :href="createFaqLink(q.id)" class="btn btn-sm btn-outline-primary ms-1">
                      إنشاء FAQ
                    </Link>
                    <button type="button" class="btn btn-sm btn-outline-secondary ms-1" @click="ignoreQuestion(q.id)">
                      تجاهل
                    </button>
                  </td>
                </tr>
                <tr v-if="!questions?.length">
                  <td colspan="8" class="text-center text-muted py-5">
                    لا توجد أسئلة مفتوحة — رائع! أو اضغط «تحديث القائمة» لمزامنة الاستفسارات الجديدة.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <AnswerGapModal
      :show="modalOpen"
      :gap="activeGap"
      :categories="categories"
      :loading="modalLoading"
      @close="closeModal"
      @saved="onSaved"
    />
  </AppLayout>
</template>
