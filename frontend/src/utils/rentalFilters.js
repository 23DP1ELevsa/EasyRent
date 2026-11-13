export function createRentalFilters () {
  return { q: '', typeId: null, companyId: null, gearbox: null, fuel: null, minPrice: null, maxPrice: null, onlyAvailable: false, nearby: false }
}

export function normalizePriceFilter (value) {
  if (value === '' || value === null || value === undefined) {
    return null
  }
  const parsed = Number(value)
  return Number.isFinite(parsed) && parsed >= 0 ? parsed : null
}

export function hasValidPriceRange (filters) {
  const min = normalizePriceFilter(filters.minPrice)
  const max = normalizePriceFilter(filters.maxPrice)
  return min === null || max === null || min <= max
}

export function activeRentalFilters (filters) {
  return Object.keys(createRentalFilters()).filter(key => {
    if (key === 'minPrice' || key === 'maxPrice') {
      return normalizePriceFilter(filters[key]) !== null
    }
    if (key === 'q') {
      return Boolean(filters.q?.trim())
    }
    return Boolean(filters[key])
  })
}

function distanceKm (first, second) {
  const radians = degrees => degrees * Math.PI / 180
  const lat = radians(second.lat - first.lat)
  const lng = radians(second.lng - first.lng)
  const a = Math.sin(lat / 2) ** 2 + Math.cos(radians(first.lat)) * Math.cos(radians(second.lat)) * Math.sin(lng / 2) ** 2
  return 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(Math.max(0, 1 - a)))
}

// Both the live map and the unapplied list draft run the same predicate.
export function filterRentalPoints (points, filters, { isAvailable, position = null, coordinates = {} }) {
  const query = (filters.q || '').trim().toLocaleLowerCase()
  const min = normalizePriceFilter(filters.minPrice)
  const max = normalizePriceFilter(filters.maxPrice)
  return points.flatMap(point => {
    if (filters.companyId && String(point.id) !== String(filters.companyId)) {
      return []
    }
    if (filters.nearby) {
      const coords = point.hasCoords ? point : coordinates[point.id]
      if (!position || !coords || distanceKm(position, coords) > 25) {
        return []
      }
    }
    const matchesPoint = [point.name, point.city, point.address].some(value => String(value || '').toLocaleLowerCase().includes(query))
    const vehicles = point.vehicles.filter(vehicle => {
      const price = Number(vehicle.dienas_nomas_cena)
      if (filters.typeId && String(vehicle.veids_id) !== String(filters.typeId)) {
        return false
      }
      if (filters.gearbox && String(vehicle.atrumkarba || '').trim() !== filters.gearbox) {
        return false
      }
      if (filters.fuel && String(vehicle.degvielas_veids || '').trim() !== filters.fuel) {
        return false
      }
      if ((min !== null || max !== null) && !Number.isFinite(price)) {
        return false
      }
      if (min !== null && price < min) {
        return false
      }
      if (max !== null && price > max) {
        return false
      }
      if (filters.onlyAvailable && !isAvailable(vehicle)) {
        return false
      }
      return !query || matchesPoint || [`${vehicle.marka || ''} ${vehicle.modelis || ''}`, vehicle.veids?.nosaukums, vehicle.registracijas_numurs]
        .some(value => String(value || '').toLocaleLowerCase().includes(query))
    })
    return vehicles.length > 0 ? [{ ...point, vehicles, availableCount: vehicles.filter(vehicle => isAvailable(vehicle)).length }] : []
  })
}

export function sortRentalVehicles (vehicles, order, isAvailable, locale) {
  const sorted = [...vehicles]
  // Keep support for browsers that do not yet implement Array.prototype.toSorted.
  // eslint-disable-next-line unicorn/no-array-sort
  return sorted.sort((a, b) => {
    if (order === 'price_asc') {
      return Number(a.dienas_nomas_cena) - Number(b.dienas_nomas_cena)
    }
    if (order === 'price_desc') {
      return Number(b.dienas_nomas_cena) - Number(a.dienas_nomas_cena)
    }
    if (order === 'availability_desc') {
      return Number(isAvailable(b)) - Number(isAvailable(a))
    }
    if (order === 'brand_asc') {
      return `${a.marka} ${a.modelis}`.localeCompare(`${b.marka} ${b.modelis}`, locale)
    }
    return 0
  })
}
