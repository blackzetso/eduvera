<script setup>
import { computed, onMounted, nextTick } from 'vue'
import SchoolTalentSectionCta from '@/Components/Marketing/SchoolTalent/SchoolTalentSectionCta.vue'
import CampusVisitExperience from '@/Components/Marketing/SchoolTalent/CampusVisitExperience.vue'
import StagesShowcase from '@/Components/Marketing/SchoolTalent/StagesShowcase.vue'
import EventsCarousel from '@/Components/Marketing/SchoolTalent/EventsCarousel.vue'
import StudentSuccessStories from '@/Components/Marketing/SchoolTalent/StudentSuccessStories.vue'
import TeacherRecruitmentBlock from '@/Components/Marketing/SchoolTalent/TeacherRecruitmentBlock.vue'
import NewsBlogShowcase from '@/Components/Marketing/SchoolTalent/NewsBlogShowcase.vue'
import CustomSectionBlock from '@/Components/Marketing/SchoolTalent/Builder/CustomSectionBlock.vue'
import { useWebsiteContent } from '@/composables/useWebsiteContent'
import { useLandingSections } from '@/composables/useLandingSections'
import { useCta } from '@/composables/useCta'
import { useUiLabel } from '@/composables/useUiLabel'
import { normalizeMapEmbedUrl } from '@/utils/normalizeMapEmbedUrl'
import { buildWhatsAppUrl } from '@/data/school-talent/content'
import BrandSocialIcon from '@/Components/Marketing/SchoolTalent/BrandSocialIcon.vue'
import SchoolTalentMediaImage from '@/Components/Marketing/SchoolTalent/SchoolTalentMediaImage.vue'

const props = defineProps({
  section: { type: Object, required: true },
  activeGalleryFilter: { type: String, default: 'All' },
})

const emit = defineEmits(['explore-stage', 'gallery-filter'])

const {
  schoolInfo, imageSrc, trustItems, parentTrustStrip, admissionsFunnelHref,
  coreValues, whyItems, studentLife, facilities, academicPrograms, galleryItems,
  galleryCategories, achievements, testimonials, admissionSteps, faqs,
  heroStats, heroBadges, heroHighlights, accreditations, sectionCtas,
} = useWebsiteContent()

const { sectionEyebrow, sectionTitle, sectionSubtitle, filterTestimonials, filterFaqs, revealClass, showHeroInlineStats } = useLandingSections()
const { resolveCta, sectionCtaList } = useCta()
const { l, heroTrustAvatars } = useUiLabel()

const type = computed(() => props.section.block_type)
const anchor = computed(() => props.section.anchor_id)
const sectionTestimonials = computed(() => filterTestimonials(props.section, testimonials.value))
const sectionFaqs = computed(() => filterFaqs(props.section, faqs.value))

const filteredGallery = computed(() => {
  const items = galleryItems.value ?? []
  if (props.activeGalleryFilter === 'All') return items
  return items.filter((g) => g.category === props.activeGalleryFilter)
})

const ctasFor = (key) => {
  const custom = props.section.settings?.ctas
  if (custom?.length) return custom.map((c) => resolveCta(c))
  return sectionCtaList(key)
}

const applyCta = computed(() => resolveCta('apply'))
const visitCta = computed(() => resolveCta('visit'))
const learnMoreCta = computed(() => resolveCta('learnMore'))
const readMoreCta = computed(() => resolveCta('readMore'))
const viewAllEventsCta = computed(() => resolveCta('viewAllEvents'))
const viewNewsBlogCta = computed(() => resolveCta('viewNewsBlog'))

const mapEmbedSrc = computed(() =>
  normalizeMapEmbedUrl(schoolInfo.value?.contact?.mapEmbedUrl ?? '')
)

const whatsAppHref = computed(() => {
  const phone = schoolInfo.value?.contact?.whatsapp
  if (!phone) return null
  return buildWhatsAppUrl(phone, 'Hello, I would like to book a campus visit.')
})

const HERO_VIDEO_DEFAULT = '/front/theme1/video/hero-video.mp4'

const heroVideoUrl = computed(() => {
  const fromSection = props.section.settings?.video_url?.trim()
  const fromHero = schoolInfo.value?.hero?.videoUrl?.trim()
  return fromSection || fromHero || HERO_VIDEO_DEFAULT
})

