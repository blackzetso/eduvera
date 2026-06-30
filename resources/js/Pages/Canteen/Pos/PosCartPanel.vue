<script setup>
import PosStudentSummary from './PosStudentSummary.vue'
import PosCartLine from './PosCartLine.vue'
import PosRestrictionAlerts from './PosRestrictionAlerts.vue'
import PosCartSummary from './PosCartSummary.vue'
import PosCheckoutButton from './PosCheckoutButton.vue'

defineProps({
  selectedStudent: Object,
  eligibility: Object,
  limitStatus: String,
  cart: Array,
  discount: String,
  itemCount: Number,
  subtotal: Number,
  total: String,
  blocks: Array,
  warnings: Array,
  dailyLimitWarning: String,
  walletWarning: String,
  busy: Boolean,
  validatingCart: Boolean,
  validationError: String,
  canCheckout: Boolean,
  message: String,
})

defineEmits([
  'update:discount',
  'retry-validation',
  'increase', 'decrease', 'remove-line',
  'checkout',
])
</script>

<template>
  <div class="pos-cart-panel">
    <div class="pos-cart-panel__section">
      <PosStudentSummary
        :selected-student="selectedStudent"
        :eligibility="eligibility"
        :limit-status="limitStatus"
      />
    </div>

    <div class="pos-cart-panel__scroll">
      <div v-if="!cart.length" class="pos-empty-state pos-empty-state--compact">
        <i class="bi bi-cart-x fs-2 d-block mb-2"></i>
        السلة فارغة
      </div>
      <PosCartLine
        v-for="line in cart"
        :key="line.product_id"
        :line="line"
        @increase="$emit('increase', line)"
        @decrease="$emit('decrease', line)"
        @remove="$emit('remove-line', line)"
      />

      <div v-if="validatingCart" class="alert alert-secondary py-2 mb-2">
        <span class="spinner-border spinner-border-sm me-2" role="status"></span>
        جاري التحقق من السلة...
      </div>
      <div v-if="validationError" class="alert alert-danger py-2 mb-2">
        <div class="small">{{ validationError }}</div>
        <button type="button" class="btn btn-sm btn-outline-danger mt-2" @click="$emit('retry-validation')">
          إعادة المحاولة
        </button>
      </div>
      <PosRestrictionAlerts
        :blocks="blocks"
        :warnings="warnings"
        :daily-limit-warning="dailyLimitWarning"
        :wallet-warning="walletWarning"
      />
    </div>

    <div class="pos-cart-panel__footer">
      <PosCartSummary
        :item-count="itemCount"
        :subtotal="subtotal"
        :discount="discount"
        :total="total"
        :show-rows="cart.length > 0"
        @update:discount="$emit('update:discount', $event)"
      />
      <p v-if="message" class="text-danger small mt-2 mb-0">{{ message }}</p>
      <PosCheckoutButton
        :busy="busy"
        :disabled="!canCheckout"
        @checkout="$emit('checkout')"
      />
    </div>
  </div>
</template>
