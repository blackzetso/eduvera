import { toRaw } from 'vue'
import { LOCALE_AR_OVERRIDES } from '@/data/school-talent/locale-ar'

function isPlainObject(value) {
  return value != null && typeof value === 'object' && !Array.isArray(value)
}

function clonePlain(value) {
  const raw = toRaw(value)
  try {
    return structuredClone(raw)
  } catch {
    try {
      return JSON.parse(JSON.stringify(raw))
    } catch {
      return raw
    }
  }
}

export function deepMerge(target, source) {
  if (!isPlainObject(target) || !isPlainObject(source)) {
    return source ?? target
  }

  const out = { ...target }
  for (const [key, value] of Object.entries(source)) {
    if (Array.isArray(value)) {
      out[key] = value.map((item, index) => {
        if (isPlainObject(item) && isPlainObject(target[key]?.[index])) {
          return deepMerge(target[key][index], item)
        }
        return item
      })
    } else if (isPlainObject(value) && isPlainObject(out[key])) {
      out[key] = deepMerge(out[key], value)
    } else {
      out[key] = value
    }
  }

  return out
}

function resolveLocalizedFields(node, locale) {
  if (Array.isArray(node)) {
    node.forEach((item) => resolveLocalizedFields(item, locale))
    return
  }

  if (!isPlainObject(node)) {
    return
  }

  for (const key of Object.keys(node)) {
    const localizedKey = `${key}_${locale}`
    if (localizedKey in node && node[localizedKey] != null && node[localizedKey] !== '') {
      node[key] = node[localizedKey]
    }

    const value = node[key]
    if (isPlainObject(value) && (value.ar != null || value.en != null)) {
      node[key] = value[locale] ?? value.en ?? value.ar ?? ''
    } else {
      resolveLocalizedFields(value, locale)
    }
  }
}

/** Merge Arabic fallback copy only (CMS already localized on server). */
export function mergeLocaleOverrides(source, overrides = LOCALE_AR_OVERRIDES) {
  if (!source) {
    return source
  }

  try {
    return deepMerge(clonePlain(source), overrides)
  } catch {
    return source
  }
}

export function localizeDeep(source, locale, overrides = LOCALE_AR_OVERRIDES) {
  if (!source || locale === 'en') {
    return source
  }

  try {
    const clone = clonePlain(source)
    resolveLocalizedFields(clone, locale)
    return deepMerge(clone, overrides)
  } catch {
    return source
  }
}
