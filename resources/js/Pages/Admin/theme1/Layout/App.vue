<script setup>
    import { computed } from 'vue'
    import { Link } from '@inertiajs/vue3'
    import { usePage } from '@inertiajs/vue3'
    import { router } from '@inertiajs/vue3'
    import { route } from 'ziggy-js';
    import { useDashboardUrl } from '@/composables/useDashboardUrl'

    const page = usePage()
    const { dashboardUrl } = useDashboardUrl()
    const pendingExtraCount = computed(() => page.props.pendingExtraSessionsCount ?? 0)
    const canteenEnabled = computed(() => page.props.modules?.canteen?.enabled ?? false)

    function changeLang(lang) {
        router.post(route('change.language'), { lang })
    }

</script>
<template>
    <!-- **************** MAIN CONTENT START **************** -->
    <main :dir="page.props.locale === 'en' ? 'ltr' : 'rtl'">
        <!-- Sidebar START -->
        <nav class="navbar sidebar navbar-expand-xl navbar-dark bg-dark">

            <!-- Navbar brand for xl START -->
            <div class="d-flex align-items-center">
                <Link class="navbar-brand" :href="route('admin.dashboard.index')">
                    <img class="navbar-brand-item" src="/front/theme1/images/jesoor-logo-white.png" alt="">
                </Link>
            </div>
            <!-- Navbar brand for xl END -->

            <div class="offcanvas offcanvas-start flex-row custom-scrollbar h-100" data-bs-backdrop="true" tabindex="-1" id="offcanvasSidebar">
                <div class="offcanvas-body sidebar-content d-flex flex-column bg-dark">

                    <!-- Sidebar menu START -->
                    <ul class="navbar-nav flex-column" id="navbar-sidebar">

                        <li class="nav-item">
                            <Link :href="route('admin.dashboard.index')" class="nav-link">
                                <i class="bi bi-house fa-fw me-2"></i>لوحة التحكم
                            </Link>
                        </li>

                        <li class="nav-item ms-2 my-2 text-muted small">عام</li>

                        <li class="nav-item">
                            <Link class="nav-link" :href="route('admin.forms.index')">
                                <i class="bi bi-ui-checks fa-fw me-2"></i>منشئ النماذج
                            </Link>
                        </li>

                        <li class="nav-item ms-2 my-2 text-muted small">المحتوى التعليمي</li>

                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="collapse" href="#collapsepage" role="button" aria-expanded="false" aria-controls="collapsepage">
                                <i class="bi bi-journal-bookmark fa-fw me-2"></i>الدروس والمناهج
                            </a>
                            <ul class="nav collapse flex-column" id="collapsepage" data-bs-parent="#navbar-sidebar">
                                <li class="nav-item">
                                    <Link class="nav-link" :href="route('admin.lessons.index')">كل الدروس</Link>
                                </li>
                                <li class="nav-item">
                                    <Link class="nav-link" :href="route('admin.categories.index')">المراحل والفئات</Link>
                                </li>
                                <li class="nav-item">
                                    <Link class="nav-link" :href="route('admin.subjects.index')">المواد الدراسية</Link>
                                </li>
                                <li class="nav-item">
                                    <Link class="nav-link" :href="route('admin.lesson-message-templates.index')">استراتيجيات الدروس</Link>
                                </li>
                            </ul>
                        </li>

                        <li class="nav-item ms-2 my-2 text-muted small">الشؤون الأكاديمية</li>

                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="collapse" href="#collapsetimetable" role="button" aria-expanded="false" aria-controls="collapsetimetable">
                                <i class="bi bi-calendar-week fa-fw me-2"></i>الجدول الدراسي
                            </a>
                            <ul class="nav collapse flex-column" id="collapsetimetable" data-bs-parent="#navbar-sidebar">
                                <li class="nav-item">
                                    <Link class="nav-link" :href="route('admin.timetable.edit')">منشئ الجدول</Link>
                                </li>
                                <li class="nav-item">
                                    <Link class="nav-link" :href="route('admin.timetable.edit', { step: 6 })">عرض الجدول</Link>
                                </li>
                                <li class="nav-item">
                                    <Link class="nav-link" :href="route('department-plan.index')">خطة القسم</Link>
                                </li>
                                <li class="nav-item">
                                    <Link class="nav-link" :href="route('admin.timetable.filters.backup-report')">تقرير الحصص الاحتياطية</Link>
                                </li>
                            </ul>
                        </li>

                        <li class="nav-item">
                            <Link class="nav-link" :href="route('admin.admissions.index')">
                                <i class="bi bi-inbox fa-fw me-2"></i>صندوق القبول
                            </Link>
                        </li>

                        <li class="nav-item">
                            <Link class="nav-link" :href="route('admin.admissions.visits.index')">
                                <i class="bi bi-calendar-event fa-fw me-2"></i>زيارات الحرم
                            </Link>
                        </li>

                        <li class="nav-item">
                            <Link class="nav-link" :href="route('admin.students.index')">
                                <i class="fas fa-user-graduate fa-fw me-2"></i>الطلاب
                            </Link>
                        </li>

                        <li class="nav-item">
                            <Link class="nav-link" :href="route('admin.parents.index')">
                                <i class="bi bi-person-heart fa-fw me-2"></i>أولياء الأمور
                            </Link>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="collapse" href="#collapseattendance" role="button" aria-expanded="false" aria-controls="collapseattendance">
                                <i class="bi bi-calendar-check fa-fw me-2"></i>الحضور والغياب
                            </a>
                            <ul class="nav collapse flex-column" id="collapseattendance" data-bs-parent="#navbar-sidebar">
                                <li class="nav-item">
                                    <Link class="nav-link" :href="route('admin.attendances.dashboard')">لوحة الحضور</Link>
                                </li>
                                <li class="nav-item">
                                    <Link class="nav-link" :href="route('admin.attendances.index')">سجلات الحضور</Link>
                                </li>
                                <li class="nav-item">
                                    <Link class="nav-link" :href="route('admin.attendances.mark.form')">تسجيل حضور حصة</Link>
                                </li>
                                <li class="nav-item">
                                    <Link class="nav-link" :href="route('admin.attendances.alerts')">تنبيهات الغياب</Link>
                                </li>
                                <li class="nav-item">
                                    <Link class="nav-link" :href="route('admin.attendances.thresholds')">إعداد العتبات</Link>
                                </li>
                            </ul>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="collapse" href="#collapseinstructors" role="button" aria-expanded="false" aria-controls="collapseinstructors">
                                <i class="fas fa-user-tie fa-fw me-2"></i>المدرسون
                            </a>
                            <ul class="nav collapse flex-column" id="collapseinstructors" data-bs-parent="#navbar-sidebar">
                                <li class="nav-item">
                                    <Link class="nav-link" :href="route('admin.teachers.index')">كل المدرسين</Link>
                                </li>
                                <li class="nav-item">
                                    <Link class="nav-link" :href="route('admin.timetable.filters.backup')">الحصص الاحتياطية</Link>
                                </li>
                            </ul>
                        </li>

                        <li class="nav-item ms-2 my-2 text-muted small">مركز الذكاء الاصطناعي</li>

                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="collapse" href="#collapsedova" role="button" aria-expanded="false" aria-controls="collapsedova">
                                <i class="bi bi-robot fa-fw me-2"></i>مركز AI — دوفا
                            </a>
                            <ul class="nav collapse flex-column" id="collapsedova" data-bs-parent="#navbar-sidebar">
                                <li class="nav-item"><Link class="nav-link" :href="route('admin.dova-knowledge.dashboard')">مركز معرفة دوفا</Link></li>
                                <li class="nav-item"><Link class="nav-link" :href="route('admin.dova-knowledge.faqs.dashboard')">إدارة الأسئلة الشائعة</Link></li>
                                <li class="nav-item"><Link class="nav-link" :href="route('admin.dova-knowledge.gaps.index')">فجوات المعرفة</Link></li>
                                <li class="nav-item"><Link class="nav-link" :href="route('admin.dova-knowledge.sources.index')">مصادر المعرفة</Link></li>
                                <li class="nav-item"><Link class="nav-link" :href="route('admin.dova-knowledge.sync.index')">مركز المزامنة</Link></li>
                                <li class="nav-item"><Link class="nav-link" :href="route('admin.dova-knowledge.explorer.index')">مستكشف المعرفة</Link></li>
                                <li class="nav-item"><Link class="nav-link" :href="route('admin.dova-knowledge.testing.index')">مركز الاختبار</Link></li>
                                <li class="nav-item"><Link class="nav-link" :href="route('admin.dova-knowledge.ai-usage.index')">استخدام AI</Link></li>
                                <li class="nav-item"><Link class="nav-link" :href="route('admin.dova-knowledge.unanswered.index')">أسئلة بلا إجابة</Link></li>
                                <li class="nav-item"><Link class="nav-link" :href="route('admin.dova-knowledge.analytics.index')">التحليلات</Link></li>
                            </ul>
                        </li>

                        <li class="nav-item ms-2 my-2 text-muted small">إدارة الموقع</li>

                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="collapse" href="#collapsewebsite" role="button" aria-expanded="false" aria-controls="collapsewebsite">
                                <i class="bi bi-globe2 fa-fw me-2"></i>الموقع — School Talent
                            </a>
                            <ul class="nav collapse flex-column" id="collapsewebsite" data-bs-parent="#navbar-sidebar">
                                <li class="nav-item"><Link class="nav-link" :href="route('admin.website.index')">نظرة عامة</Link></li>
                                <li class="nav-item"><Link class="nav-link" :href="route('admin.website.landing-builder.index')">منشئ الصفحة الرئيسية</Link></li>
                                <li class="nav-item"><Link class="nav-link" :href="route('admin.website.chrome.edit')">الرأس والتذييل</Link></li>
                                <li class="nav-item"><Link class="nav-link" :href="route('admin.website.content-blocks.index')">كتل المحتوى</Link></li>
                                <li class="nav-item"><Link class="nav-link" :href="route('admin.website.ui-labels.edit')">تسميات الواجهة</Link></li>
                                <li class="nav-item"><Link class="nav-link" :href="route('admin.website.hero')">قسم البطل</Link></li>
                                <li class="nav-item"><Link class="nav-link" :href="route('admin.website.stages.index')">المراحل</Link></li>
                                <li class="nav-item"><Link class="nav-link" :href="route('admin.website.events.index')">الفعاليات</Link></li>
                                <li class="nav-item"><Link class="nav-link" :href="route('admin.website.media.index')">مكتبة الوسائط</Link></li>
                            </ul>
                        </li>

                        <li class="nav-item ms-2 my-2 text-muted small">النظام والخدمات</li>

                        <li class="nav-item">
                            <Link class="nav-link" :href="route('admin.wallet.index')">
                                <i class="bi bi-wallet fa-fw me-2"></i>محفظة التخزين
                            </Link>
                        </li>

                        <li v-if="canteenEnabled" class="nav-item">
                            <a class="nav-link" data-bs-toggle="collapse" href="#collapsecanteen" role="button" aria-expanded="false" aria-controls="collapsecanteen">
                                <i class="bi bi-shop fa-fw me-2"></i>الكافتيريا
                            </a>
                            <ul class="nav collapse flex-column" id="collapsecanteen" data-bs-parent="#navbar-sidebar">
                                <li class="nav-item">
                                    <Link class="nav-link" :href="route('canteen.dashboard')">لوحة التحكم</Link>
                                </li>
                                <li class="nav-item">
                                    <Link class="nav-link" :href="route('canteen.pos')">نقطة البيع</Link>
                                </li>
                                <li class="nav-item">
                                    <Link class="nav-link" :href="route('canteen.products.index')">المنتجات</Link>
                                </li>
                                <li class="nav-item">
                                    <Link class="nav-link" :href="route('canteen.transactions.index')">المعاملات</Link>
                                </li>
                                <li class="nav-item">
                                    <Link class="nav-link" :href="route('canteen.settings.index')">الإعدادات</Link>
                                </li>
                            </ul>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="collapse" href="#collapselivestreams" role="button" aria-expanded="false" aria-controls="collapselivestreams">
                                <i class="bi bi-broadcast fa-fw me-2 text-danger"></i>البث المباشر
                            </a>
                            <ul class="nav collapse flex-column" id="collapselivestreams" data-bs-parent="#navbar-sidebar">
                                <li class="nav-item">
                                    <Link class="nav-link" :href="route('admin.live-streams.index')">البثوث المباشرة</Link>
                                </li>
                                <li class="nav-item">
                                    <Link class="nav-link" :href="route('admin.live-streams.details')">إعدادات البث</Link>
                                </li>
                            </ul>
                        </li>

                        <li class="nav-item">
                            <Link class="nav-link" :href="route('admin.settings.index')">
                                <i class="fas fa-user-cog fa-fw me-2"></i>إعدادات النظام
                            </Link>
                        </li>
                    </ul>
                    <!-- Sidebar menu end -->

                    <!-- Sidebar footer START -->
                    <div class="px-3 mt-auto pt-3">
                        <div class="d-flex align-items-center justify-content-between text-primary-hover">
                                <Link class="h5 mb-0 text-body" :href="route('admin.settings.index')" data-bs-toggle="tooltip" data-bs-placement="top" title="الإعدادات">
                                    <i class="bi bi-gear-fill"></i>
                                </Link>
                                <Link class="h5 mb-0 text-body" :href="route('home')" data-bs-toggle="tooltip" data-bs-placement="top" title="الموقع">
                                    <i class="bi bi-globe"></i>
                                </Link>
                                <Link as="button" method="post" :href="route('logout')" class="h5 mb-0 text-body" data-bs-placement="top" title="تسجيل الخروج">
                                    <i class="bi bi-power fa-fw"></i>
                                </Link>
                        </div>
                    </div>
                    <!-- Sidebar footer END -->

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

                        <!-- Logo START -->
                        <div class="d-flex align-items-center d-xl-none">
                            <a class="navbar-brand" href="index.html">
                                <img class="light-mode-item navbar-brand-item h-30px" src="/front/theme1/images/logo-mobile.svg" alt="">
                                <img class="dark-mode-item navbar-brand-item h-30px" src="/front/theme1/images/jesoor-logo-white.png" alt="">
                            </a>
                        </div>
                        <!-- Logo END -->

                        <!-- Toggler for sidebar START -->
                        <div class="navbar-expand-xl sidebar-offcanvas-menu">
                            <button class="navbar-toggler me-auto" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasSidebar" aria-controls="offcanvasSidebar" aria-expanded="false" aria-label="Toggle navigation" data-bs-auto-close="outside">
                                <i class="bi bi-text-right fa-fw h2 lh-0 mb-0 rtl-flip" data-bs-target="#offcanvasMenu"> </i>
                            </button>
                        </div>
                        <!-- Toggler for sidebar END -->

                        <!-- Top bar left -->
                        <div class="navbar-expand-lg ms-auto ms-xl-0">

                            <!-- Toggler for menubar START -->
                            <button class="navbar-toggler ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#navbarTopContent" aria-controls="navbarTopContent" aria-expanded="false" aria-label="Toggle navigation">
                                <span class="navbar-toggler-animation">
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </span>
                            </button>
                            <!-- Toggler for menubar END -->

                            <!-- Topbar menu START -->
                            <div class="collapse navbar-collapse w-100" id="navbarTopContent">
                                <!-- Top search START -->
                                <div class="nav my-3 my-xl-0 flex-nowrap align-items-center">
                                    <div class="nav-item w-100">
                                        <form class="position-relative">
                                            <input class="form-control pe-5 bg-secondary bg-opacity-10 border-0" type="search" placeholder="بحث..." aria-label="بحث">
                                            <button class="bg-transparent px-2 py-0 border-0 position-absolute top-50 end-0 translate-middle-y" type="submit"><i class="fas fa-search fs-6 text-primary"></i></button>
                                        </form>
                                    </div>
                                </div>
                                <!-- Top search END -->
                            </div>
                            <!-- Topbar menu END -->
                        </div>
                        <!-- Top bar left END -->

                        <!-- Top bar right START -->
                        <div class="ms-xl-auto">
                            <ul class="navbar-nav flex-row align-items-center">

                                <!-- Language dropdown START -->
                                <li class="nav-item ms-2 ms-md-3 dropdown">
                                    <!-- Language button -->
                                    <a class="btn btn-light btn-round mb-0" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="outside">
                                        <i class="bi bi-globe fa-fw"></i>
                                    </a>
                                    <!-- Language dropdown menu START -->
                                    <div class="dropdown-menu dropdown-animation dropdown-menu-end dropdown-menu-size-md p-0 shadow-lg border-0">
                                        <div class="card bg-transparent">
                                            <div class="card-body p-0">
                                                <ul class="list-group list-unstyled list-group-flush">
                                                    <!-- Notif item -->
                                                    <li>
                                                        <button @click="changeLang('ar')" class="list-group-item-action border-0 border-bottom d-flex p-3">
                                                           ar
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <button @click="changeLang('en')" class="list-group-item-action border-0 border-bottom d-flex p-3">
                                                           en
                                                        </button>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Language dropdown menu END -->
                                </li>
                                <!-- Language dropdown END -->
                                <!-- Notification dropdown START -->
                                <li class="nav-item ms-2 ms-md-3 dropdown">
                                    <!-- Notification button -->
                                    <a class="btn btn-light btn-round mb-0 position-relative" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="outside">
                                        <i class="bi bi-bell fa-fw"></i>
                                        <span v-if="pendingExtraCount > 0" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:10px; min-width:18px;">
                                            {{ pendingExtraCount }}
                                        </span>
                                    </a>

                                    <!-- Notification dropdown menu START -->
                                    <div class="dropdown-menu dropdown-animation dropdown-menu-end dropdown-menu-size-md p-0 shadow-lg border-0">
                                        <div class="card bg-transparent">
                                            <div class="card-header bg-transparent border-bottom py-4 d-flex justify-content-between align-items-center">
                                                <h6 class="m-0">الإشعارات
                                                    <span v-if="pendingExtraCount > 0" class="badge bg-danger bg-opacity-10 text-danger ms-2">{{ pendingExtraCount }} جديد</span>
                                                </h6>
                                            </div>
                                            <div class="card-body p-0">
                                                <ul class="list-group list-unstyled list-group-flush">
                                                    <li v-if="pendingExtraCount === 0">
                                                        <p class="text-muted text-center p-3 mb-0 small">لا توجد إشعارات جديدة</p>
                                                    </li>
                                                    <li v-else>
                                                        <Link :href="route('admin.live-streams.details')" class="list-group-item-action border-0 border-bottom d-flex p-3 text-decoration-none">
                                                            <div class="me-3">
                                                                <div class="avatar avatar-md bg-danger bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                                                                    <i class="bi bi-broadcast text-danger fs-5"></i>
                                                                </div>
                                                            </div>
                                                            <div>
                                                                <h6 class="mb-1 text-body">طلبات حصص إضافية</h6>
                                                                <p class="text-body small m-0">يوجد <b>{{ pendingExtraCount }}</b> طلب بانتظار الموافقة</p>
                                                            </div>
                                                        </Link>
                                                    </li>
                                                </ul>
                                            </div>
                                            <!-- Button -->
                                            <div class="card-footer bg-transparent border-0 py-3 text-center position-relative">
                                                <Link :href="route('admin.live-streams.details')" class="stretched-link">عرض كل الإشعارات</Link>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Notification dropdown menu END -->
                                </li>
                                <!-- Notification dropdown END -->

                                <!-- Profile dropdown START -->
                                <li class="nav-item ms-2 ms-md-3 dropdown">
                                    <!-- Avatar -->
                                    <a class="avatar avatar-sm p-0" href="#" id="profileDropdown" role="button" data-bs-auto-close="outside" data-bs-display="static" data-bs-toggle="dropdown" aria-expanded="false">
                                        <img class="avatar-img rounded-circle" :src="$page.props.auth.user?.profile_photo_url" :alt="$page.props.auth.user?.name" @error="$event.target.src='/front/theme1/images/avatar/01.jpg'">
                                    </a>

                                    <!-- Profile dropdown START -->
                                    <ul class="dropdown-menu dropdown-animation dropdown-menu-end shadow pt-3" aria-labelledby="profileDropdown">
                                        <!-- Profile info -->
                                        <li class="px-3">
                                            <div class="d-flex align-items-center">
                                                <!-- Avatar -->
                                                <div class="avatar me-3 mb-3">
                                                    <img class="avatar-img rounded-circle shadow" :src="$page.props.auth.user?.profile_photo_url" :alt="$page.props.auth.user?.name" @error="$event.target.src='/front/theme1/images/avatar/01.jpg'">
                                                </div>
                                                <div>
                                                    <a class="h6 mt-2 mt-sm-0" href="#">{{ $page.props.auth.user?.name }}</a>
                                                    <p class="small m-0">{{ $page.props.auth.user?.email }}</p>
                                                </div>
                                            </div>
                                        </li>
                        <li> <hr class="dropdown-divider"></li>
                                        <!-- Links -->
                                        <li>
                                            <Link class="dropdown-item" :href="dashboardUrl">
                                                <i class="bi bi-speedometer2 fa-fw me-2"></i>Dashboard
                                            </Link>
                                        </li>
                                        <li><a class="dropdown-item" href="#"><i class="bi bi-person fa-fw me-2"></i>Edit Profile</a></li>
                                        <li><a class="dropdown-item" href="#"><i class="bi bi-gear fa-fw me-2"></i>Account Settings</a></li>
                                        <li><a class="dropdown-item" href="#"><i class="bi bi-info-circle fa-fw me-2"></i>Help</a></li>
                                        <li>
                                            <Link as="button" method="post" :href="route('logout')" class="dropdown-item bg-danger-soft-hover">
                                                <i class="bi bi-power fa-fw me-2"></i>تسجيل الخروج
                                            </Link>
                                        </li>
                                        <li> <hr class="dropdown-divider"></li>

                                        <!-- Dark mode options START -->
                                        <li>
                                            <div class="bg-light dark-mode-switch theme-icon-active d-flex align-items-center p-1 rounded mt-2">
                                                <!-- <span>Mode:</span> -->
                                                <button type="button" class="btn btn-sm mb-0" data-bs-theme-value="light">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-sun fa-fw mode-switch" viewBox="0 0 16 16">
                                                        <path d="M8 11a3 3 0 1 1 0-6 3 3 0 0 1 0 6zm0 1a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM8 0a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 0zm0 13a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 13zm8-5a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2a.5.5 0 0 1 .5.5zM3 8a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2A.5.5 0 0 1 3 8zm10.657-5.657a.5.5 0 0 1 0 .707l-1.414 1.415a.5.5 0 1 1-.707-.708l1.414-1.414a.5.5 0 0 1 .707 0zm-9.193 9.193a.5.5 0 0 1 0 .707L3.05 13.657a.5.5 0 0 1-.707-.707l1.414-1.414a.5.5 0 0 1 .707 0zm9.193 2.121a.5.5 0 0 1-.707 0l-1.414-1.414a.5.5 0 0 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .707zM4.464 4.465a.5.5 0 0 1-.707 0L2.343 3.05a.5.5 0 1 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .708z"/>
                                                        <use href="#"></use>
                                                    </svg> Light
                                                </button>
                                                <button type="button" class="btn btn-sm mb-0" data-bs-theme-value="dark">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-moon-stars fa-fw mode-switch" viewBox="0 0 16 16">
                                                        <path d="M6 .278a.768.768 0 0 1 .08.858 7.208 7.208 0 0 0-.878 3.46c0 4.021 3.278 7.277 7.318 7.277.527 0 1.04-.055 1.533-.16a.787.787 0 0 1 .81.316.733.733 0 0 1-.031.893A8.349 8.349 0 0 1 8.344 16C3.734 16 0 12.286 0 7.71 0 4.266 2.114 1.312 5.124.06A.752.752 0 0 1 6 .278zM4.858 1.311A7.269 7.269 0 0 0 1.025 7.71c0 4.02 3.279 7.276 7.319 7.276a7.316 7.316 0 0 0 5.205-2.162c-.337.042-.68.063-1.029.063-4.61 0-8.343-3.714-8.343-8.29 0-1.167.242-2.278.681-3.286z"/>
                                                        <path d="M10.794 3.148a.217.217 0 0 1 .412 0l.387 1.162c.173.518.579.924 1.097 1.097l1.162.387a.217.217 0 0 1 0 .412l-1.162.387a1.734 1.734 0 0 0-1.097 1.097l-.387 1.162a.217.217 0 0 1-.412 0l-.387-1.162A1.734 1.734 0 0 0 9.31 6.593l-1.162-.387a.217.217 0 0 1 0-.412l1.162-.387a1.734 1.734 0 0 0 1.097-1.097l.387-1.162zM13.863.099a.145.145 0 0 1 .274 0l.258.774c.115.346.386.617.732.732l.774.258a.145.145 0 0 1 0 .274l-.774.258a1.156 1.156 0 0 0-.732.732l-.258.774a.145.145 0 0 1-.274 0l-.258-.774a1.156 1.156 0 0 0-.732-.732l-.774-.258a.145.145 0 0 1 0-.274l.774-.258c.346-.115.617-.386.732-.732L13.863.1z"/>
                                                        <use href="#"></use>
                                                    </svg> Dark
                                                </button>
                                                <button type="button" class="btn btn-sm mb-0 active" data-bs-theme-value="auto">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-circle-half fa-fw mode-switch" viewBox="0 0 16 16">
                                                        <path d="M8 15A7 7 0 1 0 8 1v14zm0 1A8 8 0 1 1 8 0a8 8 0 0 1 0 16z"/>
                                                        <use href="#"></use>
                                                    </svg> Auto
                                                </button>
                                            </div>
                                        </li>
                                        <!-- Dark mode options END-->
                                    </ul>
                                    <!-- Profile dropdown END -->
                                </li>
                                <!-- Profile dropdown END -->
                            </ul>
                        </div>
                        <!-- Top bar right END -->
                    </div>
                </div>
            </nav>
            <!-- Top bar END -->
            <slot/>
        </div>
        <!-- Page content END -->

    </main>
    <!-- **************** MAIN CONTENT END **************** -->
    <!-- Back to top -->
    <div class="back-top"><i class="bi bi-arrow-up-short position-absolute top-50 start-50 translate-middle"></i></div>
</template>

<!-- Sidebar scroll rules: resources/css/eduvera-responsive.css -->
