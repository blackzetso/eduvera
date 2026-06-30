<script setup>
import { computed } from 'vue'
import CanteenLayout from '@/Pages/Canteen/Layout/CanteenLayout.vue'
import { Head } from '@inertiajs/vue3'
import { useCanteenPos } from '@/composables/useCanteenPos'
import PosHeader from './PosHeader.vue'
import PosStudentBar from './PosStudentBar.vue'
import PosCatalogToolbar from './PosCatalogToolbar.vue'
import PosCategoryTabs from './PosCategoryTabs.vue'
import PosProductGrid from './PosProductGrid.vue'
import PosCartPanel from './PosCartPanel.vue'
import PosReceiptModal from './PosReceiptModal.vue'
import './pos.css'

const props = defineProps({
  categories: Object,
  products: Array,
})

const categoryItems = computed(() => props.categories?.data ?? props.categories ?? [])

const {
  productSearch,
  barcodeSearch,
  categoryFilter,
  studentQuery,
  studentResults,
  studentSearched,
  selectedStudent,
  eligibility,
  cart,
  discount,
  busy,
  message,
  validatingCart,
  validationError,
  eligibilityError,
  lastSale,
  showReceipt,
  canSelectProducts,
  catalog,
  itemCount,
  subtotal,
  total,
  restrictionBlocks,
  restrictionWarnings,
  limitStatus,
  dailyLimitWarning,
  walletWarning,
  canCheckout,
  searchStudents,
  pickStudentFromResults,
  selectStudent,
  clearStudent,
  retryEligibility,
  retryValidation,
  addToCart,
  lookupBarcode,
  changeQty,
  removeLine,
  checkout,
  newSale,
  productImageUrl,
  isHealthy,
  stockState,
} = useCanteenPos({
  list: props.products ?? [],
  categories: categoryItems.value,
})

function printReceipt() {
  window.print()
}
</script>

<template>
  <CanteenLayout fullscreen>
    <Head title="POS" />

    <div class="pos-app">
      <PosHeader />

      <PosStudentBar
        :student-query="studentQuery"
        @update:student-query="studentQuery = $event"
        :student-results="studentResults"
        :student-searched="studentSearched"
        :selected-student="selectedStudent"
        :eligibility="eligibility"
        :eligibility-error="eligibilityError"
        :limit-status="limitStatus"
        @search="searchStudents"
        @pick="pickStudentFromResults"
        @select="selectStudent"
        @clear="clearStudent"
        @retry-eligibility="retryEligibility"
      />

      <div class="pos-body">
        <section class="pos-body__catalog">
          <PosCatalogToolbar
            v-model:product-search="productSearch"
            v-model:barcode-search="barcodeSearch"
            :catalog-enabled="canSelectProducts"
            @barcode-submit="lookupBarcode"
          />

          <PosCategoryTabs
            :categories="categoryItems"
            :active-id="categoryFilter"
            :disabled="!canSelectProducts"
            @select="categoryFilter = $event"
          />

          <PosProductGrid
            :products="catalog"
            :selectable="canSelectProducts"
            :product-image-url="productImageUrl"
            :is-healthy="isHealthy"
            :stock-state="stockState"
            @add="addToCart"
          />
        </section>

        <aside class="pos-body__cart">
          <PosCartPanel
            :discount="discount"
            @update:discount="discount = $event"
            :selected-student="selectedStudent"
            :eligibility="eligibility"
            :limit-status="limitStatus"
            :cart="cart"
            :item-count="itemCount"
            :subtotal="subtotal"
            :total="total"
            :blocks="restrictionBlocks"
            :warnings="restrictionWarnings"
            :daily-limit-warning="dailyLimitWarning"
            :wallet-warning="walletWarning"
            :busy="busy"
            :validating-cart="validatingCart"
            :validation-error="validationError"
            :can-checkout="canCheckout"
            :message="message"
            @retry-validation="retryValidation"
            @increase="changeQty($event, 1)"
            @decrease="changeQty($event, -1)"
            @remove-line="removeLine"
            @checkout="checkout"
          />
        </aside>
      </div>
    </div>

    <PosReceiptModal
      :show="showReceipt"
      :sale="lastSale"
      @close="showReceipt = false"
      @new-sale="newSale"
      @print="printReceipt"
    />
  </CanteenLayout>
</template>

<style scoped>
@media print {
  .pos-app,
  :deep(.pos-header) {
    display: none !important;
  }
}
</style>
