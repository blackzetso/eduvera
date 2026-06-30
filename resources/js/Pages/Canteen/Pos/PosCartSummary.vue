<script setup>
defineProps({
  itemCount: Number,
  subtotal: Number,
  discount: String,
  total: String,
  showRows: { type: Boolean, default: true },
})

defineEmits(['update:discount'])
</script>

<template>
  <div class="pos-cart-summary">
    <div v-if="showRows" class="pos-cart-summary__row">
      <span>عدد الأصناف</span>
      <strong>{{ itemCount }}</strong>
    </div>
    <div v-if="showRows" class="pos-cart-summary__row">
      <span>المجموع الفرعي</span>
      <strong>{{ subtotal.toFixed(2) }} ج.م</strong>
    </div>
    <div v-if="showRows" class="pos-cart-summary__row pos-cart-summary__discount">
      <span>خصم</span>
      <input
        :value="discount"
        type="number"
        min="0"
        step="0.01"
        placeholder="0"
        @input="$emit('update:discount', $event.target.value)"
      >
    </div>
    <div class="pos-cart-summary__total-row">
      <div>
        <div class="pos-cart-summary__total-label">الإجمالي</div>
        <div v-if="showRows" class="pos-cart-summary__items-badge">{{ itemCount }} صنف</div>
      </div>
      <div class="pos-cart-summary__total-value">{{ total }} ج.م</div>
    </div>
  </div>
</template>
