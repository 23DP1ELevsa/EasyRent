<template>
  <div class="bg">
    <v-container class="fill-height py-12">
      <div class="page-wrap">

        <!-- AUTH: Login/Register -->
        <v-row justify="center">
          <v-col cols="12" sm="10" md="7" lg="5">
            <v-card class="surface" elevation="12">
              <v-card-text class="pa-6 pa-md-8">

                <div class="text-h5 font-weight-bold mb-2">Autorizācija</div>
                <div class="text-body-2 opacity-80 mb-6">Ieiet vai izveidot jaunu profilu.</div>

                <v-tabs v-model="tab" grow>
                  <v-tab value="login">Pieslēgties</v-tab>
                  <v-tab value="register">Reģistrēties</v-tab>
                </v-tabs>

                <v-divider class="my-6" />

                <!-- LOGIN -->
                <div v-if="tab === 'login'">
                  <v-form ref="loginForm" @submit.prevent="submitLogin">
                    <v-text-field v-model="login.email" label="E-pasts" variant="outlined" class="mb-3" />
                    <v-text-field v-model="login.password" label="Parole" variant="outlined" type="password" class="mb-4" />

                    <v-btn type="submit" block size="large" rounded="xl" elevation="6" :loading="loading">
                      <v-icon start>mdi-login</v-icon>
                      Pieslēgties
                    </v-btn>
                  </v-form>
                </div>

                <!-- REGISTER -->
                <div v-else>
                  <v-form ref="regForm" @submit.prevent="submitRegister">
                    <v-text-field v-model="reg.name" label="Vārds Uzvārds" variant="outlined" class="mb-3" :rules="nameRules" />
                    <v-text-field v-model="reg.email" label="E-pasts" variant="outlined" class="mb-3" :rules="emailRules" />
                    <v-text-field 
                      v-model="reg.kontakttalrunis" 
                      label="Tālrunis" 
                      variant="outlined" 
                      class="mb-3"
                      placeholder="+371 26123456"
                      :rules="phoneRules"
                    />
                    <v-select
                      v-model="reg.loma"
                      :items="lomas"
                      item-title="title"
                      item-value="value"
                      label="Loma"
                      variant="outlined"
                      class="mb-3"
                      @update:model-value="updateRegFields"
                    />

                    <!-- Klients fields -->
                    <v-text-field 
                      v-if="reg.loma === 'klients'"
                      v-model="reg.lietotajvards" 
                      label="Lietotājvārds" 
                      variant="outlined" 
                      class="mb-3" 
                      :rules="usernameRules"
                    />

                    <!-- Bankas konts (visdiem) -->
                    <v-text-field 
                      v-model="reg.bankas_konts" 
                      label="Banka konta numurs (IBAN)" 
                      variant="outlined" 
                      class="mb-3"
                      placeholder="LV00ABCD1234567890123"
                      :rules="ibanRules"
                    />

                    <!-- Pakalpojumu sniedzējs fields -->
                    <template v-if="reg.loma === 'pakalpojumu_sniedzejs'">
                      <v-text-field 
                        v-model="reg.registracijas_numurs" 
                        label="Reģistrācijas numurs" 
                        variant="outlined" 
                        class="mb-3" 
                        :rules="regNumRules"
                      />
                      <v-text-field 
                        v-model="reg.iela" 
                        label="Iela" 
                        variant="outlined" 
                        class="mb-3" 
                        :rules="streetRules"
                      />
                      <v-text-field 
                        v-model="reg.majas_numurs" 
                        label="Mājas numurs" 
                        variant="outlined" 
                        class="mb-3" 
                        :rules="houseNumberRules"
                      />
                      <v-text-field 
                        v-model="reg.dzivokla_numurs" 
                        label="Dzīvokļa numurs (neobligāts)" 
                        variant="outlined" 
                        class="mb-3"
                      />
                      <v-text-field 
                        v-model="reg.pilseta" 
                        label="Pilsēta" 
                        variant="outlined" 
                        class="mb-3" 
                        :rules="cityRules"
                      />
                      <v-text-field 
                        v-model="reg.pasta_indekss" 
                        label="Pasta indekss" 
                        variant="outlined" 
                        class="mb-3" 
                        :rules="postalCodeRules"
                      />
                    </template>

                    <v-text-field v-model="reg.password" label="Parole" variant="outlined" type="password" class="mb-3" :rules="passwordRules" />
                    <v-text-field
                      v-model="reg.password_confirmation"
                      label="Parole vēlreiz"
                      variant="outlined"
                      type="password"
                      class="mb-4"
                      :rules="passwordConfirmRules"
                    />

                    <v-btn type="submit" block size="large" rounded="xl" elevation="6" :loading="loading">
                      <v-icon start>mdi-account-plus</v-icon>
                      Reģistrēties
                    </v-btn>
                  </v-form>
                </div>

                <v-alert v-if="errorText" type="error" variant="tonal" class="mt-6">
                  <div v-html="errorText.split('. ').filter(e => e).map(e => e + (e.endsWith('.') ? '' : '.')).join('<br/>')"></div>
                </v-alert>

                <v-alert v-if="okText" type="success" variant="tonal" class="mt-6">
                  {{ okText }}
                </v-alert>

              </v-card-text>
              </v-card>
            </v-col>
        </v-row>

      </div>
    </v-container>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const emit = defineEmits(['auth-success'])

