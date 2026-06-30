<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import DovaSidebar from '@/Pages/Admin/theme1/DovaKnowledge/Partials/Sidebar.vue'
import { Head, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { ref } from 'vue'

defineProps({
  lastSync: String,
  groups: Array,
})

const syncing = ref(null)

function runSync(key) {
  if (!confirm('بدء المزامنة؟')) return
  syncing.value = key
  router.post(route('admin.dova-knowledge.sync.run', key), {}, {
    onFinish: () => { syncing.value = null },
  })
}
</script>

<template>
  <Head title="مركز المزامنة — دوفا" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <DovaSidebar />
        <div class="col-lg-9">
          <h1 class="h3 mb-2">مركز المزامنة</h1>
          <p class="text-muted small mb-4">آخر مزامنة: {{ lastSync }}</p>

          <div class="row g-3">
            <div v-for="g in groups" :key="g.key" class="col-md-6">
              <div class="card border h-100">
                <div class="card-body d-flex flex-column">
                  <h2 class="h6">{{ g.label }}</h2>
                  <p class="text-muted small flex-grow-1">{{ g.description }}</p>
                  <button
                    type="button"
                    class="btn btn-primary btn-sm align-self-start"
                    :disabled="syncing === g.key"
                    @click="runSync(g.key)"
                  >
                    <span v-if="syncing === g.key" class="spinner-border spinner-border-sm me-1" />
                    {{ syncing === g.key ? 'جاري المزامنة...' : 'مزامنة' }}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
