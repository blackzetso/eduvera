<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import DovaSidebar from '@/Pages/Admin/theme1/DovaKnowledge/Partials/Sidebar.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

defineProps({ sources: Array })

function toggleSource(id) {
  router.post(route('admin.dova-knowledge.sources.toggle', id))
}

function reindex(id) {
  if (!confirm('إعادة فهرسة هذا المصدر؟')) return
  router.post(route('admin.dova-knowledge.sources.reindex', id))
}
</script>

<template>
  <Head title="مصادر المعرفة — دوفا" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <DovaSidebar />
        <div class="col-lg-9">
          <h1 class="h3 mb-4">مصادر المعرفة</h1>
          <div class="row g-3">
            <div v-for="s in sources" :key="s.id" class="col-md-6">
              <div class="card border h-100">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-start mb-2">
                    <h2 class="h6 mb-0">{{ s.name }}</h2>
                    <span class="badge" :class="s.enabled ? 'bg-success' : 'bg-secondary'">
                      {{ s.enabled ? 'مفعّل' : 'معطّل' }}
                    </span>
                  </div>
                  <p class="text-muted small mb-3">{{ s.nameEn }}</p>
                  <dl class="row small mb-3">
                    <dt class="col-4 text-muted">الحالة</dt>
                    <dd class="col-8">
                      <span class="badge" :class="s.status === 'indexed' ? 'bg-success' : 'bg-warning text-dark'">
                        {{ s.statusLabel }}
                      </span>
                    </dd>
                    <dt class="col-4 text-muted">السجلات</dt>
                    <dd class="col-8">{{ s.recordCount }}</dd>
                    <dt class="col-4 text-muted">آخر مزامنة</dt>
                    <dd class="col-8">{{ s.lastSyncedLabel }}</dd>
                  </dl>
                  <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" @click="toggleSource(s.id)">
                      {{ s.enabled ? 'تعطيل' : 'تفعيل' }}
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="!s.enabled" @click="reindex(s.id)">
                      إعادة فهرسة
                    </button>
                    <Link :href="route('admin.dova-knowledge.sources.records', s.id)" class="btn btn-sm btn-light">
                      عرض السجلات
                    </Link>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
