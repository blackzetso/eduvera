<script setup>
defineProps({
  cards: { type: Array, default: () => [] },
})
</script>

<template>
  <div class="card border border-info border-opacity-25 mb-4">
    <div class="card-body py-3">
      <div class="d-flex align-items-center gap-2 mb-3">
        <i class="bi bi-chat-heart text-info"></i>
        <span class="fw-semibold small text-muted text-uppercase">مؤشرات التفاعل</span>
        <span class="badge bg-light text-muted border">إعلامي</span>
      </div>
      <div class="row g-3">
        <div v-for="card in cards" :key="card.id" class="col-6 col-md-3">
          <div class="d-flex align-items-center gap-2">
            <span
              class="rounded d-inline-flex align-items-center justify-content-center flex-shrink-0 bg-light"
              style="width:2rem;height:2rem"
            >
              <i :class="['bi', card.icon, `text-${card.color}`]"></i>
            </span>
            <div>
              <div class="fw-bold">{{ card.value }}</div>
              <div class="small text-muted">{{ card.label }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
