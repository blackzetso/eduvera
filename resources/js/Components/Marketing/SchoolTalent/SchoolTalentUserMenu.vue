<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { useDashboardUrl } from '@/composables/useDashboardUrl'
import { useLocale } from '@/composables/useLocale'

defineProps({
  variant: { type: String, default: 'desktop' },
})

const page = usePage()
const { dashboardUrl } = useDashboardUrl()
const { locale } = useLocale()

const open = ref(false)
const root = ref(null)

const user = computed(() => page.props.auth?.user ?? null)
const isAuthenticated = computed(() => Boolean(user.value))

const labels = computed(() => locale.value === 'ar'
  ? {
      dashboard: 'لوحة التحكم',
      profile: 'الملف الشخصي',
      logout: 'تسجيل الخروج',
      login: 'تسجيل الدخول',
      account: 'حسابي',
    }
  : {
      dashboard: 'Dashboard',
      profile: 'My Profile',
      logout: 'Sign out',
      login: 'Login',
      account: 'My account',
    })

function toggle() {
  open.value = !open.value
}

function close() {
  open.value = false
}

function onDocumentClick(event) {
  if (!open.value || !root.value) {
    return
  }
  if (!root.value.contains(event.target)) {
    close()
  }
}

onMounted(() => document.addEventListener('click', onDocumentClick))
onUnmounted(() => document.removeEventListener('click', onDocumentClick))
</script>

<template>
  <template v-if="isAuthenticated">
    <div
      ref="root"
      class="st-user-menu"
      :class="variant === 'mobile' ? 'st-user-menu--mobile' : 'st-user-menu--desktop'"
    >
      <button
        type="button"
        class="st-user-menu__trigger"
        :aria-expanded="open"
        aria-haspopup="true"
        @click.stop="toggle"
      >
        <img
          class="st-user-menu__avatar"
          :src="user.profile_photo_url"
          :alt="user.name"
          @error="$event.target.src = '/front/theme1/images/avatar/01.jpg'"
        />
        <span v-if="variant === 'desktop'" class="st-user-menu__name d-none d-xxl-inline">{{ user.name }}</span>
        <i class="bi bi-chevron-down st-user-menu__chevron" aria-hidden="true" />
      </button>

      <div v-show="open" class="st-user-menu__dropdown" role="menu" @click.stop>
        <div class="st-user-menu__meta">
          <p class="st-user-menu__meta-name">{{ user.name }}</p>
          <p class="st-user-menu__meta-email">{{ user.email }}</p>
        </div>
        <Link class="st-user-menu__item" :href="dashboardUrl" role="menuitem" @click="close">
          <i class="bi bi-speedometer2" aria-hidden="true" />
          {{ labels.dashboard }}
        </Link>
        <Link class="st-user-menu__item" :href="route('profile.show')" role="menuitem" @click="close">
          <i class="bi bi-person-circle" aria-hidden="true" />
          {{ labels.profile }}
        </Link>
        <Link
          as="button"
          method="post"
          :href="route('logout')"
          class="st-user-menu__item st-user-menu__item--danger"
          role="menuitem"
          @click="close"
        >
          <i class="bi bi-box-arrow-right" aria-hidden="true" />
          {{ labels.logout }}
        </Link>
      </div>
    </div>
  </template>
</template>
