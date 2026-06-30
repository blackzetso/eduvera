<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import DovaSidebar from '@/Pages/Admin/theme1/DovaKnowledge/Partials/Sidebar.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { ref } from 'vue'

const props = defineProps({
  gaps: Array,
  categories: Array,
  filters: Object,
})

const priority = ref(props.filters.priority || '')
const portal = ref(props.filters.portal || '')

function applyFilters() {
  router.get(route('admin.dova-knowledge.gaps.index'), {
    priority: priority.value || undefined,
    portal: portal.value || undefined,
  }, { preserveState: true })
}

function syncGaps() {
  router.post(route('admin.dova-knowledge.gaps.sync'))
}

function dismissGap(id) {
  if (!confirm('تجاهل هذه الفجوة؟')) return
  router.post(route('admin.dova-knowledge.gaps.dismiss', id))
}

function priorityBadge(p) {
  return { high: 'bg-danger', medium: 'bg-warning text-dark', low: 'bg-secondary' }[p] || 'bg-light text-dark'
}
</script>

<template>
  <Head title="فجوات المعرفة — دوفا" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <DovaSidebar />
        <div class="col-lg-9">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
              <h1 class="h3 mb-1">فجوات المعرفة</h1>
              <p class="text-muted small mb-0">أسئلة متكررة بلا إجابة — حوّلها إلى أسئلة شائعة</p>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm" @click="syncGaps">
              <i class="bi bi-arrow-repeat me-1" /> تحديث الفجوات
            </button>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-md-3">
              <select v-model="priority" class="form-select form-select-sm" @change="applyFilters">
                <option value="">كل الأولويات</option>
                <option value="high">عالية</option>
                <option value="medium">متوسطة</option>
                <option value="low">منخفضة</option>
              </select>
            </div>
            <div class="col-md-3">
              <select v-model="portal" class="form-select form-select-sm" @change="applyFilters">
                <option value="">كل البوابات</option>
                <option value="public">الموقع</option>
                <option value="admin">الإدارة</option>
                <option value="guardian">أولياء الأمور</option>
              </select>
            </div>
          </div>

          <div class="table-responsive border rounded">
            <table class="table table-sm mb-0">
              <thead>
                <tr>
                  <th>الموضوع</th>
                  <th>التكرار</th>
                  <th>آخر سؤال</th>
                  <th>البوابة</th>
                  <th>تصنيف مقترح</th>
                  <th>الأولوية</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="g in gaps" :key="g.id">
                  <td>
                    <div class="fw-semibold">{{ g.topic }}</div>
                    <div v-for="(q, i) in (g.sampleQuestions || []).slice(0, 2)" :key="i" class="small text-muted">«{{ q }}»</div>
                  </td>
                  <td><strong>{{ g.frequency }}</strong></td>
                  <td class="small text-muted">{{ g.lastAsked }}</td>
                  <td><span class="badge bg-light text-dark">{{ g.portal }}</span></td>
                  <td>{{ g.suggestedCategory }}</td>
                  <td><span class="badge" :class="priorityBadge(g.priority)">{{ g.priority }}</span></td>
                  <td class="text-end text-nowrap">
                    <Link
                      v-if="!g.hasFaq"
                      :href="route('admin.dova-knowledge.gaps.create-faq', g.id)"
                      class="btn btn-sm btn-primary"
                    >
                      إنشاء FAQ
                    </Link>
                    <span v-else class="badge bg-success me-1">قيد المعالجة</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary ms-1" @click="dismissGap(g.id)">تجاهل</button>
                  </td>
                </tr>
                <tr v-if="!gaps?.length">
                  <td colspan="7" class="text-center text-muted py-4">لا توجد فجوات معرفية — رائع!</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
