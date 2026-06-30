import { getImageSpec } from '@/data/website-image-specs'

const ASPECT_TOLERANCE = 0.15
const SIZE_MIN = 0.7
const SIZE_MAX = 1.5

export function readFileDimensions(file) {
  return new Promise((resolve, reject) => {
    if (!file) {
      resolve(null)
      return
    }
    const url = URL.createObjectURL(file)
    const img = new Image()
    img.onload = () => {
      URL.revokeObjectURL(url)
      resolve({ width: img.naturalWidth, height: img.naturalHeight })
    }
    img.onerror = () => {
      URL.revokeObjectURL(url)
      reject(new Error('Could not read image dimensions'))
    }
    img.src = url
  })
}

export function readUrlDimensions(url) {
  return new Promise((resolve) => {
    if (!url || typeof url !== 'string') {
      resolve(null)
      return
    }
    const img = new Image()
    img.onload = () => resolve({ width: img.naturalWidth, height: img.naturalHeight })
    img.onerror = () => resolve(null)
    img.src = url
  })
}

/**
 * @param {{ width: number, height: number }} dims
 * @param {import('@/data/website-image-specs').WEBSITE_IMAGE_SPECS[string]} spec
 */
export function evaluateDimensions(dims, spec) {
  if (!dims?.width || !dims?.height || !spec) {
    return { significantlyOff: false, message: null, sizeWarning: null }
  }

  const expectedRatio = spec.width / spec.height
  const actualRatio = dims.width / dims.height
  const ratioOff = Math.abs(actualRatio - expectedRatio) / expectedRatio

  const wRatio = dims.width / spec.width
  const hRatio = dims.height / spec.height

  const significantlyOff =
    ratioOff > ASPECT_TOLERANCE ||
    wRatio < SIZE_MIN ||
    wRatio > SIZE_MAX ||
    hRatio < SIZE_MIN ||
    hRatio > SIZE_MAX

  const message = significantlyOff
    ? `This image may not display correctly in this section. Recommended size is ${spec.width}×${spec.height}.`
    : null

  return { significantlyOff, message, ratioOff, wRatio, hRatio }
}

export function evaluateFileSize(file, spec) {
  if (!file || !spec?.maxMb) return null
  const maxBytes = spec.maxMb * 1024 * 1024
  if (file.size > maxBytes) {
    return `File size (${(file.size / 1024 / 1024).toFixed(1)} MB) exceeds the recommended maximum of ${spec.maxMb} MB. Upload is still allowed.`
  }
  return null
}

export async function analyzeImageFile(file, specKey) {
  const spec = getImageSpec(specKey)
  const dims = await readFileDimensions(file)
  const dimCheck = evaluateDimensions(dims, spec)
  const sizeWarning = evaluateFileSize(file, spec)
  return { spec, dims, dimCheck, sizeWarning }
}

export async function analyzeImageUrl(url, specKey) {
  const spec = getImageSpec(specKey)
  const dims = await readUrlDimensions(url)
  const dimCheck = evaluateDimensions(dims, spec)
  return { spec, dims, dimCheck, sizeWarning: null }
}
