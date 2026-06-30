import { ref, onMounted, onUnmounted, computed } from 'vue'

/**
 * Lightweight carousel — autoplay, prev/next, touch swipe.
 * @param {import('vue').Ref<number>|number} length
 */
export function useStCarousel(length, options = {}) {
  const { autoplayMs = 5500, initialIndex = 0 } = options
  const index = ref(initialIndex)
  let timer = null
  let touchStartX = 0

  const count = computed(() => {
    if (typeof length === 'number') return length
    return length?.value ?? 0
  })

  function normalize(i) {
    const n = count.value
    if (n <= 0) return 0
    return ((i % n) + n) % n
  }

  function goTo(i) {
    index.value = normalize(i)
  }

  function next() {
    goTo(index.value + 1)
  }

  function prev() {
    goTo(index.value - 1)
  }

  function prefersReducedMotion() {
    return typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches
  }

  function stopAutoplay() {
    if (timer) {
      clearInterval(timer)
      timer = null
    }
  }

  function startAutoplay() {
    stopAutoplay()
    if (prefersReducedMotion() || count.value <= 1) return
    timer = setInterval(next, autoplayMs)
  }

  function onTouchStart(e) {
    touchStartX = e.changedTouches?.[0]?.clientX ?? e.touches?.[0]?.clientX ?? 0
    stopAutoplay()
  }

  function onTouchEnd(e) {
    const x = e.changedTouches?.[0]?.clientX ?? 0
    const delta = x - touchStartX
    if (Math.abs(delta) > 48) {
      if (delta < 0) next()
      else prev()
    }
    startAutoplay()
  }

  onMounted(startAutoplay)
  onUnmounted(stopAutoplay)

  return {
    index,
    next,
    prev,
    goTo,
    onTouchStart,
    onTouchEnd,
    pause: stopAutoplay,
    resume: startAutoplay,
  }
}
