import { computed } from 'vue'
import { useWebsiteContent } from '@/composables/useWebsiteContent'
import { useUiLabel } from '@/composables/useUiLabel'

export function useCta() {
  const { ctaLibrary, ctaPresets, sectionCtas, admissionsFunnelHref } = useWebsiteContent()
  const { l } = useUiLabel()

  const libraryById = computed(() => {
    const map = {}
    for (const cta of ctaLibrary.value ?? []) {
      if (cta?.id) map[cta.id] = cta
    }
    for (const [id, cta] of Object.entries(ctaPresets.value ?? {})) {
      if (!map[id]) map[id] = { id, ...cta }
    }
    return map
  })

  function resolveCta(idOrObject, fallbackHref = null) {
    if (!idOrObject) return null
    if (typeof idOrObject === 'object') {
      return {
        label: idOrObject.label ?? 'Learn More',
        href: idOrObject.href ?? fallbackHref ?? admissionsFunnelHref.value,
        variant: idOrObject.variant ?? 'outline',
      }
    }
    const cta = libraryById.value[idOrObject]
    if (cta) {
      const labelOverride = l(`cta.${idOrObject}`, '')
      return {
        label: labelOverride || cta.label,
        href: cta.href ?? fallbackHref ?? admissionsFunnelHref.value,
        variant: cta.variant ?? 'outline',
      }
    }

    return { id: idOrObject, label: idOrObject, href: fallbackHref ?? admissionsFunnelHref.value, variant: 'outline' }
  }

  function sectionCtaList(sectionKey) {
    const list = sectionCtas.value?.[sectionKey] ?? []
    return list.map((item) => resolveCta(item)).filter(Boolean)
  }

  function headerCta(id, headerChrome) {
    const fromHeader = (headerChrome.value?.header_ctas ?? []).find((c) => c.id === id)
    return resolveCta(fromHeader ?? id)
  }

  return { libraryById, resolveCta, sectionCtaList, headerCta }
}
