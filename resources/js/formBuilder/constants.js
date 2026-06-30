export const OPTION_FIELD_TYPES = [
  'select', 'multi_select', 'radio', 'checkbox',
]

export const FIELD_TYPE_GROUPS = {
  basic: [
    { value: 'text', label: 'Text', label_ar: 'نص' },
    { value: 'textarea', label: 'Text Area', label_ar: 'نص متعدد الأسطر' },
    { value: 'number', label: 'Number', label_ar: 'رقم' },
    { value: 'email', label: 'Email', label_ar: 'بريد إلكتروني' },
    { value: 'phone', label: 'Phone', label_ar: 'هاتف' },
    { value: 'date', label: 'Date', label_ar: 'تاريخ' },
    { value: 'time', label: 'Time', label_ar: 'وقت' },
    { value: 'url', label: 'URL', label_ar: 'رابط' },
  ],
  choice: [
    { value: 'select', label: 'Select', label_ar: 'قائمة منسدلة' },
    { value: 'multi_select', label: 'Multi Select', label_ar: 'اختيار متعدد' },
    { value: 'radio', label: 'Radio', label_ar: 'اختيار واحد' },
    { value: 'checkbox', label: 'Checkbox', label_ar: 'مربعات اختيار' },
  ],
  advanced: [
    { value: 'file', label: 'File Upload', label_ar: 'رفع ملف' },
    { value: 'image', label: 'Image Upload', label_ar: 'رفع صورة' },
    { value: 'signature', label: 'Signature', label_ar: 'توقيع' },
    { value: 'rating', label: 'Rating', label_ar: 'تقييم' },
    { value: 'slider', label: 'Slider', label_ar: 'منزلق' },
    { value: 'color', label: 'Color Picker', label_ar: 'لون' },
  ],
  education: [
    { value: 'academic_year', label: 'Academic Year', label_ar: 'العام الدراسي' },
    { value: 'grade', label: 'Grade', label_ar: 'الصف' },
    { value: 'class', label: 'Class', label_ar: 'الفصل' },
    { value: 'subject', label: 'Subject', label_ar: 'المادة' },
    { value: 'teacher_selector', label: 'Teacher', label_ar: 'المعلم' },
  ],
}

export const FIELD_TYPE_LABELS = Object.values(FIELD_TYPE_GROUPS)
  .flat()
  .reduce((acc, item) => {
    acc[item.value] = item.label
    return acc
  }, {})

export const LOGIC_ACTIONS = [
  { value: 'show', label_ar: 'إظهار حقل', label_en: 'Show Field' },
  { value: 'hide', label_ar: 'إخفاء حقل', label_en: 'Hide Field' },
  { value: 'require', label_ar: 'جعل الحقل مطلوباً', label_en: 'Require Field' },
  { value: 'skip_section', label_ar: 'تخطي قسم', label_en: 'Skip Section' },
]

export const DEFAULT_FIELD_SCHEMA = () => ({
  label_ar: '',
  label_en: '',
  placeholder_ar: '',
  placeholder_en: '',
  help_ar: '',
  help_en: '',
  validation: {
    required: false,
    min_length: null,
    max_length: null,
    min_value: null,
    max_value: null,
    regex: '',
    email: false,
    phone: false,
  },
  default_value: '',
  default_mode: 'static',
  visibility: { mode: 'visible' },
})

export const DEFAULT_FORM_SETTINGS = () => ({
  publication_status: 'draft',
  visibility: { mode: 'staff', audiences: ['staff'] },
  submission: { limit: 'unlimited', date_from: null, date_to: null },
})

export const DEFAULT_WORKFLOW = () => ({
  enabled: false,
  stages: [],
})
