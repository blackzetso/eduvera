<script setup>
defineProps({
  blocks: Array,
  warnings: Array,
  dailyLimitWarning: String,
  walletWarning: String,
})
</script>

<template>
  <div v-if="blocks.length || warnings.length || dailyLimitWarning || walletWarning" class="mb-3">
    <div v-if="blocks.length" class="alert alert-danger py-2 mb-2">
      <div class="fw-bold mb-1"><i class="bi bi-slash-circle me-1"></i>منتجات محظورة</div>
      <div v-for="(b, i) in blocks" :key="'b'+i" class="small">
        <span class="badge bg-danger me-1">حظر</span>
        {{ b.product_name }} — {{ b.message }}
      </div>
    </div>
    <div v-if="warnings.length" class="alert alert-warning py-2 mb-2">
      <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle me-1"></i>تحذيرات قيود</div>
      <div v-for="(w, i) in warnings" :key="'w'+i" class="small">
        <span class="badge bg-warning text-dark me-1">تحذير</span>
        {{ w.product_name }} — {{ w.message }}
      </div>
    </div>
    <div v-if="dailyLimitWarning" class="alert alert-info py-2 mb-2">
      <i class="bi bi-calendar-check me-1"></i>{{ dailyLimitWarning }}
    </div>
    <div v-if="walletWarning" class="alert alert-danger py-2 mb-0">
      <i class="bi bi-wallet2 me-1"></i>{{ walletWarning }}
    </div>
  </div>
</template>
