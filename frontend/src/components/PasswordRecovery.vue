<template>
  <section aria-labelledby="recovery-title" class="recovery-page">
    <div class="recovery-card">
      <v-icon aria-hidden="true" class="recovery-icon" icon="mdi-lock-reset" />
      <h1 id="recovery-title">{{ t(reset ? 'passwordRecovery.resetTitle' : 'passwordRecovery.forgot') }}</h1>

      <v-alert
        v-if="complete"
        class="mb-5"
        role="status"
        type="success"
        variant="tonal"
      >
        {{ t(reset ? 'passwordRecovery.updated' : 'passwordRecovery.requested') }}
      </v-alert>

      <template v-else-if="reset && !token">
        <v-alert class="mb-5" type="error" variant="tonal">
          {{ t('passwordRecovery.invalidLink') }}
        </v-alert>
        <v-btn block class="recovery-submit mb-3" color="primary" :to="FORGOT_PASSWORD_ROUTE">
          {{ t('passwordRecovery.newLink') }}
        </v-btn>
      </template>

      <template v-else>
        <p class="recovery-description">{{ t(reset ? 'passwordRecovery.resetIntro' : 'passwordRecovery.forgotIntro') }}</p>
        <v-alert v-if="requestError" class="mb-5" type="error" variant="tonal">
          {{ t(requestError) }}
        </v-alert>
        <v-form :disabled="pending" novalidate @submit.prevent="submit">
          <v-text-field
            v-model.trim="email"
            autocomplete="username"
            class="mb-2"
            :error-messages="fieldError('email')"
            :label="t('auth.fields.email')"
            :readonly="reset"
            type="email"
            variant="outlined"
            @update:model-value="serverField = ''"
          />
          <template v-if="reset">
            <v-text-field
              v-model="password"
              autocomplete="new-password"
              class="mb-2"
              :error-messages="fieldError('password')"
              :label="t('passwordRecovery.newPassword')"
              type="password"
              variant="outlined"
              @update:model-value="serverField = ''"
            />
            <v-text-field
              v-model="confirmation"
              autocomplete="new-password"
              class="mb-2"
              :error-messages="fieldError('confirmation')"
              :label="t('auth.fields.passwordConfirmation')"
              type="password"
              variant="outlined"
              @update:model-value="serverField = ''"
            />
          </template>
          <v-btn
            block
            class="recovery-submit"
            color="primary"
            :disabled="pending"
            :loading="pending"
            type="submit"
          >
            {{ t(reset ? 'passwordRecovery.save' : 'passwordRecovery.send') }}
          </v-btn>
        </v-form>
      </template>

      <v-btn
        block
        class="mt-2"
        color="primary"
        :to="AUTH_ROUTE"
        variant="text"
      >
        {{ t('passwordRecovery.back') }}
      </v-btn>
    </div>
  </section>
</template>

