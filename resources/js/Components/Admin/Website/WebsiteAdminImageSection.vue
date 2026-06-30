<script setup>
import WebsiteImageUploadField from '@/Components/Admin/Website/WebsiteImageUploadField.vue'
import WebsiteImageUrlField from '@/Components/Admin/Website/WebsiteImageUrlField.vue'

defineProps({
  specKey: { type: String, required: true },
  title: { type: String, default: 'الصورة' },
  hint: { type: String, default: '' },
  uploadLabel: { type: String, default: 'رفع صورة جديدة' },
  existingUrl: { type: String, default: '' },
  src: { type: String, default: '' },
  alt: { type: String, default: '' },
})

defineEmits(['update:image', 'update:src', 'update:alt'])
</script>

<template>
  <div class="card card-body">
    <h2 class="h6 mb-1">{{ title }}</h2>
    <p v-if="hint" class="text-muted small mb-3">{{ hint }}</p>

    <div class="mb-3">
      <label class="form-label">نص بديل للصورة (Alt)</label>
      <input
        class="form-control"
        :value="alt"
        placeholder="وصف الصورة لإمكانية الوصول"
        @input="$emit('update:alt', $event.target.value)"
      />
    </div>

    <WebsiteImageUploadField
      :spec-key="specKey"
      :label="uploadLabel"
      :existing-url="existingUrl"
      @update:model-value="$emit('update:image', $event)"
    />

    <div class="text-center text-muted small my-2">— أو —</div>

    <WebsiteImageUrlField
      :spec-key="specKey"
      :model-value="src"
      label="رابط صورة (URL)"
      placeholder="https://images.unsplash.com/..."
      hint="يُستخدم إذا لم ترفع ملفاً جديداً."
      @update:model-value="$emit('update:src', $event)"
    />
  </div>
</template>
