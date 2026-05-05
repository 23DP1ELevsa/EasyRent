<template>
  <div class="bg">
    <v-container class="fill-height py-12 py-md-16">
      <div class="page-wrap">
        <v-row align="stretch" justify="center" class="auth-layout">
          <v-col cols="12" sm="11" md="9" lg="8" xl="7">
            <v-card class="surface auth-form-card" elevation="12">
              <v-card-text class="pa-6 pa-md-8">
                <div class="d-flex align-center justify-space-between mb-5">
                  <div>
                    <div class="auth-title">{{ copy.title }}</div>
                  </div>
                </div>

                <v-tabs v-model="tab" grow class="auth-tabs" color="primary">
                  <v-tab value="login">{{ copy.tabs.login }}</v-tab>
                  <v-tab value="register">{{ copy.tabs.register }}</v-tab>
                </v-tabs>

                <v-divider class="my-6" />

                <div v-if="tab === 'login'">
                  <v-form ref="loginForm" @submit.prevent="submitLogin">
                    <v-text-field v-model="login.email" :label="copy.fields.email" variant="outlined" class="mb-3" prepend-inner-icon="mdi-email-outline" />
                    <v-text-field v-model="login.password" :label="copy.fields.password" variant="outlined" type="password" class="mb-4" prepend-inner-icon="mdi-lock-outline" />

                    <v-btn type="submit" block size="large" rounded="xl" elevation="4" :loading="loading" class="submit-btn">
                      <v-icon start>mdi-login</v-icon>
                      {{ copy.actions.login }}
                    </v-btn>
                  </v-form>
                </div>

                <div v-else>
                  <v-form ref="regForm" @submit.prevent="submitRegister">
                    <v-row dense class="register-grid">
                      <v-col cols="12" md="6">
                        <v-text-field v-model="reg.name" :label="copy.fields.fullName" variant="outlined" class="mb-3" :rules="nameRules" prepend-inner-icon="mdi-account-outline" />
                      </v-col>

                      <v-col cols="12" md="6">
                        <v-text-field v-model="reg.email" :label="copy.fields.email" variant="outlined" class="mb-3" :rules="emailRules" prepend-inner-icon="mdi-email-outline" />
                      </v-col>

                      <v-col cols="12" md="6">
                        <v-text-field
                          v-model="reg.kontakttalrunis"
                          :label="copy.fields.phone"
                          variant="outlined"
                          class="mb-3"
                          :placeholder="copy.placeholders.phone"
                          :rules="phoneRules"
                          prepend-inner-icon="mdi-phone-outline"
                        />
                      </v-col>

                      <v-col cols="12" md="6">
                        <v-select
                          v-model="reg.loma"
                          :items="lomas"
                          item-title="title"
                          item-value="value"
                          :label="copy.fields.role"
                          variant="outlined"
                          class="mb-3"
                          prepend-inner-icon="mdi-account-switch-outline"
                          @update:model-value="updateRegFields"
                        />
                      </v-col>

                      <v-col v-if="reg.loma === 'klients'" cols="12" md="6">
                        <v-text-field
                          v-model="reg.lietotajvards"
                          :label="copy.fields.username"
                          variant="outlined"
                          class="mb-3"
                          :rules="usernameRules"
                          prepend-inner-icon="mdi-at"
                        />
                      </v-col>

                      <v-col :cols="12" :md="reg.loma === 'klients' ? 6 : 12">
                        <v-text-field
                          v-model="reg.bankas_konts"
                          :label="copy.fields.iban"
                          variant="outlined"
                          class="mb-3"
                          :placeholder="copy.placeholders.iban"
                          :rules="ibanRules"
                          prepend-inner-icon="mdi-bank-outline"
                        />
                      </v-col>

                      <template v-if="reg.loma === 'pakalpojumu_sniedzejs'">
                        <v-col cols="12" md="6">
                          <v-text-field
                            v-model="reg.registracijas_numurs"
                            :label="copy.fields.registrationNumber"
                            variant="outlined"
                            class="mb-3"
                            :rules="regNumRules"
                            prepend-inner-icon="mdi-file-document-outline"
                          />
                        </v-col>

                        <v-col cols="12" md="6">
                          <v-text-field
                            v-model="reg.iela"
                            :label="copy.fields.street"
                            variant="outlined"
                            class="mb-3"
                            :rules="streetRules"
                            prepend-inner-icon="mdi-road-variant"
                          />
                        </v-col>

                        <v-col cols="12" md="6">
                          <v-text-field
                            v-model="reg.majas_numurs"
                            :label="copy.fields.houseNumber"
                            variant="outlined"
                            class="mb-3"
                            :rules="houseNumberRules"
                            prepend-inner-icon="mdi-home-outline"
                          />
                        </v-col>

                        <v-col cols="12" md="6">
                          <v-text-field
                            v-model="reg.dzivokla_numurs"
                            :label="copy.fields.apartmentNumber"
                            variant="outlined"
                            class="mb-3"
                            prepend-inner-icon="mdi-door"
                          />
                        </v-col>

                        <v-col cols="12" md="6">
                          <v-text-field
                            v-model="reg.pilseta"
                            :label="copy.fields.city"
                            variant="outlined"
                            class="mb-3"
                            :rules="cityRules"
                            prepend-inner-icon="mdi-city-variant-outline"
                          />
                        </v-col>

                        <v-col cols="12" md="6">
                          <v-text-field
                            v-model="reg.pasta_indekss"
                            :label="copy.fields.postalCode"
                            variant="outlined"
                            class="mb-3"
                            :rules="postalCodeRules"
                            prepend-inner-icon="mdi-mailbox-outline"
                          />
                        </v-col>
                      </template>

                      <v-col cols="12" md="6">
                        <v-text-field v-model="reg.password" :label="copy.fields.password" variant="outlined" type="password" class="mb-3" :rules="passwordRules" prepend-inner-icon="mdi-lock-outline" />
                      </v-col>

                      <v-col cols="12" md="6">
                        <v-text-field
                          v-model="reg.password_confirmation"
                          :label="copy.fields.passwordConfirmation"
                          variant="outlined"
                          type="password"
                          class="mb-4"
                          :rules="passwordConfirmRules"
                          prepend-inner-icon="mdi-lock-check-outline"
                        />
                      </v-col>

                      <v-col cols="12">
                        <v-btn type="submit" block size="large" rounded="xl" elevation="4" :loading="loading" class="submit-btn">
                          <v-icon start>mdi-account-plus</v-icon>
                          {{ copy.actions.register }}
                        </v-btn>
                      </v-col>
                    </v-row>
                  </v-form>
                </div>

              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </div>
    </v-container>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useLocale } from '@/stores/locale'
