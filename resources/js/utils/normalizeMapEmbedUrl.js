/**
 * Google Maps admin field may contain full <iframe> HTML or bare embed URL.
 */
export function normalizeMapEmbedUrl(value) {
  if (!value || typeof value !== 'string') return value || ''
  const trimmed = value.trim()
  const srcMatch = trimmed.match(/src\s*=\s*["']([^"']+)["']/i)
  if (srcMatch) return srcMatch[1]
  const urlMatch = trimmed.match(/https?:\/\/[^\s"'<>]+/i)
  if (urlMatch) return urlMatch[0]
  return trimmed
}
