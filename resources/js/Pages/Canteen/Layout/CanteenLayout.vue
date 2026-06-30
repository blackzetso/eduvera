<script setup>
import { computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

defineProps({
  compact: { type: Boolean, default: false },
  fullscreen: { type: Boolean, default: false },
})

const page = usePage()
const canteen = computed(() => page.props.modules?.canteen ?? { enabled: false })
const permissions = computed(() => canteen.value.permissions ?? [])
const isRtl = computed(() => (page.props.direction ?? 'rtl') === 'rtl')
const isArabic = computed(() => (page.props.locale ?? 'ar') === 'ar')

function menuLabel(item) {
  return isArabic.value ? (item.label_ar ?? item.label) : (item.label ?? item.label_ar)
}

function can(permission) {
  return permissions.value.includes(permission)
}

const menuItems = computed(() => {
  const items = canteen.value.menu?.items ?? []
  return items.filter((item) => can(item.permission))
})

function changeLang(lang) {
  router.post(route('change.language'), { lang })
}
</script>

<template>
  <main :class="{ 'canteen-fullscreen': fullscreen }">
    <nav v-if="!fullscreen" class="navbar sidebar navbar-expand-xl navbar-dark bg-dark">
      <div class="d-flex align-items-center">
        <Link class="navbar-brand" :href="route('canteen.dashboard')">
          <img class="navbar-brand-item" src="/front/theme1/images/jesoor-logo-white.png" alt="">
        </Link>
      </div>

      <div class="offcanvas offcanvas-start flex-row custom-scrollbar h-100" data-bs-backdrop="true" tabindex="-1" id="offcanvasCanteenSidebar">
        <div class="offcanvas-body sidebar-content d-flex flex-column bg-dark">
          <ul class="navbar-nav flex-column" id="navbar-canteen-sidebar">
            <li class="nav-item ms-2 my-2 text-muted small">
              {{ isArabic ? (canteen.menu?.label_ar ?? 'الكافتيريا') : (canteen.menu?.label ?? 'Canteen') }}
            </li>

            <li v-for="item in menuItems" :key="item.route" class="nav-item">
              <Link :href="route(item.route)" class="nav-link">
                <i :class="['bi', item.icon, 'fa-fw', 'me-2']"></i>{{ menuLabel(item) }}
              </Link>
            </li>

            <li class="nav-item mt-3">
              <Link :href="route('admin.dashboard.index')" class="nav-link text-muted">
                <i :class="['bi', isRtl ? 'bi-arrow-right' : 'bi-arrow-left', 'fa-fw', 'me-2']"></i>
                {{ isArabic ? 'العودة للوحة الإدارة' : 'Back to Admin' }}
              </Link>
            </li>
          </ul>

          <div class="px-3 mt-auto pt-3">
            <div class="d-flex align-items-center justify-content-between text-primary-hover">
              <Link class="h5 mb-0 text-body" :href="route('home')" data-bs-toggle="tooltip" :title="isArabic ? 'الموقع' : 'Website'">
                <i class="bi bi-globe"></i>
              </Link>
              <Link as="button" method="post" :href="route('logout')" class="h5 mb-0 text-body" :title="isArabic ? 'تسجيل الخروج' : 'Logout'">
                <i class="bi bi-power fa-fw"></i>
              </Link>
            </div>
          </div>
        </div>
      </div>
    </nav>

    <div class="page-content" :class="{ 'page-content--fullscreen': fullscreen }">
      <nav v-if="!fullscreen" class="navbar top-bar navbar-light border-bottom py-0 py-xl-3">
        <div class="container-fluid p-0">
          <div class="d-flex align-items-center w-100">
            <div class="navbar-expand-xl sidebar-offcanvas-menu">
              <button class="navbar-toggler me-auto" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasCanteenSidebar">
                <i class="bi bi-text-right fa-fw h2 lh-0 mb-0 rtl-flip"></i>
              </button>
            </div>
            <div class="ms-xl-auto">
              <ul class="navbar-nav flex-row align-items-center">
                <li class="nav-item ms-2 dropdown">
                  <a class="btn btn-light btn-round mb-0" href="#" role="button" data-bs-toggle="dropdown">
                    <i class="bi bi-globe fa-fw"></i>
                  </a>
                  <div class="dropdown-menu dropdown-menu-end p-0 shadow-lg border-0">
                    <button @click="changeLang('ar')" class="dropdown-item">ar</button>
                    <button @click="changeLang('en')" class="dropdown-item">en</button>
                  </div>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </nav>

      <div
        class="page-content-wrapper"
        :class="fullscreen ? 'p-0' : (compact ? 'p-2 p-lg-3' : 'p-4')"
      >
        <slot />
      </div>
    </div>
  </main>
</template>

<style scoped>
.canteen-fullscreen .page-content--fullscreen {
  margin-inline-start: 0 !important;
  width: 100% !important;
  max-width: 100% !important;
}
</style>
