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
                    <v-text-field v-model="reg.name" label="Vārds Uzvārds" variant="outlined" class="mb-3" />
                    <v-text-field v-model="reg.email" label="E-pasts" variant="outlined" class="mb-3" />
                    <v-text-field 
                      v-model="reg.kontakttalrunis" 
                      label="Tālrunis" 
                      variant="outlined" 
                      class="mb-3"
                      placeholder="+371 26123456"
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
                    />

                    <!-- Bankas konts (visdiem) -->
                    <v-text-field 
                      v-model="reg.bankas_konts" 
                      label="Banka konta numurs (IBAN)" 
                      variant="outlined" 
                      class="mb-3"
                      placeholder="LV00ABCD1234567890123"
                    />

                    <!-- Pakalpojumu sniedzējs fields -->
                    <template v-if="reg.loma === 'pakalpojumu_sniedzejs'">
                      <v-text-field 
                        v-model="reg.registracijas_numurs" 
                        label="Reģistrācijas numurs" 
                        variant="outlined" 
                        class="mb-3" 
                      />
                      <v-text-field 
                        v-model="reg.atrasanas_adrese" 
                        label="Atrašanās adrese" 
                        variant="outlined" 
                        class="mb-3" 
                      />
                    </template>

                    <v-text-field v-model="reg.password" label="Parole" variant="outlined" type="password" class="mb-3" />
                    <v-text-field
                      v-model="reg.password_confirmation"
                      label="Parole vēlreiz"
                      variant="outlined"
                      type="password"
                      class="mb-4"
                    />

                    <v-btn type="submit" block size="large" rounded="xl" elevation="6" :loading="loading">
                      <v-icon start>mdi-account-plus</v-icon>
                      Reģistrēties
                    </v-btn>
                  </v-form>
                </div>

                <v-alert v-if="errorText" type="error" variant="tonal" class="mt-6">
                  {{ errorText }}
                </v-alert>

                <v-alert v-if="okText" type="success" variant="tonal" class="mt-6">
                  {{ okText }}
                </v-alert>

              </v-card-text>
            </v-card>

            <div class="text-caption opacity-70 mt-4 text-center">
              API: {{ apiBase }}
            </div>
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
  atrasanas_adrese: '',
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
    reg.value.atrasanas_adrese = ''
  } else {
    reg.value.lietotajvards = ''
  }
}

function getApiBase() {
  return import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000'
}

const apiBase = computed(() => getApiBase())

async function submitRegister() {
  errorText.value = ''
  okText.value = ''
  loading.value = true
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
      payload.registracijas_numurs = reg.value.registracijas_numurs
      payload.atrasanas_adrese = reg.value.atrasanas_adrese
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
      const msg = data?.message || 'Neizdevās reģistrēties'
      const details = data?.errors ? Object.values(data.errors).flat().join(' ') : ''
      throw new Error(details ? `${msg}: ${details}` : msg)
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
      atrasanas_adrese: '',
      bankas_konts: '',
    }

    // Informēt parent par sekmīgu reģistrāciju
    setTimeout(() => {
      emit('auth-success')
    }, 1500)
  } catch (e) {
    errorText.value = e?.message || 'Kļūda: Failed to fetch'
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
    errorText.value = e?.message || 'Kļūda: Failed to fetch'
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
