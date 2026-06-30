import { schoolTalentImages, imageSrc } from './images'

/** @module school-info */
export const schoolInfo = {
  name: 'Nile Private Schools',
  tagline: 'International School',
  founded: 1998,
  logo: {
    src: '/front/theme1/images/nile-private-schools-logo.png',
    alt: 'Nile Private Schools',
  },
  contact: {
    address: '123 Education Avenue, New Cairo',
    phone: '+20 2 0000 0000',
    whatsapp: '+20 100 000 0000',
    email: 'info@schooltalent.edu',
    careersEmail: 'careers@schooltalent.edu',
    hours: 'Sun–Thu 7:30 AM – 3:30 PM',
    mapEmbedUrl:
      'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3454.0!2d31.2!3d30.0!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zMzbCsDAwJzAwLjAiTiAzMcKwMTInMDAuMCJF!5e0!3m2!1sen!2s!4v1',
  },
  hero: {
    pill: 'Admissions Open 2026–2027 — Apply Now!',
    headlineLine1: 'Nile Private Language Schools',
    headlineAccent: 'Where Excellence Meets Opportunity',
    headlineLine2: 'With Nile Private Schools',
    subheadline:
      'Building future leaders through world-class education, innovative learning, and a nurturing environment that prepares students for success in top universities worldwide.',
    image: schoolTalentImages.hero,
    trustLabel: 'Trusted by 2,500+ Successful Families',
  },
  topBar: {
    phone: '+20 2 0000 0000',
    email: 'info@schooltalent.edu',
  },
  /** Header/footer social — set `url` to enable; `null` hides optional networks */
  social: {
    facebook: 'https://www.facebook.com/',
    instagram: 'https://www.instagram.com/',
    youtube: 'https://www.youtube.com/',
    linkedin: null,
  },
  about: {
    eyebrow: 'About School Talent',
    title: 'A Legacy of Excellence',
    intro:
      'Founded with a vision to blend international standards with local values, School Talent has grown into a trusted community of learners, educators, and families. Our campus inspires curiosity, character, and confidence in every child.',
    mission: 'To deliver holistic, future-ready education in a safe and inspiring environment.',
    vision: "To be the region's leading international school for academic and personal achievement.",
    image: schoolTalentImages.about,
  },
  principal: {
    eyebrow: 'Leadership',
    title: 'A Message from the Principal',
    message:
      'Welcome to School Talent. We believe every child deserves an education that challenges the mind, nurtures the heart, and opens doors to the world. Our dedicated faculty and vibrant community work together to help students discover their talents and lead with purpose.',
    image: schoolTalentImages.principal,
  },
  finalCta: {
    headline: 'Give Your Child a World-Class Education',
    subheadline:
      'Join a safe, accredited community where academic excellence, rich student life, and caring educators prepare your child for top universities.',
  },
}

export const announcements = [
  { id: 'ann-1', text: 'Admissions Open 2026–2027', href: '#admissions' },
  { id: 'ann-2', text: 'Book a Campus Tour', href: '#visit' },
  { id: 'ann-3', text: 'Scholarship Applications Open', href: '#admissions' },
  { id: 'ann-4', text: 'Upcoming Open Day — March 15', href: '#events' },
]

export const navLinks = [
  { href: '#home', label: 'Home' },
  { href: '#about', label: 'About' },
  { href: '#stages', label: 'Academics' },
  { href: '#student-life', label: 'Student Life' },
  { href: '#admissions', label: 'Admissions' },
  { href: '#news', label: 'News & Events' },
  { href: '#contact', label: 'Contact' },
]

/** Single admissions funnel — all Apply / inquiry CTAs use this anchor */
export const admissionsFunnelHref = '#visit'

export const heroStats = [
  { end: 1200, prefix: '', suffix: '+', label: 'Students' },
  { end: 120, prefix: '', suffix: '+', label: 'Teachers' },
  { end: 98, prefix: '', suffix: '%', label: 'University Acceptance', decimals: 0 },
  { end: 78, prefix: '', suffix: '', label: 'Years of Excellence', decimals: 0 },
]

export const parentTrustStrip = [
  { icon: 'bi-shield-check', label: 'Safe Campus' },
  { icon: 'bi-person-check', label: 'Qualified Teachers' },
  { icon: 'bi-award', label: 'Accredited Curriculum' },
  { icon: 'bi-mortarboard', label: 'University Preparation' },
]

export const visitCampusReasons = [
  { icon: 'bi-person-badge', text: 'Meet school leadership' },
  { icon: 'bi-building', text: 'Explore facilities' },
  { icon: 'bi-easel', text: 'Observe classrooms' },
  { icon: 'bi-chat-dots', text: 'Ask admissions questions' },
]

export function buildWhatsAppUrl(phone, message) {
  const digits = String(phone).replace(/\D/g, '')
  return `https://wa.me/${digits}?text=${encodeURIComponent(message)}`
}