const tab = ref('login')
const loading = ref(false)
const errorText = ref('')
const okText = ref('')

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

const lomas = [
  { title: 'Klients', value: 'klients' },
  { title: 'Pakalpojumu sniedzējs', value: 'pakalpojumu_sniedzejs' },
]

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

function getApiBase() {
  return import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000'
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
  v => !!v || 'Tālrunis ir obligāts',
  v => (v && v.length >= 6) || 'Tālrunim jābūt vismaz 6 simbolu garam',
  v => (v && v.length <= 20) || 'Tālruņa garums nedrīkst pārsniegt 20 simbolus',
  v => !v || /^\+?[0-9 \-()]+$/.test(v) || 'Ievadiet derīgu telefona numuru (piem., +371 26123456)'
]

const ibanRules = [
  v => !!v || 'IBAN ir obligāts',
  v => (v && v.length >= 15) || 'IBAN jābūt vismaz 15 simbolu garam',
  v => (v && v.length <= 34) || 'IBAN nedrīkst būt garāks par 34 simboliem',
  v => !v || ibanIsValid(v) || 'Ievadiet derīgu IBAN'
]

// Required / conditional rules
const nameRules = [
  v => !!v || 'Vārds un uzvārds ir obligāts',
  v => (v && v.trim().split(' ')[0].length >= 2) || 'Vārdam jābūt vismaz 2 simbolu garam'
]

const emailRules = [
  v => !!v || 'E-pasts ir obligāts',
  v => /.+@.+\..+/.test(v) || 'Ievadiet derīgu e-pasta adresi'
]

const passwordRules = [
  v => !!v || 'Parole ir obligāta',
  v => (v && v.length >= 8) || 'Parolei jābūt vismaz 8 simbolu garai'
]

const passwordConfirmRules = [
  v => !!v || 'Lūdzu, apstipriniet paroli',
  v => v === reg.value.password || 'Paroles nesakrīt'
]

const usernameRules = [
  v => (reg.value.loma !== 'klients') || (!!v && v.length >= 3) || 'Lietotājvārdam jābūt vismaz 3 simbolu garam'
]

const regNumRules = [
  v => (reg.value.loma !== 'pakalpojumu_sniedzejs') || !!v || 'Reģistrācijas numurs ir obligāts'
]

const streetRules = [
  v => (reg.value.loma !== 'pakalpojumu_sniedzejs') || !!v || 'Iela ir obligāta'
]