import { useNotifications } from '@/stores/notifications'
import { getApiBase, setAuthSession } from '@/services/auth'

const emit = defineEmits(['auth-success'])
const { notifyError, notifySuccess } = useNotifications()
const { t } = useLocale()
const copy = computed(() => t('auth'))

const tab = ref('login')
const loading = ref(false)
const errorText = ref('')
const okText = ref('')

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

function updateRegFields() {
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

function buildProviderAddress() {
  const parts = [
    reg.value.iela,
    reg.value.majas_numurs,
    reg.value.pilseta,
    reg.value.pasta_indekss,
    'Latvia',
  ].filter(part => part && String(part).trim().length > 0)
  return parts.join(', ')
}

async function geocodeAddress(address) {
  if (!address) return null
  try {
    const url = `https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(address)}`
    const response = await fetch(url)
    const data = await response.json()
    if (!Array.isArray(data) || !data.length) return null
    const result = data[0]
    if (!result?.lat || !result?.lon) return null
    return {
      lat: Number(result.lat),
      lng: Number(result.lon),
    }
  } catch (error) {
    return null
  }
}

// IBAN validation (basic, standard mod-97 check)
function ibanIsValid(iban) {
  if (!iban) return false
  const value = iban.replace(/\s+/g, '').toUpperCase()
  if (!/^[A-Z0-9]+$/.test(value) || value.length < 15 || value.length > 34) return false
  const rearranged = value.slice(4) + value.slice(0, 4)
  const converted = rearranged.split('').map(ch => {
    const code = ch.charCodeAt(0)
    if (code >= 65 && code <= 90) return (code - 55).toString()
    return ch
  }).join('')
  let remainder = '0'
  for (let i = 0; i < converted.length; i += 7) {
    const block = remainder + converted.substr(i, 7)
    remainder = (BigInt(block) % 97n).toString()
  }
  return remainder === '1'
}

const phoneRules = [
  v => !!v || copy.value.messages.phoneRequired,
  v => (v && v.length >= 6) || copy.value.messages.phoneMin,
  v => (v && v.length <= 20) || copy.value.messages.phoneMax,
  v => !v || /^\+?[0-9 \-()]+$/.test(v) || copy.value.messages.phoneInvalid
]

const ibanRules = [
  v => !!v || copy.value.messages.ibanRequired,
  v => (v && v.length >= 15) || copy.value.messages.ibanMin,
  v => (v && v.length <= 34) || copy.value.messages.ibanMax,
  v => !v || ibanIsValid(v) || copy.value.messages.ibanInvalid
]

// Required / conditional rules
const nameRules = [
  v => !!v || copy.value.messages.nameRequired,
  v => (v && v.trim().split(' ')[0].length >= 2) || copy.value.messages.nameMin
]

const emailRules = [
  v => !!v || copy.value.messages.emailRequired,
  v => /.+@.+\..+/.test(v) || copy.value.messages.emailInvalid
]

function hasUppercase(value) {
  return /[A-Z]/.test(value || '')
}

function hasSpecialCharacter(value) {
  return /[^A-Za-z0-9]/.test(value || '')
}

const passwordRules = [
  v => !!v || copy.value.messages.passwordRequired,
  v => (v && v.length >= 8) || copy.value.messages.passwordMin,
  v => hasUppercase(v) || copy.value.messages.passwordUppercase,
  v => hasSpecialCharacter(v) || copy.value.messages.passwordSpecial
]

const passwordConfirmRules = [
  v => !!v || copy.value.messages.passwordConfirmRequired,
  v => v === reg.value.password || copy.value.messages.passwordMismatch
]

const usernameRules = [
  v => (reg.value.loma !== 'klients') || (!!v && v.length >= 3) || copy.value.messages.usernameMin
]

const regNumRules = [
  v => (reg.value.loma !== 'pakalpojumu_sniedzejs') || !!v || copy.value.messages.regNumRequired
]

const streetRules = [
  v => (reg.value.loma !== 'pakalpojumu_sniedzejs') || !!v || copy.value.messages.streetRequired
]

const houseNumberRules = [
  v => (reg.value.loma !== 'pakalpojumu_sniedzejs') || !!v || copy.value.messages.houseRequired
]

const cityRules = [
  v => (reg.value.loma !== 'pakalpojumu_sniedzejs') || !!v || copy.value.messages.cityRequired
]

const postalCodeRules = [
  v => (reg.value.loma !== 'pakalpojumu_sniedzejs') || !!v || copy.value.messages.postalRequired
]

async function submitRegister() {
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
  if (errors.length) {
    errorText.value = errors.join(' ')
    loading.value = false
    return
  }
  try {
    const API = getApiBase()

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

    const r = await fetch(`${API}/api/auth/register`, {
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
        const allErrors = Object.values(data.errors).flat().filter(e => e)
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
  } catch (e) {
    errorText.value = e?.message || copy.value.messages.serverConnection
  } finally {
    loading.value = false
  }
}

async function submitLogin() {
  errorText.value = ''
  okText.value = ''
  loading.value = true
  try {
    const API = getApiBase()

    const r = await fetch(`${API}/api/auth/login`, {
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
  } catch (e) {
    errorText.value = e?.message || copy.value.messages.serverConnection
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.bg {
  position: relative;
  min-height: calc(100vh - 72px - 64px);
  background:
    linear-gradient(180deg, color-mix(in srgb, var(--er-bg-soft) 92%, transparent), color-mix(in srgb, var(--er-bg) 94%, transparent)),
    linear-gradient(135deg, color-mix(in srgb, var(--er-primary) 7%, transparent), transparent 42%),
    linear-gradient(225deg, color-mix(in srgb, var(--er-secondary) 8%, transparent), transparent 36%);
}

.page-wrap { width: min(1200px, 100%); margin: 0 auto; }

.auth-layout {
  row-gap: 20px;
}

.auth-title {
  font-family: 'Syne', 'Plus Jakarta Sans', sans-serif;
  font-size: clamp(2rem, 3vw, 3.25rem);
  line-height: 0.95;
  letter-spacing: -0.05em;
}

.surface {
  background: var(--er-surface);
  border: 1px solid var(--er-stroke);
  border-radius: 34px;
  color: var(--er-text);
  backdrop-filter: blur(18px);
  box-shadow: var(--er-shadow-lg);
}

.auth-form-card {
  position: relative;
  overflow: hidden;
}

.auth-form-card::before {
  content: '';
  position: absolute;
  inset: -120px auto auto -80px;
  width: 220px;
  height: 220px;
  border-radius: 999px;
  background: rgba(15, 118, 110, 0.08);
  filter: blur(10px);
}

.auth-form-card::after {
  content: none;
}

.auth-tabs {
  border-radius: 18px;
  border: 1px solid var(--er-stroke);
  background: var(--er-panel-soft);
  padding: 4px;
}

.auth-tabs :deep(.v-btn__content) {
  line-height: -3;
  transform: translateY(-4px);
}

.submit-btn {
  min-height: 46px;
  box-shadow: var(--er-auth-btn-shadow);
}

.register-grid {
  align-items: start;
}

.register-grid :deep(.v-input) {
  width: 100%;
}

@media (max-width: 1280px) {
  .page-wrap {
    width: min(1060px, 100%);
  }
}

@media (max-width: 960px) {
  .auth-title {
    font-size: clamp(1.8rem, 9vw, 2.8rem);
  }
}
</style>
