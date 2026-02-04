<template>
  <div class="profile-bg">
    <v-container class="py-10 py-sm-16">
      <v-row justify="center">
        <!-- Profile Header Card -->
        <v-col cols="12" md="8" lg="7">
          <v-card class="surface profile-header mb-6" elevation="12">
            <v-card-text class="pa-8">
              <div class="d-flex align-center justify-space-between">
                <div>
                  <div class="text-h4 font-weight-bold mb-2">{{ form.vards }} {{ form.uzvards }}</div>
                  <div class="text-body-2 opacity-80">
                    <span v-if="loma === 'klients'" class="badge-info">🔹 Klients</span>
                    <span v-else class="badge-info">🏢 Pakalpojumu sniedzējs</span>
                  </div>
                </div>
                <v-avatar size="80" variant="tonal" class="avatar-icon">
                  <v-icon size="48">{{ loma === 'klients' ? 'mdi-account' : 'mdi-briefcase' }}</v-icon>
                </v-avatar>
              </div>
            </v-card-text>
          </v-card>

          <!-- Edit Form Card -->
          <v-card class="surface" elevation="12">
            <v-card-title class="pa-6 pa-sm-8">
              <div class="text-h5 font-weight-bold">Rediģēt profilu</div>
            </v-card-title>

            <v-divider />

            <v-card-text class="pa-6 pa-sm-8">
              <!-- Success/Error Messages -->
              <v-alert v-if="errorText" type="error" variant="tonal" class="mb-6" dismissible @update:model-value="errorText = ''">
                <div v-html="errorText.split('. ').filter(e => e).map(e => e + (e.endsWith('.') ? '' : '.')).join('<br/>')"></div>
              </v-alert>

              <v-alert v-if="successText" type="success" variant="tonal" class="mb-6" dismissible @update:model-value="successText = ''">
                {{ successText }}
              </v-alert>

              <v-form ref="formRef" @submit.prevent="updateProfile">
                <!-- Basic Information Section -->
                <div class="section-title mb-4">Pamatinformācija</div>
                
                <v-row dense class="mb-6">
                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model="form.vards"
                      label="Vārds"
                      variant="outlined"
                      density="compact"
                      :rules="nameRules"
                      :error="!!fieldErrors.vards"
                      :error-messages="fieldErrors.vards ? [fieldErrors.vards] : []"
                    />
                  </v-col>

                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model="form.uzvards"
                      label="Uzvārds"
                      variant="outlined"
                      density="compact"
                      :error="!!fieldErrors.uzvards"
                      :error-messages="fieldErrors.uzvards ? [fieldErrors.uzvards] : []"
                    />
                  </v-col>

                  <v-col cols="12">
                    <v-text-field
                      v-model="email"
                      label="E-pasts"
                      variant="outlined"
                      density="compact"
                      disabled
                      hint="E-pastu mainīt nevar"
                    />
                  </v-col>

                  <v-col cols="12">
                    <v-text-field
                      v-model="form.kontakttalrunis"
                      label="Kontakttālrunis"
                      variant="outlined"
                      density="compact"
                      placeholder="+371 26123456"
                      :rules="phoneRules"
                      :error="!!fieldErrors.kontakttalrunis"
                      :error-messages="fieldErrors.kontakttalrunis ? [fieldErrors.kontakttalrunis] : []"
                    />
                  </v-col>
                </v-row>

                <!-- IBAN Section -->
                <div class="section-title mb-4">Bankas informācija</div>

                <v-row dense class="mb-6">
                  <v-col cols="12">
                    <v-text-field
                      v-model="form.bankas_konts"
                      label="IBAN"
                      variant="outlined"
                      density="compact"
                      placeholder="LV00ABCD1234567890123"
                      :rules="ibanRules"
                      :error="!!fieldErrors.bankas_konts"
                      :error-messages="fieldErrors.bankas_konts ? [fieldErrors.bankas_konts] : []"
                    />
                  </v-col>
                </v-row>

                <!-- Klients Section -->
                <template v-if="loma === 'klients'">
                  <v-divider class="my-6" />
                  <div class="section-title mb-4">Klienta informācija</div>

                  <v-row dense class="mb-6">
                    <v-col cols="12">
                      <v-text-field
                        v-model="form.lietotajvards"
                        label="Lietotājvārds"
                        variant="outlined"
                        density="compact"
                        :rules="usernameRules"
                        :error="!!fieldErrors.lietotajvards"
                        :error-messages="fieldErrors.lietotajvards ? [fieldErrors.lietotajvards] : []"
                      />
                    </v-col>
                  </v-row>
                </template>

                <!-- Pakalpojumu sniedzējs Section -->
                <template v-if="loma === 'pakalpojumu_sniedzejs'">
                  <v-divider class="my-6" />
                  <div class="section-title mb-4">Pakalpojumu sniedzēja informācija</div>

                  <v-row dense class="mb-6">
                    <v-col cols="12">
                      <v-text-field
                        v-model="form.registracijas_numurs"
                        label="Reģistrācijas numurs"
                        variant="outlined"
                        density="compact"
                        :rules="regNumRules"
                        :error="!!fieldErrors.registracijas_numurs"
                        :error-messages="fieldErrors.registracijas_numurs ? [fieldErrors.registracijas_numurs] : []"
                      />
                    </v-col>

                    <v-col cols="12">
                      <v-text-field
                        v-model="form.atrasanas_adrese"
                        label="Atrašanās adrese"
                        variant="outlined"
                        density="compact"
                        :rules="addressRules"
                        :error="!!fieldErrors.atrasanas_adrese"
                        :error-messages="fieldErrors.atrasanas_adrese ? [fieldErrors.atrasanas_adrese] : []"
                      />
                    </v-col>
                  </v-row>
                </template>

                <!-- Password Section -->
                <v-divider class="my-6" />
                <div class="section-title mb-4">Parole</div>

                <v-row dense class="mb-8">
                  <v-col cols="12">
                    <v-text-field
                      v-model="form.password"
                      type="password"
                      label="Jauna parole (atstāj tukšu, ja nemainīs)"
                      variant="outlined"
                      density="compact"
                      :rules="passwordRules"
                      :error="!!fieldErrors.password"
                      :error-messages="fieldErrors.password ? [fieldErrors.password] : []"
                    />
                  </v-col>
                </v-row>

                <!-- Action Buttons -->
                <v-row dense class="gap-3">
                  <v-col cols="12" sm="auto">
                    <v-btn
                      type="submit"
                      size="large"
                      rounded="xl"
                      elevation="6"
                      :loading="loading"
                      min-width="160"
                    >
                      <v-icon start>mdi-check</v-icon>
                      Saglabāt
                    </v-btn>
                  </v-col>

                  <v-col cols="12" sm="auto">
                    <v-btn
                      size="large"
                      rounded="xl"
                      variant="tonal"
                      :disabled="loading"
                      @click="logout"
                      min-width="160"
                    >
                      <v-icon start>mdi-logout</v-icon>
                      Izlogoties
                    </v-btn>
                  </v-col>

                  <v-col cols="12" sm="auto">
                    <v-btn
                      size="large"
                      rounded="xl"
                      variant="outlined"
                      to="/"
                      min-width="160"
                    >
                      <v-icon start>mdi-arrow-left</v-icon>
                      Atpakaļ
                    </v-btn>
                  </v-col>
                </v-row>
              </v-form>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </v-container>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const formRef = ref(null)