const heroPosterUrl = computed(() => {
  const sectionBg = props.section.settings?.background_image_url?.trim()
  if (sectionBg) return sectionBg
  const bg = schoolInfo.value?.hero?.backgroundImage
  if (!bg) return undefined
  return imageSrc(bg) || undefined
})

const heroPill = computed(() => {
  const pill = schoolInfo.value?.hero?.pill?.trim()
  if (pill) return pill
  const year = schoolInfo.value?.founded || 1948
  return `EST. ${year} · EXCELLENCE IN EDUCATION`
})

const watchStoryLabel = computed(() => schoolInfo.value?.hero?.watchStoryLabel || 'WATCH OUR STORY')

const heroCtas = computed(() => {
  const list = ctasFor('hero')
  if (list.length) return list
  return [
    { label: 'Explore Admissions', href: applyCta.value?.href ?? '#admissions', variant: 'gold' },
    { label: 'View Our Programs', href: learnMoreCta.value?.href ?? '#stages', variant: 'outline' },
  ]
})

function heroCtaClass(variant) {
  if (variant === 'gold' || variant === 'primary') return 'st-btn--gold-hero'
  return 'st-btn--outline-light'
}

const meridianHeroStats = computed(() => {
  const stats = heroStats.value ?? []
  const founded = schoolInfo.value?.founded || 1948
  const years = new Date().getFullYear() - founded

  return stats.map((s) => {
    if (/years/i.test(s.label ?? '')) {
      return { ...s, end: years, suffix: s.suffix ?? '' }
    }
    return s
  })
})

onMounted(() => {
  nextTick(() => {
    if (typeof window.GLightbox === 'function') {
      window.GLightbox({ selector: '.st-hero__play' })
    }
  })
})
</script>

