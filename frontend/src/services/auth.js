import { shallowRef } from 'vue'
import { startLoading } from '@/stores/loading'

export const googlePending = shallowRef(null)

let csrfRequest
const userRequests = new Map()

function getStoredToken () {
  return localStorage.getItem('token') || ''
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

export async function apiFetch (path, { auth = true, ...options } = {}) {
  const headers = new Headers(options.headers)
  headers.set('Accept', 'application/json')
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
  return send()
}

export function setAuthSession ({ token, persona }) {
  if (token === null) {
    localStorage.removeItem('token')
  } else if (token) {
    localStorage.setItem('token', token)
  }

  if (persona) {
    googlePending.value = null
    localStorage.setItem('user', JSON.stringify(persona))
    window.dispatchEvent(new CustomEvent('user-updated', { detail: persona }))
  }
}

export function clearAuthSession ({ emitLogout = true } = {}) {
  localStorage.removeItem('user')
  localStorage.removeItem('token')
  googlePending.value = null
  window.dispatchEvent(new CustomEvent(emitLogout ? 'user-logged-out' : 'user-updated', { detail: null }))
}

export function syncCurrentUser ({ useBearer = true } = {}) {
  if (!userRequests.has(useBearer)) {
    userRequests.set(useBearer, fetchCurrentUser(useBearer).finally(() => userRequests.delete(useBearer)))
  }
  return userRequests.get(useBearer)
}

async function fetchCurrentUser (useBearer) {
  const finish = startLoading()
  try {
    const response = await apiFetch('/api/auth/me', { auth: useBearer, cache: 'no-store' })
    if (response.status === 401) {
      if (useBearer) {
        clearAuthSession({ emitLogout: false })
      }
      return null
    }
    if (!response.ok) {
      return useBearer ? getStoredUser() : null
    }

    const result = await response.json()
    if (result.google_pending) {
      clearAuthSession({ emitLogout: false })
      googlePending.value = result.google_pending
      return null
    }
    if (result.persona) {
      setAuthSession({ token: useBearer ? undefined : null, persona: result.persona })
      return result.persona
    }
    return useBearer ? getStoredUser() : null
  } catch {
    return useBearer ? getStoredUser() : null
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