/** Official Bootstrap Icons — configure URLs via `schoolInfo.social` */
export const socialLinkDefinitions = [
  { id: 'facebook', label: 'Facebook', icon: 'bi-facebook', brandIcon: 'facebook', key: 'facebook' },
  { id: 'instagram', label: 'Instagram', icon: 'bi-instagram', key: 'instagram' },
  { id: 'youtube', label: 'YouTube', icon: 'bi-youtube', key: 'youtube' },
  { id: 'linkedin', label: 'LinkedIn', icon: 'bi-linkedin', key: 'linkedin' },
]

export function getSocialLinks(social = schoolInfo.social) {
  return socialLinkDefinitions
    .map((def) => ({
      ...def,
      url: social?.[def.key] ?? null,
    }))
    .filter((link) => Boolean(link.url))
}

export const whatsappQuickActions = [
  {
    id: 'admissions',
    label: 'Ask Admissions',
    icon: 'bi-chat-left-text',
    message: 'Hello School Talent, I would like to ask about admissions.',
  },
  {
    id: 'visit',
    label: 'Book a Visit',
    icon: 'bi-calendar-check',
    message: 'Hello, I would like to book a campus visit at School Talent.',
  },
  {
    id: 'inquiry',
    label: 'Quick Inquiry',
    icon: 'bi-lightning',
    message: 'Hello School Talent, I have a quick question for the admissions team.',
  },
]

/** Hero feature bullets (Rocket LMS–style highlights row) */
export const heroHighlights = [
  { icon: 'bi-calendar-check', text: 'Flexible Admissions Schedule' },
  { icon: 'bi-piggy-bank', text: 'Scholarship & Fee Plans' },
  { icon: 'bi-person-badge', text: 'Expert International Faculty' },
  { icon: 'bi-graph-up-arrow', text: 'Personalized Learning Paths' },
]

export const heroBadges = [
  { id: 'badge-academic', icon: '🏆', text: 'Top Academic Results', class: 'st-hero__float--1' },
  { id: 'badge-university', icon: '🎓', text: '98% University Acceptance', class: 'st-hero__float--2' },
  { id: 'badge-accredited', icon: '✓', text: 'Internationally Accredited', class: 'st-hero__float--3' },
]

export const ctaPresets = {
  apply: { label: 'Apply Now', href: admissionsFunnelHref, variant: 'gold' },
  visit: { label: 'Book a Visit', href: admissionsFunnelHref, variant: 'outline' },
  info: { label: 'Request Information', href: admissionsFunnelHref, variant: 'outline' },
}

/** Contextual CTAs keyed by section id */
export const sectionCtas = {
  about: [ctaPresets.visit, ctaPresets.info],
  stages: [ctaPresets.apply, ctaPresets.visit],
  why: [ctaPresets.info],
  studentLife: [ctaPresets.visit],
  facilities: [ctaPresets.visit],
  academics: [ctaPresets.apply],
  events: [ctaPresets.visit],
  achievements: [ctaPresets.apply],
  testimonials: [ctaPresets.visit, ctaPresets.apply],
  faq: [ctaPresets.info, ctaPresets.visit],
}

export const uiLabels = {
  global: {
    mission_label: 'Mission:',
    vision_label: 'Vision:',
    read_more: 'Read more',
    verify_accreditation: 'Verify accreditation',
    open_positions: 'Open positions:',
    gallery_all: 'All',
    submit_visit_request: 'Submit Visit Request',
  },
  cta: {
    apply: 'Apply Now',
    visit: 'Book a Visit',
    info: 'Request Information',
    contact: 'Contact Admissions',
    teacher: 'Become a Teacher',
    learnMore: 'Learn More',
    viewAllEvents: 'View All Events',
    readMore: 'Read More',
    viewNewsBlog: 'View News & Blog',
  },
  hero: {
    trust_avatars: [
      { mode: 'initial', value: 'A' },
      { mode: 'initial', value: 'B' },
      { mode: 'initial', value: 'C' },
      { mode: 'initial', value: 'D' },
    ],
  },
}

