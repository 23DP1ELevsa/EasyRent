<template>
  <div class="auth-bg">
    <v-container class="py-12">
      <div class="page-wrap">

        <v-row justify="center">
          <v-col cols="12" sm="10" md="7" lg="5">
            <v-card class="surface" elevation="12">
              <v-card-text class="pa-6 pa-md-8">

                <div class="text-h4 font-weight-black mb-2">Konts</div>
                <div class="text-body-2 opacity-80 mb-6">
                  Lai piekļūtu kartei un rezervācijām, nepieciešama reģistrācija/pieslēgšanās.
                </div>

                <v-tabs v-model="tab" class="mb-6" grow>
                  <v-tab value="login">Pieslēgties</v-tab>
                  <v-tab value="register">Reģistrēties</v-tab>
                </v-tabs>

                <v-window v-model="tab">
                  <v-window-item value="login">
                    <v-form ref="loginForm" @submit.prevent="submitLogin">
                      <v-text-field
                        v-model="login.email"
                        label="E-pasts"
                        variant="outlined"
                        :rules="emailRules"
                        autocomplete="email"
                        class="mb-3"
                      />
                      <v-text-field
                        v-model="login.password"
                        label="Parole"
                        variant="outlined"
                        :rules="passwordRules"
                        type="password"
                        autocomplete="current-password"
                        class="mb-4"
                      />

                      <v-btn
                        type="submit"
                        block
                        size="large"
                        rounded="xl"
                        elevation="6"
                        :loading="loading"
                      >
                        Pieslēgties
                      </v-btn>

                      <div class="text-caption opacity-70 mt-4">
                        Ja aizmirsti paroli — vēlāk pievienosim atjaunošanu.
                      </div>
                    </v-form>
                  </v-window-item>

                  <v-window-item value="register">
                    <v-form ref="regForm" @submit.prevent="submitRegister">
                      <v-text-field
                        v-model="reg.name"
                        label="Vārds"
                        variant="outlined"
                        :rules="nameRules"
                        autocomplete="name"
                        class="mb-3"
                      />
                      <v-text-field
                        v-model="reg.email"
                        label="E-pasts"
                        variant="outlined"
                        :rules="emailRules"
                        autocomplete="email"
                        class="mb-3"
                      />

                      <div class="text-subtitle-2 font-weight-bold mb-2">Loma</div>
                      <v-radio-group v-model="reg.role" inline class="mb-3">
                        <v-radio label="Klients" value="klients" />
                        <v-radio label="Pakalpojumu sniedzējs" value="pakalpojumu_sniedzejs" />
                      </v-radio-group>

                      <v-text-field
                        v-model="reg.password"
                        label="Parole"
                        variant="outlined"
                        :rules="passwordRules"
                        type="password"
                        autocomplete="new-password"
                        class="mb-3"
                      />
                      <v-text-field
                        v-model="reg.password_confirmation"
                        label="Atkārto paroli"
                        variant="outlined"
                        :rules="confirmRules"
                        type="password"
                        autocomplete="new-password"
                        class="mb-4"
                      />

                      <v-btn
                        type="submit"
                        block
                        size="large"
                        rounded="xl"
                        elevation="6"
                        :loading="loading"
                      >
                        Izveidot kontu
                      </v-btn>

                      <div class="text-caption opacity-70 mt-4">
                        Konts tiks saglabāts datubāzē (backend pusē).
                      </div>
                    </v-form>
                  </v-window-item>
                </v-window>

              </v-card-text>
            </v-card>

            <v-snackbar v-model="snackbar" :timeout="4000">
              {{ snackbarText }}
              <template #actions>
                <v-btn variant="text" @click="snackbar = false">Aizvērt</v-btn>
              </template>
            </v-snackbar>

          </v-col>
        </v-row>

      </div>
    </v-container>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const emit = defineEmits(['auth-success', 'go-home'])

const tab = ref('login')
const loading = ref(false)
const snackbar = ref(false)
const snackbarText = ref('')

const loginForm = ref(null)
const regForm = ref(null)

const login = ref({ email: '', password: '' })
const reg = ref({ name: '', email: '', role: 'klients', password: '', password_confirmation: '' })

const nameRules = [v => !!v || 'Ievadi vārdu', v => (v?.length >= 2) || 'Vārdam jābūt vismaz 2 simboli']
const emailRules = [v => !!v || 'Ievadi e-pastu', v => /.+@.+\..+/.test(v) || 'Nepareizs e-pasta formāts']
const passwordRules = [v => !!v || 'Ievadi paroli', v => (v?.length >= 6) || 'Parolei jābūt vismaz 6 simboli']
const confirmRules = [v => !!v || 'Atkārto paroli', v => (v === reg.value.password) || 'Paroles nesakrīt']

function toast(msg) {
  snackbarText.value = msg
  snackbar.value = true
}

function getApiBase() {
  return import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000'
}

async function submitLogin() {
  const res = await loginForm.value?.validate()
  if (!res?.valid) return

  loading.value = true
  try {
    const API = getApiBase()

    const r = await fetch(`${API}/api/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(login.value),
    })

    const data = await safeJson(r)
    if (!r.ok) throw new Error(extractMessage(data) || 'Neizdevās pieslēgties')

    if (data?.token) localStorage.setItem('token', data.token)
    toast('Pieslēgšanās veiksmīga!')
    emit('auth-success', data)
  } catch (e) {
    toast(`Kļūda: ${e.message}`)
  } finally {
    loading.value = false
  }
}

async function submitRegister() {
  const res = await regForm.value?.validate()
  if (!res?.valid) return

  loading.value = true
  try {
    const API = getApiBase()

    const r = await fetch(`${API}/api/auth/register`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(reg.value),
    })

    const data = await safeJson(r)
    if (!r.ok) throw new Error(extractMessage(data) || 'Neizdevās reģistrēties')

    if (data?.token) localStorage.setItem('token', data.token)
    toast('Konts izveidots! Vari turpināt.')
    emit('auth-success', data)
  } catch (e) {
    toast(`Kļūda: ${e.message}`)
  } finally {
    loading.value = false
  }
}

async function safeJson(r) {
  try { return await r.json() } catch { return null }
}

function extractMessage(data) {
  if (!data) return ''
  if (typeof data.message === 'string') return data.message
  if (data.errors) {
    const firstKey = Object.keys(data.errors)[0]
    const firstVal = data.errors[firstKey]
    return Array.isArray(firstVal) ? firstVal[0] : String(firstVal)
  }
  return ''
}
</script>

<style scoped>
.auth-bg {
  min-height: calc(100vh - 72px - 64px);
  background: radial-gradient(1200px circle at 10% 10%, rgba(255,255,255,0.12), transparent 45%),
              radial-gradient(900px circle at 90% 20%, rgba(255,255,255,0.10), transparent 40%),
              linear-gradient(135deg, #0f172a, #111827, #0b1020);
}
.page-wrap { width: min(1100px, 100%); margin: 0 auto; padding: 0 12px; }
.surface {
  background: rgba(255, 255, 255, 0.94);
  border: 1px solid rgba(15, 23, 42, 0.08);
  color: #0f172a;
  backdrop-filter: blur(6px);
}
</style>
