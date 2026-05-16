import { usePage } from '@inertiajs/vue3'

export function useTranslations() {
  const page = usePage()
  const t = (key) => page.props.translations[key] || key
  return { t }
}
