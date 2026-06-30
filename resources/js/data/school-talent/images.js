/**
 * Image asset registry — swap `src` when real school photos are available.
 * `assetKey` maps to future CMS/media library identifiers.
 */
const PLACEHOLDER = {
  hero:
    'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?w=1600&q=85',
  campus:
    'https://images.unsplash.com/photo-1541339907198-e08756dedf3f?w=800&q=80',
  principal:
    'https://images.unsplash.com/photo-1560250097-0b93528c311a?w=400&q=80',
  faculty:
    'https://images.unsplash.com/photo-1524178232363-1fb2b075b655?w=800&q=80',
}

export const schoolTalentImages = {
  hero: {
    assetKey: 'landing.hero',
    src: PLACEHOLDER.hero,
    alt: 'School Talent international campus',
  },
  about: {
    assetKey: 'landing.about',
    src: PLACEHOLDER.campus,
    alt: 'School Talent campus aerial view',
  },
  principal: {
    assetKey: 'landing.principal',
    src: PLACEHOLDER.principal,
    alt: 'School Talent principal',
  },
  careers: {
    assetKey: 'landing.careers',
    src: PLACEHOLDER.faculty,
    alt: 'School Talent faculty collaboration',
  },
  studentLife: {
    sports: {
      assetKey: 'student-life.sports',
      src: 'https://images.unsplash.com/photo-1461896836934-ffe607f8219a?w=600&q=80',
      alt: 'Students in sports activities',
    },
    arts: {
      assetKey: 'student-life.arts',
      src: 'https://images.unsplash.com/photo-1513364776144-60967b0f800f?w=600&q=80',
      alt: 'Arts programme',
    },
    music: {
      assetKey: 'student-life.music',
      src: 'https://images.unsplash.com/photo-1514320291840-7555e9c9625f?w=600&q=80',
      alt: 'Music programme',
    },
    robotics: {
      assetKey: 'student-life.robotics',
      src: 'https://images.unsplash.com/photo-1485827404703-89b55fcc595e?w=600&q=80',
      alt: 'Robotics club',
    },
    stem: {
      assetKey: 'student-life.stem',
      src: 'https://images.unsplash.com/photo-1532094349884-543bc11b234d?w=600&q=80',
      alt: 'STEM laboratory',
    },
    leadership: {
      assetKey: 'student-life.leadership',
      src: 'https://images.unsplash.com/photo-1523240795612-9a054b0db644?w=600&q=80',
      alt: 'Student leadership',
    },
    community: {
      assetKey: 'student-life.community',
      src: 'https://images.unsplash.com/photo-1559027615-cd4628902d4a?w=600&q=80',
      alt: 'Community service',
    },
  },
  stages: {
    earlyYears: {
      assetKey: 'stages.early-years',
      src: 'https://images.unsplash.com/photo-1587654780291-39c9404d746b?w=800&q=80',
      alt: 'Early Years classroom',
    },
    primary: {
      assetKey: 'stages.primary',
      src: 'https://images.unsplash.com/photo-1503676260728-1c00da094a0b?w=800&q=80',
      alt: 'Primary school learning',
    },
    preparatory: {
      assetKey: 'stages.preparatory',
      src: 'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?w=800&q=80',
      alt: 'Preparatory students',
    },
    secondary: {
      assetKey: 'stages.secondary',
      src: 'https://images.unsplash.com/photo-1541339907198-e08756dedf3f?w=800&q=80',
      alt: 'Secondary school graduates',
    },
  },
  events: {
    openDay: {
      assetKey: 'events.open-day',
      src: 'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?w=600&q=80',
      alt: 'Open Day event',
    },
    scienceFair: {
      assetKey: 'events.science-fair',
      src: 'https://images.unsplash.com/photo-1532094349884-543bc11b234d?w=600&q=80',
      alt: 'Science Fair',
    },
    trip: {
      assetKey: 'events.trip',
      src: 'https://images.unsplash.com/photo-1517486808906-6ca8b3f04846?w=600&q=80',
      alt: 'International trip',
    },
    workshop: {
      assetKey: 'events.workshop',
      src: 'https://images.unsplash.com/photo-1524178232363-1fb2b075b655?w=600&q=80',
      alt: 'Parent workshop',
    },
  },
  blog: {
    stageGuide: {
      assetKey: 'blog.stage-guide',
      src: 'https://images.unsplash.com/photo-1503676260728-1c00da094a0b?w=700&q=80',
      alt: 'Choosing school stage',
    },
    studyHabits: {
      assetKey: 'blog.study-habits',
      src: 'https://images.unsplash.com/photo-1580582932707-52ad340fd31f?w=700&q=80',
      alt: 'Primary study habits',
    },
    stem: {
      assetKey: 'blog.stem',
      src: 'https://images.unsplash.com/photo-1532094349884-543bc11b234d?w=700&q=80',
      alt: 'STEM learning',
    },
    wellbeing: {
      assetKey: 'blog.wellbeing',
      src: 'https://images.unsplash.com/photo-1523240795612-9a054b0db644?w=700&q=80',
      alt: 'Student wellbeing',
    },
    admissions: {
      assetKey: 'blog.admissions',
      src: 'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?w=700&q=80',
      alt: 'Admissions guide',
    },
  },
  success: {
    scholarship: {
      assetKey: 'success.scholarship',
      src: 'https://images.unsplash.com/photo-1523240795612-9a054b0db644?w=800&q=80',
      alt: 'Scholarship celebration',
    },
    competition: {
      assetKey: 'success.competition',
      src: 'https://images.unsplash.com/photo-1485827404703-89b55fcc595e?w=800&q=80',
      alt: 'Competition winners',
    },
    university: {
      assetKey: 'success.university',
      src: 'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?w=800&q=80',
      alt: 'University admission',
    },
    achievement: {
      assetKey: 'success.achievement',
      src: 'https://images.unsplash.com/photo-1498243691581-b145c3f54a5a?w=800&q=80',
      alt: 'Student achievement',
    },
  },
  news: {
    robotics: {
      assetKey: 'news.robotics',
      src: 'https://images.unsplash.com/photo-1485827404703-89b55fcc595e?w=600&q=80',
      alt: 'Robotics champions',
    },
    award: {
      assetKey: 'news.award',
      src: 'https://images.unsplash.com/photo-1541339907198-e08756dedf3f?w=600&q=80',
      alt: 'School award',
    },
    university: {
      assetKey: 'news.university',
      src: 'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?w=600&q=80',
      alt: 'University acceptances',
    },
  },
  gallery: [
    { id: 'gal-1', category: 'Campus Life', assetKey: 'gallery.1', src: 'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?w=500&q=80', alt: 'Campus courtyard' },
    { id: 'gal-2', category: 'Students', assetKey: 'gallery.2', src: 'https://images.unsplash.com/photo-1541339907198-e08756dedf3f?w=500&q=80', alt: 'Students on campus' },
    { id: 'gal-3', category: 'Activities', assetKey: 'gallery.3', src: 'https://images.unsplash.com/photo-1503676260728-1c00da094a0b?w=500&q=80', alt: 'Classroom activity' },
    { id: 'gal-4', category: 'Facilities', assetKey: 'gallery.4', src: 'https://images.unsplash.com/photo-1580582932707-52ad340fd31f?w=500&q=80', alt: 'School building' },
    { id: 'gal-5', category: 'Events', assetKey: 'gallery.5', src: 'https://images.unsplash.com/photo-1523240795612-9a054b0db644?w=500&q=80', alt: 'School event' },
    { id: 'gal-6', category: 'Campus Life', assetKey: 'gallery.6', src: 'https://images.unsplash.com/photo-1562774053-701939374585?w=500&q=80', alt: 'Campus facilities' },
    { id: 'gal-7', category: 'Students', assetKey: 'gallery.7', src: 'https://images.unsplash.com/photo-1498243691581-b145c3f54a5a?w=500&q=80', alt: 'Graduation celebration' },
    { id: 'gal-8', category: 'Activities', assetKey: 'gallery.8', src: 'https://images.unsplash.com/photo-1571260899304-425eee4c8efc?w=500&q=80', alt: 'Group learning' },
    { id: 'gal-9', category: 'Events', assetKey: 'gallery.9', src: 'https://images.unsplash.com/photo-1517486808906-6ca8b3f04846?w=500&q=80', alt: 'International programme' },
  ],
  testimonials: {
    parent: {
      assetKey: 'testimonials.parent',
      src: 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=200&q=80',
      alt: 'Parent testimonial portrait',
    },
    student: {
      assetKey: 'testimonials.student',
      src: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=200&q=80',
      alt: 'Student testimonial portrait',
    },
    alumni: {
      assetKey: 'testimonials.alumni',
      src: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=200&q=80',
      alt: 'Alumni testimonial portrait',
    },
    teacher: {
      assetKey: 'testimonials.teacher',
      src: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=200&q=80',
      alt: 'Teacher testimonial portrait',
    },
  },
  accreditations: {
    cambridge: { assetKey: 'accreditations.cambridge', src: null, alt: 'Cambridge Assessment logo' },
    pearson: { assetKey: 'accreditations.pearson', src: null, alt: 'Pearson Edexcel logo' },
    microsoft: { assetKey: 'accreditations.microsoft', src: null, alt: 'Microsoft Education logo' },
    britishCouncil: { assetKey: 'accreditations.british-council', src: null, alt: 'British Council logo' },
    ib: { assetKey: 'accreditations.ib', src: null, alt: 'IB Pathway logo' },
    moe: { assetKey: 'accreditations.moe', src: null, alt: 'Ministry of Education logo' },
  },
}

/** Resolve image src for templates (supports legacy string or { src } objects). */
export function imageSrc(image) {
  if (!image) return ''
  return typeof image === 'string' ? image : image.src ?? ''
}
