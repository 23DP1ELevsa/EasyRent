export const MAX_VEHICLE_PHOTOS = 4
export const VEHICLE_PHOTO_ACCEPT = 'image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif'
const MAX_BYTES = 1536 * 1024
const MAX_SIDE = 1600

async function decodePhoto (file) {
  if (typeof createImageBitmap === 'function') {
    try {
      const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' })
      return { image: bitmap, release: () => bitmap.close() }
    } catch {
      // The browser's image element can support formats the bitmap decoder does not.
    }
  }

  const url = URL.createObjectURL(file)
  try {
    const image = new Image()
    image.src = url
    await image.decode()
    return { image, release: () => URL.revokeObjectURL(url) }
  } catch (error) {
    URL.revokeObjectURL(url)
    throw error
  }
}

export async function prepareVehiclePhoto (file) {
  const allowedType = /^(image\/(jpeg|png|webp|hei[cf](?:-sequence)?))$/i
  if (!(allowedType.test(file.type) || (!file.type && /\.(jpe?g|png|webp|hei[cf])$/i.test(file.name)))) {
    throw new Error('invalid')
  }

  let decoded
  try {
    decoded = await decodePhoto(file)
  } catch {
    throw new Error('invalid')
  }

  const { image, release } = decoded
  try {
    const canvas = document.createElement('canvas')
    const width = image.naturalWidth || image.width
    const height = image.naturalHeight || image.height
    const scale = Math.min(1, MAX_SIDE / Math.max(width, height))
    canvas.width = Math.max(1, Math.round(width * scale))
    canvas.height = Math.max(1, Math.round(height * scale))
    const context = canvas.getContext('2d', { alpha: false })
    context.fillStyle = '#fff'
    context.fillRect(0, 0, canvas.width, canvas.height)
    context.drawImage(image, 0, 0, canvas.width, canvas.height)

    for (const quality of [0.9, 0.82, 0.73, 0.62]) {
      const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', quality))
      if (blob && blob.size <= MAX_BYTES) {
        return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: blob.type })
      }
    }
    throw new Error('tooLarge')
  } finally {
    release()
  }
}
