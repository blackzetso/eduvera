<script setup>
defineProps({
  line: Object,
})

defineEmits(['increase', 'decrease', 'remove'])

const lineTotal = (line) => (Number(line.price) * Number(line.quantity)).toFixed(2)
</script>

<template>
  <div class="pos-cart-line">
    <div class="pos-cart-line__header">
      <div class="pos-cart-line__name text-truncate">{{ line.name }}</div>
      <button type="button" class="pos-cart-line__remove" title="حذف" @click="$emit('remove')">
        <i class="bi bi-trash"></i>
      </button>
    </div>
    <div class="pos-cart-line__body">
      <div class="pos-cart-line__unit">{{ line.price }} ج.م</div>
      <div class="pos-cart-line__qty">
        <button type="button" class="pos-cart-line__qty-btn" @click="$emit('decrease')">−</button>
        <span>{{ line.quantity }}</span>
        <button type="button" class="pos-cart-line__qty-btn" @click="$emit('increase')">+</button>
      </div>
      <div class="pos-cart-line__total">{{ lineTotal(line) }} ج.م</div>
    </div>
  </div>
</template>
