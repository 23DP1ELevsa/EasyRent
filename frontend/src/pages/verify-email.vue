<template>
  <section aria-labelledby="verification-title" class="account-action-page">
    <div class="account-action-card verification-card">
      <v-icon
        aria-hidden="true"
        class="account-action-icon"
        :icon="status === 'verified' ? 'mdi-email-check-outline' : 'mdi-email-outline'"
      />
      <h1 id="verification-title" class="account-action-title">{{ t('emailVerification.title') }}</h1>

      <div v-if="status === 'checking'" class="verification-progress mb-5" role="status">
        <v-progress-circular color="primary" indeterminate size="22" />
        {{ t('emailVerification.checking') }}
      </div>
      <p v-else-if="status === 'ready'" class="account-action-description">{{ t('emailVerification.ready') }}</p>
      <v-alert
        v-else
        class="mb-5"
        :role="alertType === 'error' ? 'alert' : 'status'"
        :type="alertType"
        variant="tonal"
      >
        {{ t(`emailVerification.${status}`) }}
      </v-alert>

      <v-btn
        v-if="status === 'confirmError' || status === 'interactionRequired'"
        block
        class="account-action-submit mb-3"
        color="primary"
        :variant="status === 'interactionRequired' ? 'flat' : 'outlined'"
        @click="confirm(status === 'interactionRequired' || lastInteractive)"
      >
        {{ t(status === 'interactionRequired' ? 'emailVerification.confirmAction' : 'emailVerification.retryConfirm') }}
      </v-btn>

      <p v-if="status === 'verified' && !signedIn" class="account-action-description">
        {{ t(activeSession ? 'emailVerification.activeSession' : passwordResetRequired ? 'emailVerification.resetRequired' : 'emailVerification.signInRequired') }}
      </p>

      <v-form
        v-if="!['verified', 'checking', 'interactionRequired'].includes(status)"
        :disabled="resending"
        novalidate
        @submit.prevent="resend"
      >
        <v-text-field
          v-model.trim="email"
          autocomplete="email"
          :error-messages="emailError ? t(emailError) : undefined"
          :label="t('auth.fields.email')"
          type="email"
          variant="outlined"
          @update:model-value="emailError = ''"
        />
        <v-btn
          block
          class="account-action-submit"
          color="primary"
          :disabled="remaining > 0 || resending"
          :loading="resending"
          type="submit"
        >
          {{ t('emailVerification.resend') }}
        </v-btn>
        <p v-if="remaining > 0" class="verification-countdown mt-3">
          {{ t('emailVerification.countdown', { seconds: remaining }) }}
        </p>
      </v-form>

      <v-btn
        v-if="!signedIn && !activeSession && !['checking', 'interactionRequired'].includes(status)"
        block
        class="mt-2"
        color="primary"
        :to="passwordResetRequired ? FORGOT_PASSWORD_ROUTE : AUTH_ROUTE"
        variant="text"
      >
        {{ t(passwordResetRequired ? 'emailVerification.resetPassword' : 'emailVerification.signIn') }}
      </v-btn>
    </div>
  </section>
</template>

