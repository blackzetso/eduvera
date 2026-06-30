<script setup>
defineProps({
  label: { type: String, default: '' },
  enLabel: { type: String, default: 'English' },
  arLabel: { type: String, default: 'عربي' },
  type: { type: String, default: 'text' },
  rows: { type: Number, default: 3 },
  enPlaceholder: { type: String, default: '' },
  arPlaceholder: { type: String, default: '' },
  required: { type: Boolean, default: false },
  hint: { type: String, default: '' },
  compact: { type: Boolean, default: false },
})

const en = defineModel('en', { type: String, default: '' })
const ar = defineModel('ar', { type: String, default: '' })
</script>

<template>
  <div class="website-bilingual-field" :class="{ 'website-bilingual-field--compact': compact }">
    <label v-if="label" class="form-label fw-semibold">
      {{ label }}
      <span v-if="required" class="text-danger">*</span>
    </label>
    <p v-if="hint" class="form-text mb-2">{{ hint }}</p>
    <div class="row g-2">
      <div class="col-md-6">
        <label class="form-label small text-muted mb-1">{{ enLabel }}</label>
        <textarea
          v-if="type === 'textarea'"
          v-model="en"
          class="form-control text-start"
          dir="ltr"
          :rows="rows"
          :placeholder="enPlaceholder"
          :required="required"
        />
        <input
          v-else
          v-model="en"
          type="text"
          class="form-control text-start"
          dir="ltr"
          :placeholder="enPlaceholder"
          :required="required"
        />
      </div>
      <div class="col-md-6">
        <label class="form-label small text-muted mb-1">{{ arLabel }}</label>
        <textarea
          v-if="type === 'textarea'"
          v-model="ar"
          class="form-control text-end"
          dir="rtl"
          :rows="rows"
          :placeholder="arPlaceholder"
        />
        <input
          v-else
          v-model="ar"
          type="text"
          class="form-control text-end"
          dir="rtl"
          :placeholder="arPlaceholder"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.website-bilingual-field--compact .form-label.fw-semibold {
  display: none;
}
</style>
