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
        v-if="status === 'confirmError'"
        block
        class="account-action-submit mb-3"
        color="primary"
        variant="outlined"
        @click="confirm"
      >
        {{ t('emailVerification.retryConfirm') }}
      </v-btn>

      <v-form
        v-if="status !== 'verified' && status !== 'checking'"
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
        block
        class="mt-2"
        color="primary"
        :to="AUTH_ROUTE"
        variant="text"
      >
        {{ t('emailVerification.signIn') }}
      </v-btn>
    </div>
  </section>
</template>

<script setup>
  import { computed, onBeforeUnmount, ref, watch } from 'vue'
  import { useRoute, useRouter } from 'vue-router'
  import { AUTH_ROUTE, VERIFY_EMAIL_ROUTE } from '@/router/paths'
  import { apiFetch } from '@/services/auth'
  import { useLocale } from '@/stores/locale'

  const route = useRoute()
  const router = useRouter()
  const { locale, setLocale, t } = useLocale()
  const receipt = window.history.state?.emailVerification
  const email = ref(receipt?.email || '')
  const status = ref({ sent: 'sent', unavailable: 'mailUnavailable' }[receipt?.mailStatus] || 'ready')
  const emailError = ref('')
  const resending = ref(false)
  const cooldownUntil = ref(receipt?.cooldownUntil ?? 0)
  const now = ref(Date.now())
  const remaining = computed(() => Math.max(0, Math.ceil((cooldownUntil.value - now.value) / 1000)))
  const alertType = computed(() => {
    if (status.value === 'mailUnavailable' || status.value === 'rateLimited') return 'warning'
    return ['verified', 'sent', 'resent'].includes(status.value) ? 'success' : 'error'
  })

  let link = null
  const timer = window.setInterval(() => {
    now.value = Date.now()
  }, 250)
  onBeforeUnmount(() => {
    window.clearInterval(timer)
    link = null
  })

  function cooldown (seconds) {
    now.value = Date.now()
    cooldownUntil.value = now.value + seconds * 1000
  }

  function submit (action, data) {
    return apiFetch(`/api/auth/verification/${action}`, {
      auth: false,
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
    })
  }

  async function confirm () {
    const attempt = link
    if (!attempt) return
    status.value = 'checking'
    try {
      const response = await submit('confirm', attempt)
      const result = await response.json()
      if (link !== attempt) return

      const next = {
        email_verified: 'verified',
        verification_link_expired: 'expired',
        invalid_verification_link: 'invalid',
      }[result.code]
      status.value = next || 'confirmError'
      if (next) link = null
    } catch {
      if (link === attempt) status.value = 'confirmError'
    }
  }

  watch(() => route.hash, async hash => {
    if (route.path !== VERIFY_EMAIL_ROUTE || !hash) return

    const params = new URLSearchParams(window.location.hash.slice(1))
    const id = params.get('id') || ''
    const token = params.get('token') || ''
    setLocale(params.get('lang'))
    link = null
    status.value = 'checking'
    await router.replace({ hash: '' })

    if (!/^[1-9][0-9]{0,18}$/.test(id) || !/^[a-f0-9]{64}$/.test(token)) {
      status.value = 'invalid'
      return
    }
    link = { id, token }
    await confirm()
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
        status.value = 'resent'
        cooldown(Number(result.retry_after) || 60)
      } else if (response.status === 429) {
        status.value = 'rateLimited'
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
