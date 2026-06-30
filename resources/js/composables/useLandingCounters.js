import { onMounted, onUnmounted } from 'vue'

function animateValue(el, target, options = {}) {
  const { suffix = '', prefix = '', decimals = 0, duration = 2000 } = options
  const start = performance.now()

  function frame(now) {
    const p = Math.min((now - start) / duration, 1)
    const eased = 1 - (1 - p) ** 3
    const current = target * eased
    const formatted =
      decimals > 0
        ? current.toFixed(decimals)
        : Math.floor(current).toLocaleString('en-US')
    el.textContent = `${prefix}${formatted}${suffix}`
    if (p < 1) requestAnimationFrame(frame)
  }

  requestAnimationFrame(frame)
}

export function useLandingCounters() {
  let observer = null

  onMounted(() => {
    observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return
          const el = entry.target
          if (el.dataset.counted === '1') return
          el.dataset.counted = '1'
          animateValue(el, Number(el.dataset.counterEnd) || 0, {
            suffix: el.dataset.counterSuffix || '',
            prefix: el.dataset.counterPrefix || '',
            decimals: Number(el.dataset.counterDecimals) || 0,
          })
        })
      },
      { threshold: 0.35 }
    )

    document.querySelectorAll('[data-counter-end]:not([data-counted])').forEach((el) => {
      observer.observe(el)
    })
  })

  onUnmounted(() => {
    observer?.disconnect()
  })
}