export const visitFormConfig = {
  formId: 'school-talent-visit',
  crmProvider: null,
  submitEndpoint: null,
  fields: [
    { key: 'parentName', name: 'parent_name', type: 'text', enabled: true, required: true, sort: 10, rowPair: 'names', label: 'Parent Name', placeholder: '' },
    { key: 'studentName', name: 'student_name', type: 'text', enabled: true, required: true, sort: 20, rowPair: 'names', label: 'Student Name', placeholder: '' },
    { key: 'currentGrade', name: 'current_grade', type: 'select', optionsSource: 'gradeOptions', enabled: true, required: true, sort: 30, label: 'Current Grade', placeholder: 'Select grade' },
    { key: 'phone', name: 'phone', type: 'tel', enabled: true, required: true, sort: 40, rowPair: 'contact', label: 'Phone Number', placeholder: '' },
    { key: 'email', name: 'email', type: 'email', enabled: true, required: true, sort: 50, rowPair: 'contact', label: 'Email', placeholder: '' },
    { key: 'visitDate', name: 'visit_date', type: 'date', enabled: true, required: true, sort: 60, rowPair: 'schedule', label: 'Preferred Visit Date', placeholder: '' },
    { key: 'visitTime', name: 'visit_time', type: 'select', optionsSource: 'timeSlots', enabled: true, required: true, sort: 70, rowPair: 'schedule', label: 'Preferred Visit Time', placeholder: 'Select time' },
    { key: 'notes', name: 'notes', type: 'textarea', enabled: true, required: false, sort: 80, label: 'Additional Notes', placeholder: '' },
  ],
  gradeOptions: [
    'Nursery / Early Years',
    'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6',
    'Grade 7', 'Grade 8', 'Grade 9',
    'Grade 10', 'Grade 11', 'Grade 12',
    'Not yet in school',
  ],
  timeSlots: ['9:00 AM', '10:00 AM', '11:00 AM', '12:00 PM', '1:00 PM', '2:00 PM'],
  labels: {
    submit: 'Submit Visit Request',
    submitting: 'Submitting...',
    error: 'Unable to submit your request. Please try again.',
    group_family: 'Family details',
    group_contact: 'Contact information',
    group_schedule: 'Preferred visit',
    group_notes: 'Additional notes',
    success_modal_title: 'Visit Request Received',
    success_modal_lead: 'Thank you! Here are your campus visit details:',
    success_reference: 'Reference',
    parent_name: 'Parent / Guardian',
    student_name: 'Student',
    current_grade: 'Current grade',
    visit_date: 'Preferred visit date',
    visit_time: 'Preferred visit time',
    phone: 'Phone',
    success_modal_email_note: 'A confirmation email has been sent to:',
    success_modal_email_pending: 'If you do not receive it within a few minutes, please check spam or contact admissions.',
    success_modal_school_note: 'Our admissions team at',
    success_modal_school_suffix: 'will review your request and confirm your visit.',
    success_modal_close: 'Got it',
    close: 'Close',
  },
}

export const trustItems = [
  'Certified Teachers',
  'International Curriculum',
  'Safe Campus',
  'Modern Facilities',
  'Digital Learning',
  'University Preparation',
]

export const coreValues = [
  { icon: 'bi-star', title: 'Excellence', text: 'High standards in teaching and learning.' },
  { icon: 'bi-heart', title: 'Integrity', text: 'Honesty, respect, and responsibility.' },
  { icon: 'bi-lightbulb', title: 'Innovation', text: 'Future-ready skills and creativity.' },
  { icon: 'bi-globe2', title: 'Global Mindset', text: 'Cultural awareness and citizenship.' },
]

export const whyItems = [
  { icon: 'bi-mortarboard', title: 'Academic Excellence', text: 'Rigorous programs aligned with international standards.' },
  { icon: 'bi-person-hearts', title: 'Character Development', text: 'Values-based education and pastoral care.' },
  { icon: 'bi-cpu', title: 'Innovation', text: 'STEM, robotics, and digital fluency from early years.' },
  { icon: 'bi-flag', title: 'Leadership', text: 'Student councils, debates, and service leadership.' },
  { icon: 'bi-globe-americas', title: 'Global Citizenship', text: '25+ nationalities, multilingual community.' },
  { icon: 'bi-graph-up-arrow', title: 'Future Readiness', text: 'University counseling and career pathways.' },
]

/** @module student-life */
export const studentLife = [
  { id: 'sl-sports', _module: 'gallery', icon: 'bi-trophy', name: 'Sports', image: schoolTalentImages.studentLife.sports },
  { id: 'sl-arts', _module: 'gallery', icon: 'bi-palette', name: 'Arts', image: schoolTalentImages.studentLife.arts },
  { id: 'sl-music', _module: 'gallery', icon: 'bi-music-note-beamed', name: 'Music', image: schoolTalentImages.studentLife.music },
  { id: 'sl-robotics', _module: 'gallery', icon: 'bi-robot', name: 'Robotics', image: schoolTalentImages.studentLife.robotics },
  { id: 'sl-stem', _module: 'gallery', icon: 'bi-bezier2', name: 'STEM', image: schoolTalentImages.studentLife.stem },
  { id: 'sl-leadership', _module: 'gallery', icon: 'bi-people', name: 'Leadership', image: schoolTalentImages.studentLife.leadership },
  { id: 'sl-community', _module: 'gallery', icon: 'bi-hand-thumbs-up', name: 'Community Service', image: schoolTalentImages.studentLife.community },
]