<script setup>
  import { computed, ref, watch } from 'vue'
  import { useRoute, useRouter } from 'vue-router'
  import { AUTH_ROUTE, FORGOT_PASSWORD_ROUTE, RESET_PASSWORD_ROUTE } from '@/router/paths'
  import { apiFetch, syncCurrentUser } from '@/services/auth'
  import { useLocale } from '@/stores/locale'

  const props = defineProps({ reset: Boolean })
  const route = useRoute()
  const router = useRouter()
  const { locale, setLocale, t } = useLocale()
  const email = ref('')
  const token = ref('')
  const password = ref('')
  const confirmation = ref('')
  const pending = ref(false)
  const complete = ref(false)
  const attempted = ref(false)
  const requestError = ref('')
  const serverField = ref('')

  function emailError (value) {
    if (!value) return 'auth.messages.emailRequired'
    if (value.length > 255 || !/^[^\s@]+@[^\s@]+$/.test(value)) return 'auth.messages.emailInvalid'
    return ''
  }

  watch(() => route.hash, hash => {
    if (!props.reset || route.path !== RESET_PASSWORD_ROUTE || !hash) return

    const params = new URLSearchParams(window.location.hash.slice(1))
    setLocale(params.get('lang'))
    email.value = params.get('email') || ''
    const candidate = params.get('token') || ''
    token.value = /^[a-f0-9]{64}$/.test(candidate) && !emailError(email.value) ? candidate : ''
    void router.replace({ hash: '' })
  }, { immediate: true })

  const rules = computed(() => {
    const value = password.value
    let passwordError = ''
    if (!value) passwordError = 'auth.messages.passwordRequired'
    else if ([...value].length < 8) passwordError = 'auth.messages.passwordMin'
    else if (!/(\p{Ll}+.*\p{Lu})|(\p{Lu}+.*\p{Ll})/u.test(value)) passwordError = 'passwordRecovery.mixed'
    else if (!/[\p{Z}\p{S}\p{P}]/u.test(value)) passwordError = 'auth.messages.passwordSpecial'
    else if (new TextEncoder().encode(value).length > 72 || value.includes('\0')) passwordError = 'passwordRecovery.passwordLength'

    return {
      email: emailError(email.value),
      password: props.reset ? passwordError : '',
      confirmation: props.reset && confirmation.value !== value ? 'auth.messages.passwordMismatch' : '',
    }
  })

  function fieldError (field) {
    const key = (attempted.value && rules.value[field]) || (serverField.value === field && {
      email: 'auth.messages.emailInvalid',
      password: 'passwordRecovery.passwordRejected',
    }[field])
    return key ? t(key) : undefined
  }

  async function submit () {
    if (pending.value || complete.value || (props.reset && !token.value)) return

    attempted.value = true
    requestError.value = ''
    serverField.value = ''
    if (Object.values(rules.value).some(Boolean)) return

    pending.value = true
    try {
      const endpoint = props.reset ? '/api/auth/reset-password' : '/api/auth/forgot-password'
      const body = props.reset
        ? { email: email.value, token: token.value, password: password.value, password_confirmation: confirmation.value }
        : { email: email.value, locale: locale.value }
      const response = await apiFetch(endpoint, {
        auth: false,
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      })

      if (response.ok) {
        complete.value = true
        token.value = password.value = confirmation.value = ''
        if (props.reset) void syncCurrentUser()
      } else if (response.status === 429) {
        requestError.value = 'common.retryLater'
      } else if (response.status === 422) {
        const result = await response.json()
        if (result.code === 'invalid_reset_link' || result.errors?.token || (props.reset && result.errors?.email)) {
          token.value = password.value = confirmation.value = ''
        } else if (result.errors?.email) {
          serverField.value = 'email'
        } else if (result.errors?.password) {
          serverField.value = 'password'
        } else {
          requestError.value = 'passwordRecovery.failed'
        }
      } else {
        requestError.value = 'passwordRecovery.failed'
      }
    } catch {
      requestError.value = 'passwordRecovery.failed'
    } finally {
      pending.value = false
    }
  }
</script>

<style scoped>
.recovery-page {
  display: grid;
  justify-items: center;
  align-items: start;
  padding: clamp(24px, 8vw, 88px) 16px;
  background: var(--er-bg);
  color: var(--er-text);
}
.recovery-card {
  width: min(100%, 480px);
  min-width: 0;
  padding: clamp(16px, 5vw, 36px);
  background: var(--er-surface-strong);
  border: 1px solid var(--er-stroke);
  border-radius: 20px;
}
.recovery-icon {
  display: inline-grid;
  place-items: center;
  width: 44px;
  height: 44px;
  margin-bottom: 16px;
  border-radius: 12px;
  background: var(--er-primary-soft);
  color: var(--er-primary-strong);
}
.recovery-card h1 {
  margin-bottom: 16px;
  font-family: var(--er-display-font);
  font-size: clamp(1.65rem, 4vw, 2.25rem);
  font-weight: 900;
  line-height: 1.15;
}
.recovery-description { margin-bottom: 20px; color: var(--er-text-muted); }
.recovery-card :deep(.v-field), .recovery-submit { border-radius: 12px; }
.recovery-submit { min-height: 48px; }
</style>
