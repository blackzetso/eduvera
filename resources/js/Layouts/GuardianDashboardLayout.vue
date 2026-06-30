<script setup>
import AppLayout from '@/Pages/Student/Theme1/Layout/App.vue'
import { Link, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { computed } from 'vue'

const props = defineProps({
  title: { type: String, default: 'لوحة ولي الأمر' },
  guardian: { type: Object, required: true },
  children: { type: Array, default: () => [] },
  student: { type: Object, default: null },
  activeMenu: { type: String, default: 'dashboard' },
})

const page = usePage()
const user = computed(() => page.props.auth?.user ?? props.guardian)

const childMenu = computed(() => {
  if (!props.student) {
    return []
  }
  const id = props.student.id
  return [
    { key: 'overview',    label: 'نظرة عامة',       icon: 'bi-speedometer2',   href: route('guardian.students.overview', id) },
    { key: 'attendance',  label: 'الحضور والغياب',   icon: 'bi-calendar-check', href: route('guardian.students.attendance', id) },
    { key: 'grades',      label: 'الدرجات',           icon: 'bi-journal-text',   href: route('guardian.students.grades', id) },
    { key: 'courses',     label: 'الكورسات',          icon: 'bi-play-circle',    href: route('guardian.students.courses', id) },
    { key: 'schedule',    label: 'الجدول الدراسي',   icon: 'bi-table',          href: route('guardian.students.schedule', id) },
    { key: 'wallet',      label: 'المحفظة',           icon: 'bi-wallet2',        href: route('guardian.students.wallet', id) },
  ]
})

function isActive(key) {
  return props.activeMenu === key
}
</script>

<template>
  <AppLayout>
    <section class="pt-0">
      <div class="container-fluid px-0">
        <div
          class="bg-blue h-100px h-md-200px rounded-0"
          style="background: url('/front/theme1/images/pattern/04.png') no-repeat center center; background-size: cover;"
        />
      </div>
      <div class="container mt-n4">
        <div class="row">
          <div class="col-12">
            <div class="card bg-transparent card-body p-0">
              <div class="row d-flex justify-content-between">
                <div class="col-auto mt-4 mt-md-0">
                  <div class="avatar avatar-xxl mt-n3">
                    <img
                      class="avatar-img rounded-circle border border-white border-3 shadow"
                      :src="user?.profile_photo_url"
                      :alt="user?.name"
                      @error="$event.target.src='/front/theme1/images/avatar/01.jpg'"
                    >
                  </div>
                </div>
                <div class="col d-md-flex justify-content-between align-items-center mt-4">
                  <div>
                    <h1 class="my-1 fs-4">
                      {{ user?.name }}
                      <span class="badge bg-primary-soft text-primary ms-2">ولي أمر</span>
                    </h1>
                    <ul class="list-inline mb-0">
                      <li v-if="student" class="list-inline-item h6 fw-light me-3 mb-1 mb-sm-0">
                        <i class="bi bi-person-badge text-primary me-2" />
                        متابعة: <strong>{{ student.name }}</strong>
                      </li>
                      <li v-else class="list-inline-item h6 fw-light me-3 mb-1 mb-sm-0">
                        <i class="bi bi-people text-orange me-2" />
                        {{ children.length }} ابن/ابنة مسجلون
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
            <hr class="d-xl-none">
            <div class="col-12 col-xl-3 d-flex justify-content-between align-items-center">
              <a class="h6 mb-0 fw-bold d-xl-none" href="#">القائمة</a>
              <button
                class="btn btn-primary d-xl-none"
                type="button"
                data-bs-toggle="offcanvas"
                data-bs-target="#guardianSidebar"
                aria-controls="guardianSidebar"
              >
                <i class="fas fa-sliders-h" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="pt-0">
      <div class="container">
        <div class="row">
          <div class="col-xl-3">
            <div class="offcanvas-xl offcanvas-end" tabindex="-1" id="guardianSidebar">
              <div class="offcanvas-header bg-light">
                <h5 class="offcanvas-title">قائمة ولي الأمر</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#guardianSidebar" aria-label="Close" />
              </div>
              <div class="offcanvas-body p-3 p-xl-0">
                <div class="bg-dark border rounded-3 pb-0 p-3 w-100">
                  <div class="list-group list-group-dark list-group-borderless">
                    <Link
                      :href="route('guardian.dashboard')"
                      class="list-group-item"
                      :class="{ active: isActive('dashboard') }"
                    >
                      <i class="bi bi-ui-checks-grid fa-fw me-2" />لوحة التحكم
                    </Link>

                    <template v-if="student">
                      <div class="text-white-50 small px-3 py-2 mt-2">{{ student.name }}</div>
                      <Link
                        v-for="item in childMenu"
                        :key="item.key"
                        :href="item.href"
                        class="list-group-item"
                        :class="{ active: isActive(item.key) }"
                      >
                        <i :class="[item.icon, 'fa-fw', 'me-2']" />{{ item.label }}
                      </Link>
                    </template>

                    <template v-else-if="children.length">
                      <div class="text-white-50 small px-3 py-2 mt-2">الأبناء</div>
                      <Link
                        v-for="child in children"
                        :key="child.id"
                        :href="route('guardian.students.overview', child.id)"
                        class="list-group-item"
                      >
                        <i class="bi bi-person fa-fw me-2" />{{ child.name }}
                      </Link>
                    </template>

                    <Link
                      v-if="!student"
                      :href="route('guardian.wallet')"
                      class="list-group-item"
                      :class="{ active: isActive('wallet') }"
                    >
                      <i class="bi bi-wallet2 fa-fw me-2" />محفظتي
                    </Link>

                    <Link
                      :href="route('guardian.notifications')"
                      class="list-group-item"
                      :class="{ active: isActive('notifications') }"
                    >
                      <i class="bi bi-bell fa-fw me-2" />إعدادات الإشعارات
                    </Link>
                    <Link
                      :href="route('logout')"
                      method="post"
                      as="button"
                      class="list-group-item text-danger bg-danger-soft-hover"
                    >
                      <i class="fas fa-sign-out-alt fa-fw me-2" />تسجيل الخروج
                    </Link>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-xl-9">
            <slot />
          </div>
        </div>
      </div>
    </section>
  </AppLayout>
</template>
