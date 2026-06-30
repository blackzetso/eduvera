const BUST_AVATARS = {
  welcome: '/brand/dova/dova-welcome.webp',
  thinking: '/brand/dova/dova-thinking.webp',
  explaining: '/brand/dova/dova-explaining.webp',
  teaching: '/brand/dova/dova-explaining.webp',
  success: '/brand/dova/dova-success.webp',
  celebrating: '/brand/dova/dova-success.webp',
  help: '/brand/dova/dova-help.webp',
  listening: '/brand/dova/dova-welcome.webp',
}

/** Half-body transparent mascot — header hero (PNG for reliable alpha) */
const MASCOT_AVATARS = {
  welcome: '/brand/dova/dova-mascot-welcome.png',
  thinking: '/brand/dova/dova-mascot-thinking.png',
  explaining: '/brand/dova/dova-mascot-explaining.png',
  teaching: '/brand/dova/dova-mascot-explaining.png',
  success: '/brand/dova/dova-mascot-success.png',
  celebrating: '/brand/dova/dova-mascot-success.png',
  help: '/brand/dova/dova-mascot-help.png',
  listening: '/brand/dova/dova-mascot-welcome.png',
}

const EXPRESSION_ALIASES = {
  welcome: 'welcome',
  greeting: 'welcome',
  thinking: 'thinking',
  explaining: 'explaining',
  teaching: 'teaching',
  success: 'success',
  celebrating: 'celebrating',
  helping: 'help',
  help: 'help',
  error: 'help',
  recovery: 'help',
  listening: 'listening',
}

export function normalizeDovaExpression(expression) {
  if (! expression) {
    return 'welcome'
  }

  return EXPRESSION_ALIASES[expression] ?? 'explaining'
}

/**
 * @param {'hero'|'mascot'|'header'|'bust'} [variant]
 */
export function dovaAvatarSrc(expression, variant = 'mascot') {
  const key = normalizeDovaExpression(expression)

  if (variant === 'mascot' || variant === 'hero') {
    return MASCOT_AVATARS[key] ?? MASCOT_AVATARS.explaining
  }

  return BUST_AVATARS[key] ?? BUST_AVATARS.explaining
}

export const DOVA_TAGLINE = {
  en: 'Your Smart School Guide',
  ar: 'دليلك الذكي للمدرسة',
}
