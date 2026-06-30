<script setup>
import { computed } from 'vue'

const props = defineProps({
  show: Boolean,
  sale: Object,
})

defineEmits(['close', 'new-sale', 'print'])

const items = computed(() => props.sale?.items ?? [])

const soldAt = computed(() => {
  const raw = props.sale?.sold_at
  if (!raw) return new Date().toLocaleString('ar-EG')
  try {
    return new Date(raw).toLocaleString('ar-EG')
  } catch {
    return raw
  }
})
</script>

<template>
  <div v-if="show && sale" class="pos-receipt-overlay" @click.self="$emit('close')">
    <div class="card border shadow-lg pos-receipt-modal" id="pos-receipt-print">
      <div class="card-body text-center">
        <div class="text-success mb-2"><i class="bi bi-check-circle-fill fs-1"></i></div>
        <h5 class="mb-1">تمت العملية بنجاح</h5>
        <p class="text-muted mb-1">{{ sale.sale_number }}</p>
        <p class="text-muted small mb-3">{{ soldAt }}</p>

        <div class="text-start small mb-3">
          <div><strong>الطالب:</strong> {{ sale.student_name }} ({{ sale.student_id_ref }})</div>
          <div v-if="sale.grade"><strong>الصف:</strong> {{ sale.grade }} — {{ sale.class_name }}</div>
        </div>

        <table class="table table-sm mb-3">
          <thead>
            <tr>
              <th>المنتج</th>
              <th>الكمية</th>
              <th>الإجمالي</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id">
              <td>{{ item.product_name }}</td>
              <td>{{ item.quantity }}</td>
              <td>{{ item.line_total }} EGP</td>
            </tr>
          </tbody>
        </table>

        <div class="d-flex justify-content-between fw-bold fs-4 border-top pt-3 text-primary">
          <span>الإجمالي</span>
          <span>{{ sale.total }} EGP</span>
        </div>

        <div class="d-flex gap-2 mt-4">
          <button type="button" class="btn btn-primary btn-lg flex-grow-1" @click="$emit('new-sale')">
            بيع جديد
          </button>
          <button type="button" class="btn btn-outline-secondary btn-lg" @click="$emit('print')">
            <i class="bi bi-printer me-1"></i>طباعة
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
