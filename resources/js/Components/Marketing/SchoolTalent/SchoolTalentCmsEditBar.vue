<script setup>
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
</script>

<template>
  <div class="st-cms-edit-bar" role="banner" aria-label="وضع تحرير المحتوى">
    <div class="st-cms-edit-bar__inner">
      <span class="st-cms-edit-bar__badge">
        <i class="bi bi-pencil-square" aria-hidden="true"></i>
        وضع التحرير
      </span>
      <span class="st-cms-edit-bar__hint">أنت تشاهد الموقع كمسؤول — مرّر على أي قسم واضغط أيقونة القلم للتعديل.</span>
      <div class="st-cms-edit-bar__actions">
        <Link :href="route('admin.website.landing-builder.index')" class="st-cms-edit-bar__link">
          <i class="bi bi-layout-wtf"></i> منشئ الصفحة
        </Link>
        <Link :href="route('admin.website.index')" class="st-cms-edit-bar__link">
          <i class="bi bi-grid"></i> لوحة الموقع
        </Link>
      </div>
    </div>
  </div>
</template>
