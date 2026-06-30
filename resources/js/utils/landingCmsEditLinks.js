import { route } from 'ziggy-js'
import { LANDING_SECTION_CONTENT_SOURCES } from '@/data/website-landing-content-sources'

export function landingSectionContentSource(blockType) {
  return LANDING_SECTION_CONTENT_SOURCES[blockType] ?? null
}

export function resolveAdminLink(link) {
  if (!link?.route) return route('admin.website.index')
  if (typeof link.params === 'string') {
    return route(link.route, link.params)
  }
  const params = link.params ?? link.query ?? {}
  return route(link.route, params)
}

/** Primary admin URL for section content (posts, events, school info, …). */
export function landingSectionEditLink(section) {
  const source = landingSectionContentSource(section?.block_type)
  if (source?.links?.[0]) {
    return resolveAdminLink(source.links[0])
  }
  if (section?.id) {
    return route('admin.website.landing-builder.edit', section.id)
  }
  return route('admin.website.landing-builder.index')
}

export function landingSectionEditLabel(section) {
  const source = landingSectionContentSource(section?.block_type)
  return source?.links?.[0]?.label ?? source?.title ?? section?.admin_name ?? 'تعديل القسم'
}

/** Landing Builder section settings (title, eyebrow, visibility). */
export function landingSectionSettingsLink(section) {
  if (!section?.id) return null
  return route('admin.website.landing-builder.edit', section.id)
}
