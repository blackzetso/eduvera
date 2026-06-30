<script setup>
defineProps({
  busy: Boolean,
  disabled: Boolean,
})

defineEmits(['checkout'])
</script>

<template>
  <button
    type="button"
    class="btn w-100 pos-checkout-btn"
    :disabled="busy || disabled"
    @click="$emit('checkout')"
  >
    <span v-if="busy" class="spinner-border spinner-border-sm me-2"></span>
    <i v-else class="bi bi-check2-circle me-2"></i>
    تأكيد البيع
  </button>
</template>
