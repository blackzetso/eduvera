<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
  primaryContact: { type: Object, default: null },
  contactForm: { type: Object, required: true },
  isReadOnly: { type: Boolean, default: false },
})

const emit = defineEmits(['save'])
</script>

<template>
  <div>
    <div v-if="!primaryContact" class="text-muted">لا توجد جهة اتصال.</div>
    <div
      v-else-if="primaryContact.matched_guardian"
      class="alert alert-info border-0 shadow-sm rounded-4 mb-3 d-flex flex-wrap align-items-center justify-content-between gap-2"
    >
      <div>
        <i class="bi bi-people-fill me-2"></i>
        <strong>ولي أمر مطابق:</strong>
        {{ primaryContact.matched_guardian.name }}
      </div>
      <Link
        :href="primaryContact.matched_guardian.profile_url"
        class="btn btn-sm btn-primary rounded-4"
      >
        فتح ملف العائلة
      </Link>
    </div>
    <form v-if="primaryContact" class="card border" @submit.prevent="emit('save')">
      <div v-if="isReadOnly" class="alert alert-secondary m-3 mb-0 py-2 small">الطلب محوّل — التعديل معطّل.</div>
      <div class="card-body row g-3">
        <div class="col-md-6"><label class="form-label">الاسم</label><input v-model="contactForm.name" class="form-control" :disabled="isReadOnly" /></div>
        <div class="col-md-6"><label class="form-label">العلاقة</label><input v-model="contactForm.relationship_type" class="form-control" :disabled="isReadOnly" /></div>
        <div class="col-md-6"><label class="form-label">الهاتف</label><input v-model="contactForm.phone" class="form-control" :disabled="isReadOnly" /></div>
        <div class="col-md-6"><label class="form-label">البريد</label><input v-model="contactForm.email" type="email" class="form-control" :disabled="isReadOnly" /></div>
        <div class="col-md-6"><label class="form-label">الرقم القومي</label><input v-model="contactForm.national_id" class="form-control" :disabled="isReadOnly" /></div>
        <div class="col-md-6"><label class="form-label">العنوان</label><input v-model="contactForm.address" class="form-control" :disabled="isReadOnly" /></div>
        <div class="col-12">
          <label class="form-label">تفضيلات التواصل</label>
          <div class="d-flex flex-wrap gap-3">
            <label class="form-check"><input v-model="contactForm.communication_preferences.email" type="checkbox" class="form-check-input" :disabled="isReadOnly" /> بريد</label>
            <label class="form-check"><input v-model="contactForm.communication_preferences.phone" type="checkbox" class="form-check-input" :disabled="isReadOnly" /> هاتف</label>
            <label class="form-check"><input v-model="contactForm.communication_preferences.sms" type="checkbox" class="form-check-input" :disabled="isReadOnly" /> SMS</label>
            <label class="form-check"><input v-model="contactForm.communication_preferences.whatsapp" type="checkbox" class="form-check-input" :disabled="isReadOnly" /> واتساب</label>
          </div>
        </div>
        <div class="col-12"><button type="submit" class="btn btn-primary" :disabled="contactForm.processing || isReadOnly">حفظ</button></div>
      </div>
    </form>
  </div>
</template>