/** @module facilities */
export const facilities = [
  {
    id: 'fac-science',
    _module: 'facilities',
    icon: 'bi-flask',
    name: 'Science Labs',
    description: 'Fully equipped laboratories for physics, chemistry, and biology with safety-first protocols.',
    benefit: 'Hands-on experiments that build scientific inquiry from primary years.',
    image: null,
  },
  {
    id: 'fac-library',
    _module: 'facilities',
    icon: 'bi-book',
    name: 'Library',
    description: 'A calm, inspiring space with 18,000+ volumes, digital catalogs, and quiet study zones.',
    benefit: 'Daily reading programs and research support for every stage.',
    image: null,
  },
  {
    id: 'fac-sports',
    _module: 'facilities',
    icon: 'bi-dribbble',
    name: 'Sports Complex',
    description: 'Indoor courts, swimming pool, athletics track, and dedicated coaching staff.',
    benefit: 'Competitive teams and wellbeing-focused physical education.',
    image: null,
  },
  {
    id: 'fac-innovation',
    _module: 'facilities',
    icon: 'bi-lightning',
    name: 'Innovation Center',
    description: 'Makerspace, robotics lab, and design studios with industry-grade tools.',
    benefit: 'STEM competitions and project-based learning hub.',
    image: null,
  },
  {
    id: 'fac-classrooms',
    _module: 'facilities',
    icon: 'bi-display',
    name: 'Smart Classrooms',
    description: 'Interactive displays, high-speed connectivity, and blended learning platforms.',
    benefit: 'Engaging lessons aligned with digital citizenship standards.',
    image: null,
  },
  {
    id: 'fac-auditorium',
    _module: 'facilities',
    icon: 'bi-mic',
    name: 'Auditorium',
    description: '350-seat theater for assemblies, performances, and guest lectures.',
    benefit: 'Confidence-building public speaking and arts showcases.',
    image: null,
  },
  {
    id: 'fac-transport',
    _module: 'facilities',
    icon: 'bi-bus-front',
    name: 'Transportation',
    description: 'GPS-tracked fleet covering major districts with trained attendants.',
    benefit: 'Safe, reliable daily commutes for students and staff.',
    image: null,
  },
  {
    id: 'fac-clinic',
    _module: 'facilities',
    icon: 'bi-heart-pulse',
    name: 'Medical Clinic',
    description: 'Licensed nurses on campus with emergency protocols and health records.',
    benefit: 'Immediate care and wellness monitoring during school hours.',
    image: null,
  },
]

export const academicPrograms = [
  { title: 'Curriculum', text: 'Blended international framework with local accreditation pathways.' },
  { title: 'Learning Methodology', text: 'Inquiry-based, collaborative, and differentiated instruction.' },
  { title: 'Assessment System', text: 'Continuous formative assessment and standardized benchmarks.' },
  { title: 'University Preparation', text: 'Dedicated counselors, SAT/IGCSE support, and alumni network.' },
  { title: 'Digital Learning', text: '1:1 devices, LMS integration, and safe digital citizenship.' },
]

/** @module events */
export const events = [
  {
    id: 'evt-open-day',
    _module: 'events',
    slug: 'open-day-2026',
    date: 'Mar 15, 2026',
    dateShort: 'Mar 15',
    title: 'Open Day 2026',
    type: 'Open Day',
    isOpenDay: true,
    limitedSeatsLabel: 'Limited Seats Available',
    audience: 'Prospective families',
    location: 'Main Campus — New Cairo',
    image: schoolTalentImages.events.openDay,
    cta: 'Register Now',
    href: admissionsFunnelHref,
  },
  {
    id: 'evt-science-fair',
    _module: 'events',
    slug: 'science-fair-2026',
    date: 'Mar 22, 2026',
    dateShort: 'Mar 22',
    title: 'Science Fair',
    type: 'Competition',
    audience: 'Students & parents',
    location: 'Innovation Center',
    image: schoolTalentImages.events.scienceFair,
    cta: 'Learn More',
    href: '#events',
  },
  {
    id: 'evt-london-trip',
    _module: 'events',
    slug: 'london-trip-2026',
    date: 'Apr 5, 2026',
    dateShort: 'Apr 5',
    title: 'International Trip — London',
    type: 'Trip',
    audience: 'Secondary students',
    location: 'Departing from campus',
    image: schoolTalentImages.events.trip,
    cta: 'View Details',
    href: '#events',
  },
  {
    id: 'evt-parent-workshop',
    _module: 'events',
    slug: 'university-pathways-workshop',
    date: 'Apr 18, 2026',
    dateShort: 'Apr 18',
    title: 'Parent Workshop: University Pathways',
    type: 'Workshop',
    audience: 'Parents of Grades 10–12',
    location: 'Auditorium',
    image: schoolTalentImages.events.workshop,
    cta: 'Reserve Seat',
    href: '#visit',
  },
]

/** @module news */
export const newsItems = [
  {
    id: 'news-robotics',
    _module: 'news',
    slug: 'robotics-champions-2026',
    category: 'Achievement',
    title: 'National Robotics Champions 2026',
    publishedAt: 'Feb 28, 2026',
    image: schoolTalentImages.news.robotics,
    excerpt: 'Our secondary robotics team took first place at the national finals.',
  },
  {
    id: 'news-award',
    _module: 'news',
    slug: 'best-international-school',
    category: 'Award',
    title: 'Best International School — Regional',
    publishedAt: 'Feb 10, 2026',
    image: schoolTalentImages.news.award,
    excerpt: 'Recognized for academic outcomes and student wellbeing programmes.',
  },
  {
    id: 'news-university',
    _module: 'news',
    slug: 'uk-university-acceptances',
    category: 'Success',
    title: '12 Students Accepted to Top UK Universities',
    publishedAt: 'Jan 20, 2026',
    image: schoolTalentImages.news.university,
    excerpt: 'Class of 2025 achieves outstanding placement results.',
  },
]

export const galleryCategories = ['Campus Life', 'Students', 'Events', 'Activities', 'Facilities']

/** @module gallery */
export const galleryItems = schoolTalentImages.gallery

