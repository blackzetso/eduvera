<script setup>
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AdminSectionNav from '@/Components/Admin/AdminSectionNav.vue'
import { useTranslations } from '@/composables/translations'

const { t } = useTranslations()
</script>

<template>
  <AdminSectionNav id="adminSettingsNav" title="الإعدادات" breakpoint="xl">
    <ul class="nav nav-pills nav-tabs-bg-dark flex-column">
      <li class="nav-item">
        <Link class="nav-link" :href="route('admin.settings.index')" :class="{ active: route().current('admin.settings.index') }">
          <i class="fas fa-cog fa-fw me-2" />
          {{ t('general_settings') }}
        </Link>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="#tab-1"><i class="fas fa-globe fa-fw me-2" />{{ t('apperance_settings') }}</a>
      </li>
      <li class="nav-item">
        <Link class="nav-link" :href="route('admin.language.index')" :class="{ active: route().current('admin.language.index') }">
          <i class="fas fa-globe fa-fw me-2" />Language
        </Link>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="#tab-3"><i class="fas fa-bell fa-fw me-2" />Notification Settings</a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="#tab-4"><i class="fas fa-user-circle fa-fw me-2" />Account Settings</a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="#tab-5"><i class="fas fa-sliders-h fa-fw me-2" />Social Settings</a>
      </li>
      <li class="nav-item">
        <a class="nav-link mb-0" href="#tab-6"><i class="fas fa-envelope-open-text fa-fw me-2" />Email Settings</a>
      </li>
      <li class="nav-item">
        <Link class="nav-link" :href="route('admin.settings.teams')" :class="{ active: route().current('admin.settings.teams') }">
          <i class="bi bi-camera-video fa-fw me-2" />Microsoft Teams
        </Link>
      </li>
      <li class="nav-item">
        <Link class="nav-link" :href="route('admin.settings.zoom')" :class="{ active: route().current('admin.settings.zoom') }">
          <i class="bi bi-camera-video-fill fa-fw me-2" style="color:#2D8CFF;" />Zoom
        </Link>
      </li>
      <li class="nav-item">
        <Link class="nav-link" :href="route('admin.settings.livekit')" :class="{ active: route().current('admin.settings.livekit') }">
          <i class="bi bi-broadcast-pin fa-fw me-2" style="color:#1EAA53;" />LiveKit
        </Link>
      </li>
      <li class="nav-item">
        <Link class="nav-link" :href="route('admin.settings.google-meet')" :class="{ active: route().current('admin.settings.google-meet') }">
          <i class="bi bi-camera-video fa-fw me-2" style="color:#1A73E8;" />Google Meet
        </Link>
      </li>
      <li class="nav-item">
        <Link class="nav-link" :href="route('admin.settings.hms')" :class="{ active: route().current('admin.settings.hms') }">
          <span class="fw-bold me-2" style="color:#2563EB;font-size:11px;">100ms</span>غرفة مدمجة
        </Link>
      </li>
      <li class="nav-item">
        <Link
          class="nav-link"
          :href="route('admin.settings.coverage')"
          :class="{ active: route().current('admin.settings.coverage') }"
        >
          <i class="bi bi-person-x-fill fa-fw me-2 text-warning" />
          تغطية الغياب
        </Link>
      </li>
    </ul>
  </AdminSectionNav>
</template>