const houseNumberRules = [
  v => (reg.value.loma !== 'pakalpojumu_sniedzejs') || !!v || 'Mājas numurs ir obligāts'
]

const cityRules = [
  v => (reg.value.loma !== 'pakalpojumu_sniedzejs') || !!v || 'Pilsēta ir obligāta'
]

const postalCodeRules = [
  v => (reg.value.loma !== 'pakalpojumu_sniedzejs') || !!v || 'Pasta indekss ir obligāts'
]

async function submitRegister() {
  errorText.value = ''
  okText.value = ''
  loading.value = true
  // Manuāla validācija tikai obligātajiem laukiem
  const errors = []
  if (!reg.value.name || reg.value.name.trim().length < 2) errors.push('Vārds un uzvārds ir obligāts (vismaz 2 simboli).')
  if (!reg.value.email || !/.+@.+\..+/.test(reg.value.email)) errors.push('Ievadiet derīgu e-pasta adresi.')
  if (!reg.value.password || reg.value.password.length < 8) errors.push('Parolei jābūt vismaz 8 simbolu garai.')
  if (!reg.value.password_confirmation || reg.value.password !== reg.value.password_confirmation) errors.push('Paroles nesakrīt vai nav apstiprinātas.')
  if (!reg.value.kontakttalrunis || reg.value.kontakttalrunis.length < 6) errors.push('Tālrunis ir obligāts (vismaz 6 simboli).')
  if (!reg.value.bankas_konts || reg.value.bankas_konts.length < 15) errors.push('IBAN ir obligāts.')
  if (reg.value.loma === 'klients' && (!reg.value.lietotajvards || reg.value.lietotajvards.length < 3)) errors.push('Lietotājvārds ir obligāts (vismaz 3 simboli).')
  if (reg.value.loma === 'pakalpojumu_sniedzejs') {
    if (!reg.value.registracijas_numurs) errors.push('Reģistrācijas numurs ir obligāts.')
    if (!reg.value.iela) errors.push('Iela ir obligāta.')
    if (!reg.value.majas_numurs) errors.push('Mājas numurs ir obligāts.')
    if (!reg.value.pilseta) errors.push('Pilsēta ir obligāta.')
    if (!reg.value.pasta_indekss) errors.push('Pasta indekss ir obligāts.')
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

    console.log('REG PAYLOAD:', payload)

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
      throw new Error(data?.message || 'Neizdevās reģistrēties')
    }

    localStorage.setItem('token', data.token)
    localStorage.setItem('user', JSON.stringify(data.persona))
    
    okText.value = 'Reģistrācija veiksmīga!'
    
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
    errorText.value = e?.message || 'Kļūda: Neizdevās savienot ar serveri'
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

    console.log('LOGIN PAYLOAD:', login.value)

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
      const msg = data?.message || 'Neizdevās pieslēgties'
      throw new Error(msg)
    }

    localStorage.setItem('token', data.token)
    localStorage.setItem('user', JSON.stringify(data.persona))
    
    okText.value = 'Pieslēgšanās veiksmīga!'

    // Notīrīt formu
    login.value = { email: '', password: '' }

    // Informēt parent par sekmīgu pieslēgšanos
    setTimeout(() => {
      emit('auth-success')
    }, 1500)
  } catch (e) {
    errorText.value = e?.message || 'Kļūda: Neizdevās savienot ar serveri'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.bg {
  min-height: calc(100vh - 72px - 64px);
  background: radial-gradient(1200px circle at 10% 10%, rgba(255,255,255,0.12), transparent 45%),
              radial-gradient(900px circle at 90% 20%, rgba(255,255,255,0.10), transparent 40%),
              linear-gradient(135deg, #0f172a, #111827, #0b1020);
}

.page-wrap { width: min(1200px, 100%); margin: 0 auto; }

.surface {
  background: rgba(255, 255, 255, 0.94);
  border: 1px solid rgba(15, 23, 42, 0.08);
  color: #0f172a;
  backdrop-filter: blur(6px);
}
</style>