export const achievements = [
  { id: 'ach-acceptance', _module: 'achievements', value: '98%', label: 'University Acceptance' },
  { id: 'ach-competitions', _module: 'achievements', value: '45+', label: 'Competition Winners' },
  { id: 'ach-olympiad', _module: 'achievements', value: '12', label: 'Olympiad Medals' },
  { id: 'ach-sports', _module: 'achievements', value: '28', label: 'Sports Titles' },
  { id: 'ach-awards', _module: 'achievements', value: '120+', label: 'Student Awards' },
]

/** @module testimonials */
export const testimonials = [
  {
    id: 'test-parent-1',
    _module: 'testimonials',
    role: 'Parent',
    roleType: 'parent',
    name: 'Sarah M.',
    quote: 'School Talent gave our daughter confidence, curiosity, and a clear path to university.',
    photo: schoolTalentImages.testimonials.parent,
  },
  {
    id: 'test-student-1',
    _module: 'testimonials',
    role: 'Student',
    roleType: 'student',
    name: 'Omar K., Grade 11',
    quote: 'The teachers push us to think critically while supporting us every step.',
    photo: schoolTalentImages.testimonials.student,
  },
  {
    id: 'test-alumni-1',
    _module: 'testimonials',
    role: 'Alumni',
    roleType: 'alumni',
    name: 'Layla H.',
    quote: 'I still use the study habits and leadership skills I learned here at university.',
    photo: schoolTalentImages.testimonials.alumni,
  },
  {
    id: 'test-teacher-1',
    _module: 'testimonials',
    role: 'Teacher',
    roleType: 'teacher',
    name: 'Mr. James R.',
    quote: 'A professional community where innovation and student wellbeing come first.',
    photo: schoolTalentImages.testimonials.teacher,
  },
]

/** @module accreditations */
export const accreditations = [
  {
    id: 'acc-cambridge',
    _module: 'accreditations',
    name: 'Cambridge Assessment',
    abbr: 'CA',
    description: 'International curriculum and examination standards.',
    benefit: 'Globally recognized qualifications for our graduates.',
    logo: schoolTalentImages.accreditations.cambridge,
    verifyUrl: 'https://www.cambridgeinternational.org/',
  },
  {
    id: 'acc-pearson',
    _module: 'accreditations',
    name: 'Pearson Edexcel',
    abbr: 'PE',
    description: 'Accredited delivery partner for IGCSE pathways.',
    benefit: 'Rigorous assessment aligned with UK benchmarks.',
    logo: schoolTalentImages.accreditations.pearson,
    verifyUrl: 'https://qualifications.pearson.com/',
  },
  {
    id: 'acc-microsoft',
    _module: 'accreditations',
    name: 'Microsoft Education',
    abbr: 'MS',
    description: 'Official school technology and productivity partner.',
    benefit: 'Secure cloud tools and digital skills certification.',
    logo: schoolTalentImages.accreditations.microsoft,
    verifyUrl: 'https://www.microsoft.com/en-us/education',
  },
  {
    id: 'acc-british-council',
    _module: 'accreditations',
    name: 'British Council',
    abbr: 'BC',
    description: 'Language and cultural programmes partnership.',
    benefit: 'Enhanced English proficiency and global exposure.',
    logo: schoolTalentImages.accreditations.britishCouncil,
    verifyUrl: 'https://www.britishcouncil.org.eg/',
  },
  {
    id: 'acc-ib',
    _module: 'accreditations',
    name: 'IB Pathway',
    abbr: 'IB',
    description: 'Preparatory framework for international baccalaureate readiness.',
    benefit: 'Holistic learner profile development.',
    logo: schoolTalentImages.accreditations.ib,
    verifyUrl: 'https://www.ibo.org/',
  },
  {
    id: 'acc-moe',
    _module: 'accreditations',
    name: 'Ministry of Education',
    abbr: 'MOE',
    description: 'Fully licensed and accredited national school status.',
    benefit: 'Local equivalency and regulatory compliance.',
    logo: schoolTalentImages.accreditations.moe,
    verifyUrl: '#',
  },
]

export const admissionSteps = [
  { step: 1, title: 'Inquiry', text: 'Submit interest form or book a campus tour.' },
  { step: 2, title: 'Application', text: 'Complete online application with documents.' },
  { step: 3, title: 'Assessment', text: 'Age-appropriate evaluation and records review.' },
  { step: 4, title: 'Interview', text: 'Family meeting with admissions team.' },
  { step: 5, title: 'Enrollment', text: 'Offer, fees, and welcome onboarding.' },
]

/** Display labels for stage showcase slider */
export const stageShowcaseLabels = {
  'early-years': 'Kindergarten',
  primary: 'Primary',
  preparatory: 'Preparatory',
  secondary: 'Secondary',
}

