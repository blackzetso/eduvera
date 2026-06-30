<script setup>
import AppLayout from '@/Pages/Admin/theme1/Layout/App.vue'
import WebsiteSidebar from '@/Pages/Admin/theme1/Website/Partials/Sidebar.vue'
import { Head, Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

defineProps({ blocks: Array })
</script>

<template>
  <Head title="كتل المحتوى" />
  <AppLayout>
    <div class="page-content-wrapper border">
      <div class="row">
        <WebsiteSidebar />
        <div class="col-lg-9">
          <h1 class="h4 mb-3">كتل المحتوى</h1>
          <div class="list-group">
            <Link
              v-for="b in blocks"
              :key="b.slug"
              :href="route('admin.website.content-blocks.edit', b.slug)"
              class="list-group-item list-group-item-action"
            >
              {{ b.title }}
            </Link>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
