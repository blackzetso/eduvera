<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import DovaSidebar from '@/Pages/Admin/theme1/DovaKnowledge/Partials/Sidebar.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { ref } from 'vue'

const props = defineProps({
  faqs: Object,
  categories: Array,
  owners: Array,
  filters: Object,
})

const search = ref(props.filters.search || '')
const status = ref(props.filters.status || '')
const categoryId = ref(props.filters.category_id || '')
const reviewFilter = ref(props.filters.review_filter || '')
const ownerId = ref(props.filters.owner_user_id || '')

function applyFilters() {
  router.get(route('admin.dova-knowledge.faqs.index'), {
    search: search.value || undefined,
    status: status.value || undefined,
    category_id: categoryId.value || undefined,
    review_filter: reviewFilter.value || undefined,
    owner_user_id: ownerId.value || undefined,
  }, { preserveState: true })
}

function publishFaq(id) {
  if (!confirm('نشر هذا السؤال وإعادة فهرسة المعرفة؟')) return
  router.post(route('admin.dova-knowledge.faqs.publish', id))
}

function archiveFaq(id) {
  router.post(route('admin.dova-knowledge.faqs.archive', id))
}

function completeReview(id) {
  router.post(route('admin.dova-knowledge.faqs.complete-review', id))
}

function deprecateFaq(id) {
  if (!confirm('وضع هذا السؤال كمعرفة مهملة؟')) return
  router.post(route('admin.dova-knowledge.faqs.deprecate', id))
}

function deleteFaq(id) {
  if (!confirm('حذف هذا السؤال؟')) return
  router.delete(route('admin.dova-knowledge.faqs.destroy', id))
}

function knowledgeBadge(status) {
  return {
    active: 'bg-success',
    needs_review: 'bg-warning text-dark',
    deprecated: 'bg-secondary',
    archived: 'bg-dark',
  }[status] || 'bg-light text-dark'
}
</script>

<template>
  <Head title="قائمة الأسئلة الشائعة" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <DovaSidebar />
        <div class="col-lg-9">
          <div class="d-flex justify-content-between mb-3">
            <h1 class="h3 mb-0">الأسئلة الشائعة</h1>
            <Link :href="route('admin.dova-knowledge.faqs.create')" class="btn btn-primary btn-sm">إنشاء</Link>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-md-3">
              <input v-model="search" type="search" class="form-control form-control-sm" placeholder="بحث..." @keyup.enter="applyFilters" />
            </div>
            <div class="col-md-2">
              <select v-model="status" class="form-select form-select-sm" @change="applyFilters">
                <option value="">كل الحالات</option>
                <option value="draft">مسودة</option>
                <option value="review">مراجعة</option>
                <option value="published">منشور</option>
                <option value="archived">مؤرشف</option>
              </select>
            </div>
            <div class="col-md-2">
              <select v-model="reviewFilter" class="form-select form-select-sm" @change="applyFilters">
                <option value="">كل المعرفة</option>
                <option value="active">نشط</option>
                <option value="needs_review">يحتاج مراجعة</option>
                <option value="overdue">متأخر</option>
                <option value="deprecated">مهمل</option>
                <option value="archived">مؤرشف</option>
              </select>
            </div>
            <div class="col-md-2">
              <select v-model="ownerId" class="form-select form-select-sm" @change="applyFilters">
                <option value="">كل المالكين</option>
                <option v-for="o in owners" :key="o.id" :value="o.id">{{ o.name }}</option>
              </select>
            </div>
            <div class="col-md-2">
              <select v-model="categoryId" class="form-select form-select-sm" @change="applyFilters">
                <option value="">كل التصنيفات</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div class="col-md-1">
              <button type="button" class="btn btn-sm btn-outline-secondary w-100" @click="applyFilters">تصفية</button>
            </div>
          </div>

          <div class="table-responsive border rounded">
            <table class="table table-sm mb-0 align-middle">
              <thead>
                <tr>
                  <th>السؤال</th>
                  <th>التصنيف</th>
                  <th>المالك</th>
                  <th>آخر مراجعة</th>
                  <th>المراجعة القادمة</th>
                  <th>حالة المعرفة</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="f in faqs.data" :key="f.id">
                  <td class="small">{{ f.questionEn }}</td>
                  <td>{{ f.category || '—' }}</td>
                  <td class="small">{{ f.owner || '—' }}</td>
                  <td class="small text-muted">{{ f.lastReviewed }}</td>
                  <td class="small text-muted">{{ f.nextReview }}</td>
                  <td><span class="badge" :class="knowledgeBadge(f.knowledgeStatus)">{{ f.knowledgeStatusLabel }}</span></td>
                  <td class="text-end text-nowrap">
                    <Link :href="route('admin.dova-knowledge.faqs.edit', f.id)" class="btn btn-sm btn-light">تعديل</Link>
                    <button v-if="f.status === 'published' && f.knowledgeStatus !== 'active'" type="button" class="btn btn-sm btn-outline-success ms-1" @click="completeReview(f.id)">اكتملت المراجعة</button>
                    <button v-if="f.status !== 'published'" type="button" class="btn btn-sm btn-success ms-1" @click="publishFaq(f.id)">نشر</button>
                    <button v-if="f.status === 'published' && f.knowledgeStatus === 'active'" type="button" class="btn btn-sm btn-outline-warning ms-1" @click="deprecateFaq(f.id)">إهمال</button>
                    <button v-if="f.status === 'published'" type="button" class="btn btn-sm btn-outline-secondary ms-1" @click="archiveFaq(f.id)">أرشفة</button>
                    <button type="button" class="btn btn-sm btn-outline-danger ms-1" @click="deleteFaq(f.id)">حذف</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
