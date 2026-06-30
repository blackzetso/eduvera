<script setup>
const props = defineProps({
  products: Array,
  productImageUrl: Function,
  isHealthy: Function,
  stockState: Function,
  selectable: Boolean,
})

defineEmits(['add'])

function isDisabled(product) {
  return !props.selectable || props.stockState(product).key === 'out'
}
</script>

<template>
  <div class="pos-catalog-area">
    <div v-if="!selectable" class="pos-catalog-lock">
      <i class="bi bi-person-lock fs-1 text-muted d-block mb-2"></i>
      <h5 class="mb-1">اختر طالباً أولاً</h5>
      <p class="text-muted small mb-0">ابحث عن الطالب في لوحة السلة لتفعيل اختيار المنتجات</p>
    </div>

    <div v-else-if="products.length" class="pos-product-list">
      <button
        v-for="p in products"
        :key="p.id"
        type="button"
        class="pos-product-list__row w-100 text-start"
        :disabled="isDisabled(p)"
        @click="$emit('add', p)"
      >
        <div class="pos-product-list__thumb flex-shrink-0">
          <img v-if="productImageUrl(p.image_path)" :src="productImageUrl(p.image_path)" :alt="p.name">
          <i v-else class="bi bi-box-seam text-muted"></i>
        </div>
        <div class="flex-grow-1 min-w-0">
          <div class="fw-bold text-truncate">{{ p.name_ar || p.name }}</div>
          <span class="badge" :class="stockState(p).class">{{ stockState(p).label }}</span>
        </div>
        <span v-if="isHealthy(p)" class="badge bg-success">صحي</span>
        <span class="fw-bold text-nowrap">{{ p.selling_price }} ج.م</span>
      </button>
    </div>

    <div v-else class="pos-empty-state">
      لا توجد منتجات مطابقة
    </div>
  </div>
</template>

<style scoped>
.pos-product-list {
  background: var(--pos-surface, #fff);
  border: 1px solid var(--pos-border, #e4e8ef);
  border-radius: 14px;
  overflow: hidden;
}

.pos-product-list__row {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  padding: 0.85rem 1rem;
  border: none;
  border-bottom: 1px solid var(--pos-border, #e4e8ef);
  background: #fff;
  transition: background 0.12s;
}

.pos-product-list__row:hover:not(:disabled) {
  background: #f8fafc;
}

.pos-product-list__row:disabled {
  opacity: 0.5;
}

.pos-product-list__thumb {
  width: 3.5rem;
  height: 3.5rem;
  border-radius: 10px;
  background: #f1f5f9;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.pos-product-list__thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
</style>
