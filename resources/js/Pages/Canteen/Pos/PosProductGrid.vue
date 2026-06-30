<script setup>
import PosProductCard from './PosProductCard.vue'

defineProps({
  products: Array,
  productImageUrl: Function,
  isHealthy: Function,
  stockState: Function,
  selectable: Boolean,
})

defineEmits(['add'])
</script>

<template>
  <div class="pos-product-area">
    <div v-if="!selectable" class="pos-empty-state pos-empty-state--hero">
      <i class="bi bi-person-lock display-4 text-muted d-block mb-3"></i>
      <h5 class="mb-1">اختر طالباً أولاً</h5>
      <p class="text-muted small mb-0">استخدم شريط البحث في الأعلى لتحديد الطالب</p>
    </div>

    <div v-else-if="products.length" class="row g-2 g-md-3 pos-product-grid">
      <div
        v-for="p in products"
        :key="p.id"
        class="col-6 col-md-4 col-lg-3 col-xl-2"
      >
        <PosProductCard
          :product="p"
          :image-url="productImageUrl(p.image_path)"
          :healthy="isHealthy(p)"
          :selectable="selectable"
          :stock-state="stockState"
          @add="$emit('add', $event)"
        />
      </div>
    </div>

    <div v-else class="pos-empty-state pos-empty-state--hero">
      <i class="bi bi-inbox display-4 text-muted d-block mb-3"></i>
      <h5 class="mb-0">لا توجد منتجات</h5>
    </div>
  </div>
</template>
