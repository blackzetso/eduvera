/**
 * Safe zones and usage previews for Website Management image fields.
 * Zones use percentages of the full image (0–100). Frames drive crop simulation.
 */

/** @typedef {{ id: string, label: string, left: number, top: number, width: number, height: number, critical?: boolean, variant?: string }} SafeZone */
/** @typedef {{ id: string, label: string, aspectRatio: number, mock?: string }} PreviewFrame */

/**
 * @type {Record<string, { zones?: SafeZone[], frames: PreviewFrame[], safeZoneWarning?: string }>}
 */
export const IMAGE_PREVIEW_PROFILES = {
  hero_background: {
    zones: [
      { id: 'safeText', label: 'Safe Text Area', left: 4, top: 12, width: 48, height: 72, critical: true, variant: 'safe' },
      { id: 'safeMobile', label: 'Safe Mobile Area', left: 8, top: 8, width: 84, height: 42, critical: true, variant: 'safe' },
      { id: 'crop', label: 'Potential Crop Area', left: 0, top: 0, width: 100, height: 100, variant: 'crop' },
    ],
    frames: [
      { id: 'desktop', label: 'Desktop Preview', aspectRatio: 16 / 9, mock: 'hero-bg-desktop' },
      { id: 'tablet', label: 'Tablet Preview', aspectRatio: 4 / 3, mock: 'hero-bg-tablet' },
      { id: 'mobile', label: 'Mobile Preview', aspectRatio: 3 / 4, mock: 'hero-bg-mobile' },
    ],
  },
  hero_image: {
    zones: [
      { id: 'visible', label: 'Visible in Hero', left: 10, top: 8, width: 80, height: 84, critical: true, variant: 'safe' },
      { id: 'crop', label: 'Potential Crop Area', left: 0, top: 0, width: 100, height: 100, variant: 'crop' },
    ],
    frames: [
      { id: 'heroVisual', label: 'Hero Campus Visual', aspectRatio: 4 / 3, mock: 'hero-visual' },
    ],
  },
  landing_section_background: {
    zones: [
      { id: 'safeCenter', label: 'Safe Content Area', left: 15, top: 20, width: 70, height: 60, critical: true, variant: 'safe' },
      { id: 'crop', label: 'Potential Crop Area', left: 0, top: 0, width: 100, height: 100, variant: 'crop' },
    ],
    frames: [
      { id: 'desktop', label: 'Desktop Section', aspectRatio: 16 / 9, mock: 'section-bg' },
      { id: 'mobile', label: 'Mobile Section', aspectRatio: 1 / 1, mock: 'section-bg-mobile' },
    ],
  },
  stage_card: {
    zones: [
      { id: 'visible', label: 'Visible on Cards', left: 8, top: 35, width: 84, height: 55, critical: true, variant: 'safe' },
      { id: 'crop', label: 'Potential Crop Area', left: 0, top: 0, width: 100, height: 100, variant: 'crop' },
    ],
    frames: [
      { id: 'stageCard', label: 'Stage Card Preview', aspectRatio: 3 / 4, mock: 'stage-card' },
    ],
  },
  stage_gallery: {
    zones: [
      { id: 'visible', label: 'Modal Gallery Hero', left: 5, top: 25, width: 90, height: 50, critical: true, variant: 'safe' },
      { id: 'crop', label: 'Potential Crop Area', left: 0, top: 0, width: 100, height: 100, variant: 'crop' },
    ],
    frames: [
      { id: 'modalHero', label: 'Stage Detail Gallery', aspectRatio: 16 / 5, mock: 'stage-modal' },
    ],
  },
  news_featured: {
    zones: [
      { id: 'card', label: 'News Card Crop', left: 5, top: 10, width: 90, height: 80, critical: true, variant: 'safe' },
      { id: 'crop', label: 'Potential Crop Area', left: 0, top: 0, width: 100, height: 100, variant: 'crop' },
    ],
    frames: [
      { id: 'newsCard', label: 'News Card Preview', aspectRatio: 16 / 9, mock: 'news-card' },
      { id: 'articleHero', label: 'Featured Article Preview', aspectRatio: 21 / 9, mock: 'news-article' },
    ],
  },
  event_image: {
    zones: [
      { id: 'card', label: 'Event Card Crop', left: 5, top: 12, width: 90, height: 76, critical: true, variant: 'safe' },
      { id: 'crop', label: 'Potential Crop Area', left: 0, top: 0, width: 100, height: 100, variant: 'crop' },
    ],
    frames: [
      { id: 'eventCard', label: 'Event Card Preview', aspectRatio: 16 / 10, mock: 'event-card' },
    ],
  },
  testimonial_photo: {
    zones: [
      { id: 'face', label: 'Face Safe Area', left: 25, top: 12, width: 50, height: 55, critical: true, variant: 'safe' },
      { id: 'crop', label: 'Potential Crop Area', left: 0, top: 0, width: 100, height: 100, variant: 'crop' },
    ],
    frames: [
      { id: 'circle', label: 'Circular Crop Preview', aspectRatio: 1, mock: 'testimonial-circle' },
      { id: 'square', label: 'Square Crop Preview', aspectRatio: 1, mock: 'testimonial-square' },
    ],
  },
  logo: {
    zones: [
      { id: 'mark', label: 'Logo Mark Safe Area', left: 5, top: 15, width: 90, height: 70, critical: true, variant: 'safe' },
    ],
    frames: [
      { id: 'header', label: 'Header Preview', aspectRatio: 3 / 1, mock: 'logo-header' },
      { id: 'footer', label: 'Footer Preview', aspectRatio: 3 / 1, mock: 'logo-footer' },
    ],
  },
  facility_image: {
    zones: [
      { id: 'visible', label: 'Facility Card', left: 8, top: 10, width: 84, height: 80, critical: true, variant: 'safe' },
    ],
    frames: [{ id: 'facilityCard', label: 'Facility Card Preview', aspectRatio: 3 / 2, mock: 'card-landscape' }],
  },
  teacher_photo: {
    zones: [
      { id: 'subject', label: 'Subject Safe Area', left: 15, top: 5, width: 70, height: 90, critical: true, variant: 'safe' },
    ],
    frames: [{ id: 'recruitment', label: 'Become a Teacher Block', aspectRatio: 3 / 4, mock: 'portrait-block' }],
  },
  about_image: {
    zones: [
      { id: 'visible', label: 'About Section', left: 10, top: 10, width: 80, height: 80, critical: true, variant: 'safe' },
    ],
    frames: [{ id: 'about', label: 'About Section Preview', aspectRatio: 3 / 2, mock: 'card-landscape' }],
  },
  principal_image: {
    zones: [
      { id: 'subject', label: 'Portrait Safe Area', left: 20, top: 5, width: 60, height: 90, critical: true, variant: 'safe' },
    ],
    frames: [{ id: 'principal', label: 'Principal Message', aspectRatio: 3 / 4, mock: 'portrait-block' }],
  },
  gallery_image: {
    frames: [{ id: 'gallery', label: 'Gallery Tile', aspectRatio: 16 / 9, mock: 'card-landscape' }],
  },
  success_story: {
    frames: [{ id: 'story', label: 'Success Story Card', aspectRatio: 16 / 9, mock: 'news-card' }],
  },
  student_life_tile: {
    frames: [{ id: 'tile', label: 'Student Life Tile', aspectRatio: 1, mock: 'square-tile' }],
  },
  og_image: {
    frames: [{ id: 'og', label: 'Social Share Preview', aspectRatio: 1200 / 630, mock: 'og-share' }],
  },
  media_generic: {
    frames: [{ id: 'generic', label: 'Landing Usage', aspectRatio: 16 / 9, mock: 'card-landscape' }],
  },
}

export function getPreviewProfile(specKey) {
  return IMAGE_PREVIEW_PROFILES[specKey] ?? null
}

export function hasUsagePreview(specKey) {
  return Boolean(IMAGE_PREVIEW_PROFILES[specKey])
}
