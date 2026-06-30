<script setup>
import { computed } from 'vue'

const props = defineProps({
  product: Object,
  imageUrl: String,
  healthy: Boolean,
  selectable: { type: Boolean, default: false },
  stockState: { type: Function, required: true },
})

defineEmits(['add'])

const stock = computed(() => props.stockState(props.product))
const isDisabled = computed(() =>
  !props.selectable || stock.value.key === 'out',
)
</script>

<template>
  <button
    type="button"
    class="pos-product-card h-100 w-100 text-start"
    :class="{
      'pos-product-card--disabled': isDisabled,
      'pos-product-card--locked': !selectable,
    }"
    :disabled="isDisabled"
    @click="$emit('add', product)"
  >
    <div class="pos-product-card__image">
      <img v-if="imageUrl" :src="imageUrl" :alt="product.name_ar || product.name" class="img-fluid">
      <div v-else class="pos-product-card__placeholder d-flex align-items-center justify-content-center h-100">
        <i class="bi bi-box-seam fs-1 text-muted"></i>
      </div>
      <span v-if="healthy" class="badge bg-success pos-product-card__badge pos-product-card__healthy">
        <i class="bi bi-heart-fill me-1"></i>صحي
      </span>
      <span
        class="badge pos-product-card__badge pos-product-card__stock"
        :class="stock.class"
      >{{ stock.label }}</span>
    </div>
    <div class="pos-product-card__body">
      <div class="pos-product-card__name">{{ product.name_ar || product.name }}</div>
      <div class="pos-product-card__price">{{ product.selling_price }} ج.م</div>
    </div>
  </button>
</template>

<style scoped>
.pos-product-card__badge {
  position: absolute;
  font-size: 0.65rem;
}

.pos-product-card__image {
  position: relative;
}

.pos-product-card__healthy {
  top: 0.5rem;
  inset-inline-start: 0.5rem;
}

.pos-product-card__stock {
  top: 0.5rem;
  inset-inline-end: 0.5rem;
}
</style>
