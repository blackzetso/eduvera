import { computed } from 'vue'
import { useWebsiteContent } from '@/composables/useWebsiteContent'
import { useLocale } from '@/composables/useLocale'

/**
 * Resolve nested UI label paths, e.g. l('global.read_more', 'Read more')
 */
function resolveLabel(node, locale) {
  if (node == null) {
    return null
  }
  if (typeof node === 'string' || typeof node === 'number') {
    return String(node)
  }
  if (typeof node === 'object' && (node.ar != null || node.en != null)) {
    return node[locale] ?? node.en ?? node.ar ?? ''
  }
  return null
}

export function useUiLabel() {
  const { uiLabels } = useWebsiteContent()
  const { locale } = useLocale()

  const heroTrustAvatars = computed(() => {
    const list = uiLabels.value?.hero?.trust_avatars ?? []
    return list.filter((a) => a?.value || a?.src)
  })

  function l(path, fallback = '') {
    const parts = String(path).split('.')
    let node = uiLabels.value
    for (const part of parts) {
      if (node == null || typeof node !== 'object') {
        return fallback
      }
      node = node[part]
    }

    const resolved = resolveLabel(node, locale.value)
    return resolved != null && resolved !== '' ? resolved : fallback
  }

  return { uiLabels, l, heroTrustAvatars }
}
