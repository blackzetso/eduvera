import { computed, inject } from 'vue'
import { usePage } from '@inertiajs/vue3'
import * as staticContent from '@/data/school-talent'
import { useLocale } from '@/composables/useLocale'
import { localizeDeep, mergeLocaleOverrides } from '@/utils/localizeContent'

/**
 * Merges CMS payload from Inertia with static School Talent fallbacks.
 */
export function useWebsiteContent() {
  const injected = inject('websiteContent', null)
  const page = usePage()
  const { locale } = useLocale()

  const rawCms = computed(() => {
    const raw = injected?.value ?? page.props.websiteContent
    return raw && Object.keys(raw).length > 0 ? raw : null
  })

  const cms = computed(() => {
    if (!rawCms.value) {
      return null
    }
    if (locale.value === 'en') {
      return rawCms.value
    }
    // Server already resolves _ar fields via LocalizedContent; merge static AR fallbacks only.
    return mergeLocaleOverrides(rawCms.value)
  })

  const pick = (key, fallback) => computed(() => {
    if (cms.value?.[key] !== undefined) {
      return cms.value[key]
    }
    if (locale.value === 'ar' && fallback != null) {
      return localizeDeep({ [key]: fallback }, 'ar')[key]
    }
    return fallback
  })

  const schoolInfo = pick('schoolInfo', staticContent.schoolInfo)
  const announcements = pick('announcements', staticContent.announcements)
  const navLinks = pick('navLinks', staticContent.navLinks)
  const admissionsFunnelHref = pick('admissionsFunnelHref', staticContent.admissionsFunnelHref)
  const heroStats = pick('heroStats', staticContent.heroStats)
  const parentTrustStrip = pick('parentTrustStrip', staticContent.parentTrustStrip)
  const visitCampusReasons = pick('visitCampusReasons', staticContent.visitCampusReasons)
  const whatsappQuickActions = pick('whatsappQuickActions', staticContent.whatsappQuickActions)
  const heroHighlights = pick('heroHighlights', staticContent.heroHighlights)
  const heroBadges = pick('heroBadges', staticContent.heroBadges)
  const ctaLibrary = pick('ctaLibrary', staticContent.ctaPresets ? Object.entries(staticContent.ctaPresets).map(([id, c]) => ({ id, ...c })) : [])
  const ctaPresets = pick('ctaPresets', staticContent.ctaPresets)
  const sectionCtas = pick('sectionCtas', staticContent.sectionCtas)
  const visitFormConfig = pick('visitFormConfig', staticContent.visitFormConfig)
  const uiLabels = pick('uiLabels', staticContent.uiLabels)
  const headerChrome = pick('headerChrome', {})
  const footerChrome = pick('footerChrome', {})
  const campusVisit = pick('campusVisit', {})
  const admissionDocuments = pick('admissionDocuments', [])
  const floatingChrome = pick('floatingChrome', {})
  const trustItems = pick('trustItems', staticContent.trustItems)
  const coreValues = pick('coreValues', staticContent.coreValues)
  const whyItems = pick('whyItems', staticContent.whyItems)
  const studentLife = pick('studentLife', staticContent.studentLife)
  const facilities = pick('facilities', staticContent.facilities)
  const academicPrograms = pick('academicPrograms', staticContent.academicPrograms)
  const events = pick('events', staticContent.events)
  const newsItems = pick('newsItems', staticContent.newsItems)
  const blogPosts = pick('blogPosts', staticContent.blogPosts)
  const galleryCategories = pick('galleryCategories', staticContent.galleryCategories)
  const galleryItems = pick('galleryItems', staticContent.galleryItems)
  const achievements = pick('achievements', staticContent.achievements)
  const testimonials = pick('testimonials', staticContent.testimonials)
  const accreditations = pick('accreditations', staticContent.accreditations)
  const admissionSteps = pick('admissionSteps', staticContent.admissionSteps)
  const stageShowcaseLabels = pick('stageShowcaseLabels', staticContent.stageShowcaseLabels)
  const stageModalUi = pick('stageModalUi', staticContent.stageModalUi)
  const teacherRecruitment = pick('teacherRecruitment', staticContent.teacherRecruitment)
  const studentSuccessStories = pick('studentSuccessStories', staticContent.studentSuccessStories)
  const faqs = pick('faqs', staticContent.faqs)
  const stages = pick('stages', staticContent.stages)
  const landingSections = pick('landingSections', [])
  const theme = pick('theme', {})

  const schoolDisplayName = computed(() => schoolInfo.value?.name ?? 'School Talent')
  const logoUrl = computed(() => theme.value?.logo_path ?? schoolInfo.value?.logo?.src ?? null)
  const faviconUrl = computed(() => theme.value?.favicon_path ?? null)

  function getNewsBlogFeed() {
    const news = (newsItems.value ?? []).map((n) => ({ ...n, source: 'news' }))
    const blogs = (blogPosts.value ?? []).map((b) => ({ ...b, source: 'blog' }))
    return [...news, ...blogs].sort((a, b) => {
      if (a.isFeatured && !b.isFeatured) return -1
      if (!a.isFeatured && b.isFeatured) return 1
      return String(b.publishedAt ?? b.date ?? '').localeCompare(String(a.publishedAt ?? a.date ?? ''))
    })
  }

  function getSocialLinks(social = schoolInfo.value?.social) {
    return staticContent.getSocialLinks(social)
  }

  function imageSrc(ref) {
    if (!ref) return ''
    let src = typeof ref === 'string' ? ref : (ref.src ?? staticContent.imageSrc(ref))
    if (!src) return ''
    if (src.startsWith('/') && typeof window !== 'undefined') {
      return `${window.location.origin}${src}`
    }
    return src
  }

  function isSectionEnabled(key) {
    const sections = landingSections.value
    if (!sections?.length) return true
    const row = sections.find((s) => s.key === key)
    return row ? row.enabled !== false : true
  }

  function formatCopyright(template) {
    const tpl = template ?? '© {year} {school_name}. All rights reserved.'
    return tpl
      .replace('{year}', String(new Date().getFullYear()))
      .replace('{school_name}', schoolDisplayName.value)
  }

  const themeCssVars = computed(() => {
    const t = theme.value
    if (!t || (!t.primary_color && !t.font_family)) return ''
    const set = (name, val) => (val ? `${name}: ${val}` : null)
    const vars = [
      set('--st-primary', t.primary_color),
      set('--st-primary-hover', t.primary_hover || t.primary_dark || t.primary_color),
      set('--st-primary-dark', t.primary_dark || t.primary_hover || t.primary_color),
      set('--st-primary-light', t.primary_light),
      set('--st-gold', t.gold_color || t.primary_color),
      set('--st-gold-dark', t.gold_dark || t.primary_hover),
      set('--st-gold-light', t.gold_light || t.primary_light),
      set('--st-accent', t.accent_color || t.primary_color),
      set('--st-accent-light', t.accent_light),
      set('--st-navy', t.secondary_color || t.navy_color),
      set('--st-navy-soft', t.navy_soft),
      set('--st-section-dark', t.section_dark),
      set('--st-cream', t.cream_color),
      set('--st-bg', t.bg_color || t.cream_color),
      set('--st-white', t.white_color),
      set('--st-muted', t.muted_color),
      set('--st-border', t.border_color),
      set('--st-success', t.success_color || t.accent_color),
      set('--st-warning', t.warning_color),
      set('--st-danger', t.danger_color),
      set('--st-stat-blue', t.stat_blue || t.primary_color),
      set('--st-stat-green', t.stat_green),
      set('--st-stat-orange', t.stat_orange),
      set('--st-stat-red', t.stat_red),
      set('--st-font', t.font_family),
      set('--st-display', t.display_font),
      set('--st-radius', t.radius),
      set('--st-radius-lg', t.radius_lg),
      t.button_style === 'filled' ? '--st-btn-style: filled' : null,
    ].filter(Boolean)

    return vars.length ? `:root { ${vars.join('; ')}; }` : ''
  })

  return {
    cms,
    schoolInfo,
    schoolDisplayName,
    logoUrl,
    faviconUrl,
    announcements,
    navLinks,
    admissionsFunnelHref,
    heroStats,
    parentTrustStrip,
    visitCampusReasons,
    whatsappQuickActions,
    heroHighlights,
    heroBadges,
    ctaLibrary,
    ctaPresets,
    sectionCtas,
    visitFormConfig,
    uiLabels,
    headerChrome,
    footerChrome,
    campusVisit,
    admissionDocuments,
    floatingChrome,
    trustItems,
    coreValues,
    whyItems,
    studentLife,
    facilities,
    academicPrograms,
    events,
    newsItems,
    blogPosts,
    galleryCategories,
    galleryItems,
    achievements,
    testimonials,
    accreditations,
    admissionSteps,
    stageShowcaseLabels,
    stageModalUi,
    teacherRecruitment,
    studentSuccessStories,
    faqs,
    stages,
    landingSections,
    theme,
    themeCssVars,
    getNewsBlogFeed,
    getSocialLinks,
    imageSrc,
    isSectionEnabled,
    formatCopyright,
    buildWhatsAppUrl: staticContent.buildWhatsAppUrl,
    socialLinkDefinitions: staticContent.socialLinkDefinitions,
    schoolTalentImages: staticContent.schoolTalentImages,
  }
}
