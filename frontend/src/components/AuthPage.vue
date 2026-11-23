<template>
  <div class="auth-page">
    <aside aria-hidden="true" class="auth-visual">
      <img alt="" class="auth-visual__image" src="@/assets/cat-moto.jpg">
      <div class="auth-visual__overlay" />
      <div class="auth-visual__copy">
        <span class="auth-visual__brand">EasyRent</span>
        <p>{{ t('home.hero.slogan') }}</p>
        <span>{{ t('app.brandSubtitle') }}</span>
      </div>
    </aside>
    <div class="auth-form-shell">
      <v-card class="surface auth-form-card" elevation="0">
        <v-card-text class="auth-form-content">
          <div class="d-flex align-center justify-space-between mb-5">
            <div>
              <p class="auth-kicker">{{ t('app.brandSubtitle') }}</p><h1 class="auth-title">{{ copy.title }}</h1>
            </div>
          </div>

          <v-tabs
            v-if="!linkEmail"
            v-model="tab"
            class="auth-tabs"
            color="primary"
            grow
          >
            <v-tab value="login">{{ copy.tabs.login }}</v-tab>
            <v-tab value="register">{{ copy.tabs.register }}</v-tab>
          </v-tabs>

          <v-alert
            v-if="errorText"
            class="auth-feedback"
            role="alert"
            type="error"
            variant="tonal"
          >{{ errorText }}</v-alert><v-alert
            v-if="okText"
            aria-live="polite"
            class="auth-feedback"
            type="success"
            variant="tonal"
          >{{ okText }}</v-alert>

          <v-alert
            v-if="googleError"
            class="auth-feedback"
            role="alert"
            type="error"
            variant="tonal"
          >
            {{ googleError }}
          </v-alert>

          <v-form v-if="linkEmail" @submit.prevent="linkGoogle">
            <p class="mb-4">{{ t('google.linkIntro') }}</p>
            <v-text-field
              autocomplete="username"
              :label="copy.fields.email"
              :model-value="linkEmail"
              readonly
              type="email"
              variant="outlined"
            />
            <v-text-field
              v-model="linkPassword"
              autocomplete="current-password"
              :error-messages="linkPasswordError"
              :label="t('google.password')"
              required
              type="password"
              variant="outlined"
            />
            <v-btn
              block
              class="submit-btn"
              color="primary"
              :loading="loading"
              rounded="xl"
              type="submit"
            >{{ t('google.link') }}</v-btn>
            <v-btn
              block
              class="mt-2"
              :disabled="loading"
              variant="text"
              @click="cancelLink"
            >{{ t('common.cancel') }}</v-btn>
          </v-form>

          <template v-else>
            <div v-if="tab === 'register'" class="auth-role-picker">
              <p>{{ copy.fields.role }}</p>
              <v-btn-toggle v-model="reg.loma" class="auth-role-options" mandatory @update:model-value="updateRegFields">
                <v-btn v-for="role in lomas" :key="role.value" :value="role.value" variant="outlined">
                  <v-icon start>{{ role.value === 'klients' ? 'mdi-account-outline' : 'mdi-domain' }}</v-icon>
                  {{ role.title }}
                </v-btn>
              </v-btn-toggle>
            </div>

            <v-btn
              block
              class="submit-btn google-sign-in"
              :disabled="loading"
              :loading="googleLoading"
              rounded="xl"
              type="button"
              variant="outlined"
              @click="startGoogle"
            >
              <svg
                aria-hidden="true"
                class="mr-3"
                height="18"
                viewBox="0 0 48 48"
                width="18"
              >
                <path d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z" fill="#EA4335" />
                <path d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.25 5.48-4.76 7.18l7.73 6c4.51-4.18 7.07-10.36 7.07-17.65z" fill="#4285F4" />
                <path d="M10.53 28.59a14.42 14.42 0 0 1 0-9.18l-7.98-6.19a24 24 0 0 0 0 21.56l7.98-6.19z" fill="#FBBC05" />
                <path d="M24 48c6.48 0 11.93-2.13 15.91-5.8l-7.73-6C30.03 37.65 27.26 38.5 24 38.5c-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z" fill="#34A853" />
              </svg>
              {{ t('google.continue') }}
            </v-btn>
            <div class="google-divider"><span>{{ t('google.orEmail') }}</span></div>
          </template>

          <div v-if="!linkEmail && tab === 'login'">
            <v-form ref="loginForm" @submit.prevent="submitLogin">
              <v-text-field
                v-model="login.email"
                autocomplete="username"
                class="mb-3"
                :label="copy.fields.email"
                prepend-inner-icon="mdi-email-outline"
                type="email"
                variant="outlined"
              />
              <v-text-field
                v-model="login.password"
                autocomplete="current-password"
                class="mb-4"
                :label="copy.fields.password"
                prepend-inner-icon="mdi-lock-outline"
                type="password"
                variant="outlined"
              />

              <RouterLink class="auth-recovery-link" :to="FORGOT_PASSWORD_ROUTE">
                {{ t('passwordRecovery.forgot') }}
              </RouterLink>

              <v-btn
                block
                class="submit-btn"
                color="primary"
                elevation="4"
                :loading="loading"
                rounded="xl"
                size="large"
                type="submit"
              >
                <v-icon start>mdi-login</v-icon>
                {{ copy.actions.login }}
              </v-btn>
            </v-form>
          </div>

          <div v-else-if="!linkEmail">
            <v-form ref="regForm" @submit.prevent="submitRegister">
              <v-row class="register-grid" dense>
                <v-col cols="12" md="6">
                  <v-text-field
                    v-model="reg.name"
                    autocomplete="name"
                    class="mb-3"
                    :label="copy.fields.fullName"
                    prepend-inner-icon="mdi-account-outline"
                    :rules="nameRules"
                    variant="outlined"
                  />
                </v-col>

                <v-col cols="12" md="6">
                  <v-text-field
                    v-model="reg.email"
                    autocomplete="email"
                    class="mb-3"
                    :label="copy.fields.email"
                    prepend-inner-icon="mdi-email-outline"
                    :rules="emailRules"
                    type="email"
                    variant="outlined"
                  />
                </v-col>

                <v-col cols="12" md="6">
                  <v-text-field
                    v-model="reg.kontakttalrunis"
                    class="mb-3"
                    :label="copy.fields.phone"
                    :placeholder="copy.placeholders.phone"
                    prepend-inner-icon="mdi-phone-outline"
                    :rules="phoneRules"
                    variant="outlined"
                  />
                </v-col>

                <v-col v-if="reg.loma === 'klients'" cols="12" md="6">
                  <v-text-field
                    v-model="reg.lietotajvards"
                    class="mb-3"
                    :label="copy.fields.username"
                    prepend-inner-icon="mdi-at"
                    :rules="usernameRules"
                    variant="outlined"
                  />
                </v-col>

                <v-col cols="12" md="6">
                  <v-text-field
                    v-model="reg.bankas_konts"
                    class="mb-3"
                    :label="copy.fields.iban"
                    :placeholder="copy.placeholders.iban"
                    prepend-inner-icon="mdi-bank-outline"
                    :rules="ibanRules"
                    variant="outlined"
                  />
                </v-col>

                <template v-if="reg.loma === 'pakalpojumu_sniedzejs'">
                  <v-col cols="12" md="6">
                    <v-text-field
                      v-model="reg.registracijas_numurs"
                      class="mb-3"
                      :label="copy.fields.registrationNumber"
                      prepend-inner-icon="mdi-file-document-outline"
                      :rules="regNumRules"
                      variant="outlined"
                    />
                  </v-col>

                  <v-col cols="12" md="6">
                    <v-text-field
                      v-model="reg.iela"
                      class="mb-3"
                      :label="copy.fields.street"
                      prepend-inner-icon="mdi-road-variant"
                      :rules="streetRules"
                      variant="outlined"
                    />
                  </v-col>

                  <v-col cols="12" md="6">
                    <v-text-field
                      v-model="reg.majas_numurs"
                      class="mb-3"
                      :label="copy.fields.houseNumber"
                      prepend-inner-icon="mdi-home-outline"
                      :rules="houseNumberRules"
                      variant="outlined"
                    />
                  </v-col>

                  <v-col cols="12" md="6">
                    <v-text-field
                      v-model="reg.dzivokla_numurs"
                      class="mb-3"
                      :label="copy.fields.apartmentNumber"
                      prepend-inner-icon="mdi-door"
                      variant="outlined"
                    />
                  </v-col>

                  <v-col cols="12" md="6">
                    <v-text-field
                      v-model="reg.pilseta"
                      class="mb-3"
                      :label="copy.fields.city"
                      prepend-inner-icon="mdi-city-variant-outline"
                      :rules="cityRules"
                      variant="outlined"
                    />
                  </v-col>

                  <v-col cols="12" md="6">
                    <v-text-field
                      v-model="reg.pasta_indekss"
                      class="mb-3"
                      :label="copy.fields.postalCode"
                      prepend-inner-icon="mdi-mailbox-outline"
                      :rules="postalCodeRules"
                      variant="outlined"
                    />
                  </v-col>
                </template>

                <v-col cols="12" md="6">
                  <v-text-field
                    v-model="reg.password"
                    autocomplete="new-password"
                    class="mb-3"
                    :label="copy.fields.password"
                    prepend-inner-icon="mdi-lock-outline"
                    :rules="passwordRules"
                    type="password"
                    variant="outlined"
                  />
                </v-col>

                <v-col cols="12" md="6">
                  <v-text-field
                    v-model="reg.password_confirmation"
                    autocomplete="new-password"
                    class="mb-4"
                    :label="copy.fields.passwordConfirmation"
                    prepend-inner-icon="mdi-lock-check-outline"
                    :rules="passwordConfirmRules"
                    type="password"
                    variant="outlined"
                  />
                </v-col>

                <v-col cols="12">
                  <v-btn
                    block
                    class="submit-btn"
                    color="primary"
                    elevation="4"
                    :loading="loading"
                    rounded="xl"
                    size="large"
                    type="submit"
                  >
                    <v-icon start>mdi-account-plus</v-icon>
                    {{ copy.actions.register }}
                  </v-btn>
                </v-col>
              </v-row>
            </v-form>
          </div>

        </v-card-text>
      </v-card>
    </div>
  </div>
