/**
 * Where landing section *content* is edited (vs display-only settings in Landing Builder).
 */
export const LANDING_SECTION_CONTENT_SOURCES = {
  hero: {
    title: 'قسم البطل',
    body: 'النصوص والصور من إعدادات Hero. هنا: عنوان العرض والخلفية فقط.',
    links: [
      { label: 'إعدادات Hero', route: 'admin.website.hero' },
    ],
  },
  about: {
    title: 'عن المدرسة',
    body: 'المحتوى (النص، الرسالة، الرؤية، الصورة) من معلومات المدرسة. هنا: عنوان القسم الاختياري.',
    links: [
      { label: 'معلومات المدرسة → عن المدرسة', route: 'admin.website.school-info' },
    ],
  },
  principal: {
    title: 'كلمة المدير',
    body: 'التسمية، العنوان، نص الرسالة، والصورة من معلومات المدرسة.',
    links: [
      { label: 'معلومات المدرسة → كلمة المدير', route: 'admin.website.school-info' },
    ],
  },
  achievements: {
    title: 'إنجازات الطلاب',
    body: 'أرقام البطاقات (98%، 45+، …) من كتل المحتوى. هنا: عنوان القسم والأزرار.',
    links: [
      { label: 'كتل المحتوى → Achievements', route: 'admin.website.content-blocks.edit', params: 'achievements' },
    ],
  },
  final_cta: {
    title: 'الدعوة الختامية',
    body: 'العنوان والنص الفرعي من معلومات المدرسة → القسم الختامي.',
    links: [
      { label: 'معلومات المدرسة → القسم الختامي', route: 'admin.website.school-info' },
    ],
  },
  stages: {
    title: 'المراحل الدراسية',
    body: 'بطاقات المراحل من إدارة المراحل.',
    links: [
      { label: 'المراحل الدراسية', route: 'admin.website.stages.index' },
    ],
  },
  events: {
    title: 'الفعاليات',
    body: 'قائمة الفعاليات من إدارة الفعاليات.',
    links: [{ label: 'الفعاليات', route: 'admin.website.events.index' }],
  },
  hero_stats: {
    title: 'إحصائيات Hero',
    body: 'تفعيل/إيقاف هذا القسم يظهر أو يخفي شريط الإحصائيات داخل الهيرو. الأرقام من إعدادات Hero.',
    links: [{ label: 'إعدادات Hero', route: 'admin.website.hero' }],
  },
  trust: {
    title: 'شريط الثقة',
    body: 'عناصر الثقة من كتل المحتوى.',
    links: [{ label: 'كتل المحتوى → Trust Strip', route: 'admin.website.content-blocks.edit', params: 'trust-strip' }],
  },
  why: {
    title: 'لماذا نحن',
    body: 'بطاقات Why Choose Us من كتل المحتوى.',
    links: [{ label: 'كتل المحتوى → Why Choose Us', route: 'admin.website.content-blocks.edit', params: 'why-choose' }],
  },
  student_life: {
    title: 'حياة الطالب',
    body: 'بلاطات Student Life من كتل المحتوى.',
    links: [{ label: 'كتل المحتوى → Student Life', route: 'admin.website.content-blocks.edit', params: 'student-life' }],
  },
  facilities: {
    title: 'المرافق',
    body: 'بطاقات المرافق من إدارة المرافق.',
    links: [{ label: 'المرافق', route: 'admin.website.facilities.index' }],
  },
  academics: {
    title: 'البرامج الأكاديمية',
    body: 'قائمة البرامج من كتل المحتوى.',
    links: [{ label: 'كتل المحتوى → Academic Programs', route: 'admin.website.content-blocks.edit', params: 'academic-programs' }],
  },
  parent_trust: {
    title: 'شريط ثقة الأهالي',
    body: 'العناصر من كتل المحتوى → Parent Trust.',
    links: [{ label: 'كتل المحتوى → Parent Trust', route: 'admin.website.content-blocks.edit', params: 'parent-trust' }],
  },
  blog_anchor: {
    title: 'رابط المدونة',
    body: 'مقالات المدونة من إدارة المنشورات.',
    links: [{ label: 'المدونة', route: 'admin.website.posts.index', query: { type: 'blog' } }],
  },
  custom: {
    title: 'قسم مخصص',
    body: 'محتوى وإعدادات القسم من منشئ الصفحة.',
    links: [{ label: 'منشئ الصفحة', route: 'admin.website.landing-builder.index' }],
  },
  news: {
    title: 'الأخبار',
    body: 'المقالات من إدارة المنشورات (نوع news).',
    links: [{ label: 'الأخبار', route: 'admin.website.posts.index', query: { type: 'news' } }],
  },
  gallery: {
    title: 'المعرض',
    body: 'الصور من معرض الصور.',
    links: [{ label: 'معرض الصور', route: 'admin.website.gallery.index' }],
  },
  testimonials: {
    title: 'آراء الأهالي',
    body: 'الشهادات من إدارة التوصيات.',
    links: [{ label: 'التوصيات', route: 'admin.website.testimonials.index' }],
  },
  success_stories: {
    title: 'قصص النجاح',
    body: 'القصص من إدارة Success Stories.',
    links: [{ label: 'قصص النجاح', route: 'admin.website.success-stories.index' }],
  },
  faq: {
    title: 'الأسئلة الشائعة',
    body: 'الأسئلة من كتل المحتوى → FAQs.',
    links: [{ label: 'كتل المحتوى → FAQs', route: 'admin.website.content-blocks.edit', params: 'faqs' }],
  },
  partners: {
    title: 'الشركاء والاعتمادات',
    body: 'بطاقات الاعتماد من كتل المحتوى → Accreditations.',
    links: [{ label: 'كتل المحتوى → Accreditations', route: 'admin.website.content-blocks.edit', params: 'accreditations' }],
  },
  contact: {
    title: 'التواصل',
    body: 'العنوان والهاتف والخريطة من إعدادات التواصل.',
    links: [{ label: 'التواصل', route: 'admin.website.contact' }],
  },
  admissions: {
    title: 'القبول',
    body: 'خطوات القبول ونموذج الزيارة من إعدادات القبول.',
    links: [{ label: 'القبول', route: 'admin.website.admissions' }],
  },
  careers: {
    title: 'التوظيف',
    body: 'وظائف وقسم Become a Teacher من الوظائف.',
    links: [{ label: 'الوظائف', route: 'admin.website.careers.index' }],
  },
}
