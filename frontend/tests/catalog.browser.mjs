// UI regression checks with isolated API fixtures; no requests reach a real backend.
// Run Vite first. Optionally set CATALOG_CDP to an existing Chrome debugging URL.
import assert from 'node:assert/strict'
import { spawn } from 'node:child_process'
import { existsSync } from 'node:fs'
import { mkdir, readFile, writeFile } from 'node:fs/promises'
import path from 'node:path'
import { setTimeout as delay } from 'node:timers/promises'

const base = process.env.CATALOG_URL || 'http://127.0.0.1:3000'
const artifacts = path.resolve('node_modules/.cache/catalog-qa')
await mkdir(artifacts, { recursive: true })
let browser
let endpoint = process.env.CATALOG_CDP
if (!endpoint) {
  const executable = process.env.CATALOG_BROWSER_PATH || ['C:/Program Files/Google/Chrome/Application/chrome.exe', 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(candidate => existsSync(candidate))
  assert.ok(executable, 'Set CATALOG_BROWSER_PATH to a Chromium browser')
  const profile = path.join(artifacts, `profile-${Date.now()}`)
  browser = spawn(executable, ['--headless=new', '--disable-gpu', '--no-sandbox', '--remote-debugging-port=0', `--user-data-dir=${profile}`, '--no-first-run', '--no-default-browser-check', 'about:blank'], { windowsHide: true, stdio: 'ignore' })
  for (let attempt = 0; attempt < 100; attempt++) {
    try {
      const port = (await readFile(path.join(profile, 'DevToolsActivePort'), 'utf8')).split('\n')[0]
      endpoint = `http://127.0.0.1:${port}`
      break
    } catch {
      await delay(100)
    }
  }
}
assert.ok(endpoint, 'Chrome debugging endpoint did not start')
const target = await fetch(`${endpoint}/json/new?${encodeURIComponent(`${base}/map`)}`, { method: 'PUT' }).then(response => response.json())
const socket = new WebSocket(target.webSocketDebuggerUrl)
await new Promise((resolve, reject) => {
  socket.addEventListener('open', resolve, { once: true })
  socket.addEventListener('error', reject, { once: true })
})
let sequence = 0
const pending = new Map()
const failures = []
const writes = []
const types = ['Automašīna', 'Motocikls', 'Kvadracikls', 'Laiva', 'Velosipēds', 'Furgons'].map((nosaukums, index) => ({ veids_id: index + 1, nosaukums }))
const providerCoordinates = [[56.95, 24.1], [56.96, 24.12], [56.94, 24.09], [56.58, 21.08], [56.59, 21.09], [56.6, 21.1], [56.61, 21.11]]
const vehicles = Array.from({ length: 12 }, (_, index) => ({
  transportlidzeklis_id: index + 1,
  sniedzejs_id: index < 6 ? 1 : index - 4,
  marka: index === 0 ? 'Toyota' : (index === 1 ? 'Honda' : `Brand ${index + 1}`),
  modelis: index === 0 ? 'Corolla' : (index === 1 ? 'CB500' : 'Touring'),
  veids_id: index % types.length + 1,
  veids: types[index % types.length],
  atrumkarba: index % 2 === 0 ? 'Automāts' : 'Mehānika',
  degvielas_veids: index % 2 === 0 ? 'Elektro' : 'Benzīns',
  dienas_nomas_cena: 20 + index * 5,
  registracijas_numurs: `QA-${index + 1}`,
  statuss: index === 10 ? 'neaktivs' : (index === 11 ? 'aiznemts' : 'pieejams'),
  atsauksmju_skaits: 3,
  videjais_vertejums: 4.7,
  rezervacijas: [],
  sniedzejs: { sniedzejs_id: index < 6 ? 1 : index - 4, persona: { vards: 'Rental', uzvards: String(index < 6 ? 1 : index - 4) }, pilseta: 'Rīga', iela: 'Brīvības', maja: '1', latitude: providerCoordinates[index < 6 ? 0 : index - 5][0], longitude: providerCoordinates[index < 6 ? 0 : index - 5][1] },
}))

function send (method, params = {}) {
  const id = ++sequence
  return new Promise((resolve, reject) => {
    pending.set(id, { resolve, reject })
    socket.send(JSON.stringify({ id, method, params }))
  })
}
socket.addEventListener('message', async event => {
  const message = JSON.parse(event.data)
  if (message.id) {
    const promise = pending.get(message.id)
    pending.delete(message.id)
    if (message.error) {
      promise?.reject(new Error(JSON.stringify(message.error)))
    } else {
      promise?.resolve(message.result)
    }
  } else if (message.method === 'Runtime.exceptionThrown') {
    failures.push(message.params.exceptionDetails)
  } else if (message.method === 'Fetch.requestPaused') {
    const { requestId, request } = message.params
    try {
      const url = new URL(request.url)
      let result = {}
      if (request.method !== 'GET' && request.method !== 'OPTIONS') {
        writes.push({ method: request.method, path: url.pathname, body: request.postData })
      }
      if (url.pathname === '/api/transport/veidi') {
        result = types
      } else if (url.pathname === '/api/transport') {
        result = vehicles
      } else if (url.pathname.startsWith('/api/atsauksmes')) {
        result = []
      }
      await send('Fetch.fulfillRequest', { requestId, responseCode: 200, responseHeaders: [{ name: 'Content-Type', value: 'application/json' }, { name: 'Access-Control-Allow-Origin', value: '*' }, { name: 'Access-Control-Allow-Methods', value: 'GET, POST, PUT, DELETE, OPTIONS' }, { name: 'Access-Control-Allow-Headers', value: '*' }], body: Buffer.from(JSON.stringify(result)).toString('base64') })
    } catch (error) {
      failures.push(String(error))
      await send('Fetch.failRequest', { requestId, errorReason: 'Failed' })
    }
  }
})
async function evaluate (expression) {
  const result = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true })
  if (result.exceptionDetails) {
    throw new Error(JSON.stringify(result.exceptionDetails))
  }
  return result.result.value
}
async function waitFor (expression, label = expression) {
  for (let attempt = 0; attempt < 150; attempt++) {
    if (await evaluate(expression)) {
      return
    }
    await delay(100)
  }
  throw new Error(`Timed out: ${label}`)
}
async function click (selector) {
  assert.ok(await evaluate(`!!document.querySelector(${JSON.stringify(selector)})`), `Missing ${selector}`)
  await evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`)
  await delay(350)
}
async function input (selector, value) {
  await evaluate(`(() => { const el = document.querySelector(${JSON.stringify(selector)}); el.value = ${JSON.stringify(value)}; el.dispatchEvent(new Event('input', { bubbles: true })); })()`)
  await delay(250)
}
async function key (value, extra = {}) {
  await send('Input.dispatchKeyEvent', { type: 'keyDown', key: value, code: value, ...extra })
  await send('Input.dispatchKeyEvent', { type: 'keyUp', key: value, code: value, ...extra })
  await delay(350)
}
async function screenshot (name) {
  const { data } = await send('Page.captureScreenshot', { format: 'png' })
  await writeFile(path.join(artifacts, `${name}.png`), Buffer.from(data, 'base64'))
}
async function resize (width, height) {
  await send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: width < 768 })
  await delay(400)
}
async function panMap () {
  const rect = await evaluate('(() => { const r = document.querySelector(".map-viewport").getBoundingClientRect(); return { x: r.x, y: r.y, width: r.width, height: r.height }; })()')
  const x = rect.x + rect.width * 0.8
  const y = rect.y + rect.height / 2
  await send('Input.dispatchMouseEvent', { type: 'mousePressed', x, y, button: 'left', buttons: 1, clickCount: 1 })
  for (let step = 1; step <= 12; step++) {
    await send('Input.dispatchMouseEvent', { type: 'mouseMoved', x: x - rect.width * 0.6 * step / 12, y, button: 'left', buttons: 1 })
    await delay(20)
  }
  await send('Input.dispatchMouseEvent', { type: 'mouseReleased', x: x - rect.width * 0.6, y, button: 'left', buttons: 0, clickCount: 1 })
  await delay(600)
}
async function noOverflow () {
  assert.ok(await evaluate('document.documentElement.scrollWidth <= innerWidth'), 'No page horizontal overflow')
  assert.ok(await evaluate(`[...document.querySelectorAll('.slide-over-body, .drawer-scroll, .list-catalog')].filter(el => el.getClientRects().length).every(el => el.scrollWidth <= el.clientWidth + 1)`), 'No panel horizontal overflow')
}

try {
  await send('Page.enable')
  await send('Runtime.enable')
  await send('Fetch.enable', { patterns: [{ urlPattern: '*127.0.0.1:8000/api/*' }] })
  await send('Page.addScriptToEvaluateOnNewDocument', { source: 'localStorage.clear(); localStorage.setItem(\'easy-rent-locale\', \'en\');' })
  await resize(1440, 1000)
  await send('Page.navigate', { url: `${base}/map` })
  await waitFor('document.querySelectorAll(".point-row").length === 5 && document.querySelectorAll(".marker-point").length === 7', 'Map API data loaded')
  assert.equal(await evaluate('Math.round(document.querySelector(".map-workspace").getBoundingClientRect().width)'), 380)
  assert.ok(await evaluate('!document.querySelector(".quick-filters, .map-hud, .map-hud-toggle, .map-toolbar")'), 'Map controls stay in the sidebar without quick filters or HUD')
  assert.ok(await evaluate('!!document.querySelector(".map-workspace .workspace-sort")'), 'Map sorting lives in the sidebar')
  await click('.map-workspace .header-close-btn')
  assert.ok(await evaluate('document.querySelector(".map-menu-btn").getBoundingClientRect().left < 50'), 'Filter trigger stays on the left of the map')
  await click('.map-menu-btn')
  await click('.map-workspace .filter-expansions .v-expansion-panel-title')
  await click('.map-workspace .v-switch input')
  assert.equal(await evaluate('document.querySelector(".workspace-stats > div:nth-child(2) strong").textContent'), '10', 'Live availability filter')
  await click('.map-workspace .v-switch input')
  await input('.map-workspace .price-inputs .v-input:nth-child(2) input', '50')
  assert.equal(await evaluate('document.querySelector(".workspace-stats > div:nth-child(2) strong").textContent'), '7', 'Live price filter')
  await input('.map-workspace .price-inputs .v-input:nth-child(2) input', '')
  await click('.map-workspace .filter-expansions .v-expansion-panel-title')
  await click('.rental-points > .v-btn')
  assert.equal(await evaluate('document.querySelectorAll(".point-row").length'), 7)
  await click('.point-row')
  await waitFor('!!document.querySelector(".marker-point.is-selected")')
  await waitFor('document.querySelector(".workspace-stats > div:first-child strong").textContent === "3"', 'Zooming into Riga shows its three providers')
  assert.equal(await evaluate('document.querySelectorAll(".point-row").length'), 3)
  assert.equal(await evaluate('document.querySelectorAll(".marker-point").length'), 7, 'Other markers remain available when panning')
  await panMap()
  await panMap()
  await waitFor('document.querySelector(".workspace-stats > div:first-child strong").textContent === "0"', 'Panning to an empty area clears the sidebar')
  assert.ok(await evaluate('document.querySelector(".points-section").textContent.includes("No vehicles in this area")'))
  assert.ok(await evaluate('!document.querySelector(".panel-catalog")'), 'Selected provider outside the area does not leak into the sidebar')
  await screenshot('empty-map-area')
  await click('.points-section .dialog-state .v-btn')
  await waitFor('document.querySelector(".workspace-stats > div:first-child strong").textContent === "7"', 'Show all restores every located provider')
  await click('.point-row')
  await screenshot('desktop-map')
  await click('.desktop-view-switch button:nth-child(2)')
  assert.equal(await evaluate('document.querySelectorAll(".list-catalog .rental-vehicle").length'), 6)
  await click('.catalog-heading > .v-btn')
  assert.equal(await evaluate('document.querySelectorAll(".list-catalog .rental-vehicle").length'), 12)
  const columns = await evaluate('getComputedStyle(document.querySelector(".list-catalog .vehicle-grid")).gridTemplateColumns.split(" ").length')
  assert.equal(columns, 4)
  await screenshot('desktop-list')
  await click('.toolbar-filter')
  await waitFor('!!document.querySelector(".rental-slide-over.v-overlay--active")')
  assert.equal(await evaluate('Math.round(document.querySelector(".rental-slide-over .v-overlay__content").getBoundingClientRect().width)'), 420)
  await input('.rental-slide-over input', 'Honda')
  assert.equal(await evaluate('document.querySelectorAll(".list-catalog .rental-vehicle").length'), 12, 'Draft is not applied')
  assert.ok(await evaluate('document.querySelector(".slide-over-footer").textContent.includes("Show 1 results")'))
  await screenshot('desktop-filters')
  await key('Escape')
  assert.equal(await evaluate('document.querySelectorAll(".list-catalog .rental-vehicle").length'), 12)
  assert.ok(await evaluate('document.activeElement.closest(".toolbar-filter") !== null'), 'Focus returns to filter trigger')
  await click('.toolbar-filter')
  assert.equal(await evaluate('document.querySelector(".rental-slide-over input").value'), '')
  await input('.rental-slide-over input', 'Honda')
  await send('Input.dispatchMouseEvent', { type: 'mousePressed', x: 20, y: 300, button: 'left', clickCount: 1 })
  await send('Input.dispatchMouseEvent', { type: 'mouseReleased', x: 20, y: 300, button: 'left', clickCount: 1 })
  await waitFor('!document.querySelector(".rental-slide-over.v-overlay--active")', 'Outside click cancels')
  await click('.toolbar-filter')
  await input('.rental-slide-over input', 'Honda')
  await click('.slide-over-footer .v-btn:last-child')
  assert.equal(await evaluate('document.querySelectorAll(".list-catalog .rental-vehicle").length'), 1)
  await click('.desktop-view-switch button:first-child')
  assert.equal(await evaluate('document.querySelector(".map-workspace input").value'), 'Honda')
  await click('.desktop-view-switch button:nth-child(2)')
  await click('.toolbar-filter')
  await click('.slide-over-footer .v-btn:first-child')
  assert.equal(await evaluate('document.querySelectorAll(".list-catalog .rental-vehicle").length'), 1, 'Draft reset stays local')
  await click('.slide-over-footer .v-btn:last-child')
  await click('.toolbar-filter')
  await input('.rental-slide-over input', 'No matching vehicle')
  await click('.slide-over-footer .v-btn:last-child')
  assert.ok(await evaluate('document.querySelector(".list-catalog .dialog-state").textContent.includes("No results")'))
  await click('.list-catalog .dialog-state .v-btn')
  await click('.list-catalog .rental-vehicle .rental-vehicle__actions .v-btn:first-child')
  assert.ok(await evaluate('!!document.querySelector(".v-dialog.v-overlay--active .rental-vehicle")'), 'Details open')
  await key('Escape')
  await noOverflow()
  for (const width of [1024, 768, 390, 320]) {
    await resize(width, 844)
    await noOverflow()
    const count = await evaluate('getComputedStyle(document.querySelector(".list-catalog .vehicle-grid")).gridTemplateColumns.split(" ").length')
    assert.equal(count, width >= 768 ? (width === 1024 ? 3 : 2) : 1)
  }
  await resize(390, 844)
  await screenshot('mobile-list')
  await click('.toolbar-filter')
  assert.equal(await evaluate('Math.round(document.querySelector(".rental-slide-over .v-overlay__content").getBoundingClientRect().width)'), 390)
  await noOverflow()
  await screenshot('mobile-filters')
  await input('.rental-slide-over input', 'Toyota')
  await resize(390, 500)
  assert.ok(await evaluate('document.querySelector(".slide-over-footer").getBoundingClientRect().bottom <= innerHeight + 1'), 'Footer stays visible with reduced viewport height')
  await noOverflow()
  await resize(390, 844)
  await evaluate('document.querySelector(".slide-over-header .v-btn").focus()')
  await key('Tab', { modifiers: 8 })
  assert.ok(await evaluate('!!document.activeElement.closest(".slide-over-card")'), 'Focus stays in modal')
  await key('Escape')
  await click('.mobile-view-switch button:first-child')
  await click('.map-menu-btn')
  await noOverflow()
  await screenshot('mobile-map-sheet')
  await key('Escape')
  await resize(1440, 1000)
  await click('.desktop-view-switch button:nth-child(2)')
  await evaluate('window.dispatchEvent(new CustomEvent(\'user-updated\', { detail: { persona_id: 1, loma: \'pakalpojumu_sniedzejs\', pakalpojumu_sniedzejs: { sniedzejs_id: 1 }, sniedzejs: { sniedzejs_id: 1 } } }))')
  await waitFor('!!document.querySelector(".toolbar-add")')
  await click('.toolbar-add')
  assert.equal(await evaluate('document.querySelectorAll(".provider-panel form").length'), 2, 'Both existing provider forms remain')
  assert.equal(await evaluate('document.querySelectorAll(".provider-panel .v-select").length'), 5, 'Type management and all vehicle selects remain')
  await screenshot('provider-panel')
  await key('Escape')
  await click('.list-catalog .rental-vehicle:first-child .rental-vehicle__actions .v-btn:last-child')
  assert.ok(await evaluate('document.querySelector(".v-dialog.v-overlay--active").textContent.includes("Edit vehicle")'), 'Owner edit action')
  assert.equal(await evaluate('document.querySelectorAll(".v-dialog.v-overlay--active .v-select").length'), 4, 'Edit type, gearbox, fuel, and status fields')
  await click('.v-dialog.v-overlay--active .v-card-actions .v-btn')
  assert.ok(await evaluate('[...document.querySelectorAll(".v-dialog.v-overlay--active")].some(el => el.textContent.includes("Delete vehicle"))'), 'Delete confirmation remains available')
  await click('.v-dialog.v-overlay--active .v-card-actions .v-btn')
  await key('Escape')
  await evaluate('window.dispatchEvent(new CustomEvent(\'user-updated\', { detail: { persona_id: 2, loma: \'klients\', klients: { klients_id: 2 } } }))')
  await click('.list-catalog .rental-vehicle:first-child .rental-vehicle__actions .v-btn:last-child')
  assert.ok(await evaluate('document.querySelector(".v-dialog.v-overlay--active").textContent.includes("Reservation")'), 'Client reservation action')
  assert.equal(await evaluate('document.querySelectorAll(".reservation-period-fields input").length'), 4, 'Date and time fields retained')
  await key('Escape')
  await click('.list-catalog .rental-vehicle:first-child .rental-vehicle__rating')
  await waitFor('!!document.querySelector(".v-dialog.v-overlay--active .v-rating")', 'Reviews open')
  await key('Escape')
  await evaluate('window.dispatchEvent(new CustomEvent(\'user-updated\', { detail: null }))')
  assert.ok(await evaluate('!document.querySelector(".toolbar-add")'), 'Provider action hidden from guest')
  await click('.utility-controls .theme-toggle')
  assert.equal(await evaluate('document.documentElement.dataset.theme'), 'dark')
  await screenshot('desktop-dark')
  for (const locale of ['ru', 'lv', 'en']) {
    await evaluate(`document.querySelectorAll('.language-switch button')[${['lv', 'ru', 'en'].indexOf(locale)}].click()`)
    await delay(200)
    await click('.toolbar-filter')
    assert.ok(await evaluate('!document.querySelector(".slide-over-card").textContent.includes("???")'))
    await noOverflow()
    await screenshot(`filters-${locale}-dark`)
    await key('Escape')
  }
  await send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] })
  await click('.toolbar-filter')
  assert.ok(await evaluate('matchMedia("(prefers-reduced-motion: reduce)").matches'))
  await key('Escape')
  assert.equal(failures.length, 0, JSON.stringify(failures))
  assert.equal(writes.length, 0, 'Read-only UI checks do not perform backend mutations')
  console.log('PASS: map markers/selection, 4/3/2/1-column grids, draft/apply/reset/Escape/outside click, focus, view persistence, details, provider forms/permissions, mobile sheets, LV/RU/EN, light/dark, no overflow or runtime exceptions.')
  console.log(`Screenshots: ${artifacts}`)
} finally {
  socket.close()
  await fetch(`${endpoint}/json/close/${target.id}`).catch(() => {})
  if (browser) {
    browser.kill()
  }
}
