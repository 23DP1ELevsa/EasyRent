export function getTransportTypeIcon (name = '') {
  const kind = name.toLowerCase()
  if (/quad|kvadr|atv|квадр/.test(kind)) {
    return 'mdi-atv'
  }
  if (/moto|мото/.test(kind)) {
    return 'mdi-motorbike'
  }
  if (/laiv|boat|jetski|ūdens|water|лод|водн/.test(kind)) {
    return 'mdi-ferry'
  }
  if (/velos|bike|bicycle|вело/.test(kind)) {
    return 'mdi-bicycle'
  }
  if (/kravas|van|furgon|груз/.test(kind)) {
    return 'mdi-truck-outline'
  }
  if (/piekab|trailer|прицеп/.test(kind)) {
    return 'mdi-trailer'
  }
  if (/bus|автобус/.test(kind)) {
    return 'mdi-bus'
  }
  if (/auto|car|suv|apvid|авто/.test(kind)) {
    return 'mdi-car-outline'
  }
  return 'mdi-steering'
}

export function getVehicleImage (vehicle) {
  return vehicle?.primary_photo_url || null
}
