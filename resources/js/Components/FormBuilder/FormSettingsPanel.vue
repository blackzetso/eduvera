<script setup>
defineProps({
  formMeta: Object,
  builderConfig: Object,
})
</script>

<template>
  <div class="fb-v2__panel">
    <h5 class="fw-bold mb-3">إعدادات النموذج</h5>
    <div class="fb-v2__settings-grid">
      <div>
        <label class="form-label">حالة النشر</label>
        <select v-model="formMeta.publication_status" class="form-select">
          <option value="draft">مسودة</option>
          <option value="published">منشور</option>
          <option value="archived">مؤرشف</option>
        </select>
      </div>
      <div>
        <label class="form-label">الظهور</label>
        <select v-model="formMeta.visibility.mode" class="form-select">
          <option value="public">عام</option>
          <option value="students">طلاب</option>
          <option value="parents">أولياء أمور</option>
          <option value="teachers">معلمون</option>
          <option value="staff">موظفون</option>
          <option value="custom_roles">أدوار مخصصة</option>
        </select>
      </div>
      <div>
        <label class="form-label">حد الإرسال</label>
        <select v-model="formMeta.submission.limit" class="form-select">
          <option value="unlimited">غير محدود</option>
          <option value="once_per_user">مرة لكل مستخدم</option>
          <option value="date_range">نطاق تاريخ</option>
        </select>
      </div>
      <div v-if="formMeta.submission.limit === 'date_range'" class="row g-2">
        <div class="col-6">
          <label class="form-label">من</label>
          <input v-model="formMeta.submission.date_from" type="date" class="form-control" />
        </div>
        <div class="col-6">
          <label class="form-label">إلى</label>
          <input v-model="formMeta.submission.date_to" type="date" class="form-control" />
        </div>
      </div>
    </div>
  </div>
</template>
