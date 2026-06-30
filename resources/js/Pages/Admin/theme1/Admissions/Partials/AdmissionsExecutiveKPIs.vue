<script setup>
defineProps({
  cards: { type: Array, default: () => [] },
})
</script>

<template>
  <div class="card admissions-command-card border-0 shadow-sm">
    <div class="card-body py-2">
      <div class="row g-2 g-md-3">
        <div v-for="card in cards" :key="card.id" class="col-6 col-md-4 col-xl-2">
          <div class="admissions-kpi-card">
            <div class="d-flex align-items-start gap-2">
              <i :class="['bi', card.icon, `text-${card.color}`, 'flex-shrink-0']" style="margin-top:2px"></i>
              <div class="min-w-0">
                <div class="admissions-kpi-card__value" :class="`text-${card.color}`">{{ card.value }}</div>
                <div class="admissions-kpi-card__label">{{ card.label }}</div>
                <div v-if="card.sub" class="small text-muted">{{ card.sub }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
