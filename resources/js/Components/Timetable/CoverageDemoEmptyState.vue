<script setup>
import { ref } from 'vue'
import { useDailyAbsenceCoverage } from '@/composables/useDailyAbsenceCoverage'
import { toast } from 'vue3-toastify'

const props = defineProps({
  compact: { type: Boolean, default: false },
})

const emit = defineEmits(['seeded'])

const { seedDemoData, loading } = useDailyAbsenceCoverage()
const seeding = ref(false)

async function createDemoData() {
  if (seeding.value || loading.value) return
  seeding.value = true
  try {
    const data = await seedDemoData()
    if (data.already_exists) {
      toast.info(data.message || 'تم إنشاء بيانات تجريبية مسبقاً')
    } else if (data.success) {
      toast.success(data.message || 'تم إنشاء بيانات غياب تجريبية بنجاح')
    } else {
      toast.warning(data.message || 'تعذر إنشاء البيانات')
    }
    emit('seeded', data)
  } catch (e) {
    const msg = e.response?.data?.message || e.message
    if (msg) toast.error(msg)
  } finally {
    seeding.value = false
  }
}
</script>

<template>
  <div class="ev-card ev-empty-state" :class="{ 'ev-empty-state--compact': compact }" dir="rtl">
    <div class="ev-empty-state__icon">
      <i class="bi bi-person-x"></i>
    </div>
    <p class="ev-empty-state__title mb-2">لا يوجد غياب مسجل اليوم</p>
    <p v-if="!compact" class="ev-empty-state__hint small text-muted mb-3">
      أنشئ بيانات تجريبية لتجربة توزيع التغطية والبدائل دون تشغيل أوامر من الطرفية.
    </p>
    <button
      type="button"
      class="ev-action-btn"
      :disabled="seeding || loading"
      @click.stop="createDemoData"
    >
      <span v-if="seeding || loading" class="spinner-border spinner-border-sm ms-2"></span>
      <i v-else class="bi bi-magic ms-1"></i>
      إنشاء بيانات تجريبية
    </button>
  </div>
</template>
