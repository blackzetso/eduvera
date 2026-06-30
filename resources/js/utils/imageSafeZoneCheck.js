/**
 * Simulate object-fit: cover center crop and test safe-zone visibility.
 */

/**
 * @param {number} imageW
 * @param {number} imageH
 * @param {number} frameRatio width/height
 */
export function computeCoverCrop(imageW, imageH, frameRatio) {
  if (!imageW || !imageH || !frameRatio) {
    return {
      offsetXPct: 0,
      offsetYPct: 0,
      visibleWidthPct: 100,
      visibleHeightPct: 100,
    }
  }

  const imageRatio = imageW / imageH

  if (imageRatio > frameRatio) {
    const visibleWidthPct = (frameRatio / imageRatio) * 100
    return {
      offsetXPct: (100 - visibleWidthPct) / 2,
      offsetYPct: 0,
      visibleWidthPct,
      visibleHeightPct: 100,
    }
  }

  const visibleHeightPct = (imageRatio / frameRatio) * 100
  return {
    offsetXPct: 0,
    offsetYPct: (100 - visibleHeightPct) / 2,
    visibleWidthPct: 100,
    visibleHeightPct,
  }
}

/**
 * @param {{ left: number, top: number, width: number, height: number, critical?: boolean }} zone
 * @param {{ offsetXPct: number, offsetYPct: number, visibleWidthPct: number, visibleHeightPct: number }} crop
 */
export function isZoneFullyVisible(zone, crop) {
  const right = zone.left + zone.width
  const bottom = zone.top + zone.height
  const cropRight = crop.offsetXPct + crop.visibleWidthPct
  const cropBottom = crop.offsetYPct + crop.visibleHeightPct

  return (
    zone.left >= crop.offsetXPct - 0.5 &&
    zone.top >= crop.offsetYPct - 0.5 &&
    right <= cropRight + 0.5 &&
    bottom <= cropBottom + 0.5
  )
}

/**
 * @param {Array<{ critical?: boolean, left: number, top: number, width: number, height: number }>} zones
 * @param {Array<{ aspectRatio: number }>} frames
 * @param {{ width: number, height: number } | null} dims
 */
export function evaluateSafeZones(zones, frames, dims) {
  if (!dims?.width || !dims?.height || !zones?.length || !frames?.length) {
    return { hasRisk: false, message: null, frameIssues: [] }
  }

  const criticalZones = zones.filter((z) => z.critical)
  const frameIssues = []

  for (const frame of frames) {
    const crop = computeCoverCrop(dims.width, dims.height, frame.aspectRatio)
    const badZones = criticalZones.filter((z) => !isZoneFullyVisible(z, crop))
    if (badZones.length) {
      frameIssues.push({ frameId: frame.id, frameLabel: frame.label, zones: badZones.map((z) => z.label) })
    }
  }

  const hasRisk = frameIssues.length > 0
  const message = hasRisk
    ? 'Important content may be cropped on some devices.'
    : null

  return { hasRisk, message, frameIssues }
}
