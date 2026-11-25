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
const documentsFromServiceWorker = new Map()
const signIn = { scenario: null, persona: null, pending: null, selectedRole: null, language: null, lastProfile: null }
const deletion = { blockers: 0, conflict: false, google: false, confirmed: false, checks: 0 }
const recovery = { requestLimit: false, invalidLink: false }
const verification = { resendStatus: 202, confirmCode: 'invalid_verification_link', active: false, autoPersona: null, resetRequired: false }
const googleIdentity = { vards: 'Anna', uzvards: 'Ozola', epasts: 'anna@example.com' }
const providerRole = 'pakalpojumu_sniedzejs'
const adminQueries = []
const adminUser = { persona_id: 18, vards: 'QA', uzvards: 'User', epasts: 'qa@example.test',
  loma: 'klients', verified_at: '2026-11-01T12:00:00Z', created_at: '2026-11-01T12:00:00Z', active_restrictions: 0 }
function signedInPersona (role, completed = false) {
  return {
    ...googleIdentity,
    loma: role,
    persona_id: 99,
    profile_completed: completed,
    profile_missing_fields: ['kontakttalrunis', 'bankas_konts', ...(role === 'klients'
      ? ['lietotajvards']
      : ['registracijas_numurs', 'iela', 'majas_numurs', 'pilseta', 'pasta_indekss'])],
    ...(role === 'klients' ? { klients: { klients_id: 99 } } : { pakalpojumu_sniedzejs: { sniedzejs_id: 99 } }),
  }
}

