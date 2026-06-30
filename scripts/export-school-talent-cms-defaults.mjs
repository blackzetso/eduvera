/**
 * Exports current School Talent static JS content to JSON for CMS seeding.
 * Run: node scripts/export-school-talent-cms-defaults.mjs
 */
import { writeFileSync, mkdirSync } from 'fs'
import { dirname, join } from 'path'
import { fileURLToPath } from 'url'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')

// Dynamic import of built content — use vite-less direct read via createRequire
const { createRequire } = await import('module')
const require = createRequire(import.meta.url)

// Register minimal path — content is ESM; use dynamic import from project
const contentUrl = new URL('../resources/js/data/school-talent/content.js', import.meta.url).href

const content = await import(contentUrl)

const payload = {
  exportedAt: new Date().toISOString(),
  schoolInfo: content.schoolInfo,
  announcements: content.announcements,
  navLinks: content.navLinks,
  admissionsFunnelHref: content.admissionsFunnelHref,
  heroStats: content.heroStats,
  parentTrustStrip: content.parentTrustStrip,
  visitCampusReasons: content.visitCampusReasons,
  whatsappQuickActions: content.whatsappQuickActions,
  heroHighlights: content.heroHighlights,
  heroBadges: content.heroBadges,
  ctaPresets: content.ctaPresets,
  sectionCtas: content.sectionCtas,
  visitFormConfig: content.visitFormConfig,
  trustItems: content.trustItems,
  coreValues: content.coreValues,
  whyItems: content.whyItems,
  studentLife: content.studentLife,
  facilities: content.facilities,
  academicPrograms: content.academicPrograms,
  events: content.events,
  newsItems: content.newsItems,
  blogPosts: content.blogPosts,
  galleryCategories: content.galleryCategories,
  galleryItems: content.galleryItems,
  achievements: content.achievements,
  testimonials: content.testimonials,
  accreditations: content.accreditations,
  admissionSteps: content.admissionSteps,
  stageShowcaseLabels: content.stageShowcaseLabels,
  teacherRecruitment: content.teacherRecruitment,
  studentSuccessStories: content.studentSuccessStories,
  faqs: content.faqs,
  stages: content.stages,
  landingSections: [
    { key: 'hero', label: 'Hero', enabled: true, sort_order: 1 },
    { key: 'hero_stats', label: 'Hero Statistics', enabled: true, sort_order: 2 },
    { key: 'trust', label: 'Trust Strip', enabled: true, sort_order: 3 },
    { key: 'about', label: 'About', enabled: true, sort_order: 4 },
    { key: 'stages', label: 'Stages', enabled: true, sort_order: 5 },
    { key: 'why', label: 'Why School Talent', enabled: true, sort_order: 6 },
    { key: 'student_life', label: 'Student Life', enabled: true, sort_order: 7 },
    { key: 'facilities', label: 'Facilities', enabled: true, sort_order: 8 },
    { key: 'academics', label: 'Academics', enabled: true, sort_order: 9 },
    { key: 'events', label: 'Events', enabled: true, sort_order: 10 },
    { key: 'news', label: 'News & Blog', enabled: true, sort_order: 11 },
    { key: 'gallery', label: 'Gallery', enabled: true, sort_order: 12 },
    { key: 'principal', label: 'Principal Message', enabled: true, sort_order: 13 },
    { key: 'achievements', label: 'Achievements', enabled: true, sort_order: 14 },
    { key: 'success_stories', label: 'Success Stories', enabled: true, sort_order: 15 },
    { key: 'testimonials', label: 'Testimonials', enabled: true, sort_order: 16 },
    { key: 'parent_trust', label: 'Parent Trust Band', enabled: true, sort_order: 17 },
    { key: 'admissions', label: 'Admissions', enabled: true, sort_order: 18 },
    { key: 'careers', label: 'Careers', enabled: true, sort_order: 19 },
    { key: 'partners', label: 'Partners', enabled: true, sort_order: 20 },
    { key: 'faq', label: 'FAQ', enabled: true, sort_order: 21 },
    { key: 'contact', label: 'Contact & Visit', enabled: true, sort_order: 22 },
    { key: 'final_cta', label: 'Final CTA', enabled: true, sort_order: 23 },
  ],
  theme: {
    primary_color: '#0d6efd',
    secondary_color: '#0f172a',
    accent_color: '#22c55e',
    cta_style: 'outline',
    logo_path: null,
    favicon_path: null,
  },
  seo: {
    meta_title: 'School Talent — International School',
    meta_description:
      'School Talent International School — admissions, world-class education, and vibrant student life in New Cairo.',
    keywords: 'international school, admissions, School Talent, New Cairo',
    og_image_path: null,
  },
}

const outDir = join(root, 'database/data')
mkdirSync(outDir, { recursive: true })
const outFile = join(outDir, 'school-talent-cms-defaults.json')
writeFileSync(outFile, JSON.stringify(payload, null, 2), 'utf8')
console.log('Written', outFile)