</template>

<script setup>
  import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
  import { useRoute, useRouter } from 'vue-router'
  import { apiFetch, googlePending, setAuthSession } from '@/services/auth'
  import { FORGOT_PASSWORD_ROUTE } from '@/router/paths'
  import { useLoadingState } from '@/stores/loading'
  import { useLocale } from '@/stores/locale'
  import { useNotifications } from '@/stores/notifications'

  const emit = defineEmits(['auth-success'])
  const route = useRoute()
  const router = useRouter()
  const { notifyError, notifySuccess } = useNotifications()
  const { locale, t } = useLocale()
  const copy = computed(() => t('auth'))

  const tab = ref('login')
  const loading = ref(false)
  useLoadingState(loading)
  const errorText = ref('')
  const okText = ref('')
  const googleLoading = ref(false)
  const linkPassword = ref('')
  const linkPasswordError = ref('')
  const linkEmail = computed(() => route.query.google === 'link' && googlePending.value?.purpose === 'link'
    ? googlePending.value.epasts
    : '')
  const googleError = computed(() => {
    const errors = t('google.errors')
    const status = route.query.google
    if (status === 'link' && !linkEmail.value) return errors.invalid
    return typeof status === 'string' && Object.hasOwn(errors, status) ? errors[status] : ''
  })

  function resetGoogleLoading () {
    googleLoading.value = false
  }

  onMounted(() => window.addEventListener('pageshow', resetGoogleLoading))
  onBeforeUnmount(() => window.removeEventListener('pageshow', resetGoogleLoading))

  function startGoogle () {
    googleLoading.value = true
    const query = new URLSearchParams({ lang: locale.value })
    if (tab.value === 'register') query.set('role', reg.value.loma)
    window.location.assign(`/oauth/google/start?${query}`)
  }

  async function linkGoogle () {
    loading.value = true
    errorText.value = ''
    linkPasswordError.value = ''
    try {
      const response = await apiFetch('/oauth/google/link', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ password: linkPassword.value }),
      })
      const result = await response.json().catch(() => ({}))
      if (!response.ok) {
        linkPasswordError.value = result.errors?.password?.[0] || ''
        if (!linkPasswordError.value) {
          errorText.value = response.status === 429
            ? t('common.retryLater')
            : result.message || t('google.linkError')
        }
        return
      }
      setAuthSession({ token: null, persona: result.persona })
      linkPassword.value = ''
      emit('auth-success', { google: true })
    } catch {
      errorText.value = t('common.networkError')
    } finally {
      loading.value = false
    }
  }

  async function cancelLink () {
    loading.value = true
    try {
      const response = await apiFetch('/oauth/google/cancel', { method: 'POST' })
      if (!response.ok) {
        throw new Error(t('google.cancelLinkError'))
      }
      linkPassword.value = ''
      linkPasswordError.value = ''
      errorText.value = ''
      await router.replace({ query: {} })
    } catch {
      errorText.value = t('google.cancelLinkError')
    } finally {
      loading.value = false
    }
  }

  watch(errorText, value => {
    if (value) notifyError(value)
  })

  watch(okText, value => {
    if (value) notifySuccess(value)
  })

  const loginForm = ref(null)
  const regForm = ref(null)

  const login = ref({ email: '', password: '' })

  const reg = ref({
    name: '',
    email: '',
    password: '',
    kontakttalrunis: '',
    password_confirmation: '',
    loma: 'klients',
    lietotajvards: '',
    registracijas_numurs: '',
    iela: '',
    majas_numurs: '',
    dzivokla_numurs: '',
    pilseta: '',
    pasta_indekss: '',
    bankas_konts: '',
  })

  const lomas = computed(() => [
    { title: copy.value.roles.client, value: 'klients' },
    { title: copy.value.roles.provider, value: 'pakalpojumu_sniedzejs' },
  ])

  function updateRegFields () {
    // Notīrīt laukus kad mainas lomu
    if (reg.value.loma === 'klients') {
      reg.value.registracijas_numurs = ''
      reg.value.iela = ''
      reg.value.majas_numurs = ''
      reg.value.dzivokla_numurs = ''
      reg.value.pilseta = ''
      reg.value.pasta_indekss = ''
    } else {
      reg.value.lietotajvards = ''
    }
  }

  function buildProviderAddress () {
    const parts = [
      reg.value.iela,
      reg.value.majas_numurs,
      reg.value.pilseta,
      reg.value.pasta_indekss,
      'Latvia',
    ].filter(part => part && String(part).trim().length > 0)
    return parts.join(', ')
  }

  async function geocodeAddress (address) {
    if (!address) return null
    try {
      const url = `https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(address)}`
      const response = await fetch(url)
      const data = await response.json()
      if (!Array.isArray(data) || data.length === 0) return null
      const result = data[0]
      if (!result?.lat || !result?.lon) return null
      return {
        lat: Number(result.lat),
        lng: Number(result.lon),
      }
    } catch {
      return null
    }
  }

  // IBAN validation (basic, standard mod-97 check)
  function ibanIsValid (iban) {
    if (!iban) return false
    const value = iban.replace(/\s+/g, '').toUpperCase()
    if (!/^[A-Z0-9]+$/.test(value) || value.length < 15 || value.length > 34) return false
    const rearranged = value.slice(4) + value.slice(0, 4)
    const converted = rearranged.split('').map(ch => {
      const code = ch.codePointAt(0)
      if (code >= 65 && code <= 90) return (code - 55).toString()
      return ch
    }).join('')
    let remainder = '0'
    for (let i = 0; i < converted.length; i += 7) {
      const block = remainder + converted.slice(i, i + 7)
      remainder = (BigInt(block) % 97n).toString()
    }
    return remainder === '1'
  }

  const phoneRules = [
    v => !!v || copy.value.messages.phoneRequired,
    v => (v && v.length >= 6) || copy.value.messages.phoneMin,
    v => (v && v.length <= 20) || copy.value.messages.phoneMax,
    v => !v || /^\+?[0-9 \-()]+$/.test(v) || copy.value.messages.phoneInvalid,
  ]

  const ibanRules = [
    v => !!v || copy.value.messages.ibanRequired,
    v => (v && v.length >= 15) || copy.value.messages.ibanMin,
    v => (v && v.length <= 34) || copy.value.messages.ibanMax,
    v => !v || ibanIsValid(v) || copy.value.messages.ibanInvalid,
  ]

  // Required / conditional rules
  const nameRules = [
    v => !!v || copy.value.messages.nameRequired,
    v => (v && v.trim().split(' ')[0].length >= 2) || copy.value.messages.nameMin,
  ]

  const emailRules = [
    v => !!v || copy.value.messages.emailRequired,
    v => /.+@.+\..+/.test(v) || copy.value.messages.emailInvalid,
  ]

  function hasUppercase (value) {
    return /[A-Z]/.test(value || '')
  }

  function hasSpecialCharacter (value) {
    return /[^A-Za-z0-9]/.test(value || '')
  }

  const passwordRules = [
    v => !!v || copy.value.messages.passwordRequired,
    v => (v && v.length >= 8) || copy.value.messages.passwordMin,
    v => hasUppercase(v) || copy.value.messages.passwordUppercase,
    v => hasSpecialCharacter(v) || copy.value.messages.passwordSpecial,
  ]

  const passwordConfirmRules = [
    v => !!v || copy.value.messages.passwordConfirmRequired,
    v => v === reg.value.password || copy.value.messages.passwordMismatch,
  ]

  const usernameRules = [
    v => (reg.value.loma !== 'klients') || (!!v && v.length >= 3) || copy.value.messages.usernameMin,
  ]

  const regNumRules = [
    v => (reg.value.loma !== 'pakalpojumu_sniedzejs') || !!v || copy.value.messages.regNumRequired,
  ]

  const streetRules = [
    v => (reg.value.loma !== 'pakalpojumu_sniedzejs') || !!v || copy.value.messages.streetRequired,
  ]

  const houseNumberRules = [
    v => (reg.value.loma !== 'pakalpojumu_sniedzejs') || !!v || copy.value.messages.houseRequired,
  ]

  const cityRules = [
    v => (reg.value.loma !== 'pakalpojumu_sniedzejs') || !!v || copy.value.messages.cityRequired,
  ]

  const postalCodeRules = [
    v => (reg.value.loma !== 'pakalpojumu_sniedzejs') || !!v || copy.value.messages.postalRequired,
  ]

  async function submitRegister () {
    errorText.value = ''
    okText.value = ''
    loading.value = true
    // Manuāla validācija tikai obligātajiem laukiem
    const errors = []
    if (!reg.value.name || reg.value.name.trim().length < 2) errors.push(copy.value.messages.manualName)
    if (!reg.value.email || !/.+@.+\..+/.test(reg.value.email)) errors.push(copy.value.messages.manualEmail)
    if (!reg.value.password || reg.value.password.length < 8 || !hasUppercase(reg.value.password) || !hasSpecialCharacter(reg.value.password)) errors.push(copy.value.messages.manualPassword)
    if (!reg.value.password_confirmation || reg.value.password !== reg.value.password_confirmation) errors.push(copy.value.messages.manualPasswordConfirm)
    if (!reg.value.kontakttalrunis || reg.value.kontakttalrunis.length < 6) errors.push(copy.value.messages.manualPhone)
    if (!reg.value.bankas_konts || reg.value.bankas_konts.length < 15) errors.push(copy.value.messages.manualIban)
    if (reg.value.loma === 'klients' && (!reg.value.lietotajvards || reg.value.lietotajvards.length < 3)) errors.push(copy.value.messages.manualUsername)
    if (reg.value.loma === 'pakalpojumu_sniedzejs') {
      if (!reg.value.registracijas_numurs) errors.push(copy.value.messages.manualRegNum)
      if (!reg.value.iela) errors.push(copy.value.messages.manualStreet)
      if (!reg.value.majas_numurs) errors.push(copy.value.messages.manualHouse)
      if (!reg.value.pilseta) errors.push(copy.value.messages.manualCity)
      if (!reg.value.pasta_indekss) errors.push(copy.value.messages.manualPostal)
    }
    if (errors.length > 0) {
      errorText.value = errors.join(' ')
      loading.value = false
      return
    }
    try {
      const payload = {
        name: reg.value.name,
        kontakttalrunis: reg.value.kontakttalrunis,
        email: reg.value.email,
        password: reg.value.password,
        password_confirmation: reg.value.password_confirmation,
        loma: reg.value.loma,
        bankas_konts: reg.value.bankas_konts,
      }

      if (reg.value.loma === 'klients') {
        payload.lietotajvards = reg.value.lietotajvards
      } else {
        const address = buildProviderAddress()
        const coords = await geocodeAddress(address)

        payload.registracijas_numurs = reg.value.registracijas_numurs
        payload.iela = reg.value.iela
        payload.majas_numurs = reg.value.majas_numurs
        if (reg.value.dzivokla_numurs) payload.dzivokla_numurs = reg.value.dzivokla_numurs
        payload.pilseta = reg.value.pilseta
        payload.pasta_indekss = reg.value.pasta_indekss
        if (coords) {
          payload.latitude = coords.lat
          payload.longitude = coords.lng
        }
      }

      const r = await apiFetch('/api/auth/register', {
        auth: false,
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify(payload),
      })

      const data = await r.json().catch(() => ({}))

      if (!r.ok) {
        if (data?.errors && Object.keys(data.errors).length > 0) {
          const allErrors = Object.values(data.errors).flat().filter(Boolean)
          throw new Error(allErrors.join('. '))
        }
        throw new Error(data?.message || copy.value.messages.registerFailed)
      }

      setAuthSession({ token: data.token, persona: data.persona })

      okText.value = copy.value.messages.registered

      // Notīrīt formu
      reg.value = {
        name: '',
        email: '',
        kontakttalrunis: '',
        password: '',
        password_confirmation: '',
        loma: 'klients',
        lietotajvards: '',
        registracijas_numurs: '',
        iela: '',
        majas_numurs: '',
        dzivokla_numurs: '',
        pilseta: '',
        pasta_indekss: '',
        bankas_konts: '',
      }

      // Informēt parent par sekmīgu reģistrāciju
      setTimeout(() => {
        emit('auth-success')
      }, 1500)
    } catch (error) {
      errorText.value = error?.message || copy.value.messages.serverConnection
    } finally {
      loading.value = false
    }
  }

  async function submitLogin () {
    errorText.value = ''
    okText.value = ''
    loading.value = true
    try {
      const r = await apiFetch('/api/auth/login', {
        auth: false,
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify(login.value),
      })

      const data = await r.json().catch(() => ({}))

      if (!r.ok) {
        const msg = data?.message || copy.value.messages.loginFailed
        throw new Error(msg)
      }

      setAuthSession({ token: data.token, persona: data.persona })

      okText.value = copy.value.messages.loggedIn

      // Notīrīt formu
      login.value = { email: '', password: '' }

      // Informēt parent par sekmīgu pieslēgšanos
      setTimeout(() => {
        emit('auth-success')
      }, 1500)
    } catch (error) {
      errorText.value = error?.message || copy.value.messages.serverConnection
    } finally {
      loading.value = false
    }
  }
