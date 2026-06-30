import { computed, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const SUPPORTED = ['ar', 'en']

export function normalizeLocale(value) {
  return value === 'ar' ? 'ar' : 'en'
}

export function useLocale() {
  const page = usePage()

  const locale = computed(() => normalizeLocale(page.props.locale))

  const dir = computed(() => (locale.value === 'ar' ? 'rtl' : 'ltr'))

  const isRtl = computed(() => dir.value === 'rtl')

  const languageLabel = computed(() => (locale.value === 'ar' ? 'العربية' : 'English'))

  const switchTargetLocale = computed(() => (locale.value === 'ar' ? 'en' : 'ar'))

  const switchLanguageLabel = computed(() =>
    locale.value === 'ar' ? 'English' : 'العربية'
  )

  function switchLocale(targetLocale = null) {
    const next = normalizeLocale(targetLocale ?? switchTargetLocale.value)
    if (next === locale.value) {
      return
    }

    router.post(
      route('change.language'),
      { lang: next },
      { preserveScroll: true }
    )
  }

  function toggleLocale() {
    switchLocale()
  }

  watch(
    [locale, dir],
    ([lang, direction]) => {
      document.documentElement.setAttribute('lang', lang)
      document.documentElement.setAttribute('dir', direction)
      document.body.classList.toggle('st-locale-ar', lang === 'ar')
      document.body.classList.toggle('st-locale-en', lang === 'en')
    },
    { immediate: true }
  )

  return {
    locale,
    dir,
    isRtl,
    languageLabel,
    switchLanguageLabel,
    switchTargetLocale,
    switchLocale,
    toggleLocale,
    supportedLocales: SUPPORTED,
  }
}
