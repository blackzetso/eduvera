<script setup>
import { Link, router, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { useTranslations } from '@/composables/translations'

const { t } = useTranslations()
const page = usePage()

function changeLang(lang) {
    router.post(route('change.language'), { lang })
}
</script>

<template>
    <main>
        <!-- Sidebar START -->
        <nav class="navbar sidebar navbar-expand-xl navbar-dark bg-dark">

            <div class="d-flex align-items-center">
                <Link class="navbar-brand" :href="route('teacher.dashboard.index')">
                    <img class="navbar-brand-item" src="/front/theme1/images/jesoor-logo-white.png" alt="">
                </Link>
            </div>

            <div class="offcanvas offcanvas-start flex-row custom-scrollbar h-100" data-bs-backdrop="true" tabindex="-1" id="offcanvasSidebar">
                <div class="offcanvas-body sidebar-content d-flex flex-column bg-dark">

                    <!-- Sidebar menu -->
                    <ul class="navbar-nav flex-column" id="navbar-sidebar">

                        <li class="nav-item">
                            <Link :href="route('teacher.dashboard.index')" class="nav-link">
                                <i class="bi bi-house fa-fw me-2"></i>Dashboard
                            </Link>
                        </li>

                        <li class="nav-item ms-2 my-2">Academic</li>

                        <li class="nav-item">
                            <Link class="nav-link" :href="route('teacher.timetables.index')">
                                <i class="bi bi-calendar-week fa-fw me-2"></i>{{ t('timetable') }}
                            </Link>
                        </li>

                        <li class="nav-item ms-2 my-2">البث المباشر</li>

                        <li class="nav-item">
                            <Link class="nav-link" :href="route('teacher.live-streams.index')">
                                <i class="bi bi-broadcast fa-fw me-2 text-danger"></i>البثوث المباشرة
                            </Link>
                        </li>

                    </ul>

                    <!-- Sidebar footer -->
                    <div class="px-3 mt-auto pt-3">
                        <div class="d-flex align-items-center justify-content-between text-primary-hover">
                            <Link as="button" method="post" :href="route('logout')" class="h5 mb-0 text-body" title="Sign out">
                                <i class="bi bi-power fa-fw"></i>
                            </Link>
                        </div>
                    </div>

                </div>
            </div>
        </nav>
        <!-- Sidebar END -->

        <!-- Page content START -->
        <div class="page-content">

            <!-- Top bar START -->
            <nav class="navbar top-bar navbar-light border-bottom py-0 py-xl-3">
                <div class="container-fluid p-0">
                    <div class="d-flex align-items-center w-100">

                        <!-- Mobile logo -->
                        <div class="d-flex align-items-center d-xl-none">
                            <a class="navbar-brand" href="#">
                                <img class="dark-mode-item navbar-brand-item h-30px" src="/front/theme1/images/jesoor-logo-white.png" alt="">
                            </a>
                        </div>

                        <!-- Sidebar toggler (mobile) -->
                        <div class="navbar-expand-xl sidebar-offcanvas-menu">
                            <button class="navbar-toggler me-auto" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasSidebar" aria-controls="offcanvasSidebar" aria-expanded="false" aria-label="Toggle navigation" data-bs-auto-close="outside">
                                <i class="bi bi-text-right fa-fw h2 lh-0 mb-0 rtl-flip"></i>
                            </button>
                        </div>

                        <!-- Top bar right -->
                        <div class="ms-xl-auto">
                            <ul class="navbar-nav flex-row align-items-center">

                                <!-- Language dropdown -->
                                <li class="nav-item ms-2 ms-md-3 dropdown">
                                    <a class="btn btn-light btn-round mb-0" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="outside">
                                        <i class="bi bi-globe fa-fw"></i>
                                    </a>
                                    <div class="dropdown-menu dropdown-animation dropdown-menu-end dropdown-menu-size-md p-0 shadow-lg border-0">
                                        <div class="card bg-transparent">
                                            <div class="card-body p-0">
                                                <ul class="list-group list-unstyled list-group-flush">
                                                    <li>
                                                        <button @click="changeLang('ar')" class="list-group-item-action border-0 border-bottom d-flex p-3">ar</button>
                                                    </li>
                                                    <li>
                                                        <button @click="changeLang('en')" class="list-group-item-action border-0 border-bottom d-flex p-3">en</button>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </li>

                                <!-- Profile dropdown -->
                                <li class="nav-item ms-2 ms-md-3 dropdown">
                                    <a class="avatar avatar-sm p-0" href="#" role="button" data-bs-auto-close="outside" data-bs-display="static" data-bs-toggle="dropdown" aria-expanded="false">
                                        <img class="avatar-img rounded-circle" src="/front/theme1/images/avatar/01.jpg" alt="avatar">
                                    </a>
                                    <ul class="dropdown-menu dropdown-animation dropdown-menu-end shadow pt-3" aria-labelledby="profileDropdown">
                                        <li class="px-3 pb-2">
                                            <p class="mb-0 fw-semibold">{{ page.props.auth?.user?.name }}</p>
                                            <p class="small text-muted mb-0">{{ page.props.auth?.user?.email }}</p>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <Link as="button" method="post" :href="route('logout')" class="dropdown-item text-danger">
                                                <i class="bi bi-power fa-fw me-2"></i>تسجيل الخروج
                                            </Link>
                                        </li>
                                    </ul>
                                </li>

                            </ul>
                        </div>
                    </div>
                </div>
            </nav>
            <!-- Top bar END -->

            <slot />
        </div>
        <!-- Page content END -->
    </main>
</template>

<style>
@media (min-width: 1200px) {
  .sidebar.navbar-expand-xl {
    overflow: hidden;
  }
  .sidebar.navbar-expand-xl > .offcanvas.offcanvas-start {
    flex: 1 1 0;
    min-height: 0;
    max-height: none;
    display: flex !important;
    flex-direction: column !important;
    height: auto !important;
  }
  .sidebar.navbar-expand-xl .offcanvas .offcanvas-body.sidebar-content {
    flex: 1 1 auto !important;
    min-height: 0 !important;
    overflow: hidden !important;
  }
}
</style>
