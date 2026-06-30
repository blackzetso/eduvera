<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import DovaSidebar from '@/Pages/Admin/theme1/DovaKnowledge/Partials/Sidebar.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { computed, ref } from 'vue'

const props = defineProps({
  faq: Object,
  categories: Array,
  owners: Array,
  reviewFrequencies: Array,
  prefill: Object,
})

const customFrequency = ref(false)

const form = useForm({
  question_en: props.faq?.question_en || props.prefill?.question_en || '',
  question_ar: props.faq?.question_ar || props.prefill?.question_ar || '',
  answer_en: props.faq?.answer_en || props.prefill?.answer_en || '',
  answer_ar: props.faq?.answer_ar || props.prefill?.answer_ar || '',
  category_id: props.faq?.category_id || props.prefill?.category_id || '',
  custom_category: '',
  tags: props.faq?.tags || props.prefill?.tags || [],
  status: props.faq?.status || 'draft',
  source: props.faq?.source || props.prefill?.source || 'manual',
  knowledge_gap_id: props.faq?.knowledge_gap_id || props.prefill?.knowledge_gap_id || null,
  owner_user_id: props.faq?.owner_user_id || '',
  review_frequency_days: props.faq?.review_frequency_days || 180,
})

const tagsInput = ref((props.faq?.tags || props.prefill?.tags || []).join(', '))

const isPresetFrequency = computed(() =>
  (props.reviewFrequencies || []).some((f) => f.days === form.review_frequency_days),
)

function submit() {
  form.tags = tagsInput.value.split(',').map((t) => t.trim()).filter(Boolean)

  if (props.faq?.id) {
    form.put(route('admin.dova-knowledge.faqs.update', props.faq.id))
  } else {
    form.post(route('admin.dova-knowledge.faqs.store'))
  }
}

function completeReview() {
  if (!props.faq?.id) return
  router.post(route('admin.dova-knowledge.faqs.complete-review', props.faq.id))
}
</script>

<template>
  <Head :title="faq ? 'تعديل سؤال' : 'إنشاء سؤال'" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <DovaSidebar />
        <div class="col-lg-9">
          <Link :href="route('admin.dova-knowledge.faqs.index')" class="btn btn-sm btn-link px-0 mb-2">← قائمة الأسئلة</Link>
          <h1 class="h3 mb-3">{{ faq ? 'تعديل سؤال' : 'إنشاء سؤال شائع' }}</h1>

          <div v-if="prefill?.gap" class="alert alert-info small">
            من فجوة معرفية: {{ prefill.gap.topic }} — تكرار {{ prefill.gap.frequency }}
          </div>

          <div v-if="faq?.knowledge_status" class="alert alert-light border small d-flex justify-content-between align-items-center">
            <div>
              <strong>مالك المعرفة:</strong> {{ faq.ownerName || '—' }} ·
              <strong>حالة المعرفة:</strong> {{ faq.knowledgeStatusLabel }} ·
              <strong>آخر مراجعة:</strong> {{ faq.last_reviewed_at || '—' }} ·
              <strong>المراجعة القادمة:</strong> {{ faq.next_review_due_at || '—' }}
            </div>
            <button v-if="faq.status === 'published'" type="button" class="btn btn-sm btn-success" @click="completeReview">
              اكتملت المراجعة
            </button>
          </div>

          <form class="card border" @submit.prevent="submit">
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">السؤال (English) *</label>
                  <textarea v-model="form.question_en" class="form-control" rows="2" required />
                </div>
                <div class="col-md-6">
                  <label class="form-label">السؤال (العربية)</label>
                  <textarea v-model="form.question_ar" class="form-control" rows="2" />
                </div>
                <div class="col-md-6">
                  <label class="form-label">الإجابة (English) *</label>
                  <textarea v-model="form.answer_en" class="form-control" rows="4" required />
                </div>
                <div class="col-md-6">
                  <label class="form-label">الإجابة (العربية)</label>
                  <textarea v-model="form.answer_ar" class="form-control" rows="4" />
                </div>
                <div class="col-md-4">
                  <label class="form-label">التصنيف</label>
                  <select v-model="form.category_id" class="form-select">
                    <option value="">— اختر —</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">مالك المعرفة *</label>
                  <select v-model="form.owner_user_id" class="form-select" required>
                    <option value="">— اختر المالك —</option>
                    <option v-for="o in owners" :key="o.id" :value="o.id">{{ o.name }}</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">دورية المراجعة</label>
                  <select
                    v-if="!customFrequency"
                    class="form-select"
                    :value="isPresetFrequency ? form.review_frequency_days : 'custom'"
                    @change="(e) => { if (e.target.value === 'custom') { customFrequency = true } else { form.review_frequency_days = Number(e.target.value) } }"
                  >
                    <option v-for="f in reviewFrequencies" :key="f.days" :value="f.days">{{ f.label_ar }}</option>
                    <option value="custom">مخصص</option>
                  </select>
                  <input v-else v-model.number="form.review_frequency_days" type="number" min="7" max="730" class="form-control" />
                </div>
                <div class="col-md-4">
                  <label class="form-label">تصنيف مخصص</label>
                  <input v-model="form.custom_category" type="text" class="form-control" placeholder="إن لم تجد تصنيفاً" />
                </div>
                <div class="col-md-4">
                  <label class="form-label">الحالة</label>
                  <select v-model="form.status" class="form-select">
                    <option value="draft">مسودة</option>
                    <option value="review">قيد المراجعة</option>
                    <option value="published">منشور</option>
                  </select>
                </div>
                <div class="col-12">
                  <label class="form-label">الوسوم (مفصولة بفاصلة)</label>
                  <input v-model="tagsInput" type="text" class="form-control" />
                </div>
              </div>

              <div v-if="faq" class="mt-3 small text-muted">
                أنشأه: {{ faq.createdBy || '—' }} · {{ faq.createdAt }} —
                آخر تحديث: {{ faq.updatedBy || '—' }} · {{ faq.updatedAt }}
              </div>

              <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary" :disabled="form.processing">حفظ</button>
                <Link :href="route('admin.dova-knowledge.faqs.index')" class="btn btn-outline-secondary">إلغاء</Link>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
