import assert from 'node:assert/strict'
import { test } from 'node:test'
import { buildNavigationLinks, formatPublicAddress } from '../src/utils/addressNavigation.js'

const publicLocation = { address: ' Brīvības iela 10/A & B ', city: ' Rīga ', postalCode: 'LV-1010' }
const fullAddress = 'Brīvības iela 10/A & B, Rīga, LV-1010'

test('uses a complete coordinate pair before the public address', () => {
  const links = buildNavigationLinks({ ...publicLocation, latitude: '56.9496', longitude: 24.1052 })
  assert.deepEqual(links, {
    googleMaps: 'https://www.google.com/maps/dir/?api=1&destination=56.9496%2C24.1052',
    waze: 'https://waze.com/ul?ll=56.9496%2C24.1052&navigate=yes',
    appleMaps: 'https://maps.apple.com/?daddr=56.9496%2C24.1052&dirflg=d',
  })
  assert.equal(new URL(buildNavigationLinks({ latitude: 0, longitude: 180 }).waze).searchParams.get('ll'), '0,180')
})

test('encodes the full public address and leaves the origin to the navigation service', () => {
  assert.equal(formatPublicAddress(publicLocation), fullAddress)
  const links = buildNavigationLinks(publicLocation)
  assert.deepEqual(Object.fromEntries(new URL(links.googleMaps).searchParams), { api: '1', destination: fullAddress })
  assert.deepEqual(Object.fromEntries(new URL(links.waze).searchParams), { q: fullAddress, navigate: 'yes' })
  assert.deepEqual(Object.fromEntries(new URL(links.appleMaps).searchParams), { daddr: fullAddress, dirflg: 'd' })
  assert.match(links.waze, /Br%C4%ABv%C4%ABbas%20iela%2010%2FA%20%26%20B/)
})

test('falls back to the address for missing, incomplete, or invalid coordinates', () => {
  const addressLinks = buildNavigationLinks(publicLocation)
  for (const [latitude, longitude] of [[null, null], [56, ' '], [false, 24], [56, Infinity], [91, 24], [56, -181], ['bad', 24], ['0x10', 24]]) {
    assert.deepEqual(buildNavigationLinks({ ...publicLocation, latitude, longitude }), addressLinks)
  }
})

test('does not use a city, postal code, or placeholder alone as a pickup point', () => {
  assert.equal(buildNavigationLinks({}), null)
  assert.equal(buildNavigationLinks({ address: ' — ', city: 'Rīga', postalCode: 'LV-1010', latitude: 56 }), null)
  assert.equal(formatPublicAddress({ address: 'Čaka iela 7', city: ' ', postalCode: '—' }), 'Čaka iela 7')
})
