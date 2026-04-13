const API_BASE = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000'

export function getApiBase() {
  return API_BASE
}

export function getStoredToken() {
  return localStorage.getItem('token') || ''
}

export function getStoredUser() {
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

export function getAuthHeaders(headers = {}) {
  const token = getStoredToken()

  if (!token) {
    return { ...headers }
  }

  return {
    ...headers,
    Authorization: `Bearer ${token}`,
  }
}

export function setAuthSession({ token, persona }) {
  if (token) {
    localStorage.setItem('token', token)
  }

  if (persona) {
    localStorage.setItem('user', JSON.stringify(persona))
    window.dispatchEvent(new CustomEvent('user-updated', { detail: persona }))
  }
}

export function clearAuthSession({ emitLogout = true } = {}) {
  localStorage.removeItem('user')
  localStorage.removeItem('token')

  if (emitLogout) {
    window.dispatchEvent(new CustomEvent('user-logged-out'))
  }
}

export async function syncCurrentUser() {
  const token = getStoredToken()

  if (!token) {
    return getStoredUser()
  }

  try {
    const response = await fetch(`${API_BASE}/api/auth/me`, {
      headers: getAuthHeaders({
        Accept: 'application/json',
      }),
    })

    if (!response.ok) {
      if (response.status === 401) {
        clearAuthSession()
        return null
      }

      return getStoredUser()
    }

    const result = await response.json().catch(() => ({}))

    if (!result?.persona) {
      return getStoredUser()
    }

    setAuthSession({ token, persona: result.persona })
    return result.persona
  } catch {
    return getStoredUser()
  }
}

export async function logoutRequest() {
  try {
    const token = getStoredToken()

    if (token) {
      await fetch(`${API_BASE}/api/auth/logout`, {
        method: 'POST',
        headers: getAuthHeaders({
          Accept: 'application/json',
        }),
      })
    }
  } catch {
  } finally {
    clearAuthSession()
  }
}