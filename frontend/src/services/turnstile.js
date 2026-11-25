import { computed, ref, toValue } from 'vue'
import { apiFetch } from '@/services/auth'

export const turnstileEnabled = import.meta.env.VITE_TURNSTILE_ENABLED === 'true'
const configuredKey = import.meta.env.VITE_TURNSTILE_SITE_KEY?.trim() || ''
export const turnstileSiteKey = import.meta.env.PROD && /^[123]x0{20}(?:AA|AB|BB|FF)$/.test(configuredKey) ? '' : configuredKey

const scriptUrl = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit'
let scriptPromise

export function loadTurnstile () {
  if (window.turnstile) {
    return Promise.resolve(window.turnstile)
  }

  scriptPromise ??= new Promise((resolve, reject) => {
    const script = document.createElement('script')
    const timeout = window.setTimeout(failed, 12_000)
    function failed () {
      window.clearTimeout(timeout)
      script.remove()
      reject(new Error('Turnstile script unavailable'))
    }
    script.src = scriptUrl
    script.async = true
    script.addEventListener('error', failed, { once: true })
    script.addEventListener('load', () => {
      if (!window.turnstile) {
        return failed()
      }
      window.clearTimeout(timeout)
      resolve(window.turnstile)
    }, { once: true })
    document.head.append(script)
  }).catch(error => {
    scriptPromise = undefined
    throw error
  })

  return scriptPromise
}

const errors = {
  captcha_required: 'turnstile.required',
  captcha_invalid: 'turnstile.invalid',
  captcha_expired: 'turnstile.expired',
  captcha_unavailable: 'turnstile.unavailable',
  captcha_configuration: 'turnstile.configuration',
  captcha_offline: 'turnstile.offline',
}

export function captchaErrorKey (result) {
  return Object.hasOwn(errors, result?.code) ? errors[result.code] : ''
}

export function useTurnstile (required = turnstileEnabled) {
  const token = ref('')
  const widget = ref(null)
  const enabled = computed(() => toValue(required))
  const ready = computed(() => !enabled.value || Boolean(token.value))

  async function post (path, data, { auth = false } = {}) {
    if (navigator.onLine === false) {
      throw Object.assign(new Error('Offline'), { code: 'captcha_offline' })
    }
    if (!ready.value) {
      throw Object.assign(new Error('Missing Turnstile token'), { code: 'captcha_required' })
    }

    const responseToken = token.value
    if (responseToken) {
      token.value = ''
    }
    try {
      return await apiFetch(path, {
        auth,
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(responseToken ? { ...data, turnstile_token: responseToken } : data),
        singleAttempt: Boolean(responseToken),
        cache: 'no-store',
      })
    } finally {
      if (responseToken) {
        widget.value?.reset()
      }
    }
  }

  return { enabled, token, widget, ready, post }
}