const loading = ref(false)
const errorText = ref('')
const successText = ref('')
const fieldErrors = ref({})

const email = ref('')
const loma = ref('')
const form = ref({
  vards: '',
  uzvards: '',
  kontakttalrunis: '',
  lietotajvards: '',
  registracijas_numurs: '',
  atrasanas_adrese: '',
  bankas_konts: '',
  password: '',
})

// IBAN Validator
function ibanIsValid(iban) {
  if (!iban) return true // Optional
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

// Validation Rules
const nameRules = [
  v => !!v || 'Vārds ir obligāts',
  v => (v && v.trim().length >= 2) || 'Vārdam jābūt vismaz 2 simbolu garam'
]

const phoneRules = [
  v => !v || (v && v.length >= 6) || 'Tālrunim jābūt vismaz 6 simbolu garam',
  v => !v || (v && v.length <= 20) || 'Tālruņa garums nedrīkst pārsniegt 20 simbolus',
  v => !v || /^\+?[0-9 \-()]+$/.test(v) || 'Ievadiet derīgu telefona numuru'
]

const ibanRules = [
  v => !v || (v && v.length >= 15) || 'IBAN jābūt vismaz 15 simbolu garam',
  v => !v || (v && v.length <= 34) || 'IBAN nedrīkst būt garāks par 34 simboliem',
  v => !v || ibanIsValid(v) || 'Ievadiet derīgu IBAN'
]

const usernameRules = [
  v => !!v || 'Lietotājvārds ir obligāts',
  v => (v && v.length >= 3) || 'Lietotājvārdam jābūt vismaz 3 simbolu garam'
]

const regNumRules = [
  v => !!v || 'Reģistrācijas numurs ir obligāts'
]

const addressRules = [
  v => !!v || 'Atrašanās adrese ir obligāta'
]

const passwordRules = [
  v => !v || (v && v.length >= 8) || 'Parolei jābūt vismaz 8 simbolu garai'
]

// Load user data
onMounted(() => {
  const userStr = localStorage.getItem('user')
  const token = localStorage.getItem('token')

  if (!userStr || !token) {
    errorText.value = 'Jums jāpiesakās, lai apskatītu profilu'
    setTimeout(() => router.push('/'), 2000)
    return
  }

  const userData = JSON.parse(userStr)
  email.value = userData.epasts
  loma.value = userData.loma

  form.value.vards = userData.vards || ''
  form.value.uzvards = userData.uzvards || ''
  form.value.kontakttalrunis = userData.kontakttalrunis || ''
  form.value.bankas_konts = userData.bankas_konts || ''

  if (userData.loma === 'klients' && userData.klients) {
    form.value.lietotajvards = userData.klients.lietotajvards || ''
  } else if (userData.loma === 'pakalpojumu_sniedzejs' && userData.pakalpojumuSniedzejs) {
    form.value.registracijas_numurs = userData.pakalpojumuSniedzejs.registracijas_numurs || ''
    form.value.atrasanas_adrese = userData.pakalpojumuSniedzejs.atrasanas_adrese || ''
  }
})

// Update Profile
async function updateProfile() {
  errorText.value = ''
  successText.value = ''
  fieldErrors.value = {}

  const valid = await formRef.value?.validate()
  if (!valid?.valid) {
    errorText.value = 'Lūdzu, izlabojiet formā norādītās kļūdas'
    return
  }

  loading.value = true
  try {
    const token = localStorage.getItem('token')
    const userStr = localStorage.getItem('user')
    
    if (!token || !userStr) {
      throw new Error('Sesija beidzas. Lūdzu, piesakieties atkārtoti.')
    }

    const user = JSON.parse(userStr)
    const API_BASE = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000'

    if (!user?.persona_id) {
      throw new Error('Lietotāja dati nav ielādēti pareizi. Atsvaidziniet lapu.')
    }

    // Build update payload with only changed fields
    const updateData = {}

    if (form.value.vards) updateData.vards = form.value.vards
    if (form.value.uzvards) updateData.uzvards = form.value.uzvards
    if (form.value.kontakttalrunis) updateData.kontakttalrunis = form.value.kontakttalrunis
    if (form.value.bankas_konts) updateData.bankas_konts = form.value.bankas_konts
    if (form.value.password) updateData.password = form.value.password

    if (loma.value === 'klients' && form.value.lietotajvards) {
      updateData.lietotajvards = form.value.lietotajvards
    } else if (loma.value === 'pakalpojumu_sniedzejs') {
      if (form.value.registracijas_numurs) updateData.registracijas_numurs = form.value.registracijas_numurs
      if (form.value.atrasanas_adrese) updateData.atrasanas_adrese = form.value.atrasanas_adrese
    }

    console.log('Sending profile update to:', `${API_BASE}/api/profile/${user.persona_id}`)
    console.log('Update data:', updateData)

    const response = await fetch(`${API_BASE}/api/profile/${user.persona_id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`,
      },
      body: JSON.stringify(updateData),
    })

    console.log('Response status:', response.status)
    
    let result
    const contentType = response.headers.get('content-type')
    if (contentType?.includes('application/json')) {
      result = await response.json()
    } else {
      result = { message: await response.text() }
    }

    console.log('Response data:', result)

    if (!response.ok) {
      if (result?.errors) {
        // Map server errors to individual fields
        Object.keys(result.errors).forEach(field => {
          const errorMessages = Array.isArray(result.errors[field]) ? result.errors[field] : [result.errors[field]]
          fieldErrors.value[field] = errorMessages[0] || 'Kļūda šajā laukā'
        })
        errorText.value = 'Lūdzu, labojiet norādītās kļūdas apakšā'
        return
      }
      throw new Error(result?.message || `Kļūda (${response.status}): Neizdevās savienot ar serveri`)
    }

    if (!result?.persona) {
      throw new Error('Servera atbilde nav pareiza.')
    }

    localStorage.setItem('user', JSON.stringify(result.persona))
    
    // Dispatch custom event to notify App.vue to update user display
    window.dispatchEvent(new CustomEvent('user-updated', { detail: result.persona }))
    
    successText.value = 'Profils atjaunināts sekmīgi!'
    form.value.password = ''
  } catch (error) {
    console.error('Profile update error:', error)
    errorText.value = error?.message || 'Kļūda: Neizdevās savienot ar serveri'
  } finally {
    loading.value = false
  }
}

