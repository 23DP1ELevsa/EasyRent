import bike from '@/assets/cat-bike.jpg'
import boat from '@/assets/cat-boat.jpg'
import car from '@/assets/cat-car.jpg'
import moto from '@/assets/cat-moto.jpg'
import suv from '@/assets/cat-suv.jpg'
import van from '@/assets/cat-van.jpg'

// The original API has no vehicle photos. Use the reference's category imagery.
export function getVehicleImage (vehicle) {
  const kind = `${vehicle?.veids?.tips || ''} ${vehicle?.veids?.nosaukums || ''}`.toLowerCase()
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
