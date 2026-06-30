<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
  matchedFamily: { type: Object, default: null },
})
</script>

<template>
  <div
    class="admissions-hero-strip"
    :class="matchedFamily ? 'admissions-hero-strip--success' : 'border bg-light'"
  >
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
      <i :class="['bi fs-5', matchedFamily ? 'bi-people-fill text-success' : 'bi-people text-muted']"></i>
      <h6 class="mb-0 fw-bold">
        {{ matchedFamily ? 'العائلة المرتبطة' : 'لا توجد عائلة مرتبطة' }}
      </h6>
    </div>

    <template v-if="matchedFamily">
      <div class="row g-3 align-items-center">
        <div class="col-md-4">
          <div class="small text-muted">ولي الأمر</div>
          <div class="fw-bold">{{ matchedFamily.guardian.name }}</div>
        </div>
        <div class="col-md-3">
          <div class="small text-muted">عدد الأبناء</div>
          <div class="fw-bold fs-5">{{ matchedFamily.childrenCount }}</div>
        </div>
        <div class="col-md-5">
          <div class="small text-muted">الملخص المالي</div>
          <div class="small text-muted">عرض التفاصيل في ملف العائلة</div>
        </div>
        <div class="col-12 d-flex flex-wrap gap-2">
          <Link
            v-if="matchedFamily.profileUrl"
            :href="matchedFamily.profileUrl"
            class="btn btn-sm btn-success"
          >
            <i class="bi bi-house-heart me-1"></i>فتح ملف العائلة
          </Link>
          <Link
            v-for="child in matchedFamily.children.slice(0, 3)"
            :key="child.id"
            :href="child.profile_url"
            class="btn btn-sm btn-outline-success"
          >
            {{ child.name }}
          </Link>
        </div>
      </div>
    </template>
    <p v-else class="mb-0 small text-muted">
      لم يتم ربط ولي أمر بعد. راجع تطابقات العائلة أو أكمل بيانات جهة الاتصال.
    </p>
  </div>
</template>
