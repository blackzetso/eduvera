import axios from 'axios'
import { route } from 'ziggy-js'

/**
 * Fill missing bilingual pair values via server auto-translation.
 * Only translates when exactly one side of a known pair is present.
 */
export async function translateBilingualPayload(payload) {
  const { data } = await axios.post(route('admin.forms.translate-bilingual'), { payload })

  return data.payload ?? payload
}

export async function translateNamePair(nameAr = '', nameEn = '') {
  const ar = String(nameAr ?? '').trim()
  const en = String(nameEn ?? '').trim()

  if ((!ar && !en) || (ar && en)) {
    return { name_ar: ar, name_en: en }
  }

  const translated = await translateBilingualPayload({ name_ar: ar, name_en: en })

  return {
    name_ar: String(translated.name_ar ?? ar).trim(),
    name_en: String(translated.name_en ?? en).trim(),
  }
}
