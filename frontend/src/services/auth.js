import { shallowRef } from 'vue'
import { startLoading } from '@/stores/loading'
import { getLocale } from '@/stores/locale'

export const googlePending = shallowRef(null)
export const blockedLogin = shallowRef(null)

let csrfRequest
let authRevision = 0
const userRequests = new Map()

function getStoredToken () {
  return localStorage.getItem('token') || ''
}

export function hasAuthSession () {
  return Boolean(getStoredToken() || localStorage.getItem('user') || googlePending.value)
}

export function getStoredUser () {
  const raw = localStorage.getItem('user')
  if (!raw) {
    return null
  }

  try {
    return JSON.parse(raw)
  } catch {
    clearAuthSession({ emitLogout: false })
    return null
  }
}

function csrfCookie () {
  const value = document.cookie.split('; ').find(cookie => cookie.startsWith('XSRF-TOKEN='))
  return value ? decodeURIComponent(value.slice('XSRF-TOKEN='.length)) : ''
}

async function refreshCsrf () {
  csrfRequest ??= fetch('/sanctum/csrf-cookie', { cache: 'no-store' }).finally(() => {
    csrfRequest = null
  })
  await csrfRequest
}

export async function apiFetch (path, { auth = true, singleAttempt = false, ...options } = {}) {
  const headers = new Headers(options.headers)
  headers.set('Accept', 'application/json')
  headers.set('Accept-Language', getLocale())
  const token = auth ? getStoredToken() : ''
  if (token) {
    headers.set('Authorization', `Bearer ${token}`)
  }

  const isWrite = !['GET', 'HEAD', 'OPTIONS'].includes((options.method || 'GET').toUpperCase())
  if (!isWrite) {
    return fetch(path, { ...options, headers })
  }

  if (!csrfCookie()) {
    await refreshCsrf()
  }
  const send = () => {
    const csrf = csrfCookie()
    if (csrf) {
      headers.set('X-XSRF-TOKEN', csrf)
    } else {
      headers.delete('X-XSRF-TOKEN')
    }
    return fetch(path, { ...options, headers })
  }

  const response = await send()
  if (response.status !== 419) {
    return response
  }

  await refreshCsrf()
  return singleAttempt ? response : send()
}

export function setAuthSession ({ token, persona }) {
  if (token !== undefined && (token ?? '') !== getStoredToken()) {
    authRevision++
  }
  if (token === null) {
    localStorage.removeItem('token')
  } else if (token) {
    localStorage.setItem('token', token)
  }

  if (persona) {
    googlePending.value = null
    blockedLogin.value = null
    localStorage.setItem('user', JSON.stringify(persona))
    window.dispatchEvent(new CustomEvent('user-updated', { detail: persona }))
  }
}

export function clearAuthSession ({ emitLogout = true } = {}) {
  authRevision++
  localStorage.removeItem('user')
  localStorage.removeItem('token')
  googlePending.value = null
  window.dispatchEvent(new CustomEvent(emitLogout ? 'user-logged-out' : 'user-updated', { detail: null }))
}

export function syncCurrentUser ({ useBearer = true, allowStoredUser = true } = {}) {
  const key = `${authRevision}:${useBearer}:${allowStoredUser}`
  if (!userRequests.has(key)) {
    userRequests.set(key, fetchCurrentUser(useBearer, allowStoredUser).finally(() => userRequests.delete(key)))
  }
  return userRequests.get(key)
}

async function fetchCurrentUser (useBearer, allowStoredUser) {
  const revision = authRevision
  const finish = startLoading()
  try {
    const response = await apiFetch('/api/auth/me', { auth: useBearer, cache: 'no-store' })
    if (revision !== authRevision) {
      return null
    }
    if (response.status === 401 || response.status === 403) {
      const result = response.status === 403 ? await response.json().catch(() => null) : null
      if (revision !== authRevision) return null
      if (result?.code === 'account_blocked') blockedLogin.value = result
      if (useBearer || result?.code === 'account_blocked') {
        clearAuthSession({ emitLogout: false })
      }
      return null
    }
    if (!response.ok) {
      return useBearer && allowStoredUser ? getStoredUser() : null
    }

    const result = await response.json()
    if (revision !== authRevision) {
      return null
    }
    if (result.google_pending) {
      clearAuthSession({ emitLogout: false })
      googlePending.value = result.google_pending
      return null
    }
    if (result.persona) {
      setAuthSession({ token: useBearer ? undefined : null, persona: result.persona })
      return result.persona
    }
    return useBearer && allowStoredUser ? getStoredUser() : null
  } catch {
    return useBearer && allowStoredUser ? getStoredUser() : null
  } finally {
    finish()
  }
}

export async function logoutRequest () {
  const finish = startLoading()
  try {
    const response = await apiFetch('/api/auth/logout', { method: 'POST' })
    if (!response.ok && response.status !== 401) {
      return false
    }
    clearAuthSession()
    return true
  } catch {
    return false
  } finally {
    finish()
  }
}
