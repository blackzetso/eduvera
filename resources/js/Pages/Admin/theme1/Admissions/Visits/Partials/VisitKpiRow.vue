<script setup>
defineProps({
  cards: { type: Array, required: true },
})
</script>

<template>
  <div class="row g-3 mb-4">
    <div
      v-for="card in cards"
      :key="card.id"
      class="col-6 col-md-4 col-xl-2"
    >
      <div class="card visit-dashboard-card">
        <div class="card-body d-flex align-items-center gap-3 p-3">
          <span
            class="admission-kpi-card__icon"
            :class="`bg-${card.bg} bg-opacity-10 text-${card.color}`"
          >
            <i :class="['bi', card.icon]"></i>
          </span>
          <div class="min-w-0">
            <div class="fw-bold fs-5 lh-1 mb-1">{{ card.value }}</div>
            <div class="small text-muted text-truncate">{{ card.label }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
