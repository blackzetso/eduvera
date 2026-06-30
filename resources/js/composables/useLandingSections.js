import { computed, inject } from 'vue'
import { usePage } from '@inertiajs/vue3'

const DEFAULT_BLOCK_ORDER = [
  'hero', 'hero_stats', 'trust', 'about', 'stages', 'why', 'student_life', 'facilities',
  'academics', 'events', 'news', 'gallery', 'principal', 'achievements', 'success_stories',
  'testimonials', 'parent_trust', 'admissions', 'careers', 'blog_anchor', 'partners', 'faq', 'contact', 'final_cta',
]

export function useLandingSections() {
  const page = usePage()
  const injected = inject('websiteContent', null)

  const cms = computed(() => injected?.value ?? page.props.websiteContent ?? {})
  const pageBuilderSections = computed(() => cms.value?.pageBuilderSections ?? [])
  const landingPreview = computed(() => page.props.landingPreview === true)
  const previewDevice = computed(() => page.props.previewDevice ?? 'desktop')

  const visibleSections = computed(() => {
    const rows = pageBuilderSections.value
    if (rows?.length) {
      return rows.filter((s) => s.is_enabled !== false && s.is_visible !== false)
    }
    const legacy = cms.value?.landingSections ?? []
    if (legacy.length) {
      return legacy
        .filter((r) => r.enabled !== false)
        .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))
        .map((r, i) => ({
          uuid: `legacy-${r.key ?? i}`,
          block_type: r.key,
          admin_name: r.label ?? r.key,
          anchor_id: null,
          sort_order: r.sort_order ?? i + 1,
          settings: {},
          content: {},
          show_desktop: true,
          show_tablet: true,
          show_mobile: true,
        }))
    }
    return DEFAULT_BLOCK_ORDER.map((block_type, i) => ({
      uuid: `legacy-${block_type}`,
      block_type,
      admin_name: block_type,
      anchor_id: null,
      sort_order: i + 1,
      settings: {},
      content: {},
      show_desktop: true,
      show_tablet: true,
      show_mobile: true,
    }))
  })

  /** Hero Statistics block — toggled in Landing Builder; renders inside the cinematic hero only. */
  const showHeroInlineStats = computed(() => {
    const data = page.props.websiteContent ?? cms.value ?? {}

    if (data.heroStatsEnabled === true) {
      return true
    }
    if (data.heroStatsEnabled === false) {
      return false
    }

    return pageBuilderSections.value.some((s) => s.block_type === 'hero_stats')
  })

  function deviceClass(section) {
    const classes = []
    if (!section.show_desktop) classes.push('st-hide-desktop')
    if (!section.show_tablet) classes.push('st-hide-tablet')
    if (!section.show_mobile) classes.push('st-hide-mobile')
    return classes.join(' ')
  }

  function wrapperStyle(section) {
    const s = section.settings ?? {}
    const style = {}
    if (s.background_color) style.backgroundColor = s.background_color
    if (s.background_image_url) {
      style.backgroundImage = `url(${s.background_image_url})`
      style.backgroundSize = 'cover'
      style.backgroundPosition = 'center'
    }
    if (s.padding_top) style.paddingTop = s.padding_top
    if (s.padding_bottom) style.paddingBottom = s.padding_bottom
    return style
  }

  function revealClass(section) {
    const anim = section.settings?.animation
    if (anim === 'none') return ''
    return 'st-reveal'
  }

  function sectionEyebrow(section, fallback) {
    return section.settings?.eyebrow ?? fallback
  }

  function sectionTitle(section, fallback) {
    return section.settings?.title ?? fallback
  }

  function sectionSubtitle(section, fallback) {
    return section.settings?.subtitle ?? fallback
  }

  function filterTestimonials(section, list) {
    const c = section.content ?? {}
    if (c.testimonial_ids?.length) {
      return (list ?? []).filter((t) => c.testimonial_ids.includes(t.id))
    }
    if (c.roles?.length) {
      return (list ?? []).filter((t) => c.roles.includes(t.role))
    }
    return list ?? []
  }

  function filterFaqs(section, list) {
    const c = section.content ?? {}
    if (c.categories?.length) {
      return (list ?? []).filter((f) => c.categories.includes(f.cat))
    }
    return list ?? []
  }

  return {
    visibleSections,
    landingPreview,
    previewDevice,
    deviceClass,
    wrapperStyle,
    revealClass,
    sectionEyebrow,
    sectionTitle,
    sectionSubtitle,
    filterTestimonials,
    filterFaqs,
    showHeroInlineStats,
  }
}
