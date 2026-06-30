import { ref, onMounted, onUnmounted } from 'vue'

/** Nav href → section ids observed for active state */
const SECTION_MAP = [
  { href: '#home', ids: ['home'] },
  { href: '#about', ids: ['about'] },
  { href: '#stages', ids: ['stages'] },
  { href: '#student-life', ids: ['student-life'] },
  { href: '#admissions', ids: ['admissions'] },
  { href: '#news', ids: ['news', 'events'] },
  { href: '#contact', ids: ['contact', 'visit'] },
]

const SCROLL_OFFSET_FALLBACK = 88

export function useSchoolTalentNavigation() {
  const scrollProgress = ref(0)
  const headerCompact = ref(false)
  const headerFloating = ref(false)
  const announceHidden = ref(false)

  /** Scroll distance before Rocket-style floating nav (px) */
  const HEADER_FLOAT_THRESHOLD = 120
  const activeHref = ref('#home')
  const backToTopVisible = ref(false)

  let lastScrollY = 0
  let scrollTicking = false
  let sectionObserver = null
  let scrollOffset = SCROLL_OFFSET_FALLBACK

  function measureScrollOffset() {
    const header = document.querySelector('.st-header')
    if (header) scrollOffset = Math.ceil(header.getBoundingClientRect().height) + 8
  }

  function updateScrollState() {
    const y = window.scrollY
    const max = document.documentElement.scrollHeight - window.innerHeight
    scrollProgress.value = max > 0 ? Math.min(100, Math.max(0, (y / max) * 100)) : 0
    headerCompact.value = y > 60
    headerFloating.value = document.querySelector('.st-landing--meridian') ? false : y >= HEADER_FLOAT_THRESHOLD
    document.documentElement.classList.toggle('st-header-is-floating', headerFloating.value)
    document.documentElement.classList.toggle('st-header-is-scrolled', headerCompact.value)
    if (headerFloating.value) measureScrollOffset()
    backToTopVisible.value = y > 500

    const delta = y - lastScrollY
    if (y <= 60) {
      announceHidden.value = false
    } else if (delta > 4) {
      announceHidden.value = true
    } else if (delta < -4) {
      announceHidden.value = false
    }
    lastScrollY = y
    scrollTicking = false
  }

  function onScroll() {
    if (!scrollTicking) {
      scrollTicking = true
      requestAnimationFrame(updateScrollState)
    }
  }

  function scrollToHash(hash, behavior) {
    if (!hash || !hash.startsWith('#')) return
    const id = hash.slice(1)
    const el = document.getElementById(id)
    if (!el) return
    measureScrollOffset()
    const top = el.getBoundingClientRect().top + window.scrollY - scrollOffset
    window.scrollTo({ top: Math.max(0, top), behavior: behavior ?? 'smooth' })
  }

  function navigateTo(href, event) {
    if (event) event.preventDefault()
    const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
    scrollToHash(href, prefersReduced ? 'auto' : 'smooth')
    if (href) activeHref.value = href
  }

  function scrollToTop() {
    const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
    window.scrollTo({ top: 0, behavior: prefersReduced ? 'auto' : 'smooth' })
  }

  function setupSectionObserver() {
    const visible = new Map()

    sectionObserver = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          visible.set(entry.target.id, entry.isIntersecting ? entry.intersectionRatio : 0)
        })

        let bestHref = activeHref.value
        let bestRatio = 0

        for (const { href, ids } of SECTION_MAP) {
          const ratio = Math.max(...ids.map((id) => visible.get(id) ?? 0))
          if (ratio > bestRatio) {
            bestRatio = ratio
            bestHref = href
          }
        }

        if (bestRatio > 0) activeHref.value = bestHref
      },
      {
        root: null,
        threshold: [0, 0.15, 0.35, 0.55, 0.75],
        rootMargin: '-20% 0px -55% 0px',
      }
    )

    const seen = new Set()
    SECTION_MAP.forEach(({ ids }) => {
      ids.forEach((id) => {
        if (seen.has(id)) return
        seen.add(id)
        const el = document.getElementById(id)
        if (el) sectionObserver.observe(el)
      })
    })
  }

  function onAnchorClick(e) {
    const anchor = e.target.closest('a[href^="#"]')
    if (!anchor || !anchor.getAttribute('href')?.startsWith('#')) return
    const href = anchor.getAttribute('href')
    if (href === '#') return
    const inLanding = anchor.closest('.st-landing')
    if (!inLanding) return
    e.preventDefault()
    navigateTo(href)
  }

  onMounted(() => {
    document.documentElement.classList.add('st-landing-page')
    measureScrollOffset()
    updateScrollState()
    window.addEventListener('scroll', onScroll, { passive: true })
    window.addEventListener('resize', measureScrollOffset, { passive: true })
    document.addEventListener('click', onAnchorClick)
    setupSectionObserver()

    if (window.location.hash) {
      requestAnimationFrame(() => scrollToHash(window.location.hash, 'auto'))
    }
  })

  onUnmounted(() => {
    document.documentElement.classList.remove('st-landing-page', 'st-header-is-floating', 'st-header-is-scrolled')
    window.removeEventListener('scroll', onScroll)
    window.removeEventListener('resize', measureScrollOffset)
    document.removeEventListener('click', onAnchorClick)
    sectionObserver?.disconnect()
  })

  return {
    scrollProgress,
    headerCompact,
    headerFloating,
    announceHidden,
    activeHref,
    backToTopVisible,
    navigateTo,
    scrollToTop,
    navLinks: SECTION_MAP.map((s) => s.href),
  }
}
