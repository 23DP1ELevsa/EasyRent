import assert from 'node:assert/strict'
import test from 'node:test'
import en from '../src/lang/en.js'
import lv from '../src/lang/lv.js'
import ru from '../src/lang/ru.js'
import { activeRentalFilters, createRentalFilters, filterRentalPoints, hasValidPriceRange, normalizePriceFilter, sortRentalVehicles } from '../src/utils/rentalFilters.js'

const car = { transportlidzeklis_id: 1, marka: 'Toyota', modelis: 'Corolla', veids_id: 1, atrumkarba: 'Automāts', degvielas_veids: 'Elektro', dienas_nomas_cena: '50.00', statuss: 'pieejams' }
const bike = { transportlidzeklis_id: 2, marka: 'Honda', modelis: 'CB500', veids_id: 2, atrumkarba: '-', degvielas_veids: 'Benzīns', dienas_nomas_cena: '20.00', statuss: 'aiznemts' }
const points = [
  { id: 1, name: 'Riga Rent', city: 'Rīga', address: 'Brīvības 1', hasCoords: true, lat: 56.95, lng: 24.1, vehicles: [car, bike] },
  { id: 2, name: 'Coast Rent', city: 'Liepāja', address: 'Jūras 1', hasCoords: true, lat: 56.51, lng: 21.01, vehicles: [{ ...car, transportlidzeklis_id: 3 }] },
  { id: 3, name: 'No coordinates', city: 'Rīga', vehicles: [{ ...bike, transportlidzeklis_id: 4 }] },
]
const isAvailable = vehicle => vehicle.statuss === 'pieejams'
const filter = (overrides = {}, options = {}) => filterRentalPoints(points, { ...createRentalFilters(), ...overrides }, { isAvailable, ...options })
const ids = result => result.flatMap(point => point.vehicles.map(vehicle => vehicle.transportlidzeklis_id))

test('search matches full brand/model, city, provider, and empty/cleared values', () => {
  assert.deepEqual(ids(filter({ q: 'toyota corolla' })), [1, 3])
  assert.deepEqual(ids(filter({ q: ' RĪGA ' })), [1, 2, 4])
  assert.deepEqual(ids(filter({ q: 'coast' })), [3])
  assert.deepEqual(ids(filter({ q: null })), [1, 2, 3, 4])
})

test('combines real attributes, inclusive price limits, availability, and string IDs', () => {
  assert.deepEqual(ids(filter({ companyId: '1', typeId: '1', gearbox: 'Automāts', fuel: 'Elektro', minPrice: 50, maxPrice: 50, onlyAvailable: true })), [1])
  assert.deepEqual(ids(filter({ maxPrice: 49.99 })), [2, 4])
  assert.deepEqual(ids(filter({ minPrice: 51, maxPrice: 20 })), [])
  assert.equal(filter({ onlyAvailable: true })[0].availableCount, 1)
})

test('draft preview and sorting never mutate the applied filters or API data', () => {
  const applied = createRentalFilters()
  const draft = { ...applied, onlyAvailable: true, maxPrice: 50 }
  const before = JSON.stringify(points)
  filterRentalPoints(points, draft, { isAvailable })
  assert.deepEqual(applied, createRentalFilters())
  assert.deepEqual(sortRentalVehicles([car, bike], 'price_asc', isAvailable, 'en').map(vehicle => vehicle.transportlidzeklis_id), [2, 1])
  assert.deepEqual(sortRentalVehicles([bike, car], 'availability_desc', isAvailable, 'en').map(vehicle => vehicle.transportlidzeklis_id), [1, 2])
  assert.equal(JSON.stringify(points), before)
})

test('nearby uses a real 25km radius and resolved coordinates, never invented coordinates', () => {
  const position = { lat: 56.95, lng: 24.1 }
  assert.deepEqual(ids(filter({ nearby: true }, { position })), [1, 2])
  assert.deepEqual(ids(filter({ nearby: true }, { position, coordinates: { 3: position } })), [1, 2, 4])
  assert.deepEqual(filter({ nearby: true }), [])
})

test('zero prices count as filters; blanks and invalid prices do not', () => {
  assert.deepEqual(activeRentalFilters({ ...createRentalFilters(), minPrice: 0, q: '  ', gearbox: 'Automāts' }), ['gearbox', 'minPrice'])
  assert.equal(normalizePriceFilter(''), null)
  assert.equal(normalizePriceFilter(-1), null)
  assert.equal(hasValidPriceRange({ minPrice: 100, maxPrice: 50 }), false)
  assert.equal(hasValidPriceRange({ minPrice: 0, maxPrice: '' }), true)
})

test('all new catalog labels and sorting options exist in LV/RU/EN', () => {
  const keys = ['mapSubtitle', 'searchPlaceholder', 'quickFilters', 'quickAvailable', 'quickPrice', 'quickAutomatic', 'quickElectric', 'quickNearby', 'priceAvailability', 'additionalParameters', 'activeFilters', 'showMore', 'showResults', 'resultCount', 'sortBy', 'noTypes', 'availabilityHint', 'invalidPriceRange', 'locationFailed']
  for (const locale of [lv, ru, en]) {
    for (const key of keys) {
      assert.equal(typeof locale.map[key], 'string', key)
      assert.ok(!locale.map[key].includes('???'), key)
    }
    assert.equal(locale.map.sortOptions.length, 5)
  }
})
