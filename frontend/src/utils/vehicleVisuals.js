import atv from '@/assets/cat-atv.jpg'
import bike from '@/assets/cat-bike.jpg'
import boat from '@/assets/cat-boat.jpg'
import car from '@/assets/cat-car.jpg'
import moto from '@/assets/cat-moto.jpg'
import suv from '@/assets/cat-suv.jpg'
import van from '@/assets/cat-van.jpg'

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

// The original API has no vehicle photos. Use the reference's category imagery.
export function getVehicleImage (vehicle) {
  const kind = `${vehicle?.veids?.tips || ''} ${vehicle?.veids?.nosaukums || ''}`.toLowerCase()
  if (/quad|kvadr|atv|квадр/.test(kind)) {
    return atv
  }
  if (/laiv|boat|jetski|ūdens|water/.test(kind)) {
    return boat
  }
  if (/suv|apvid/.test(kind)) {
    return suv
  }
  if (/kravas|van|bus|furgon/.test(kind)) {
    return van
  }
  if (/velos|bike|bicycle/.test(kind)) {
    return bike
  }
  if (/moto|quad|kvadr/.test(kind)) {
    return moto
  }
  return car
}
