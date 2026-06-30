<script setup>
import { ref, computed, onMounted, onUnmounted, watch, provide, nextTick } from 'vue'
import { Head } from '@inertiajs/vue3'
import { useWebsiteContent } from '@/composables/useWebsiteContent'
import { useCta } from '@/composables/useCta'
import { useLandingCounters } from '@/composables/useLandingCounters'
import { useSchoolTalentNavigation } from '@/composables/useSchoolTalentNavigation'
import { useLocale } from '@/composables/useLocale'
import FloatingAdmissionsPanel from '@/Components/Marketing/SchoolTalent/FloatingAdmissionsPanel.vue'
import FloatingWhatsApp from '@/Components/Marketing/SchoolTalent/FloatingWhatsApp.vue'
import DovaWidget from '@/Components/Dova/DovaWidget.vue'
import BrandSocialIcon from '@/Components/Marketing/SchoolTalent/BrandSocialIcon.vue'
import SchoolTalentUserMenu from '@/Components/Marketing/SchoolTalent/SchoolTalentUserMenu.vue'
import { usePage } from '@inertiajs/vue3'

const props = defineProps({
  meridianHero: { type: Boolean, default: false },
})

const {
  announcements,
  navLinks,
  schoolInfo,
  headerChrome,
  footerChrome,
  floatingChrome,
  schoolDisplayName,
  logoUrl,
  faviconUrl,
  getSocialLinks,
  formatCopyright,
} = useWebsiteContent()

const page = usePage()
const { headerCta } = useCta()
const { locale, dir, switchLanguageLabel, toggleLocale, switchLocale } = useLocale()

const isAuthenticated = computed(() => Boolean(page.props.auth?.user))

const menuOpen = ref(false)
const announcementIndex = ref(0)
let announceTimer = null
let revealObserver = null

useLandingCounters()

const {
  scrollProgress,
  headerCompact,
  headerFloating,
  announceHidden,
  activeHref,
  backToTopVisible,
  navigateTo,
  scrollToTop,
} = useSchoolTalentNavigation()

provide('stNavigate', navigateTo)
provide('locale', locale)

const socialLinks = computed(() => getSocialLinks(schoolInfo.value?.social))
const headerAnchorHeight = ref(0)

const announcementBadge = computed(() => headerChrome.value?.announcement_badge ?? 'New')
const headerCtas = computed(() => headerChrome.value?.header_ctas ?? [])
const loginLink = computed(() => headerChrome.value?.login ?? { label: 'Login', href: '/login', visible: true })
const footerColumns = computed(() => footerChrome.value?.columns ?? [])
const footerNewsletter = computed(() => footerChrome.value?.newsletter ?? { enabled: false })
const footerLegal = computed(() => footerChrome.value?.legal_links ?? [])
const footerTagline = computed(() => footerChrome.value?.tagline ?? '')
const footerCopyright = computed(() => formatCopyright(footerChrome.value?.copyright))
const logoMark = computed(() => headerChrome.value?.logo_mark_fallback ?? 'ST')
const showLogoImage = computed(() => Boolean(logoUrl.value) && headerChrome.value?.use_logo_image !== false)
const showFloatingPanel = computed(() => floatingChrome.value?.admissions_panel_enabled !== false)
const showWhatsApp = computed(() => floatingChrome.value?.whatsapp_enabled !== false)
const showBackToTop = computed(() => floatingChrome.value?.back_to_top_enabled !== false)

function measureHeaderAnchor() {
  const el = document.querySelector('.st-header')
  if (el) headerAnchorHeight.value = Math.ceil(el.getBoundingClientRect().height)
}

watch(headerFloating, async () => {
  await nextTick()
  measureHeaderAnchor()
})

watch(headerCompact, async () => {
  if (headerFloating.value) {
    await nextTick()
    measureHeaderAnchor()
  }
})

function toggleLang() {
  toggleLocale()
}

function closeMenu() {
  menuOpen.value = false
}

function onNavClick(href, event) {
  navigateTo(href, event)
  closeMenu()
}

function ctaProps(id) {
  const c = headerCta(id, headerChrome)
  return c ? { href: c.href, label: c.label, variant: c.variant } : { href: '#visit', label: id, variant: 'outline' }
}

