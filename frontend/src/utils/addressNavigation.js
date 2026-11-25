function addressPart (value) {
  const text = typeof value === 'string' ? value.trim() : ''
  return /[\p{L}\p{N}]/u.test(text) ? text : ''
}

export function formatPublicAddress ({ address, city, postalCode }) {
  const street = addressPart(address)
  if (!street) {
    return ''
  }
  return [street, addressPart(city), addressPart(postalCode)].filter(Boolean).join(', ')
}

export function toValidCoordinate (value, limit) {
  if (typeof value !== 'number' && (typeof value !== 'string' || !/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:e[+-]?\d+)?$/i.test(value.trim()))) {
    return null
  }
  const coordinate = Number(value)
  return Number.isFinite(coordinate) && Math.abs(coordinate) <= limit ? coordinate : null
}

export function buildNavigationLinks ({ address, city, postalCode, latitude, longitude }) {
  const lat = toValidCoordinate(latitude, 90)
  const lng = toValidCoordinate(longitude, 180)
  const coordinates = lat !== null && lng !== null ? `${lat},${lng}` : ''
  const destination = coordinates || formatPublicAddress({ address, city, postalCode })
  if (!destination) {
    return null
  }

  const encoded = encodeURIComponent(destination)
  return {
    googleMaps: `https://www.google.com/maps/dir/?api=1&destination=${encoded}`,
    waze: `https://waze.com/ul?${coordinates ? 'll' : 'q'}=${encoded}&navigate=yes`,
    appleMaps: `https://maps.apple.com/?daddr=${encoded}&dirflg=d`,
  }
}