<template>
  <!-- Hero — cinematic video backdrop -->
  <section v-if="type === 'hero'" class="st-hero st-hero--cinematic" :id="anchor || 'home'">
    <div class="st-hero__media" aria-hidden="true">
      <video
        class="st-hero__video"
        autoplay
        muted
        loop
        playsinline
        :poster="heroPosterUrl"
      >
        <source :src="heroVideoUrl" type="video/mp4" />
      </video>
      <div class="st-hero__overlay"></div>
    </div>

    <div class="st-container st-hero__shell">
      <div class="st-hero__grid" :class="revealClass(section)">
        <div class="st-hero__copy">
          <span class="st-hero__pill">{{ heroPill }}</span>
          <h1 class="st-hero__title">
            <span class="st-hero__title-line">{{ schoolInfo.hero.headlineLine1 }}</span>
            <span class="st-hero__title-accent">{{ schoolInfo.hero.headlineAccent }}</span>
            <span v-if="schoolInfo.hero.headlineLine2" class="st-hero__title-line st-hero__title-line--secondary">
              {{ schoolInfo.hero.headlineLine2 }}
            </span>
          </h1>
          <p class="st-hero__sub">{{ sectionSubtitle(section, schoolInfo.hero.subheadline) }}</p>
          <div class="st-hero__cta">
            <a
              v-for="(cta, i) in heroCtas"
              :key="cta.label + i"
              :href="cta.href"
              class="st-btn"
              :class="heroCtaClass(cta.variant)"
            >
              {{ cta.label }}
              <i v-if="i === 0" class="bi bi-arrow-right" aria-hidden="true"></i>
              <i v-else class="bi bi-chevron-down" aria-hidden="true"></i>
            </a>
          </div>
        </div>

        <div class="st-hero__story">
          <a
            :href="heroVideoUrl"
            class="st-hero__play glightbox"
            data-glightbox="type: video"
            :aria-label="watchStoryLabel"
          >
            <span class="st-hero__play-ring">
              <i class="bi bi-play-fill" aria-hidden="true"></i>
            </span>
            <span class="st-hero__play-label">{{ watchStoryLabel }}</span>
          </a>
        </div>
      </div>

      <div v-if="showHeroInlineStats" class="st-hero__stats" aria-label="School statistics">
        <div class="st-hero__stats-grid">
          <div v-for="s in meridianHeroStats" :key="s.label" class="st-hero__stat">
            <div
              class="st-hero__stat-value"
              :data-counter-end="String(s.end)"
              :data-counter-prefix="s.prefix ?? ''"
              :data-counter-suffix="s.suffix ?? ''"
              :data-counter-decimals="String(s.decimals ?? 0)"
            >0</div>
            <div class="st-hero__stat-label">{{ s.label }}</div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- hero_stats: visibility toggled in Landing Builder; numbers render inside the hero band above -->
  <template v-else-if="type === 'hero_stats'" />

  <div v-else-if="type === 'trust'" class="st-trust">
    <div class="st-container">
      <div class="st-trust__inner">
        <span v-for="t in trustItems" :key="t" class="st-trust__item"><i class="bi bi-check-circle-fill"></i>{{ t }}</span>
      </div>
    </div>
  </div>

  <section v-else-if="type === 'about'" class="st-section st-section--white" :id="anchor || 'about'">
    <div class="st-container">
      <div class="st-about-grid" :class="revealClass(section)">
        <div>
          <span class="st-eyebrow">{{ sectionEyebrow(section, schoolInfo.about.eyebrow) }}</span>
          <h2 class="mb-3 st-section-title">{{ sectionTitle(section, schoolInfo.about.title) }}</h2>
          <p class="text-muted">{{ schoolInfo.about.intro }}</p>
          <p class="text-muted"><strong>{{ l('global.mission_label', 'Mission:') }}</strong> {{ schoolInfo.about.mission }}</p>
          <p class="text-muted"><strong>{{ l('global.vision_label', 'Vision:') }}</strong> {{ schoolInfo.about.vision }}</p>
          <div class="st-values">
            <div v-for="v in coreValues" :key="v.title" class="st-value">
              <i :class="['bi', v.icon]"></i>
              <strong class="d-block mt-2">{{ v.title }}</strong>
              <span class="small text-muted">{{ v.text }}</span>
            </div>
          </div>
          <a :href="learnMoreCta.href" class="st-btn st-btn--navy mt-3">{{ learnMoreCta.label }}</a>
          <SchoolTalentSectionCta :ctas="ctasFor('about')" align="start" />
        </div>
        <SchoolTalentMediaImage :image="schoolInfo.about.image" :alt="schoolInfo.about.image?.alt" img-class="st-about__img" />
      </div>
    </div>
  </section>

  <section v-else-if="type === 'stages'" class="st-stages st-stages--showcase" :id="anchor || 'stages'">
    <div class="st-section-shape st-section-shape--3" aria-hidden="true"></div>
    <div class="st-container">
      <div class="st-stages__head" :class="revealClass(section)">
        <span class="st-eyebrow">{{ sectionEyebrow(section, 'Featured') }}</span>
        <h2>{{ sectionTitle(section, "Choose Your Child's Journey") }}</h2>
        <p class="text-muted mx-auto" style="max-width: 520px">{{ sectionSubtitle(section, 'Explore each stage — curriculum, activities, and admission pathways from Kindergarten through Secondary.') }}</p>
      </div>
      <StagesShowcase @explore="emit('explore-stage', $event)" />
      <SchoolTalentSectionCta :ctas="ctasFor('stages')" />
    </div>
  </section>

  <section v-else-if="type === 'why'" class="st-section st-section--soft-blue st-features" :id="anchor || 'why'">
    <div class="st-container">
      <div class="st-features__layout" :class="revealClass(section)">
        <div class="st-features__intro">
          <span class="st-eyebrow">{{ sectionEyebrow(section, 'Features') }}</span>
          <h2>{{ sectionTitle(section, 'More Than a School — Advanced Learning Experience') }}</h2>
          <p>{{ sectionSubtitle(section, 'Modern classrooms, caring educators, and programs designed to help every student excel academically and personally.') }}</p>
          <ul class="st-features__bullets">
            <li v-for="h in heroHighlights" :key="'feat-' + h.text"><i :class="['bi', h.icon]"></i>{{ h.text }}</li>
          </ul>
          <a :href="readMoreCta.href" class="st-btn st-btn--primary st-btn--sm">{{ readMoreCta.label }}</a>
        </div>
        <div class="st-features__cards">
          <div v-for="w in whyItems" :key="w.title" class="st-feature-card">
            <div class="st-feature-card__icon"><i :class="['bi', w.icon]"></i></div>
            <h3>{{ w.title }}</h3>
            <p>{{ w.text }}</p>
          </div>
        </div>
      </div>
      <SchoolTalentSectionCta :ctas="ctasFor('why')" />
    </div>
  </section>

  <section v-else-if="type === 'student_life'" class="st-section st-section--cream" :id="anchor || 'student-life'">
    <div class="st-container">
      <div class="st-section__head" :class="revealClass(section)">
        <span class="st-eyebrow">{{ sectionEyebrow(section, 'Student Life') }}</span>
        <h2>{{ sectionTitle(section, 'Beyond the Classroom') }}</h2>
        <p>{{ sectionSubtitle(section, 'Sports, arts, STEM, leadership, and service — every student finds their passion.') }}</p>
      </div>
      <div class="st-life-grid" :class="revealClass(section)">
        <div v-for="item in studentLife" :key="item.id" class="st-life-tile">
          <SchoolTalentMediaImage :image="item.image" :alt="item.image?.alt || item.name" />
          <div class="st-life-tile__label"><i :class="['bi', item.icon]"></i>{{ item.name }}</div>
        </div>
      </div>
      <SchoolTalentSectionCta :ctas="ctasFor('studentLife')" />
    </div>
  </section>

  <section v-else-if="type === 'facilities'" class="st-section st-section--soft-blue" :id="anchor || 'facilities'">
    <div class="st-container">
      <div class="st-section__head" :class="revealClass(section)">
        <span class="st-eyebrow">{{ sectionEyebrow(section, 'Campus') }}</span>
        <h2>{{ sectionTitle(section, 'World-Class Facilities') }}</h2>
        <p>{{ sectionSubtitle(section, 'Spaces designed for learning, performance, wellbeing, and innovation.') }}</p>
      </div>
      <div class="st-grid-3" :class="revealClass(section)">
        <div v-for="f in facilities" :key="f.id" class="st-facility-card">
          <div class="st-facility-card__icon"><i :class="['bi', f.icon]"></i></div>
          <h3>{{ f.name }}</h3>
          <p class="st-facility-card__desc">{{ f.description }}</p>
          <p class="st-facility-card__benefit"><i class="bi bi-check-circle-fill"></i> {{ f.benefit }}</p>
        </div>
      </div>
      <SchoolTalentSectionCta :ctas="ctasFor('facilities')" />
    </div>
  </section>

  <section v-else-if="type === 'academics'" class="st-section st-section--white" :id="anchor || 'academics'">
    <div class="st-container">
      <div class="st-section__head" :class="revealClass(section)">
        <span class="st-eyebrow">{{ sectionEyebrow(section, 'Programs') }}</span>
        <h2>{{ sectionTitle(section, 'Academic Programs') }}</h2>
      </div>
      <div class="st-grid-3" :class="revealClass(section)">
        <div v-for="p in academicPrograms" :key="p.title" class="st-card">
          <h3>{{ p.title }}</h3>
          <p class="text-muted small mb-0">{{ p.text }}</p>
        </div>
      </div>
      <SchoolTalentSectionCta :ctas="ctasFor('academics')" />
    </div>
  </section>

  <section v-else-if="type === 'events'" class="st-section st-section--soft-blue" :id="anchor || 'events'">
    <div class="st-container">
      <div class="st-section__head" :class="revealClass(section)">
        <span class="st-eyebrow">{{ sectionEyebrow(section, 'Calendar') }}</span>
        <h2>{{ sectionTitle(section, 'Events & Activities') }}</h2>
      </div>
      <EventsCarousel />
      <SchoolTalentSectionCta :ctas="ctasFor('events')" />
      <div class="text-center mt-3" :class="revealClass(section)">
        <a :href="viewAllEventsCta.href" class="st-btn st-btn--outline">{{ viewAllEventsCta.label }}</a>
      </div>
    </div>
  </section>

  <section v-else-if="type === 'news'" class="st-section st-section--cream" :id="anchor || 'news'">
    <div class="st-container">
      <div class="st-section__head" :class="revealClass(section)">
        <span class="st-eyebrow">{{ sectionEyebrow(section, 'News & Blog') }}</span>
        <h2>{{ sectionTitle(section, 'Latest Stories & Updates') }}</h2>
        <p>{{ sectionSubtitle(section, 'School news, parent guides, and insights from our community.') }}</p>
      </div>
      <NewsBlogShowcase />
    </div>
  </section>

  <section v-else-if="type === 'gallery'" class="st-section st-section--soft-blue" :id="anchor || 'gallery'">
    <div class="st-container">
      <div class="st-section__head" :class="revealClass(section)">
        <span class="st-eyebrow">{{ sectionEyebrow(section, 'Gallery') }}</span>
        <h2>{{ sectionTitle(section, 'Life at School Talent') }}</h2>
        <div class="d-flex flex-wrap justify-content-center gap-2 mt-3">
          <button type="button" class="st-btn st-btn--sm" :class="activeGalleryFilter === 'All' ? 'st-btn--gold' : 'st-btn--outline'" @click="emit('gallery-filter', 'All')">{{ l('global.gallery_all', 'All') }}</button>
          <button v-for="cat in galleryCategories" :key="cat" type="button" class="st-btn st-btn--sm" :class="activeGalleryFilter === cat ? 'st-btn--gold' : 'st-btn--outline'" @click="emit('gallery-filter', cat)">{{ cat }}</button>
        </div>
      </div>
      <div v-if="filteredGallery.length" class="st-gallery-grid" :class="revealClass(section)">
        <figure v-for="img in filteredGallery" :key="img.id" class="st-gallery-card">
          <SchoolTalentMediaImage :image="{ src: img.src, alt: img.alt }" :alt="img.alt || 'Gallery photo'" />
          <figcaption v-if="img.alt" class="st-gallery-card__caption">{{ img.alt }}</figcaption>
        </figure>
      </div>
      <p v-else class="text-center text-muted st-gallery-empty">No photos in this category yet.</p>
    </div>
  </section>

  <section v-else-if="type === 'principal'" class="st-section st-section--cream">
    <div class="st-container">
      <div class="st-principal" :class="revealClass(section)">
        <SchoolTalentMediaImage :image="schoolInfo.principal.image" :alt="schoolInfo.principal.image?.alt" img-class="st-principal__photo" />
        <div>
          <span class="st-eyebrow">{{ sectionEyebrow(section, schoolInfo.principal.eyebrow) }}</span>
          <h2 class="st-section-title">{{ sectionTitle(section, schoolInfo.principal.title) }}</h2>
          <p class="text-muted">{{ schoolInfo.principal.message }}</p>
          <a :href="readMoreCta.href" class="st-btn st-btn--outline">{{ readMoreCta.label }}</a>
        </div>
      </div>
    </div>
  </section>

  <section v-else-if="type === 'achievements'" class="st-section st-section--navy">
    <div class="st-container">
      <div class="st-section__head" :class="revealClass(section)">
        <span class="st-eyebrow">{{ sectionEyebrow(section, 'Achievements') }}</span>
        <h2>{{ sectionTitle(section, 'Our Students Shine') }}</h2>
        <p>{{ sectionSubtitle(section, 'University placements, competitions, olympiads, sports, and awards.') }}</p>
      </div>
      <div class="st-stats-band" :class="revealClass(section)">
        <div v-for="a in achievements" :key="a.id" class="st-stat-box">
          <strong>{{ a.value }}</strong>
          <span class="small">{{ a.label }}</span>
        </div>
      </div>
      <SchoolTalentSectionCta :ctas="ctasFor('achievements')" />
    </div>
  </section>

  <StudentSuccessStories v-else-if="type === 'success_stories'" />

  <section v-else-if="type === 'testimonials'" class="st-section st-section--soft-blue">
    <div class="st-container">
      <div class="st-section__head" :class="revealClass(section)">
        <span class="st-eyebrow">{{ sectionEyebrow(section, 'Community') }}</span>
        <h2>{{ sectionTitle(section, 'What Families Say') }}</h2>
      </div>
      <div class="st-grid-4" :class="revealClass(section)">
        <div v-for="t in sectionTestimonials" :key="t.id" class="st-testimonial">
          <SchoolTalentMediaImage :image="t.photo" :alt="t.photo?.alt || t.name" img-class="st-testimonial__photo" />
          <span class="st-testimonial__role">{{ t.role }}</span>
          <p class="st-testimonial__quote">«{{ t.quote }}»</p>
          <strong class="st-testimonial__name">{{ t.name }}</strong>
        </div>
      </div>
      <SchoolTalentSectionCta :ctas="ctasFor('testimonials')" />
    </div>
  </section>

  <div v-else-if="type === 'parent_trust'" class="st-parent-trust" :class="revealClass(section)">
    <div class="st-container st-parent-trust__inner">
      <div v-for="item in parentTrustStrip" :key="item.label" class="st-parent-trust__item">
        <i :class="['bi', item.icon]"></i><span>{{ item.label }}</span>
      </div>
    </div>
  </div>

  <section v-else-if="type === 'admissions'" class="st-section st-section--cream" :id="anchor || 'admissions'">
    <div class="st-container">
      <div class="st-section__head" :class="revealClass(section)">
        <span class="st-eyebrow">{{ sectionEyebrow(section, 'Admissions') }}</span>
        <h2>{{ sectionTitle(section, 'Become a Student') }}</h2>
        <p>{{ sectionSubtitle(section, 'Your journey to School Talent in five clear steps.') }}</p>
      </div>
      <div class="st-steps" :class="revealClass(section)">
        <div v-for="s in admissionSteps" :key="s.step" class="st-step">
          <div class="st-step__num">{{ s.step }}</div>
          <h3 class="h6">{{ s.title }}</h3>
          <p class="small text-muted mb-0">{{ s.text }}</p>
        </div>
      </div>
        <div class="text-center mt-4" :class="revealClass(section)">
          <a :href="applyCta.href" class="st-btn st-btn--gold st-btn--lift">{{ applyCta.label }}</a>
          <a :href="visitCta.href" class="st-btn st-btn--outline ms-2 st-btn--lift">{{ visitCta.label }}</a>
        </div>
    </div>
  </section>

  <TeacherRecruitmentBlock v-else-if="type === 'careers'" />

  <section v-else-if="type === 'blog_anchor'" class="st-section st-section--cream st-blog-anchor" :id="anchor || 'blog'">
    <div class="st-container text-center" :class="revealClass(section)">
      <span class="st-eyebrow">{{ sectionEyebrow(section, 'Blog') }}</span>
      <h2 class="h4 mb-2">{{ sectionTitle(section, 'Explore All Articles') }}</h2>
      <p class="text-muted mb-3">{{ sectionSubtitle(section, 'Parent guides, learning tips, and school updates are featured in our news hub above.') }}</p>
      <a :href="viewNewsBlogCta.href" class="st-btn st-btn--outline st-btn--lift">{{ viewNewsBlogCta.label }}</a>
    </div>
  </section>

  <section v-else-if="type === 'partners'" class="st-section st-section--soft-blue">
    <div class="st-container">
      <div class="st-section__head" :class="revealClass(section)">
        <span class="st-eyebrow">{{ sectionEyebrow(section, 'Partners') }}</span>
        <h2>{{ sectionTitle(section, 'Accreditations & Partners') }}</h2>
      </div>
      <div class="st-accred-grid" :class="revealClass(section)">
        <div v-for="a in accreditations" :key="a.id" class="st-accred-card">
          <div class="st-accred-card__logo">
            <SchoolTalentMediaImage v-if="a.logo?.src" :image="a.logo" :alt="a.logo.alt" />
            <span v-else class="st-accred-card__mark">{{ a.abbr }}</span>
          </div>
          <h3>{{ a.name }}</h3>
          <p class="st-accred-card__desc">{{ a.description }}</p>
          <p class="st-accred-card__benefit"><i class="bi bi-award"></i> {{ a.benefit }}</p>
          <a v-if="a.verifyUrl && a.verifyUrl !== '#'" :href="a.verifyUrl" class="st-accred-card__verify" target="_blank" rel="noopener noreferrer">{{ l('global.verify_accreditation', 'Verify accreditation') }} <i class="bi bi-box-arrow-up-right"></i></a>
        </div>
      </div>
    </div>
  </section>

  <section v-else-if="type === 'faq'" class="st-section st-section--cream" :id="anchor || 'faq'">
    <div class="st-container">
      <div class="st-section__head" :class="revealClass(section)">
        <span class="st-eyebrow">{{ sectionEyebrow(section, 'FAQ') }}</span>
        <h2>{{ sectionTitle(section, 'Frequently Asked Questions') }}</h2>
      </div>
      <div class="st-faq mx-auto" :class="revealClass(section)" style="max-width: 720px">
        <details v-for="f in sectionFaqs" :key="f.q">
          <summary>{{ f.q }} <span class="float-end badge bg-light text-muted">{{ f.cat }}</span></summary>
          <p>{{ f.a }}</p>
        </details>
      </div>
      <SchoolTalentSectionCta :ctas="ctasFor('faq')" />
    </div>
  </section>

  <section v-else-if="type === 'contact'" class="st-section st-contact-section" :id="anchor || 'contact'">
    <div class="st-contact-hero" :class="revealClass(section)">
      <div class="st-contact-hero__shape st-contact-hero__shape--1" aria-hidden="true"></div>
      <div class="st-contact-hero__shape st-contact-hero__shape--2" aria-hidden="true"></div>
      <div class="st-container st-contact-hero__inner">
        <span class="st-eyebrow">{{ sectionEyebrow(section, 'Visit Us') }}</span>
        <h2 class="st-contact-hero__title">{{ sectionTitle(section, 'Contact & Campus Visit') }}</h2>
        <p class="st-contact-hero__lead">
          {{ sectionSubtitle(section, 'Plan your tour, explore our campus, and connect with our admissions team — we are here to welcome your family.') }}
        </p>
        <div class="st-contact-hero__pills">
          <span><i class="bi bi-geo-alt"></i> {{ schoolInfo.contact.address }}</span>
          <span><i class="bi bi-clock"></i> {{ schoolInfo.contact.hours }}</span>
        </div>
      </div>
    </div>

    <div class="st-container">
      <div class="st-contact-grid" :class="revealClass(section)">
        <aside class="st-contact-info">
          <div class="st-contact-info__map st-map">
            <iframe
              v-if="mapEmbedSrc"
              :title="schoolInfo.contact.mapTitle || 'School Talent location'"
              :src="mapEmbedSrc"
              width="100%"
              height="100%"
              style="border:0"
              loading="lazy"
              referrerpolicy="no-referrer-when-downgrade"
              allowfullscreen
            />
            <div class="st-contact-info__map-badge">
              <i class="bi bi-pin-map-fill"></i>
              {{ l('global.find_us', 'Find us on the map') }}
            </div>
          </div>

          <div class="st-contact-info__cards">
            <a
              v-if="schoolInfo.contact.phone"
              :href="`tel:${schoolInfo.contact.phone.replace(/\s/g, '')}`"
              class="st-contact-info__card"
            >
              <span class="st-contact-info__icon"><i class="bi bi-telephone-fill"></i></span>
              <span class="st-contact-info__text">
                <strong>{{ l('global.phone', 'Phone') }}</strong>
                <span>{{ schoolInfo.contact.phone }}</span>
              </span>
            </a>

            <a
              v-if="whatsAppHref"
              :href="whatsAppHref"
              class="st-contact-info__card st-contact-info__card--whatsapp"
              target="_blank"
              rel="noopener noreferrer"
            >
              <span class="st-contact-info__icon"><BrandSocialIcon brand="whatsapp" size="1.35rem" /></span>
              <span class="st-contact-info__text">
                <strong>WhatsApp</strong>
                <span>{{ schoolInfo.contact.whatsapp }}</span>
              </span>
            </a>

            <a
              v-if="schoolInfo.contact.email"
              :href="`mailto:${schoolInfo.contact.email}`"
              class="st-contact-info__card"
            >
              <span class="st-contact-info__icon"><i class="bi bi-envelope-fill"></i></span>
              <span class="st-contact-info__text">
                <strong>{{ l('global.email', 'Email') }}</strong>
                <span>{{ schoolInfo.contact.email }}</span>
              </span>
            </a>

            <div class="st-contact-info__card st-contact-info__card--static">
              <span class="st-contact-info__icon"><i class="bi bi-geo-alt-fill"></i></span>
              <span class="st-contact-info__text">
                <strong>{{ l('global.address', 'Address') }}</strong>
                <span>{{ schoolInfo.contact.address }}</span>
              </span>
            </div>
          </div>
        </aside>

        <div id="visit" class="st-contact-visit">
          <CampusVisitExperience />
        </div>
      </div>
    </div>
  </section>

  <section v-else-if="type === 'final_cta'" class="st-cta-final" :class="revealClass(section)">
    <div class="st-container">
      <h2>{{ sectionTitle(section, schoolInfo.finalCta.headline) }}</h2>
      <p class="mb-4 opacity-75">{{ sectionSubtitle(section, schoolInfo.finalCta.subheadline) }}</p>
      <div class="d-flex flex-wrap justify-content-center gap-2">
        <a :href="applyCta.href" class="st-btn st-btn--gold st-btn--lift">{{ applyCta.label }}</a>
        <a :href="visitCta.href" class="st-btn st-btn--outline-light st-btn--lift">{{ visitCta.label }}</a>
      </div>
    </div>
  </section>

  <CustomSectionBlock v-else-if="type === 'custom'" :section="section" />
</template>