<script setup>
  import { computed, onBeforeUnmount, ref, watch } from 'vue'
  import { useRoute, useRouter } from 'vue-router'
  import { accountHome, AUTH_ROUTE, FORGOT_PASSWORD_ROUTE, PROFILE_ROUTE, VERIFY_EMAIL_ROUTE } from '@/router/paths'
  import { apiFetch, hasAuthSession, setAuthSession } from '@/services/auth'
  import { useLocale } from '@/stores/locale'

  const route = useRoute()
  const router = useRouter()
  const { locale, setLocale, t } = useLocale()
  const receipt = window.history.state?.emailVerification
  const email = ref(receipt?.email || '')
  const status = ref({ sent: 'sent', unavailable: 'mailUnavailable' }[receipt?.mailStatus] || 'ready')
  const emailError = ref('')
  const resending = ref(false)
  const signedIn = ref(false)
  const activeSession = ref(false)
  const passwordResetRequired = ref(false)
  const cooldownUntil = ref(receipt?.cooldownUntil ?? 0)
  const now = ref(Date.now())
  const remaining = computed(() => Math.max(0, Math.ceil((cooldownUntil.value - now.value) / 1000)))
  const alertType = computed(() => {
    if (['mailUnavailable', 'resendMailUnavailable', 'rateLimited'].includes(status.value)) return 'warning'
    if (['resent', 'interactionRequired'].includes(status.value)) return 'info'
    return ['verified', 'sent', 'resentConfirmed'].includes(status.value) ? 'success' : 'error'
  })

  let link = null
  let confirmation = null
  let lastInteractive = false
  let redirectTimer
  const timer = window.setInterval(() => {
    now.value = Date.now()
  }, 250)
  document.addEventListener('visibilitychange', confirmVisibleLink)
  onBeforeUnmount(() => {
    window.clearInterval(timer)
    window.clearTimeout(redirectTimer)
    document.removeEventListener('visibilitychange', confirmVisibleLink)
    link = null
  })

  function cooldown (seconds) {
    now.value = Date.now()
    cooldownUntil.value = now.value + seconds * 1000
  }

  function submit (action, data) {
    return apiFetch(`/api/auth/verification/${action}`, {
      auth: action === 'confirm',
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
    })
  }

  function confirmVisibleLink () {
    if (document.visibilityState === 'visible' && status.value === 'checking') {
      void confirm()
    }
  }

  async function confirm (interactive = false) {
    const attempt = link
    if (!attempt || confirmation === attempt) return
    confirmation = attempt
    lastInteractive = interactive
    status.value = 'checking'
    try {
      const response = await submit('confirm', {
        ...attempt,
        interactive,
        auto_login: !hasAuthSession(),
      })
      const result = await response.json()
      if (link !== attempt) return

      if (response.ok && result.code === 'email_verified') {
        link = null
        status.value = 'verified'
        activeSession.value = result.active_session === true || hasAuthSession()
        passwordResetRequired.value = result.password_reset_required === true
        if (result.token && result.persona && !activeSession.value) {
          setAuthSession({ token: result.token, persona: result.persona })
          signedIn.value = true
          const destination = result.persona.profile_completed === false ? PROFILE_ROUTE : accountHome(result.persona)
          redirectTimer = window.setTimeout(() => router.replace(destination), 450)
        }
        return
      }

      const next = {
        verification_interaction_required: 'interactionRequired',
        verification_link_expired: 'expired',
        invalid_verification_link: 'invalid',
      }[result.code]
      status.value = next || 'confirmError'
      if (next === 'expired' || next === 'invalid') link = null
    } catch {
      if (link === attempt) status.value = 'confirmError'
    } finally {
      if (confirmation === attempt) confirmation = null
    }
  }

  watch(() => route.hash, hash => {
    if (route.path !== VERIFY_EMAIL_ROUTE || !hash || !window.location.hash) return

    const params = new URLSearchParams(window.location.hash.slice(1))
    window.history.replaceState(window.history.state, '', window.location.pathname + window.location.search)
    void router.replace({ hash: '' })

    const id = params.get('id') || ''
    const token = params.get('token') || ''
    setLocale(params.get('lang'))
    link = null
    window.clearTimeout(redirectTimer)
    lastInteractive = false
    signedIn.value = false
    activeSession.value = false
    passwordResetRequired.value = false

    if (!/^[1-9][0-9]{0,18}$/.test(id) || !/^[a-f0-9]{64}$/.test(token)) {
      status.value = 'invalid'
      return
    }
    link = { id, token }
    status.value = 'checking'
    confirmVisibleLink()
  }, { immediate: true })

  async function resend () {
    if (resending.value || remaining.value > 0) return
    if (!email.value || email.value.length > 255 || !/^[^\s@]+@[^\s@]+$/.test(email.value)) {
      emailError.value = email.value ? 'auth.messages.emailInvalid' : 'auth.messages.emailRequired'
      return
    }
    resending.value = true
    try {
      const response = await submit('resend', { email: email.value, locale: locale.value })
      if (response.ok) {
        const result = await response.json()
        link = null
        status.value = result.code === 'verification_mail_sent' ? 'resentConfirmed' : 'resent'
        cooldown(Number(result.retry_after) || 60)
      } else if (response.status === 429) {
        status.value = 'rateLimited'
        cooldown(Number(response.headers.get('Retry-After')) || 60)
      } else if (response.status === 503 && response.headers.has('Retry-After')) {
        status.value = 'resendMailUnavailable'
        cooldown(Number(response.headers.get('Retry-After')) || 60)
      } else if (response.status === 422) {
        emailError.value = 'auth.messages.emailInvalid'
      } else {
        status.value = 'resendError'
      }
    } catch {
      status.value = 'resendError'
    } finally {
      resending.value = false
    }
  }
</script>

<style src="@/styles/account-action.css"></style>

<style scoped>
.verification-progress { display: flex; gap: 12px; align-items: center; }
.verification-countdown { color: var(--er-text-muted); font-size: .9rem; text-align: center; }
.verification-card .account-action-submit :deep(.v-btn__content) { white-space: normal; }
</style>