watch(menuOpen, (open) => {
  document.body.classList.toggle('st-menu-open', open)
})

onMounted(() => {
  document.body.classList.add('st-school-talent')
  const n = announcements.value?.length ?? 0
  if (n > 0) {
    announceTimer = setInterval(() => {
      announcementIndex.value = (announcementIndex.value + 1) % n
    }, 4500)
  }

  measureHeaderAnchor()
  window.addEventListener('resize', measureHeaderAnchor, { passive: true })

  revealObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((e) => {
        if (e.isIntersecting) e.target.classList.add('is-visible')
      })
    },
    { threshold: 0.08, rootMargin: '0px 0px -40px 0px' }
  )
  document.querySelectorAll('.st-reveal').forEach((el) => revealObserver.observe(el))
})

onUnmounted(() => {
  clearInterval(announceTimer)
  revealObserver?.disconnect()
  window.removeEventListener('resize', measureHeaderAnchor)
  document.body.classList.remove('st-menu-open', 'st-school-talent')
})
</script>

<template>
  <Head>
    <link v-if="faviconUrl" rel="icon" :href="faviconUrl" />
    <link
      rel="stylesheet"
      href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700;800&display=swap"
    />
  </Head>
  <div class="st-landing" :class="{ 'st-landing--meridian': meridianHero }" :dir="dir" :lang="locale">
    <div
      v-if="!meridianHero"
      class="st-scroll-progress"
      role="progressbar"
      :aria-valuenow="Math.round(scrollProgress)"
      aria-valuemin="0"
      aria-valuemax="100"
    >
      <div class="st-scroll-progress__bar" :style="{ transform: `scaleX(${scrollProgress / 100})` }"></div>
    </div>

    <div
      class="st-chrome"
      :class="{
        'st-chrome--scroll-away': headerFloating && !meridianHero,
        'st-chrome--scrolled': headerCompact && meridianHero,
      }"
    >
      <div class="st-topbar d-none d-md-block">
        <div class="st-container st-topbar__inner">
          <template v-if="meridianHero">
            <div class="st-meridian-contact">
              <a :href="`tel:${schoolInfo.topBar?.phone}`">{{ schoolInfo.topBar?.phone }}</a>
              <span class="st-meridian-contact__sep" aria-hidden="true">|</span>
              <a :href="`mailto:${schoolInfo.topBar?.email}`">{{ schoolInfo.topBar?.email }}</a>
            </div>
            <div class="st-meridian-langs" role="navigation" aria-label="Language">
              <button type="button" class="st-meridian-langs__btn" :class="{ 'is-active': locale === 'ar' }" @click="switchLocale('ar')">Arabic</button>
              <button type="button" class="st-meridian-langs__btn" :class="{ 'is-active': locale === 'en' }" @click="switchLocale('en')">English</button>
              <span class="st-meridian-langs__btn st-meridian-langs__btn--muted">French</span>
            </div>
          </template>
          <template v-else>
            <div class="st-topbar__contact">
              <a :href="`tel:${schoolInfo.topBar?.phone}`">
                <i class="bi bi-telephone"></i> {{ schoolInfo.topBar?.phone }}
              </a>
              <a :href="`mailto:${schoolInfo.topBar?.email}`">
                <i class="bi bi-envelope"></i> {{ schoolInfo.topBar?.email }}
              </a>
            </div>
            <div class="st-topbar__meta">
              <button type="button" class="st-topbar__lang" @click="toggleLang">
                <i class="bi bi-globe2"></i>
                {{ switchLanguageLabel }}
              </button>
            </div>
          </template>
        </div>
      </div>

      <div v-if="!meridianHero && announcements?.length" class="st-announce" :class="{ 'st-announce--hidden': announceHidden }">
        <div class="st-container st-announce__inner">
          <span class="st-announce__badge">{{ announcementBadge }}</span>
          <a
            :href="announcements[announcementIndex]?.href"
            class="st-announce__text"
            :key="announcementIndex"
            @click="onNavClick(announcements[announcementIndex]?.href, $event)"
          >
            {{ announcements[announcementIndex]?.text }}
          </a>
          <div class="st-announce__dots">
            <span v-for="(_, i) in announcements" :key="i" :class="{ active: i === announcementIndex }"></span>
          </div>
        </div>
      </div>
    </div>

    <div
      class="st-header-anchor"
      :class="{ 'st-header-anchor--floating': headerFloating && !meridianHero }"
      :style="headerFloating && !meridianHero && headerAnchorHeight ? { height: `${headerAnchorHeight}px` } : undefined"
    >
      <header
        class="st-header"
        :class="{
          'st-header--compact': headerCompact && !meridianHero,
          'st-header--floating': headerFloating && !meridianHero,
          'st-header--meridian': meridianHero,
          'st-header--scrolled': headerCompact && meridianHero,
        }"
      >
        <div class="st-container st-header__inner">
          <a href="#home" class="st-logo" @click="onNavClick('#home', $event)">
            <img
              v-if="showLogoImage"
              :src="logoUrl"
              :alt="schoolDisplayName"
              class="st-logo__img"
              :class="{ 'st-logo__img--meridian': meridianHero }"
            />
            <template v-else>
              <span class="st-logo__mark">{{ logoMark }}</span>
              <span class="st-logo__text">{{ schoolDisplayName }}</span>
            </template>
          </a>

          <button type="button" class="st-header__toggle d-lg-none" :aria-expanded="menuOpen" @click="menuOpen = !menuOpen">
            <i :class="menuOpen ? 'bi bi-x-lg' : 'bi bi-list'"></i>
          </button>

          <nav class="st-nav d-none d-lg-flex" aria-label="Primary">
            <a
              v-for="link in navLinks"
              :key="link.href + link.label"
              :href="link.href"
              class="st-nav__link"
              :class="{ 'st-nav__link--active': activeHref === link.href }"
              @click="onNavClick(link.href, $event)"
            >
              <span class="st-nav__label">{{ link.label }}</span>
            </a>
          </nav>

          <div class="st-header__actions">
            <button v-if="!meridianHero" type="button" class="st-header__lang d-md-none" @click="toggleLang" :aria-label="switchLanguageLabel">
              <i class="bi bi-globe2"></i>
            </button>
            <div v-if="!meridianHero && socialLinks.length" class="st-header-social d-none d-xl-flex">
              <a
                v-for="link in socialLinks"
                :key="link.id"
                :href="link.url"
                class="st-header-social__link"
                :class="link.brandIcon ? `st-header-social__link--${link.brandIcon}` : ''"
                target="_blank"
                rel="noopener noreferrer"
                :aria-label="link.label"
              >
                <BrandSocialIcon v-if="link.brandIcon" :brand="link.brandIcon" size="2rem" />
                <i v-else :class="['bi', link.icon]"></i>
              </a>
            </div>
            <template v-for="cta in headerCtas" :key="cta.id">
              <a
                :href="ctaProps(cta.id).href"
                class="st-btn st-btn--sm d-none d-md-inline-flex"
                :class="meridianHero ? 'st-btn--meridian-ghost' : 'st-btn--lift st-btn--outline'"
                @click="onNavClick(ctaProps(cta.id).href, $event)"
              >
                {{ ctaProps(cta.id).label }}
              </a>
            </template>
            <SchoolTalentUserMenu v-if="isAuthenticated" variant="desktop" />
            <a
              v-else-if="loginLink.visible !== false"
              :href="loginLink.href"
              class="d-none d-xl-inline-flex"
              :class="meridianHero ? 'st-btn st-btn--sm st-btn--meridian-login' : 'st-header__link'"
            >{{ loginLink.label }}</a>
          </div>
        </div>
      </header>
    </div>

    <div id="st-mobile-nav" class="st-mobile-nav" :class="{ 'st-mobile-nav--open': menuOpen }">
      <div class="st-mobile-nav__backdrop" @click="closeMenu"></div>
      <div class="st-mobile-nav__panel">
        <div class="st-mobile-nav__head">
          <img v-if="showLogoImage" :src="logoUrl" :alt="schoolDisplayName" class="st-logo__img st-logo__img--sm" />
          <span v-else class="st-logo__text">{{ schoolDisplayName }}</span>
          <button type="button" class="st-mobile-nav__close" @click="closeMenu"><i class="bi bi-x-lg"></i></button>
        </div>
        <nav class="st-mobile-nav__links">
          <a v-for="link in navLinks" :key="'m-' + link.href" :href="link.href" class="st-mobile-nav__link" @click="onNavClick(link.href, $event)">{{ link.label }}</a>
        </nav>
        <div class="st-mobile-nav__cta">
          <a
            v-for="cta in headerCtas"
            :key="'mcta-' + cta.id"
            :href="ctaProps(cta.id).href"
            class="st-btn st-btn--lift"
            :class="ctaProps(cta.id).variant === 'primary' ? 'st-btn--primary' : 'st-btn--outline'"
            @click="onNavClick(ctaProps(cta.id).href, $event)"
          >
            {{ ctaProps(cta.id).label }}
          </a>
          <SchoolTalentUserMenu v-if="isAuthenticated" variant="mobile" />
          <a
            v-else-if="loginLink.visible !== false"
            :href="loginLink.href"
            class="st-btn st-btn--outline st-btn--lift"
            @click="closeMenu"
          >{{ loginLink.label }}</a>
        </div>
      </div>
    </div>

    <main><slot :locale="locale" /></main>

    <FloatingAdmissionsPanel v-if="showFloatingPanel" />
    <FloatingWhatsApp v-if="showWhatsApp" />
    <DovaWidget />

    <button v-show="showBackToTop && backToTopVisible" type="button" class="st-back-top st-lift" aria-label="Back to top" @click="scrollToTop">
      <i class="bi bi-arrow-up"></i>
    </button>

    <footer class="st-footer" id="footer">
      <div class="st-container">
        <div class="st-footer__top">
          <div class="st-footer__brand">
            <a href="#home" class="st-logo st-logo--light" @click="onNavClick('#home', $event)">
              <img v-if="showLogoImage" :src="logoUrl" :alt="schoolDisplayName" class="st-logo__img st-logo__img--sm" />
              <template v-else>
                <span class="st-logo__mark">{{ logoMark }}</span>
                <span class="st-logo__text">{{ schoolDisplayName }}</span>
              </template>
            </a>
            <p v-if="footerTagline">{{ footerTagline }}</p>
            <div v-if="socialLinks.length" class="st-social">
              <a
                v-for="link in socialLinks"
                :key="'ft-' + link.id"
                :href="link.url"
                class="st-social__link"
                :class="link.brandIcon ? `st-social__link--${link.brandIcon}` : ''"
                target="_blank"
                rel="noopener noreferrer"
                :aria-label="link.label"
              >
                <BrandSocialIcon v-if="link.brandIcon" :brand="link.brandIcon" size="2.25rem" />
                <i v-else :class="['bi', link.icon]"></i>
              </a>
            </div>
          </div>
          <div class="st-footer__cols">
            <div v-for="col in footerColumns" :key="col.title" class="st-footer__col">
              <h5>{{ col.title }}</h5>
              <ul>
                <li v-for="link in col.links" :key="link.label + link.href">
                  <a :href="link.href" @click="link.href?.startsWith('#') ? onNavClick(link.href, $event) : undefined">{{ link.label }}</a>
                </li>
              </ul>
            </div>
          </div>
          <div v-if="footerNewsletter?.enabled" class="st-footer__newsletter">
            <h5>{{ footerNewsletter.title }}</h5>
            <p class="small">{{ footerNewsletter.description }}</p>
            <form class="st-newsletter" :action="footerNewsletter.submit_url || undefined" method="post" @submit.prevent="!footerNewsletter.submit_url && $event.preventDefault()">
              <input type="email" :placeholder="footerNewsletter.placeholder" required />
              <button type="submit" class="st-btn st-btn--gold st-btn--sm">{{ footerNewsletter.button_label }}</button>
            </form>
          </div>
        </div>
        <div class="st-footer__bottom">
          <span>{{ footerCopyright }}</span>
          <div class="st-footer__legal">
            <a v-for="link in footerLegal" :key="link.label" :href="link.href">{{ link.label }}</a>
          </div>
        </div>
      </div>
    </footer>
  </div>
</template>
