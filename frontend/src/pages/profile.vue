<template>
  <div class="profile-page bg">
    <v-container class="py-12">
      <v-row justify="center">
        <v-col cols="12" md="6">
          <v-card class="surface" elevation="12">
            <v-card-title class="pa-6">
              <div class="text-h5 font-weight-bold">Mans profils</div>
            </v-card-title>

            <v-divider />

            <v-card-text class="pa-6">
              <v-form ref="formRef" @submit.prevent="updateProfile">
                <v-row dense>
                  <!-- Pamatinformācija -->
                  <v-col cols="12">
                    <div class="text-subtitle-2 font-weight-bold mb-4">Pamatinformācija</div>
                  </v-col>

                  <v-col cols="12" sm="6">
                    <v-text-field 
                      v-model="form.vards" 
                      label="Vārds" 
                      variant="outlined" 
                      :rules="[v => !!v || 'Ievadi vārdu']"
                    />
                  </v-col>

                  <v-col cols="12" sm="6">
                    <v-text-field 
                      v-model="form.uzvards" 
                      label="Uzvārds" 
                      variant="outlined"
                    />
                  </v-col>

                  <v-col cols="12">
                    <v-text-field 
                      v-model="email" 
                      label="E-pasts" 
                      variant="outlined" 
                      disabled
                    />
                  </v-col>

                  <v-col cols="12">
                    <v-text-field 
                      v-model="form.kontakttalrunis" 
                      label="Kontakttālrunis" 
                      variant="outlined"
                    />
                  </v-col>

                  <!-- Klients dati -->
                  <template v-if="loma === 'klients'">
                    <v-col cols="12">
                      <v-divider class="my-4" />
                      <div class="text-subtitle-2 font-weight-bold mb-4">Klienta dati</div>
                    </v-col>

                    <v-col cols="12">
                      <v-text-field 
                        v-model="form.lietotajvards" 
                        label="Lietotājvārds" 
                        variant="outlined"
                      />
                    </v-col>
                  </template>

                  <!-- Pakalpojumu sniedzēja dati -->
                  <template v-if="loma === 'pakalpojumu_sniedzejs'">
                    <v-col cols="12">
                      <v-divider class="my-4" />
                      <div class="text-subtitle-2 font-weight-bold mb-4">Pakalpojumu sniedzēja dati</div>
                    </v-col>

                    <v-col cols="12">
                      <v-text-field 
                        v-model="form.registracijas_numurs" 
                        label="Reģistrācijas numurs" 
                        variant="outlined"
                      />
                    </v-col>

                    <v-col cols="12">
                      <v-text-field 
                        v-model="form.atrasanas_adrese" 
                        label="Atrašanās adrese" 
                        variant="outlined"
                      />
                    </v-col>

                    <v-col cols="12">
                      <v-text-field 
                        v-model="form.bankas_konts" 
                        label="Banka konta numurs" 
                        variant="outlined"
                      />
                    </v-col>
                  </template>

                  <!-- Parole -->
                  <v-col cols="12">
                    <v-divider class="my-4" />
                    <div class="text-subtitle-2 font-weight-bold mb-4">Mainīt paroli</div>
                  </v-col>

                  <v-col cols="12">
                    <v-text-field 
                      v-model="form.password" 
                      type="password"
                      label="Jauna parole (atstāj tukšu, ja nemainīs)" 
                      variant="outlined"
                    />
                  </v-col>

                  <!-- Pogas -->
                  <v-col cols="12" class="d-flex gap-3">
                    <v-btn type="submit" size="large" rounded="xl" elevation="6" :loading="loading">
                      <v-icon start>mdi-check</v-icon>
                      Saglabāt
                    </v-btn>

                    <v-btn size="large" rounded="xl" variant="tonal" :disabled="loading" @click="logout">
                      <v-icon start>mdi-logout</v-icon>
                      Izlogoties
                    </v-btn>

                    <router-link to="/">
                      <v-btn size="large" rounded="xl" variant="outlined">
                        <v-icon start>mdi-arrow-left</v-icon>
                        Atpakaļ
                      </v-btn>
                    </router-link>
                  </v-col>
                </v-row>
              </v-form>

              <v-snackbar v-model="snackbar" :timeout="3500">
                {{ snackbarText }}
                <template #actions>
                  <v-btn variant="text" @click="snackbar = false">Aizvērt</v-btn>
                </template>
              </v-snackbar>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </v-container>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, RouterLink } from 'vue-router'

const router = useRouter()
const formRef = ref(null)
const loading = ref(false)
const snackbar = ref(false)
const snackbarText = ref('')

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

onMounted(() => {
  const user = localStorage.getItem('user')
  const token = localStorage.getItem('token')

  if (!user || !token) {
    router.push('/auth')
    return
  }

  const userData = JSON.parse(user)
  email.value = userData.epasts
  loma.value = userData.loma

  form.value.vards = userData.vards
  form.value.uzvards = userData.uzvards
  form.value.kontakttalrunis = userData.kontakttalrunis || ''
  form.value.bankas_konts = userData.bankas_konts || ''

  if (userData.loma === 'klients' && userData.klients) {
    form.value.lietotajvards = userData.klients.lietotajvards || ''
  } else if (userData.loma === 'pakalpojumu_sniedzejs' && userData.pakalpojumuSniedzejs) {
    form.value.registracijas_numurs = userData.pakalpojumuSniedzejs.registracijas_numurs || ''
    form.value.atrasanas_adrese = userData.pakalpojumuSniedzejs.atrasanas_adrese || ''
  }
})

async function updateProfile() {
  const res = await formRef.value?.validate()
  if (!res?.valid) return

  loading.value = true
  try {
    const token = localStorage.getItem('token')
    const user = JSON.parse(localStorage.getItem('user'))
    const API_BASE = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000'

    const updateData = {
      vards: form.value.vards,
      uzvards: form.value.uzvards,
      kontakttalrunis: form.value.kontakttalrunis,
    }

    if (loma.value === 'klients') {
      updateData.lietotajvards = form.value.lietotajvards
    } else {
      updateData.registracijas_numurs = form.value.registracijas_numurs
      updateData.atrasanas_adrese = form.value.atrasanas_adrese
      updateData.bankas_konts = form.value.bankas_konts
    }

    if (form.value.password) {
      updateData.password = form.value.password
    }

    const response = await fetch(`${API_BASE}/api/profile/${user.persona_id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`,
      },
      body: JSON.stringify(updateData),
    })

    if (!response.ok) {
      throw new Error(await response.text())
    }

    const result = await response.json()
    localStorage.setItem('user', JSON.stringify(result.persona))

    snackbarText.value = 'Profils atjaunināts sekmīgi!'
    snackbar.value = true
    form.value.password = ''
  } catch (error) {
    snackbarText.value = `Kļūda: ${error.message}`
    snackbar.value = true
  } finally {
    loading.value = false
  }
}

function logout() {
  localStorage.removeItem('user')
  localStorage.removeItem('token')
  router.push('/')
}
</script>

<style scoped>
.profile-page {
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
}

.surface :deep(.v-card-title) {
  color: #0f172a;
}

a {
  text-decoration: none;
  color: inherit;
}

.gap-3 {
  gap: 12px;
}
</style>