// Logout
function logout() {
  localStorage.removeItem('user')
  localStorage.removeItem('token')
  
  // Dispatch event to notify App.vue to update state
  window.dispatchEvent(new CustomEvent('user-logged-out'))
  
  // Navigate to home
  router.push('/')
}
</script>

<style scoped>
.profile-bg {
  min-height: calc(100vh - 72px - 64px);
  background: radial-gradient(1200px circle at 10% 10%, rgba(255,255,255,0.12), transparent 45%),
              radial-gradient(900px circle at 90% 20%, rgba(255,255,255,0.10), transparent 40%),
              linear-gradient(135deg, #0f172a, #111827, #0b1020);
}

.surface {
  background: rgba(255, 255, 255, 0.94);
  border: 1px solid rgba(15, 23, 42, 0.08);
  color: #0f172a;
  backdrop-filter: blur(6px);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.surface:hover {
  box-shadow: 0 20px 48px rgba(15, 23, 42, 0.12);
}

.profile-header {
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.96), rgba(255, 255, 255, 0.90));
  border: 2px solid rgba(15, 23, 42, 0.1);
}

.avatar-icon {
  background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(99, 102, 241, 0.1));
  border: 2px solid rgba(59, 130, 246, 0.2);
}

.section-title {
  font-weight: 700;
  font-size: 0.95rem;
  letter-spacing: 0.5px;
  color: rgba(15, 23, 42, 0.85);
  text-transform: uppercase;
  opacity: 0.7;
}

.badge-info {
  display: inline-block;
  padding: 6px 12px;
  background: rgba(59, 130, 246, 0.1);
  color: #3b82f6;
  border-radius: 20px;
  font-size: 0.875rem;
  font-weight: 600;
  border: 1px solid rgba(59, 130, 246, 0.2);
}

.gap-3 {
  gap: 12px !important;
}

/* Responsive Adjustments */
@media (max-width: 600px) {
  .profile-bg {
    min-height: calc(100vh - 72px - 64px);
  }

  .profile-header :deep(.v-card__text) {
    flex-direction: column;
    text-align: center;
  }

  .avatar-icon {
    margin-top: 16px;
  }

  .gap-3 {
    flex-direction: column;
  }

  .gap-3 :deep(.v-btn) {
    width: 100%;
  }
}
</style>