function verificationResponse (postData) {
  const { interactive, auto_login: autoLogin } = JSON.parse(postData)
  const code = verification.confirmCode === 'verification_interaction_required' && interactive ? 'email_verified' : verification.confirmCode
  const responseCode = {
    email_verified: 200,
    verification_interaction_required: 409,
    verification_link_expired: 410,
    invalid_verification_link: 422,
    verification_unavailable: 503,
  }[code]

  verification.active = responseCode === 200
  if (!verification.active) {
    return { responseCode, result: { code } }
  }
  if (autoLogin && verification.autoPersona) {
    signIn.persona = verification.autoPersona
    return { responseCode, result: { code, token: 'activation-auto-token', persona: signIn.persona } }
  }
  return { responseCode, result: { code, password_reset_required: verification.resetRequired, active_session: Boolean(signIn.persona) } }
}

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
  } else if (message.method === 'Network.responseReceived' && message.params.type === 'Document') {
    const { url, fromServiceWorker } = message.params.response
    documentsFromServiceWorker.set(url, fromServiceWorker)
  } else if (message.method === 'Fetch.requestPaused') {
    const { requestId, request } = message.params
    try {
      const url = new URL(request.url)
      let result = {}
      let responseCode = 200
      const responseHeaders = [{ name: 'Content-Type', value: 'application/json' }]
      if (request.method !== 'GET' && request.method !== 'OPTIONS') {
        const headers = new Headers(request.headers)
        writes.push({
          method: request.method,
          path: url.pathname,
          body: request.postData,
          language: headers.get('accept-language'),
          authorization: headers.get('authorization'),
        })
      }
      if (url.pathname === '/api/auth/me') {
        result = { persona: signIn.persona, google_pending: signIn.pending }
        responseCode = signIn.persona || signIn.pending ? 200 : 401
      } else if (url.pathname === '/api/auth/register') {
        const { email } = JSON.parse(request.postData)
        responseCode = 201
        result = { code: 'verification_required', email, mail_status: 'sent', retry_after: 60 }
      } else if (url.pathname === '/api/auth/login') {
        if (signIn.scenario === 'admin-login') {
          signIn.persona = signedInPersona('administrators', true)
          result = { token: 'admin-token', persona: signIn.persona }
        } else if (signIn.scenario === 'blocked-login') {
          responseCode = 403
          result = { code: 'account_blocked', reason: 'QA account hold', ends_at: null }
        } else if (verification.active) {
          signIn.persona = signedInPersona('klients', true)
          result = { token: 'verified-token', persona: signIn.persona }
        } else {
          responseCode = 403
          result = { code: 'email_not_verified' }
        }
      } else if (url.pathname === '/api/admin/dashboard') {
        const days = Number(url.searchParams.get('days') || 30)
        result = {
          users: 8, roles: { klients: 5, pakalpojumu_sniedzejs: 2 }, new_registrations: 3, vehicles: 9, active_vehicles: 7,
          hidden_vehicles: 2, bookings: { apmaksata: 4, neapmaksata: 2 }, confirmed_payment_volume: '140.00',
          active_restrictions: 1, open_reports: 2, recent_bookings: [], recent_actions: [],
          series: Array.from({ length: days }, (_, day) => ({
            date: new Date(Date.UTC(2026, 10, 25 - days + day)).toISOString().slice(0, 10),
            registrations: day % 4, paid_bookings: day % 3,
          })),
        }
      } else if (url.pathname === '/api/admin/users') {
        adminQueries.push(Object.fromEntries(url.searchParams))
        result = { last_page: 2, data: url.searchParams.get('page') === '2' ? [] : [adminUser] }
      } else if (url.pathname === '/api/admin/users/18') {
        result = { user: adminUser, bookings: 0, vehicles: 0, reviews: 0, reports: 0, audit: 0, restrictions: { data: [], last_page: 1, current_page: 1 } }
      } else if (url.pathname === '/api/admin/users/18/restrictions') {
        responseCode = 201
        result = { ierobezojums_id: 1 }
      } else if (url.pathname.startsWith('/api/admin/')) {
        result = { last_page: 1, data: [] }
      } else if (url.pathname === '/api/auth/verification/resend') {
        responseCode = verification.resendStatus
        result = {
          code: { 200: 'verification_mail_sent', 202: 'verification_requested', 503: 'mail_unavailable' }[responseCode],
          retry_after: 60,
        }
        if (responseCode === 503) {
          responseHeaders.push({ name: 'Retry-After', value: '1' })
        }
      } else if (url.pathname === '/api/auth/verification/confirm') {
        ({ result, responseCode } = verificationResponse(request.postData))
      } else if (url.pathname === '/api/auth/forgot-password') {
        await delay(800)
        responseCode = recovery.requestLimit ? 429 : 200
      } else if (url.pathname === '/api/auth/reset-password') {
        if (recovery.invalidLink) {
          responseCode = 422
          result = { code: 'invalid_reset_link' }
        }
      } else if (url.pathname === '/oauth/google/start') {
        signIn.selectedRole = url.searchParams.get('role')
        signIn.language = url.searchParams.get('lang')
        let destination = '/profile?google=ok'
        if (signIn.scenario === 'cancelled') {
          destination = '/auth?google=cancelled'
        } else if (signIn.scenario === 'returning') {
          signIn.persona = signedInPersona(providerRole, true)
        } else if (signIn.selectedRole) {
          signIn.persona = signedInPersona(signIn.selectedRole)
        } else {
          signIn.pending = { ...googleIdentity, purpose: 'role' }
          destination = '/profile?google=role'
        }
        responseCode = 302
        responseHeaders.push({ name: 'Location', value: destination })
      } else if (url.pathname === '/oauth/google/role') {
        signIn.persona = signedInPersona(JSON.parse(request.postData).loma)
        signIn.pending = null
        result = { next: '/profile?google=ok' }
      } else if (url.pathname === '/oauth/google/deletion') {
        deletion.confirmed = true
        result = { authorization_url: '/profile?delete_google=ok' }
      } else if (url.pathname === '/api/profile/deletion-check') {
        deletion.checks++
        result = {
          can_delete: deletion.blockers === 0,
          blocking_reservations_count: deletion.blockers,
          confirmation_method: deletion.google ? 'google' : 'password',
          google_available: deletion.google,
          google_confirmed: deletion.confirmed,
        }
      } else if (url.pathname === '/api/profile/me') {
        if (deletion.conflict) {
          responseCode = 409
          result = { can_delete: false, blocking_reservations_count: 1, reason: 'active_or_upcoming_reservations' }
        } else {
          signIn.persona = null
          responseCode = 204
        }
      } else if (url.pathname === '/api/rezervacijas') {
        result = [{
          rezervacija_id: 1, apmaksas_statuss: 'apmaksata', kopa_summa: '99.98', invoice_available: true,
          sakuma_laiks: '2025-10-01T10:00:00Z', beigu_laiks: '2025-10-03T10:00:00Z',
          transporta_snapshot: { marka: 'Volkswagen', modelis: 'Golf' },
          transportlidzeklis: { marka: 'Audi', modelis: 'A4', sniedzejs: { persona_deleted: true, persona: null } },
          klients: { persona_deleted: true, persona: null },
        }]
      } else if (url.pathname === '/api/profile/99') {
        signIn.lastProfile = JSON.parse(request.postData)
        signIn.persona = { ...signIn.persona, ...signIn.lastProfile, profile_completed: true, profile_missing_fields: [] }
        result = { persona: signIn.persona }
      } else if (url.pathname === '/api/auth/logout') {
        signIn.persona = null
        signIn.pending = null
      } else if (url.hostname === 'nominatim.openstreetmap.org') {
        result = url.searchParams.get('q') === 'Brīvības iela, 10, Rīga, LV-1010, Latvia'
          ? [{ lat: '56.9496', lon: '24.1052' }]
          : []
        responseHeaders.push({ name: 'Access-Control-Allow-Origin', value: '*' })
      } else if (url.pathname === '/sanctum/csrf-cookie') {
        responseCode = 204
      } else if (url.pathname === '/api/transport/veidi') {
        result = types
      } else if (url.pathname === '/api/transport') {
        result = vehicles
      } else if (/^\/api\/transport\/\d+\/photos$/.test(url.pathname)) {
        result = []
      } else if (url.pathname.startsWith('/api/atsauksmes')) {
        result = []
      }
      await send('Fetch.fulfillRequest', { requestId, responseCode, responseHeaders, body: responseCode === 204 ? '' : Buffer.from(JSON.stringify(result)).toString('base64') })
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
async function changeLanguage (locale) {
  const index = ['lv', 'ru', 'en'].indexOf(locale)
  await evaluate(`document.querySelectorAll('.language-switch button')[${index}].click()`)
  await waitFor(`document.documentElement.lang === '${locale}'`)
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
  if (process.env.CATALOG_VERIFY_PWA === '1') await send('Network.enable')
  await send('Fetch.enable', {
    patterns: [`${base}/api/*`, `${base}/oauth/google/*`, `${base}/sanctum/*`, 'https://nominatim.openstreetmap.org/search*']
      .map(urlPattern => ({ urlPattern })),
  })
  await send('Page.addScriptToEvaluateOnNewDocument', { source: "if (!localStorage.getItem('easy-rent-locale')) localStorage.setItem('easy-rent-locale', 'en')" })
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
  assert.equal(await evaluate('document.querySelectorAll(".provider-panel form").length'), 1, 'Providers only see the vehicle form')
  assert.equal(await evaluate('document.querySelectorAll(".provider-panel .v-select").length'), 4, 'Vehicle fields use existing categories')
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
  for (const [locale, type, zoomLabel] of [
    ['ru', 'Легковой автомобиль', 'Приблизить'],
    ['lv', 'Automašīna', 'Tuvināt'],
    ['en', 'Passenger car', 'Zoom in'],
  ]) {
    await changeLanguage(locale)
    await click('.toolbar-filter')
    assert.ok(await evaluate(`document.querySelector('.slide-over-card').textContent.includes(${JSON.stringify(type)})`))
    assert.equal(await evaluate('document.querySelector(".leaflet-control-zoom-in").getAttribute("aria-label")'), zoomLabel)
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

  await resize(390, 844)
  await send('Page.navigate', { url: `${base}/auth` })
  await waitFor('!!document.querySelector(".google-sign-in")')
  assert.match(await evaluate('document.querySelector(".google-sign-in").textContent'), /Continue with Google/)
  await changeLanguage('ru')
  assert.match(await evaluate('document.querySelector(".google-sign-in").textContent'), /Продолжить с Google/)
  await changeLanguage('lv')
  assert.match(await evaluate('document.querySelector(".google-sign-in").textContent'), /Turpināt ar Google/)
  await noOverflow()
  await click('.auth-tabs [role=tab]:nth-child(2)')
  await click('.auth-role-options button:nth-child(2)')
  await click('.google-sign-in')
  await waitFor('location.pathname === "/profile" && !location.search && !!document.querySelector(".profile-details input")', 'Provider lands in profile')
  assert.equal(signIn.selectedRole, providerRole)
  assert.equal(signIn.language, 'lv')
  assert.match(await evaluate('document.querySelector(".profile-name").textContent'), /Anna Ozola/)
  assert.match(await evaluate('document.querySelector(".profile-email").textContent'), /anna@example.com/)
  assert.match(await evaluate('document.querySelector(".profile-content .v-alert").textContent'), /Pabeidziet sava profila izveidi/)
  assert.ok(await evaluate(`!document.querySelector('.nav-link[href="/map"]') && !document.querySelector('.profile-reservations')`))
  assert.ok(await evaluate('!document.querySelector("#profile-deletion-title")'))
  assert.equal(deletion.checks, 0, 'Registration does not check eligibility for account deletion')
  await changeLanguage('ru')
  assert.match(await evaluate('document.querySelector(".profile-setup-notice").textContent'), /Завершите настройку профиля/)
  await changeLanguage('en')
  assert.match(await evaluate('document.querySelector(".profile-setup-notice").textContent'), /Finish setting up your profile/)
  await changeLanguage('lv')
  assert.ok(await evaluate(`!document.querySelector('.profile-details input[placeholder="56.9496"], .profile-details input[placeholder="24.1052"]')`), 'Google onboarding hides manual coordinates')
  await noOverflow()
  await screenshot('google-provider-profile-mobile')
  await send('Page.navigate', { url: `${base}/map` })
  await waitFor('location.pathname === "/profile" && !!document.querySelector(".profile-details input")', 'Direct map URL returns to profile')
  await click('button[type=submit]')
  await waitFor('document.querySelector(".profile-details").textContent.includes("Šis lauks ir obligāts.")', 'Field validation is visible')
  assert.equal(signIn.lastProfile, null)

  for (const [label, value] of [
    ['Kontakttālrunis *', '+371 20000000'],
    ['IBAN *', 'LV80BANK0000435195001'],
    ['Reģistrācijas numurs *', 'REG-99'],
    ['Iela *', 'Brīvības iela'],
    ['Mājas numurs *', '10'],
    ['Pilsēta *', 'Rīga'],
    ['Pasta indekss *', 'LV-1010'],
  ]) {
    const fieldId = await evaluate(`(() => {
      const fields = [...document.querySelectorAll('.profile-details .v-input')];
      const field = fields.find(item => item.querySelector('label')?.textContent.trim() === ${JSON.stringify(label)});
      return field?.querySelector('input')?.id;
    })()`)
    assert.ok(fieldId, `Missing profile field ${label}`)
    await input(`input[id="${fieldId}"]`, value)
  }
  await click('button[type=submit]')
  await waitFor('location.pathname === "/map" && !!document.querySelector(".map-workspace")', 'Provider can open map after completion')
  assert.equal(signIn.lastProfile.iela, 'Brīvības iela')
  assert.equal(signIn.lastProfile.latitude, 56.9496, 'Latitude was geocoded from the saved address')
  assert.equal(signIn.lastProfile.longitude, 24.1052, 'Longitude was geocoded from the saved address')
  await waitFor('document.body.innerText.includes("Profils veiksmīgi aizpildīts!")', 'Completion notification is visible')

  signIn.persona = null
  await send('Page.navigate', { url: `${base}/auth` })
  await waitFor('!!document.querySelector(".google-sign-in")')
  await click('.google-sign-in')
  await waitFor('location.pathname === "/profile" && !!document.querySelector(".profile-details input[type=radio]")', 'Unknown role is chosen inside the profile')
  assert.equal(signIn.selectedRole, null)
  await click('.profile-details input[value="klients"]')
  await click('.profile-details button[type=submit]')
  await waitFor('location.pathname === "/profile" && !!document.querySelector(".profile-details input:not([type=radio])") && !document.querySelector(".profile-details input[type=radio]")', 'Client profile loads after selection')
  assert.match(await evaluate('document.querySelector(".profile-role").textContent'), /Klients/)
  assert.ok(await evaluate('!document.querySelector("#profile-deletion-title")'))
  for (const [width, height] of [[360, 800], [768, 1024], [1440, 1000]]) {
    await resize(width, height)
    await noOverflow()
  }
  await click('.profile-hero__logout')
  await waitFor('location.pathname === "/" && !localStorage.getItem("user")', 'Logout works before completion')

  await send('Page.navigate', { url: `${base}/auth` })
  await waitFor('!!document.querySelector(".google-sign-in")')
  signIn.scenario = 'returning'
  await click('.google-sign-in')
  await waitFor('location.pathname === "/map" && !!document.querySelector(".map-workspace")', 'Completed provider goes straight to the map')
  assert.ok(await evaluate('!document.querySelector(".profile-content")'))

  signIn.persona = null
  signIn.scenario = 'cancelled'
  await send('Page.navigate', { url: `${base}/auth` })
  await waitFor('!!document.querySelector(".google-sign-in")')
  await click('.google-sign-in')
  await waitFor('document.querySelector(".auth-feedback")?.textContent.includes("Google autorizācija tika atcelta")')
  assert.equal(failures.length, 0, JSON.stringify(failures))
  console.log('PASS: Google entry, role selection, profile guard, required-field feedback, provider completion, logout, returning account, cancellation and responsive profile.')

  const deletionSection = 'section[aria-labelledby="profile-deletion-title"]'
  const deletionDialog = '.v-overlay--active .profile-confirmation'
  const deleteSubmit = `${deletionDialog} button[type="submit"]`
  await resize(360, 800)
  signIn.persona = signedInPersona(providerRole, true)
  deletion.blockers = 2
  await send('Page.navigate', { url: `${base}/profile` })
  await waitFor(`document.querySelector('${deletionSection}')?.textContent.includes('rezervāciju skaits: 2')`)
  assert.ok(await evaluate(`document.querySelector('${deletionSection} button').disabled`))
  assert.ok(await evaluate(`document.querySelector('${deletionSection} a').hash === '#profile-reservations'`))
  await waitFor('document.querySelector(".reservation-card-history")?.textContent.includes("Dzēsts lietotājs")')

  signIn.persona = signedInPersona('klients', true)
  deletion.blockers = 0
  await send('Page.navigate', { url: `${base}/profile?qa=client` })
  await waitFor('document.querySelector(".reservation-card-history")?.textContent.includes("Dzēsts pakalpojumu sniedzējs")')
  const history = await evaluate('document.querySelector(".reservation-card-history").textContent')
  assert.match(history, /Volkswagen Golf/)
  assert.match(history, /Lejupielādēt rēķinu/)
  await click(`${deletionSection} button`)
  await waitFor(`!!document.querySelector('${deleteSubmit}')`)
  assert.ok(await evaluate(`document.querySelector('${deleteSubmit}').disabled`))
  await changeLanguage('en')
  assert.match(await evaluate(`document.querySelector('${deletionDialog}').textContent`), /Enter DELETE/)
  assert.match(await evaluate('document.querySelector(".reservation-card-history").textContent'), /Deleted provider/)
  await changeLanguage('lv')
  await input(`${deletionDialog} input:not([type=password])`, 'DZĒST')
  await input(`${deletionDialog} input[type=password]`, 'Current123!')
  await noOverflow()
  deletion.conflict = true
  await click(deleteSubmit)
  await waitFor(`document.querySelector('${deletionSection}')?.textContent.includes('rezervāciju skaits: 1') && !document.querySelector('${deleteSubmit}')`)
  assert.deepEqual(JSON.parse(writes.findLast(write => write.path === '/api/profile/me').body), {
    confirmation: 'DZĒST', password: 'Current123!',
  })
  deletion.conflict = false
  await click(`${deletionSection} button:last-of-type`)
  await click(`${deletionSection} button`)
  await input(`${deletionDialog} input:not([type=password])`, 'DZĒST')
  await input(`${deletionDialog} input[type=password]`, 'Current123!')
  await click(deleteSubmit)
  await waitFor('location.pathname === "/" && localStorage.length === 0 && document.body.innerText.includes("Jūsu konts ir veiksmīgi dzēsts.")')

  deletion.google = true
  signIn.persona = signedInPersona('klients', true)
  await changeLanguage('ru')
  await send('Page.navigate', { url: `${base}/profile?qa=google-deletion` })
  await waitFor(`!!document.querySelector('${deletionSection} button')`)
  assert.match(await evaluate(`document.querySelector('${deletionSection}').textContent`), /Удаление аккаунта/)
  await click(`${deletionSection} button`)
  await waitFor(`!!document.querySelector('${deleteSubmit}')`)
  assert.ok(await evaluate(`!document.querySelector('${deletionDialog} input[type=password]')`))
  await click(`${deletionDialog} .v-card-text button`)
  await waitFor(`!location.search && document.querySelector('${deletionDialog}')?.textContent.includes('Личность подтверждена через Google')`)
  await input(`${deletionDialog} input`, 'УДАЛИТЬ')
  await click(deleteSubmit)
  await waitFor('location.pathname === "/" && localStorage.length === 0 && document.body.innerText.includes("Ваш аккаунт успешно удалён.")')
  const googleDeletion = writes.findLast(write => write.path === '/api/profile/me')
  assert.deepEqual(JSON.parse(googleDeletion.body), { confirmation: 'DZĒST' })
  assert.equal(googleDeletion.language, 'ru')
  assert.equal(failures.length, 0, JSON.stringify(failures))
  console.log('PASS: account deletion blockers, late conflict, archived booking display, password and Google deletion after profile completion.')

  await resize(1440, 1000)
  await send('Page.navigate', { url: `${base}/auth` })
  await waitFor('!!document.querySelector(".auth-recovery-link")')
  for (const [locale, label] of [['lv', 'Aizmirsāt paroli?'], ['ru', 'Забыли пароль?'], ['en', 'Forgot password?']]) {
    await changeLanguage(locale)
    assert.equal((await evaluate('document.querySelector(".auth-recovery-link").textContent')).trim(), label)
  }
  await click('.auth-recovery-link')
  await waitFor('location.pathname === "/forgot-password" && document.querySelector("#recovery-title")?.textContent === "Forgot password?"')
  await changeLanguage('ru')
  await waitFor('document.querySelector("#recovery-title")?.textContent === "Забыли пароль?"')
  await resize(360, 800)
  await noOverflow()
  const recoveryEmail = '.recovery-card input[type=email]'
  const recoverySubmit = '.recovery-card button[type=submit]'
  await input(recoveryEmail, 'invalid')
  await click(recoverySubmit)
  assert.match(await evaluate('document.querySelector(".recovery-card .v-messages").textContent'), /почт/i)
  assert.ok(!writes.some(write => write.path === '/api/auth/forgot-password'))

  const email = 'renter+test@example.com'
  recovery.requestLimit = true
  await evaluate('localStorage.setItem("token", "stale-token")')
  await input(recoveryEmail, email)
  await click(recoverySubmit)
  await waitFor('document.querySelector(".recovery-card button[type=submit]")?.classList.contains("v-btn--loading")')
  await waitFor('document.querySelector(".recovery-card .v-alert")?.textContent.includes("Слишком много")')
  recovery.requestLimit = false
  await click(recoverySubmit)
  await waitFor('document.querySelector(".recovery-card [role=status]")?.textContent.includes("отправим инструкцию")')
  const requestLink = writes.findLast(write => write.path === '/api/auth/forgot-password')
  assert.deepEqual(JSON.parse(requestLink.body), { email, locale: 'ru' })
  assert.equal(requestLink.language, 'ru')
  assert.equal(requestLink.authorization, null)
  await screenshot('forgot-password-mobile')

  const token = 'a'.repeat(64)
  const resetUrl = `${base}/reset-password#${new URLSearchParams({ token, email, lang: 'lv' })}`
  const password = 'Replacement123!'
  const newPassword = '.recovery-card input[type=password]:nth-of-type(1)'
  const fillPasswords = () => evaluate(`(() => {
    const fields = [...document.querySelectorAll(".recovery-card input[type=password]")];
    fields.forEach(el => {
      el.value = ${JSON.stringify(password)};
      el.dispatchEvent(new Event("input", { bubbles: true }));
    });
  })()`)
  await send('Page.navigate', { url: resetUrl })
  await waitFor('!location.hash && document.querySelector("#recovery-title")?.textContent === "Paroles atjaunošana"')
  assert.equal(await evaluate(`document.querySelector('${recoveryEmail}').value`), email)
  await resize(320, 700)
  await noOverflow()
  await resize(1440, 1000)
  await changeLanguage('en')
  await waitFor('document.querySelector("#recovery-title")?.textContent === "Reset your password"')
  await resize(360, 800)
  await input(newPassword, 'LOWERLESS!')
  await click(recoverySubmit)
  assert.match(await evaluate('document.querySelector(".recovery-card").innerText'), /one uppercase and one lowercase/)
  await input(newPassword, password)
  await click(recoverySubmit)
  assert.match(await evaluate('document.querySelector(".recovery-card").innerText'), /Passwords do not match/)
  assert.ok(!writes.some(write => write.path === '/api/auth/reset-password'))
  await screenshot('reset-password-mobile')

  recovery.invalidLink = true
  await fillPasswords()
  await click(recoverySubmit)
  await waitFor('document.querySelector(".recovery-card .v-alert")?.textContent.includes("invalid or has expired")')
  await click('.recovery-card a[href="/forgot-password"]')
  await waitFor('location.pathname === "/forgot-password"')
  recovery.invalidLink = false
  await send('Page.navigate', { url: resetUrl })
  await waitFor('!location.hash && !!document.querySelector(".recovery-card input[type=password]")')
  await fillPasswords()
  await click(recoverySubmit)
  await waitFor('document.querySelector(".recovery-card [role=status]")?.textContent.includes("Parole ir nomainīta") && !localStorage.getItem("token")')
  const reset = writes.findLast(write => write.path === '/api/auth/reset-password')
  assert.deepEqual(JSON.parse(reset.body), { token, email, password, password_confirmation: password })
  assert.equal(reset.authorization, null)
  await click('.recovery-card a[href="/auth"]')
  await waitFor('location.pathname === "/auth" && !!document.querySelector(".auth-recovery-link")')
  assert.equal(failures.length, 0, JSON.stringify(failures))
  console.log('PASS: password recovery in LV/RU/EN, mobile forms, validation, loading, rate limit, expired link and sign-in navigation.')

  const activationEmail = 'activate+qa@example.com'
  const activationPassword = 'Activation123!'
  const verificationText = phrase => `document.querySelector(".verification-card")?.innerText.includes(${JSON.stringify(phrase)})`
  const verificationLink = locale => `${base}/verify-email#${new URLSearchParams({ id: '42', token, lang: locale })}`
  const followVerificationLink = locale => evaluate(`location.hash = ${JSON.stringify(new URL(verificationLink(locale)).hash)}`)
  const confirmationWrites = () => writes.filter(write => write.path === '/api/auth/verification/confirm')
  await changeLanguage('ru')
  await click('.auth-tabs [role=tab]:nth-child(2)')
  await waitFor('document.querySelectorAll(".register-grid input").length === 7')
  const registrationValues = ['Activation QA', activationEmail, '+37120000000', 'activateqa', 'LV80BANK0000435195001', activationPassword, activationPassword]
  await evaluate(`[...document.querySelectorAll(".register-grid input")].forEach((el, i) => {
    el.value = ${JSON.stringify(registrationValues)}[i];
    el.dispatchEvent(new Event("input", { bubbles: true }));
  })`)
  await click('.register-grid button[type=submit]')
  await waitFor(`location.pathname === "/verify-email" && ${verificationText('Почтовый сервис принял письмо подтверждения')}`)
  assert.equal(await evaluate('document.querySelector(".verification-card input").value'), activationEmail)
  assert.ok(await evaluate('document.querySelector(".verification-card button[type=submit]").disabled'))
  assert.equal(await evaluate('location.search'), '')
  assert.ok(await evaluate('!localStorage.getItem("token") && !localStorage.getItem("user")'))
  assert.equal(writes.findLast(write => write.path === '/api/auth/register').language, 'ru')
  for (const width of [320, 768, 1440]) {
    await resize(width, 800)
    await noOverflow()
  }
  await resize(360, 800)
  await screenshot('email-verification-mobile')

  await click('.verification-card a[href="/auth"]')
  await waitFor('location.pathname === "/auth" && !!document.querySelector(".auth-recovery-link")')
  await input('.auth-form-card input[autocomplete=username]', activationEmail)
  await input('.auth-form-card input[autocomplete=current-password]', activationPassword)
  await click('.auth-form-card button[type=submit]')
  await waitFor(`location.pathname === "/verify-email" && ${verificationText('Для входа откройте ссылку')}`)
  await changeLanguage('en')
  await waitFor(verificationText('To sign in, open the verification link'))
  const resendButton = '.verification-card button[type=submit]'
  verification.resendStatus = 503
  await click(resendButton)
  await waitFor(verificationText('The mail service couldn’t send a new verification email'))
  await waitFor('!document.querySelector(".verification-card button[type=submit]").disabled')
  verification.resendStatus = 200
  await click(resendButton)
  await waitFor(verificationText('The mail service accepted the new verification email'))
  const verificationResend = writes.findLast(write => write.path === '/api/auth/verification/resend')
  assert.deepEqual(JSON.parse(verificationResend.body), { email: activationEmail, locale: 'en' })
  assert.equal(verificationResend.authorization, null)
  assert.ok(await evaluate('document.querySelector(".verification-card button[type=submit]").disabled'))

  verification.confirmCode = 'verification_link_expired'
  await followVerificationLink('lv')
  await waitFor(`!location.hash && ${verificationText('Saites derīguma termiņš ir beidzies')}`)
  assert.ok(await evaluate('!!document.querySelector(".verification-card button[type=submit]")'))

  verification.confirmCode = 'invalid_verification_link'
  await followVerificationLink('ru')
  await waitFor(verificationText('Ссылка недействительна или уже использована'))

  verification.confirmCode = 'verification_unavailable'
  await send('Page.navigate', { url: `${base}/auth?verificationqa=mail` })
  await waitFor('location.pathname === "/auth" && !!document.querySelector(".auth-recovery-link")')
  await send('Page.navigate', { url: verificationLink('en') })
  await waitFor(verificationText('We couldn’t verify the link'))
  verification.confirmCode = 'email_verified'
  await click('.verification-card button[type=button]')
  await waitFor(`!location.hash && ${verificationText('Your email address is verified')}`)
  const verificationConfirm = confirmationWrites().at(-1)
  assert.deepEqual(JSON.parse(verificationConfirm.body), { id: '42', token, interactive: false, auto_login: true })
  assert.equal(verificationConfirm.authorization, null)
  assert.ok(await evaluate('!localStorage.getItem("token") && !document.querySelector(".verification-card form")'))
  await click('.verification-card a[href="/auth"]')
  await waitFor('location.pathname === "/auth" && !!document.querySelector(".auth-recovery-link")')
  await input('.auth-form-card input[autocomplete=username]', activationEmail)
  await input('.auth-form-card input[autocomplete=current-password]', activationPassword)
  await click('.auth-form-card button[type=submit]')
  await waitFor('location.pathname === "/" && localStorage.getItem("token") === "verified-token"')

  verification.confirmCode = 'verification_interaction_required'
  let confirmationCount = confirmationWrites().length
  await send('Page.navigate', { url: verificationLink('ru') })
  await waitFor(`!location.hash && ${verificationText('Если вы запросили эту ссылку')}`)
  assert.equal(confirmationWrites().length, confirmationCount + 1)
  const activeConfirmation = confirmationWrites().at(-1)
  assert.equal(activeConfirmation.authorization, 'Bearer verified-token')
  assert.equal(JSON.parse(activeConfirmation.body).auto_login, false)
  await click('.verification-card button[type=button]')
  await waitFor(verificationText('Вы всё ещё в текущем аккаунте'))
  assert.equal(await evaluate('localStorage.getItem("token")'), 'verified-token')
  assert.equal(confirmationWrites().length, confirmationCount + 2)

  signIn.persona = null
  await evaluate('localStorage.removeItem("user"); localStorage.removeItem("token")')
  verification.resetRequired = true
  await followVerificationLink('lv')
  await waitFor(verificationText('Ja pieprasījāt šo saiti'))
  await click('.verification-card button[type=button]')
  await waitFor(verificationText('Lai pierakstītos šajā ierīcē, iestatiet jaunu paroli'))
  assert.ok(await evaluate('!!document.querySelector(".verification-card a[href=\'/forgot-password\']")'))
  assert.equal(await evaluate('localStorage.getItem("token")'), null)

  verification.confirmCode = 'email_verified'
  verification.autoPersona = signedInPersona(providerRole, true)
  confirmationCount = confirmationWrites().length
  await evaluate('Object.defineProperty(document, "visibilityState", { configurable: true, value: "hidden" })')
  await followVerificationLink('en')
  await waitFor(`!location.hash && ${verificationText('Checking your verification link')}`)
  assert.equal(confirmationWrites().length, confirmationCount)
  await evaluate('delete document.visibilityState; document.dispatchEvent(new Event("visibilitychange"))')
  await waitFor(`!location.hash && ${verificationText('Your email address is verified')}`)
  assert.ok(await evaluate('!document.querySelector(".verification-card a[href=\'/auth\']")'))
  await waitFor('location.pathname === "/map" && localStorage.getItem("token") === "activation-auto-token"')
  assert.equal(await evaluate('JSON.parse(localStorage.getItem("user")).loma'), providerRole)
  assert.equal(confirmationWrites().length, confirmationCount + 1)
  // The guard relies on the server identity even when local storage contains an obsolete admin.
  signIn.persona = null
  await evaluate('localStorage.setItem("user", JSON.stringify({loma:"administrators"}))')
  await send('Page.navigate', { url: `${base}/admin/users` })
  await waitFor('location.pathname === "/auth" && !!document.querySelector(".auth-form-card")')
  signIn.persona = signedInPersona('klients', true)
  await send('Page.navigate', { url: `${base}/admin/payments` })
  await waitFor('location.pathname === "/" && !document.querySelector(".admin-layout")')
  signIn.persona = signedInPersona(providerRole, true)
  await send('Page.navigate', { url: `${base}/admin/categories` })
  await waitFor('location.pathname === "/map" && !document.querySelector(".admin-layout")')

  signIn.persona = null
  signIn.scenario = 'admin-login'
  await resize(1440, 960)
  await send('Page.navigate', { url: `${base}/auth` })
  await waitFor('location.pathname === "/auth" && !!document.querySelector(".auth-form-card input[autocomplete=username]")')
  await changeLanguage('en')
  await input('.auth-form-card input[autocomplete=username]', 'admin@example.test')
  await input('.auth-form-card input[autocomplete=current-password]', 'SampleAdmin42!')
  await click('.auth-form-card button[type=submit]')
  await waitFor('location.pathname === "/admin" && document.querySelectorAll(".metric").length === 8', 'Admin login opens real dashboard UI')
  assert.ok(await evaluate('document.body.innerText.includes("Confirmed payment volume")'))
  await waitFor('document.querySelector(".admin-sidebar").getBoundingClientRect().left >= 0 && document.querySelector(".admin-content").getBoundingClientRect().left >= 252', 'The desktop sidebar remains open')
  assert.equal(await evaluate('getComputedStyle(document.querySelector(".admin-navigation")).backgroundColor'), 'rgba(0, 0, 0, 0)')
  assert.equal(await evaluate('document.querySelector(".chart-value").getBoundingClientRect().width'), 1)
  await screenshot('admin-desktop')
  await click('.admin-content__header .v-btn-toggle button')
  await waitFor('document.querySelectorAll(".chart-bar").length === 14', 'Seven-day charts rerender')

  await click('.admin-navigation a[href="/admin/users"]')
  await waitFor('document.querySelector(".records-table tbody").innerText.includes("QA User")')
  await input('.admin-filters input', 'qa')
  await click('.records-table th:nth-child(2) button')
  assert.equal(adminQueries.at(-1).q, 'qa')
  assert.equal(adminQueries.at(-1).sort, 'vards')
  assert.equal(adminQueries.at(-1).direction, 'asc')
  await click('button[aria-label="View #18"]')
  await waitFor('document.body.innerText.includes("Restriction history")')
  await evaluate('[...document.querySelectorAll(".v-dialog button")].find(button => button.textContent.trim() === "Apply restriction").click()')
  await waitFor('!!document.querySelector(".v-dialog textarea")')
  assert.ok(await evaluate('document.querySelector(".v-dialog button[type=submit]").disabled'))
  await input('.v-dialog textarea', 'QA moderation reason')
  await click('.v-dialog button[type=submit]')
  await waitFor('document.body.innerText.includes("Changes saved.")')
  const restrictionWrite = writes.findLast(write => write.path === '/api/admin/users/18/restrictions')
  assert.equal(restrictionWrite.authorization, 'Bearer admin-token')
  assert.equal(JSON.parse(restrictionWrite.body).iemesls, 'QA moderation reason')

  await resize(390, 844)
  await noOverflow()
  await click('.admin-bar button[aria-label="Administration navigation"]')
  await waitFor('document.querySelector(".admin-sidebar").classList.contains("v-navigation-drawer--active")')
  await click('.admin-navigation a[href="/admin/reports"]')
  await waitFor('location.pathname === "/admin/reports" && document.querySelector(".table-empty")?.textContent.includes("No records")')
  await click('.admin-bar button[aria-label="Language"]')
  await evaluate('[...document.querySelectorAll(".v-overlay .v-list-item")].find(item => item.textContent.includes("Русский")).click()')
  await waitFor('document.documentElement.lang === "ru" && document.querySelector(".admin-content h1")?.textContent === "Жалобы"')
  await noOverflow()
  const themeBefore = await evaluate('document.documentElement.dataset.theme')
  await click('.admin-bar button:nth-last-child(2)')
  await waitFor(`document.documentElement.dataset.theme !== ${JSON.stringify(themeBefore)}`)
  await screenshot('admin-mobile-ru')
  await click('.admin-bar button:last-child')
  await waitFor('location.pathname === "/auth" && !document.querySelector(".admin-layout")')
  signIn.scenario = 'blocked-login'
  await input('.auth-form-card input[autocomplete=username]', 'blocked@example.test')
  await input('.auth-form-card input[autocomplete=current-password]', 'SampleAdmin42!')
  await click('.auth-form-card button[type=submit]')
  await waitFor('document.body.innerText.includes("QA account hold") && !!document.querySelector(".auth-form-card a[href=\'/support\']")')
  await click('.auth-form-card a[href="/support"]')
  await waitFor('location.pathname === "/support" && !!document.querySelector(".support-page")')
  console.log('PASS: administrator login and route guards, dashboard range, server search and sorting, restriction confirmation, mobile drawer, localization, theme, and block support.')

  if (process.env.CATALOG_VERIFY_PWA === '1') {
    await waitFor('!!navigator.serviceWorker.controller', 'Production service worker controls the application')
    for (const path of ['/auth', '/verify-email']) {
      const url = `${base}${path}?pwa-check=1`
      await send('Page.navigate', { url })
      await waitFor(`location.href === ${JSON.stringify(url)} && document.readyState === "complete"`)
      assert.equal(documentsFromServiceWorker.get(url), false, `${path} loads its document from the network`)
    }
  }
  assert.equal(failures.length, 0, JSON.stringify(failures))
  console.log('PASS: email registration, automatic sign-in, cross-browser recovery, active-session protection, resend, LV/RU/EN and responsive layout.')
  console.log(`Screenshots: ${artifacts}`)
} finally {
  socket.close()
  await fetch(`${endpoint}/json/close/${target.id}`).catch(() => {})
  if (browser) {
    browser.kill()
  }
}
