<script setup>
/**
 * Collapsible section sidebar for admin modules (Website CMS, Dova, Settings).
 * Desktop: static column. Tablet/mobile: Bootstrap offcanvas drawer.
 */
defineProps({
  id: { type: String, required: true },
  title: { type: String, default: 'القائمة' },
  /** Bootstrap offcanvas breakpoint: 'lg' (992px) or 'xl' (1200px) */
  breakpoint: {
    type: String,
    default: 'lg',
    validator: (v) => ['lg', 'xl'].includes(v),
  },
})
</script>

<template>
  <div
    class="col-12"
    :class="breakpoint === 'xl' ? 'col-xl-3' : 'col-lg-3'"
  >
    <div
      class="eduvera-section-nav__toggle-row"
      :class="breakpoint === 'xl' ? 'd-xl-none' : 'd-lg-none'"
    >
      <span class="h6 mb-0 fw-bold">{{ title }}</span>
      <button
        type="button"
        class="btn btn-primary btn-sm"
        data-bs-toggle="offcanvas"
        :data-bs-target="`#${id}`"
        :aria-controls="id"
      >
        <i class="bi bi-list" aria-hidden="true" />
        <span class="ms-1">القائمة</span>
      </button>
    </div>

    <div
      :id="id"
      tabindex="-1"
      class="eduvera-section-nav__offcanvas offcanvas-start"
      :class="breakpoint === 'xl' ? 'offcanvas-xl' : 'offcanvas-lg'"
    >
      <div
        class="offcanvas-header"
        :class="breakpoint === 'xl' ? 'd-xl-none' : 'd-lg-none'"
      >
        <h5 class="offcanvas-title">{{ title }}</h5>
        <button
          type="button"
          class="btn-close"
          data-bs-dismiss="offcanvas"
          :data-bs-target="`#${id}`"
          aria-label="إغلاق"
        />
      </div>
      <div class="offcanvas-body">
        <slot />
      </div>
    </div>
  </div>
</template>