</script>

<style scoped>
.auth-page { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); min-height: calc(100dvh - 72px); background: var(--er-bg); color: var(--er-text); }
.auth-visual { position: relative; isolation: isolate; overflow: hidden; min-height: 720px; background: var(--er-night, #172329); color: #f3f7f7; }
.auth-visual__image, .auth-visual__overlay { position: absolute; inset: 0; width: 100%; height: 100%; z-index: -1; }
.auth-visual__image { object-fit: cover; opacity: .85; }
.auth-visual__overlay { background: linear-gradient(180deg, rgba(15, 27, 34, .2), rgba(15, 27, 34, .6) 50%, rgba(15, 27, 34, .93)); }
.auth-visual__copy { position: sticky; top: calc(100vh - 360px); max-width: 580px; padding: 48px; }
.auth-visual__brand { display: inline-block; margin-bottom: 24px; font-family: var(--er-display-font, 'Archivo', sans-serif); color: var(--er-primary); font-weight: 900; letter-spacing: -.03em; font-size: 1.5rem; }
.auth-visual__copy p { font-family: var(--er-display-font, 'Archivo', sans-serif); font-weight: 900; font-size: clamp(2rem, 3vw, 3rem); line-height: 1.12; letter-spacing: -.03em; text-wrap: balance; }
.auth-visual__copy > span:last-child { display: block; color: #b4c4cb; margin-top: 24px; font-size: .9rem; }
.auth-form-shell { display: flex; align-items: center; justify-content: center; min-width: 0; padding: 64px 32px; }
.auth-form-card { width: 100%; max-width: 560px; background: transparent; border: 0; border-radius: 0; box-shadow: none !important; color: var(--er-text); overflow: visible; }
.auth-form-content { padding: 0 !important; }
.auth-kicker { margin-bottom: 12px; font-size: .75rem; font-weight: 800; color: var(--er-primary-strong); text-transform: uppercase; letter-spacing: .12em; }
.auth-title { font-family: var(--er-display-font, 'Archivo', sans-serif); font-size: clamp(1.8rem, 3vw, 2.5rem); font-weight: 900; letter-spacing: -.035em; line-height: 1.1; }
.auth-tabs { height: auto !important; margin-block: 28px 32px; padding: 5px; border: 0; border-radius: 999px; background: var(--er-panel-soft); }
.auth-tabs :deep(.v-slide-group__content) { gap: 4px; }
.auth-tabs :deep(.v-tab) { height: 46px !important; border-radius: 999px !important; color: var(--er-text-muted) !important; font-weight: 800; flex: 1 1 0; min-width: 0; }
.auth-tabs :deep(.v-tab--selected) { background: var(--er-primary-strong) !important; color: var(--er-on-primary) !important; }
.auth-tabs :deep(.v-tab__slider) { display: none; }
.auth-role-picker { margin-bottom: 24px; }
.auth-role-picker > p { font-size: .85rem; font-weight: 800; margin-bottom: 10px; }
.auth-role-options { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; height: auto !important; overflow: visible; border-radius: 0; }
.auth-role-options :deep(.v-btn) { min-height: 60px; height: auto; white-space: normal; border: 2px solid var(--er-stroke) !important; border-radius: 18px !important; color: var(--er-text); background: transparent !important; font-weight: 800; }
.auth-role-options :deep(.v-btn--active) { border-color: var(--er-primary) !important; background: var(--er-primary-soft) !important; color: var(--er-primary-strong); }
.auth-role-options :deep(.v-btn__content) { white-space: normal; line-height: 1.4; padding-block: 12px; }
.auth-feedback { margin-bottom: 24px; border-radius: 16px; font-size: .875rem; }
.auth-recovery-link { display: table; color: var(--er-primary-strong); font-weight: 700; margin: -16px 0 16px auto; padding-block: 8px; }
.auth-form-card :deep(.v-field) { min-height: 52px; border-radius: 12px !important; background: var(--er-surface-strong); box-shadow: none; }
.auth-form-card :deep(.v-field__outline) { --v-field-border-opacity: 1; color: var(--er-stroke-strong); }
.auth-form-card :deep(.v-field--focused .v-field__outline) { color: var(--er-primary); }
.auth-form-card :deep(.v-field__prepend-inner .v-icon) { color: var(--er-primary); opacity: .8; }
.auth-form-card :deep(.v-field__input) { font-size: .925rem; }
.auth-form-card :deep(.v-input__details) { padding-inline: 4px; }
.auth-form-card :deep(.v-messages) { line-height: 1.5; }
.submit-btn { height: 52px !important; border-radius: 12px !important; font-weight: 800; box-shadow: none !important; }
.google-sign-in { background: #fff !important; color: #1f1f1f !important; border-color: #747775 !important; }
.google-divider { display: flex; align-items: center; gap: 12px; margin-block: 20px; color: var(--er-text-muted); font-size: .8rem; }
.google-divider::before, .google-divider::after { content: ''; flex: 1; border-top: 1px solid var(--er-stroke); }
.register-grid { align-items: start; column-gap: 0; }
.register-grid :deep(.v-input) { width: 100%; }
@media (max-width: 1279px) {
  .auth-form-shell { padding: 56px 28px; }
  .auth-visual__copy { padding: 36px; }
}
@media (max-width: 959px) {
  .auth-page { grid-template-columns: 1fr; }
  .auth-visual { display: none; }
  .auth-form-shell { padding: 56px 24px; }
  .auth-form-card { max-width: 640px; }
}
@media (max-width: 599px) {
  .auth-form-shell { padding: 40px 20px; }
  .auth-tabs { margin-top: 24px; }
  .auth-role-options { gap: 8px; }
  .auth-role-options :deep(.v-btn) { padding-inline: 8px; font-size: .8rem; }
  .auth-form-card :deep(.v-input) { margin-bottom: 8px !important; }
}
</style>
