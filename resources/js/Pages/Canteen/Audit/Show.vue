<script setup>
import CanteenLayout from '@/Pages/Canteen/Layout/CanteenLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

defineProps({
  log: Object,
})
</script>

<template>
  <CanteenLayout>
    <Head :title="`Audit: ${log?.action_label}`" />

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
      <div>
        <h4 class="mb-0">{{ log?.action_label }}</h4>
        <p class="text-muted small mb-0">{{ log?.timestamp }}</p>
      </div>
      <Link :href="route('canteen.audit.index')" class="btn btn-light btn-sm">رجوع للسجل</Link>
    </div>

    <div class="row g-4">
      <div class="col-lg-5">
        <div class="card border h-100">
          <div class="card-header bg-transparent"><strong>معلومات عامة</strong></div>
          <div class="card-body">
            <dl class="row mb-0 small">
              <dt class="col-sm-4 text-muted">الوقت</dt>
              <dd class="col-sm-8">{{ log?.timestamp }}</dd>

              <dt class="col-sm-4 text-muted">المستخدم</dt>
              <dd class="col-sm-8">{{ log?.user }}</dd>

              <dt class="col-sm-4 text-muted">الإجراء</dt>
              <dd class="col-sm-8">{{ log?.action_label }}</dd>

              <dt class="col-sm-4 text-muted">نوع الكيان</dt>
              <dd class="col-sm-8">{{ log?.entity_type }}</dd>

              <dt class="col-sm-4 text-muted">المرجع</dt>
              <dd class="col-sm-8">{{ log?.entity_reference }}</dd>

              <dt class="col-sm-4 text-muted">الوصف</dt>
              <dd class="col-sm-8">{{ log?.description }}</dd>
            </dl>

            <div v-if="log?.related_link" class="mt-3">
              <a :href="log.related_link.url" class="btn btn-outline-primary btn-sm">
                {{ log.related_link.label }}
              </a>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-7">
        <div class="card border mb-4" v-if="log?.changes?.length">
          <div class="card-header bg-transparent"><strong>التغييرات</strong></div>
          <div class="card-body p-0">
            <table class="table table-sm mb-0">
              <thead>
                <tr>
                  <th>الحقل</th>
                  <th>قبل</th>
                  <th>بعد</th>
                  <th>المقارنة</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="change in log.changes" :key="change.field">
                  <td>{{ change.label }}</td>
                  <td class="text-muted">{{ change.before ?? '—' }}</td>
                  <td>{{ change.after ?? '—' }}</td>
                  <td><span class="badge bg-light text-dark border">{{ change.formatted }}</span></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="card border" v-if="log?.metadata?.length">
          <div class="card-header bg-transparent"><strong>بيانات إضافية</strong></div>
          <div class="card-body">
            <dl class="row mb-0 small">
              <template v-for="item in log.metadata" :key="item.label">
                <dt class="col-sm-4 text-muted">{{ item.label }}</dt>
                <dd class="col-sm-8">{{ item.value }}</dd>
              </template>
            </dl>
          </div>
        </div>

        <div v-if="!log?.changes?.length && !log?.metadata?.length" class="card border">
          <div class="card-body text-muted text-center py-4">
            لا توجد تفاصيل تغيير إضافية لهذا السجل.
          </div>
        </div>
      </div>
    </div>
  </CanteenLayout>
</template>
