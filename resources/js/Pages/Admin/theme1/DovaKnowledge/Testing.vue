<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import DovaSidebar from '@/Pages/Admin/theme1/DovaKnowledge/Partials/Sidebar.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  result: Object,
  question: String,
  locale: String,
  aiEnabled: Boolean,
  aiModel: String,
})

const form = useForm({
  question: props.question || '',
  locale: props.locale || 'en',
})

function submit() {
  form.post(route('admin.dova-knowledge.testing.run'))
}
</script>

<template>
  <Head title="مركز الاختبار — دوفا" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <DovaSidebar />
        <div class="col-lg-9">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">مركز اختبار دوفا</h1>
            <span class="badge" :class="aiEnabled ? 'bg-success' : 'bg-secondary'">
              AI {{ aiEnabled ? aiModel : 'معطّل' }}
            </span>
          </div>

          <form class="card border mb-4" @submit.prevent="submit">
            <div class="card-body">
              <label class="form-label">السؤال</label>
              <textarea v-model="form.question" class="form-control mb-3" rows="3" placeholder="What is the name of this school?" required />
              <div class="row g-2 align-items-end">
                <div class="col-auto">
                  <label class="form-label small">اللغة</label>
                  <select v-model="form.locale" class="form-select form-select-sm">
                    <option value="en">English</option>
                    <option value="ar">العربية</option>
                  </select>
                </div>
                <div class="col-auto">
                  <button type="submit" class="btn btn-primary" :disabled="form.processing">اسأل دوفا</button>
                </div>
              </div>
            </div>
          </form>

          <div v-if="result" class="card border mb-4">
            <div class="card-header d-flex justify-content-between">
              <span class="fw-semibold">النتيجة النهائية</span>
              <span class="badge" :class="result.matched ? 'bg-success' : 'bg-warning text-dark'">
                {{ result.matched ? 'معرفة + AI' : 'بدون معرفة' }}
              </span>
            </div>
            <div class="card-body">
              <dl class="row small mb-0">
                <dt class="col-sm-3">الإجابة النهائية</dt>
                <dd class="col-sm-9" style="white-space: pre-wrap">{{ result.finalAnswer }}</dd>
                <dt class="col-sm-3">المصدر</dt>
                <dd class="col-sm-9">{{ result.source || '—' }}</dd>
                <dt class="col-sm-3">الثقة</dt>
                <dd class="col-sm-9">{{ Math.round((result.confidence || 0) * 100) }}%</dd>
                <dt class="col-sm-3">زمن المعرفة</dt>
                <dd class="col-sm-9">{{ result.knowledgeMs }} ms</dd>
                <dt class="col-sm-3">استخدام AI</dt>
                <dd class="col-sm-9">
                  {{ result.usedLlm ? 'نعم' : 'لا' }}
                  <span v-if="result.llmFallback" class="text-muted">(fallback)</span>
                </dd>
              </dl>
            </div>
          </div>

          <div v-if="result?.matched" class="row g-3">
            <div class="col-md-6">
              <div class="card border h-100">
                <div class="card-header small fw-semibold">نتيجة طبقة المعرفة (خام)</div>
                <div class="card-body small" style="white-space: pre-wrap">{{ result.rawAnswer }}</div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="card border h-100">
                <div class="card-header small fw-semibold">بعد OpenAI</div>
                <div class="card-body small" style="white-space: pre-wrap">{{ result.finalAnswer }}</div>
              </div>
            </div>
          </div>

          <div v-if="result?.aiDebug" class="card border mt-3">
            <div class="card-header fw-semibold">تفاصيل AI (للمسؤولين فقط)</div>
            <div class="card-body small">
              <dl class="row mb-3">
                <dt class="col-sm-2">النموذج</dt><dd class="col-sm-10">{{ result.aiDebug.model }}</dd>
                <dt class="col-sm-2">الرموز</dt>
                <dd class="col-sm-10">{{ result.aiDebug.promptTokens }} + {{ result.aiDebug.completionTokens }} = {{ result.aiDebug.totalTokens }}</dd>
                <dt class="col-sm-2">التكلفة</dt><dd class="col-sm-10">${{ result.aiDebug.estimatedCost }}</dd>
                <dt class="col-sm-2">زمن AI</dt><dd class="col-sm-10">{{ result.aiDebug.responseMs }} ms</dd>
              </dl>
              <details>
                <summary>عرض الـ Prompt</summary>
                <pre class="bg-light p-2 mt-2 small" style="max-height: 200px; overflow: auto">{{ result.aiDebug.userPrompt }}</pre>
              </details>
              <details class="mt-2">
                <summary>عرض الاستجابة الخام</summary>
                <pre class="bg-light p-2 mt-2 small" style="max-height: 200px; overflow: auto">{{ result.aiDebug.rawResponse }}</pre>
              </details>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
