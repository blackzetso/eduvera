<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

defineProps({
  periods: Array,
  date: String,
})
</script>

<template>
  <Head title="حضور حصصي" />
  <div class="container py-4">
    <h1 class="h4 mb-3">حصصي — تسجيل الحضور</h1>
    <p class="text-muted">التاريخ: {{ date }}</p>
    <ul class="list-group">
      <li v-for="p in periods" :key="p.id" class="list-group-item d-flex justify-content-between">
        <span>{{ p.category?.name }} — حصة {{ p.period_number }}</span>
        <Link :href="route('teacher.attendances.class', { period: p.id, date })" class="btn btn-sm btn-primary">تسجيل</Link>
      </li>
      <li v-if="!periods?.length" class="list-group-item text-muted">لا توجد حصص معينة لك</li>
    </ul>
  </div>
</template>
