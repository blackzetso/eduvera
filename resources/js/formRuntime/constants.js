/** Mirrors config/form-builder.php — keep in sync with server validation/logic. */

export const SUPPORTED_FIELD_TYPES = [
  'text',
  'textarea',
  'number',
  'email',
  'phone',
  'date',
  'time',
  'url',
  'select',
  'multi_select',
  'radio',
  'checkbox',
]

export const UNSUPPORTED_FIELD_TYPES = [
  'file',
  'image',
  'signature',
  'rating',
  'slider',
  'color',
  'academic_year',
  'grade',
  'class',
  'subject',
  'teacher_selector',
]

export const LOGIC_MAX_PASSES = 10

export const VALIDATION_RULE_ORDER = [
  'min_length',
  'max_length',
  'min_value',
  'max_value',
  'regex',
  'email',
  'phone',
]

export const PHONE_PATTERN = /^\+?[0-9\s\-()]{7,20}$/

export const VALIDATION_MESSAGES = {
  required: {
    ar: 'هذا الحقل مطلوب',
    en: 'This field is required',
  },
  min_length: {
    ar: 'يجب ألا يقل عن :min أحرف',
    en: 'Must be at least :min characters',
  },
  max_length: {
    ar: 'يجب ألا يزيد عن :max حرفاً',
    en: 'Must not exceed :max characters',
  },
  min_value: {
    ar: 'يجب أن تكون القيمة :min على الأقل',
    en: 'Value must be at least :min',
  },
  max_value: {
    ar: 'يجب ألا تتجاوز القيمة :max',
    en: 'Value must not exceed :max',
  },
  regex: {
    ar: 'القيمة لا تطابق النمط المطلوب',
    en: 'Value does not match the required pattern',
  },
  email: {
    ar: 'يرجى إدخال بريد إلكتروني صالح',
    en: 'Please enter a valid email address',
  },
  phone: {
    ar: 'يرجى إدخال رقم هاتف صالح',
    en: 'Please enter a valid phone number',
  },
}