export const stageModalUi = {
  tabs: [
    { id: 'overview', label: 'Overview' },
    { id: 'curriculum', label: 'Curriculum' },
    { id: 'activities', label: 'Activities' },
    { id: 'schedule', label: 'Daily Schedule' },
    { id: 'outcomes', label: 'Learning Outcomes' },
    { id: 'gallery', label: 'Gallery' },
    { id: 'teachers', label: 'Teachers' },
    { id: 'faq', label: 'Parent FAQ' },
    { id: 'admission', label: 'Admission' },
  ],
  paneTitles: {
    curriculum: 'Subjects & Program',
    activities: 'Activities & Student Life',
    schedule: 'Daily Schedule',
    outcomes: 'Learning Outcomes',
    teachers: 'Our Educators',
    faq: 'Parent FAQ',
    admission: 'Admission Requirements',
  },
  footer: {
    applyCtaId: 'apply',
    visitCtaId: 'visit',
    closeLabel: 'Close',
    applyLabel: 'Apply For This Stage',
  },
}

/** @module careers — teacher recruitment */
export const teacherRecruitment = {
  eyebrow: 'Careers',
  title: 'Shape the Future as an Educator',
  intro:
    'Join a collaborative faculty community where your expertise is valued, your growth is invested in, and every lesson makes a lasting difference.',
  image: schoolTalentImages.careers,
  benefits: [
    'Competitive salary packages & relocation support',
    'International faculty network & mentorship',
    'Supportive leadership and wellbeing programmes',
    'Purpose-driven school culture since 1998',
  ],
  highlights: [
    {
      icon: 'bi-mortarboard',
      title: 'Professional Development',
      text: 'Annual training, Cambridge workshops, and leadership pathways.',
    },
    {
      icon: 'bi-pc-display',
      title: 'Modern Learning Environment',
      text: 'Smart classrooms, LMS tools, and innovation labs.',
    },
    {
      icon: 'bi-graph-up-arrow',
      title: 'Career Growth',
      text: 'Department lead roles, curriculum design, and global exchanges.',
    },
  ],
  vacancies: ['Primary English Teacher', 'Secondary Physics', 'School Counselor', 'Early Years Specialist'],
  applyLabel: 'Apply as Teacher',
  positionsLabel: 'View Open Positions',
}

export const blogPosts = [
  {
    id: 'blog-stage-guide',
    _module: 'blog',
    slug: 'choose-school-stage',
    title: 'How to Choose the Right School Stage',
    category: 'Parent Guides',
    date: 'Mar 1, 2026',
    publishedAt: 'Mar 1, 2026',
    image: schoolTalentImages.blog.stageGuide,
    excerpt: 'A practical guide for families navigating Early Years through Secondary placement.',
  },
  {
    id: 'blog-study-habits',
    _module: 'blog',
    slug: 'study-habits-primary',
    title: 'Building Study Habits in Primary Years',
    category: 'Learning Tips',
    date: 'Feb 18, 2026',
    publishedAt: 'Feb 18, 2026',
    image: schoolTalentImages.blog.studyHabits,
    excerpt: 'Routines that help young learners build confidence and independence at home.',
  },
  {
    id: 'blog-stem',
    _module: 'blog',
    slug: 'stem-at-school-talent',
    title: 'STEM at School Talent',
    category: 'School Updates',
    date: 'Feb 5, 2026',
    publishedAt: 'Feb 5, 2026',
    image: schoolTalentImages.blog.stem,
    excerpt: 'Robotics, coding, and inquiry labs that prepare students for future careers.',
  },
  {
    id: 'blog-wellbeing',
    _module: 'blog',
    slug: 'student-wellbeing',
    title: 'Wellbeing at the Heart of Learning',
    category: 'Parent Guides',
    date: 'Jan 28, 2026',
    publishedAt: 'Jan 28, 2026',
    image: schoolTalentImages.blog.wellbeing,
    excerpt: 'How advisory programmes and counselors support every learner.',
  },
  {
    id: 'blog-admissions',
    _module: 'blog',
    slug: 'admissions-checklist',
    title: 'Your Admissions Checklist for 2026–27',
    category: 'Admissions',
    date: 'Jan 12, 2026',
    publishedAt: 'Jan 12, 2026',
    image: schoolTalentImages.blog.admissions,
    excerpt: 'Documents, timelines, and campus visit tips for a smooth application.',
  },
]

/** Combined news + blog for landing layouts */
export function getNewsBlogFeed() {
  return [
    ...newsItems.map((n) => ({ ...n, source: 'news' })),
    ...blogPosts.map((b) => ({ ...b, source: 'blog' })),
  ]
}

/** @module success-stories */
export const studentSuccessStories = [
  {
    id: 'success-scholarship',
    category: 'Scholarships',
    icon: 'bi-award',
    title: 'Merit Scholarships Awarded',
    text: '42 students received partial and full merit scholarships for academic excellence and leadership.',
    stat: '42',
    statLabel: 'Scholarships',
    image: schoolTalentImages.success.scholarship,
  },
  {
    id: 'success-competition',
    category: 'Competitions',
    icon: 'bi-trophy',
    title: 'National Robotics Champions',
    text: 'Our secondary team won first place at the national robotics finals — third year in a row.',
    stat: '1st',
    statLabel: 'Place',
    image: schoolTalentImages.success.competition,
  },
  {
    id: 'success-university',
    category: 'University Admissions',
    icon: 'bi-mortarboard',
    title: 'Top UK & US Offers',
    text: 'Class of 2025 secured offers from Russell Group universities and leading US colleges.',
    stat: '98%',
    statLabel: 'Acceptance',
    image: schoolTalentImages.success.university,
  },
  {
    id: 'success-achievement',
    category: 'Student Achievements',
    icon: 'bi-stars',
    title: 'Olympiad & Arts Excellence',
    text: '12 olympiad medals, regional debate titles, and international arts festival selections.',
    stat: '120+',
    statLabel: 'Awards',
    image: schoolTalentImages.success.achievement,
  },
]

