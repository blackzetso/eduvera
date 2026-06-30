<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import DovaSidebar from '@/Pages/Admin/theme1/DovaKnowledge/Partials/Sidebar.vue'
import { Head, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { ref, watch } from 'vue'

const props = defineProps({
  query: String,
  results: Array,
})

const search = ref(props.query || '')

function submit() {
  router.get(route('admin.dova-knowledge.explorer.index'), { q: search.value }, { preserveState: true })
}

watch(() => props.query, (v) => { search.value = v || '' })
</script>

<template>
  <Head title="مستكشف المعرفة — دوفا" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <DovaSidebar />
        <div class="col-lg-9">
          <h1 class="h3 mb-4">مستكشف المعرفة</h1>

          <form class="input-group mb-4" @submit.prevent="submit">
            <input v-model="search" type="search" class="form-control" placeholder="ابحث في المحتوى المفهرس..." />
            <button type="submit" class="btn btn-primary">بحث</button>
          </form>

          <div v-if="query && !results.length" class="alert alert-light">لا توجد نتائج لـ «{{ query }}»</div>

          <div v-for="r in results" :key="r.id" class="card border mb-3">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                  <span class="badge bg-primary me-2">{{ r.source }}</span>
                  <span class="text-muted small">{{ r.recordKey }} · {{ r.locale }}</span>
                </div>
                <span class="badge bg-success">ثقة {{ Math.round(r.confidence * 100) }}%</span>
              </div>
              <h2 v-if="r.title" class="h6">{{ r.title }}</h2>
              <p class="small mb-2" style="white-space: pre-wrap">{{ r.content }}</p>
              <div class="text-muted small">آخر تحديث: {{ r.lastUpdated }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
