<script setup>
defineProps({
  productSearch: String,
  barcodeSearch: String,
  catalogEnabled: { type: Boolean, default: false },
})

defineEmits(['update:productSearch', 'update:barcodeSearch', 'barcode-submit'])
</script>

<template>
  <div class="pos-toolbar pos-toolbar--compact">
    <div class="row g-2 align-items-center">
      <div class="col-md-6">
        <div class="pos-toolbar__search">
          <i class="bi bi-search pos-toolbar__search-icon"></i>
          <input
            :value="productSearch"
            class="pos-toolbar__input"
            placeholder="بحث عن منتج..."
            :disabled="!catalogEnabled"
            @input="$emit('update:productSearch', $event.target.value)"
          >
        </div>
      </div>
      <div class="col-md-6">
        <div class="d-flex gap-2">
          <div class="pos-toolbar__search flex-grow-1">
            <i class="bi bi-upc-scan pos-toolbar__search-icon"></i>
            <input
              :value="barcodeSearch"
              class="pos-toolbar__input"
              :placeholder="catalogEnabled ? 'مسح الباركود...' : 'اختر طالباً أولاً'"
              :disabled="!catalogEnabled"
              @input="$emit('update:barcodeSearch', $event.target.value)"
              @keyup.enter="$emit('barcode-submit')"
            >
          </div>
          <button
            type="button"
            class="pos-toolbar__barcode-btn"
            :disabled="!catalogEnabled"
            @click="$emit('barcode-submit')"
          >
            إضافة
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