export const faqs = [
  { q: 'When do admissions open?', a: 'Admissions for 2026–2027 are open year-round with priority deadlines in March and June.', cat: 'Admissions' },
  { q: 'What are the school fees?', a: 'Fees vary by stage. Contact admissions for a detailed fee structure and payment plans.', cat: 'Fees' },
  { q: 'Is transportation available?', a: 'Yes — door-to-door bus service across major districts with GPS-tracked buses.', cat: 'Transportation' },
  { q: 'Which curriculum do you follow?', a: 'International blended curriculum with pathways for IGCSE and national equivalency.', cat: 'Curriculum' },
  { q: 'What are school hours?', a: '7:30 AM – 2:30 PM (Early Years finish earlier). After-school activities until 4:00 PM.', cat: 'School Hours' },
  { q: 'What extracurricular activities are offered?', a: 'Sports, arts, music, robotics, debate, MUN, and community service programs.', cat: 'Activities' },
]

/** @module admissions — school stages */
export const stages = [
  {
    id: 'early-years',
    _module: 'admissions',
    slug: 'early-years',
    title: 'Early Years',
    subtitle: 'Ages 3–5',
    ageRange: 'Ages 3–5',
    tagline: 'Play-based discovery in a nurturing environment',
    studentCount: 180,
    classSize: 18,
    keySkills: ['Communication', 'Social skills', 'Early literacy'],
    tone: 1,
    image: schoolTalentImages.stages.earlyYears,
    overview: 'Our Early Years program builds curiosity, language, and social skills through structured play, sensory learning, and caring classroom communities.',
    curriculum: ['Literacy & phonics', 'Numeracy foundations', 'Creative arts', 'Physical development', 'Arabic & English immersion'],
    activities: ['Story circles', 'Outdoor play', 'Music & movement', 'Swimming introduction', 'Celebration days'],
    teachers: 'Certified early childhood specialists with international training and low student–teacher ratios.',
    schedule: [
      { time: '7:45 AM', activity: 'Arrival & free play' },
      { time: '8:30 AM', activity: 'Circle time & phonics' },
      { time: '10:00 AM', activity: 'Snack & outdoor learning' },
      { time: '11:00 AM', activity: 'Creative arts / movement' },
      { time: '12:30 PM', activity: 'Dismissal' },
    ],
    learningOutcomes: [
      'Develop confidence in bilingual communication',
      'Build fine and gross motor skills through play',
      'Form positive relationships with peers and adults',
    ],
    parentFaq: [
      { q: 'What is the typical class size?', a: 'Classes average 18 students with a lead teacher and teaching assistant.' },
      { q: 'Is the program full-day?', a: 'Early Years runs 7:45 AM – 12:30 PM with optional extended care.' },
      { q: 'How do you support toilet training?', a: 'Our team partners with families using consistent, gentle routines.' },
    ],
    gallery: [
      imageSrc(schoolTalentImages.stages.earlyYears),
      imageSrc(schoolTalentImages.stages.earlyYears),
      imageSrc(schoolTalentImages.studentLife.arts),
    ],
    admission: ['Birth certificate', 'Immunization records', 'Developmental readiness visit', 'Parent interview'],
  },
  {
    id: 'primary',
    _module: 'admissions',
    slug: 'primary',
    title: 'Primary School',
    subtitle: 'Grades 1–6',
    ageRange: 'Grades 1–6',
    tagline: 'Strong foundations for lifelong learning',
    studentCount: 600,
    classSize: 22,
    keySkills: ['Literacy', 'Numeracy', 'Creativity'],
    tone: 2,
    image: schoolTalentImages.stages.primary,
    overview: 'Primary students develop core academic skills, digital literacy, and character through project-based learning and personalized support.',
    curriculum: ['English & Arabic', 'Mathematics', 'Science', 'Social studies', 'ICT & coding basics', 'Islamic studies / ethics'],
    activities: ['Sports teams', 'Art club', 'Reading challenges', 'Science fair', 'Student council junior'],
    teachers: 'Subject specialists and homeroom teachers trained in differentiated instruction.',
    schedule: [
      { time: '7:30 AM', activity: 'Homeroom & morning routines' },
      { time: '8:00 AM', activity: 'Core subjects block' },
      { time: '10:30 AM', activity: 'Break & specialist lesson' },
      { time: '12:00 PM', activity: 'Lunch & activities' },
      { time: '2:30 PM', activity: 'Dismissal (clubs until 4:00 PM)' },
    ],
    learningOutcomes: [
      'Master foundational literacy and numeracy standards',
      'Apply critical thinking in collaborative projects',
      'Demonstrate responsible digital citizenship',
    ],
    parentFaq: [
      { q: 'How are students grouped?', a: 'By grade with flexible grouping for literacy and math acceleration.' },
      { q: 'Is homework assigned daily?', a: 'Age-appropriate tasks focus on reading and practice, not overload.' },
      { q: 'Can parents track progress?', a: 'Yes — via the parent portal with reports and teacher messages.' },
    ],
    gallery: [
      imageSrc(schoolTalentImages.stages.primary),
      imageSrc(schoolTalentImages.gallery[3]),
      imageSrc(schoolTalentImages.studentLife.leadership),
    ],
    admission: ['Previous school reports', 'Entrance assessment', 'Interview', 'Placement review'],
  },
  {
    id: 'preparatory',
    _module: 'admissions',
    slug: 'preparatory',
    title: 'Preparatory School',
    subtitle: 'Grades 7–9',
    ageRange: 'Grades 7–9',
    tagline: 'Transition years with depth and independence',
    studentCount: 320,
    classSize: 24,
    keySkills: ['Critical thinking', 'Research', 'Collaboration'],
    tone: 3,
    image: schoolTalentImages.stages.preparatory,
    overview: 'Preparatory students strengthen study skills, explore electives, and prepare for secondary pathways with mentoring and advisory programs.',
    curriculum: ['Advanced English & Arabic', 'Algebra & geometry', 'Integrated science', 'History & geography', 'Design & technology', 'World languages'],
    activities: ['Robotics club', 'Model UN prep', 'Drama productions', 'Inter-school sports', 'Service projects'],
    teachers: 'Department leads with experience in adolescent learning and exam preparation.',
    schedule: [
      { time: '7:30 AM', activity: 'Advisory & planner check-in' },
      { time: '8:00 AM', activity: 'Rotating subject blocks' },
      { time: '11:00 AM', activity: 'Lab / design sessions' },
      { time: '1:00 PM', activity: 'Electives & clubs' },
      { time: '2:30 PM', activity: 'Dismissal' },
    ],
    learningOutcomes: [
      'Strengthen independent study and time management',
      'Engage in inquiry-led research projects',
      'Lead service initiatives within the school community',
    ],
    parentFaq: [
      { q: 'When do students choose electives?', a: 'Elective pathways begin in Grade 8 with counselor guidance.' },
      { q: 'How is wellbeing supported?', a: 'Weekly advisory sessions and access to the school counselor.' },
      { q: 'Are devices required?', a: 'School-issued devices are provided; personal devices follow BYOD policy in Grade 9.' },
    ],
    gallery: [
      imageSrc(schoolTalentImages.stages.preparatory),
      imageSrc(schoolTalentImages.studentLife.robotics),
      imageSrc(schoolTalentImages.studentLife.stem),
    ],
    admission: ['Report cards (2 years)', 'Placement tests', 'Student interview', 'Counselor recommendation'],
  },
  {
    id: 'secondary',
    _module: 'admissions',
    slug: 'secondary',
    title: 'Secondary School',
    subtitle: 'Grades 10–12',
    ageRange: 'Grades 10–12',
    tagline: 'University-ready graduates and global leaders',
    studentCount: 280,
    classSize: 20,
    keySkills: ['Leadership', 'Research', 'University readiness'],
    tone: 4,
    image: schoolTalentImages.stages.secondary,
    overview: 'Secondary students follow rigorous academic tracks, leadership programs, and university counseling for top domestic and international placements.',
    curriculum: ['IGCSE / national pathways', 'Advanced sciences', 'Economics & business', 'Literature', 'Research & extended essay', 'College prep seminars'],
    activities: ['Debate & MUN', 'Internships', 'International trips', 'Varsity athletics', 'Capstone projects'],
    teachers: 'Master teachers and university counselors with proven placement outcomes.',
    schedule: [
      { time: '7:30 AM', activity: 'University prep seminar (Gr. 11–12)' },
      { time: '8:15 AM', activity: 'A-Level / IGCSE subject blocks' },
      { time: '12:00 PM', activity: 'Independent study & labs' },
      { time: '2:00 PM', activity: 'Counseling & extracurriculars' },
      { time: '2:30 PM', activity: 'Dismissal' },
    ],
    learningOutcomes: [
      'Achieve competitive exam results and portfolio quality',
      'Complete capstone research with faculty mentorship',
      'Secure university offers with personalized counseling',
    ],
    parentFaq: [
      { q: 'When does university counseling start?', a: 'Structured counseling begins in Grade 10 with intensified support in Grade 12.' },
      { q: 'Which exam boards are offered?', a: 'Cambridge IGCSE and national equivalency pathways are available.' },
      { q: 'Are internships supported?', a: 'Yes — Grade 11 students may join approved internship placements.' },
    ],
    gallery: [
      imageSrc(schoolTalentImages.stages.secondary),
      imageSrc(schoolTalentImages.gallery[6]),
      imageSrc(schoolTalentImages.news.university),
    ],
    admission: ['Transcripts', 'IGCSE/placement exams where applicable', 'Personal statement', 'Principal recommendation'],
  },
]

/** Back-compat: flat URL list for gallery filter (until filter uses galleryItems) */
export const galleryImages = galleryItems.map((g) => g.src)

export { schoolTalentImages, imageSrc }
