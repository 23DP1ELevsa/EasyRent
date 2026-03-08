<template>
  <div class="profile-bg">
    <v-container class="py-10 py-sm-16">
      <v-row justify="center">
        <!-- Profile Header Card -->
        <v-col cols="12" md="8" lg="7">
          <v-card class="surface profile-header mb-6" elevation="12">
            <v-card-text class="pa-8">
              <div class="d-flex align-center justify-space-between flex-wrap ga-4">
                <div>
                  <div class="text-overline opacity-70 mb-1">Profils</div>
                  <div class="text-h4 font-weight-bold mb-2">{{ form.vards }} {{ form.uzvards }}</div>
                  <div class="text-body-2 opacity-80 d-flex align-center ga-2 flex-wrap">
                    <span v-if="loma === 'klients'" class="badge-info">🔹 Klients</span>
                    <span v-else class="badge-info">🏢 Pakalpojumu sniedzējs</span>
                    <span class="profile-email">{{ email }}</span>
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
              <div>
                <div class="text-h5 font-weight-bold">Rediģēt profilu</div>
                <div class="text-caption opacity-70 mt-1">Atjaunojiet kontaktus, bankas datus un profila informāciju.</div>
              </div>
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
                        v-model="form.iela"
                        label="Iela"
                        variant="outlined"
                        density="compact"
                        :rules="streetRules"
                        :error="!!fieldErrors.iela"
                        :error-messages="fieldErrors.iela ? [fieldErrors.iela] : []"
                      />
                    </v-col>

                    <v-col cols="12" sm="6">
                      <v-text-field
                        v-model="form.majas_numurs"
                        label="Mājas numurs"
                        variant="outlined"
                        density="compact"
                        :rules="houseNumberRules"
                        :error="!!fieldErrors.majas_numurs"
                        :error-messages="fieldErrors.majas_numurs ? [fieldErrors.majas_numurs] : []"
                      />
                    </v-col>

                    <v-col cols="12" sm="6">
                      <v-text-field
                        v-model="form.dzivokla_numurs"
                        label="Dzīvokļa numurs (neobligāts)"
                        variant="outlined"
                        density="compact"
                        :error="!!fieldErrors.dzivokla_numurs"
                        :error-messages="fieldErrors.dzivokla_numurs ? [fieldErrors.dzivokla_numurs] : []"
                      />
                    </v-col>

                    <v-col cols="12" sm="6">
                      <v-text-field
                        v-model="form.pilseta"
                        label="Pilsēta"
                        variant="outlined"
                        density="compact"
                        :rules="cityRules"
                        :error="!!fieldErrors.pilseta"
                        :error-messages="fieldErrors.pilseta ? [fieldErrors.pilseta] : []"
                      />
                    </v-col>

                    <v-col cols="12" sm="6">
                      <v-text-field
                        v-model="form.pasta_indekss"
                        label="Pasta indekss"
                        variant="outlined"
                        density="compact"
                        :rules="postalCodeRules"
                        :error="!!fieldErrors.pasta_indekss"
                        :error-messages="fieldErrors.pasta_indekss ? [fieldErrors.pasta_indekss] : []"
                      />
                    </v-col>

                    <v-col cols="12" sm="6">
                      <v-text-field
                        v-model="form.latitude"
                        label="Platums (latitude)"
                        variant="outlined"
                        density="compact"
                        placeholder="56.9496"
                      />
                    </v-col>

                    <v-col cols="12" sm="6">
                      <v-text-field
                        v-model="form.longitude"
                        label="Garums (longitude)"
                        variant="outlined"
                        density="compact"
                        placeholder="24.1052"
                      />
                    </v-col>

                    <v-col cols="12">
                      <div v-if="geocodeLoading" class="text-caption opacity-70">Meklējam koordinātes no adreses...</div>
                      <div v-else-if="geocodeError" class="text-caption text-error">{{ geocodeError }}</div>
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
                      label="Jauna parole"
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
                      elevation="4"
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
                      variant="flat"
                      :disabled="loading"
                      @click="logout"
                      min-width="160"
                      class="logout-btn"
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
                      class="back-btn"
                    >
                      <v-icon start>mdi-arrow-left</v-icon>
                      Atpakaļ
                    </v-btn>
                  </v-col>
                </v-row>
              </v-form>
            </v-card-text>
          </v-card>

          <!-- Reservations Card (Clients only) -->
          <v-card v-if="loma === 'klients'" class="surface mt-6 profile-reservations" elevation="12">
            <v-card-title class="pa-6 pa-sm-8">
              <div>
                <div class="text-h5 font-weight-bold">Rezervācijas</div>
                <div class="text-caption opacity-70 mt-1">Pārskatiet aktīvās un neapmaksātās rezervācijas.</div>
              </div>
            </v-card-title>
            <v-divider />
            <v-card-text class="pa-6 pa-sm-8">
              <v-alert
                v-if="unpaidReservations.length"
                type="warning"
                variant="tonal"
                class="mb-4"
              >
                Jums ir {{ unpaidReservations.length }} neapmaksāta(s) rezervācija(s).
              </v-alert>

              <v-alert v-if="reservationsError" type="error" variant="tonal" class="mb-4">
                {{ reservationsError }}
              </v-alert>

              <v-alert v-if="reservationsSuccess" type="success" variant="tonal" class="mb-4">
                {{ reservationsSuccess }}
              </v-alert>

              <div v-if="reservationsLoading" class="text-body-2 opacity-70">Ielādē...</div>
              <div v-else-if="!reservations.length" class="text-body-2 opacity-70">
                Vēl nav rezervāciju.
              </div>

              <template v-else>
                <div class="section-title mb-3">Aktīvās rezervācijas</div>

                <div v-if="!activeReservations.length" class="text-body-2 opacity-70 mb-4">
                  Nav aktīvu rezervāciju.
                </div>

                <div v-else class="d-flex flex-column ga-3 mb-6">
                  <v-card
                    v-for="item in activeReservations"
                    :key="`active-${item.rezervacija_id}`"
                    class="reservation-card"
                    elevation="4"
                  >
                    <v-card-text class="pa-4">
                      <div class="d-flex justify-space-between flex-wrap gap-2">
                        <div>
                          <div class="font-weight-bold">
                            {{ item.transportlidzeklis?.marka }} {{ item.transportlidzeklis?.modelis }}
                          </div>
                          <div class="text-caption opacity-70">
                            {{ item.transportlidzeklis?.sniedzejs?.persona?.vards }} {{ item.transportlidzeklis?.sniedzejs?.persona?.uzvards }}
                          </div>
                          <div class="text-caption opacity-70">
                            {{ formatDateTime(item.sakuma_laiks) }} - {{ formatDateTime(item.beigu_laiks) }}
                          </div>
                        </div>
                        <div class="text-right">
                          <div class="font-weight-bold">{{ formatPrice(item.kopa_summa) }}</div>
                          <v-chip size="small" color="success" variant="tonal">Apmaksāts</v-chip>
                        </div>
                      </div>
                    </v-card-text>
                  </v-card>
                </div>

                <div class="section-title mb-3">Neapmaksātās rezervācijas</div>

                <div v-if="!unpaidReservations.length" class="text-body-2 opacity-70">
                  Nav neapmaksātu rezervāciju.
                </div>

                <div v-else class="d-flex flex-column ga-3">
                  <v-card
                    v-for="item in unpaidReservations"
                    :key="`unpaid-${item.rezervacija_id}`"
                    class="reservation-card reservation-card-unpaid"
                    elevation="4"
                  >
                    <v-card-text class="pa-4">
                      <div class="d-flex justify-space-between flex-wrap gap-2 mb-3">
                        <div>
                          <div class="font-weight-bold">
                            {{ item.transportlidzeklis?.marka }} {{ item.transportlidzeklis?.modelis }}
                          </div>
                          <div class="text-caption opacity-70">
                            {{ item.transportlidzeklis?.sniedzejs?.persona?.vards }} {{ item.transportlidzeklis?.sniedzejs?.persona?.uzvards }}
                          </div>
                          <div class="text-caption opacity-70">
                            {{ formatDateTime(item.sakuma_laiks) }} - {{ formatDateTime(item.beigu_laiks) }}
                          </div>
                        </div>
                        <div class="text-right">
                          <div class="font-weight-bold">{{ formatPrice(item.kopa_summa) }}</div>
                          <v-chip size="small" color="warning" variant="tonal">Neapmaksāts</v-chip>
                        </div>
                      </div>

                      <v-btn
                        color="primary"
                        variant="outlined"
                        size="small"
                        :loading="payingReservationId === item.rezervacija_id"
                        @click="payReservation(item.rezervacija_id)"
                      >
                        Apmaksāt tagad
                      </v-btn>
                      <v-btn
                        color="error"
                        variant="text"
                        size="small"
                        class="ms-2"
                        :loading="cancellingReservationId === item.rezervacija_id"
                        @click="openCancelDialog(item.rezervacija_id)"
                      >
                        Atcelt rezervāciju
                      </v-btn>
                    </v-card-text>
                  </v-card>
                </div>
              </template>

              <v-dialog v-model="cancelDialog" max-width="420">
                <v-card>
                  <v-card-title class="font-weight-bold">Drošības apstiprinājums</v-card-title>
                  <v-divider />
                  <v-card-text class="pa-4">
                    Vai tiešām vēlaties atcelt šo neapmaksāto rezervāciju?
                  </v-card-text>
                  <v-card-actions class="px-4 pb-4">
                    <v-spacer />
                    <v-btn variant="text" :disabled="cancellingReservationId !== null" @click="cancelDialog = false">Nē</v-btn>
                    <v-btn color="error" :loading="cancellingReservationId !== null" @click="cancelReservation">Jā, atcelt</v-btn>
                  </v-card-actions>
                </v-card>
              </v-dialog>

            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </v-container>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const formRef = ref(null)
const loading = ref(false)
const errorText = ref('')
const successText = ref('')
const fieldErrors = ref({})
const reservations = ref([])
const reservationsLoading = ref(false)
const reservationsError = ref('')
const reservationsSuccess = ref('')
const payingReservationId = ref(null)
const cancellingReservationId = ref(null)
const cancelDialog = ref(false)
const pendingCancelReservationId = ref(null)
const currentClientId = ref(null)
const geocodeLoading = ref(false)
const geocodeError = ref('')
const initialProviderAddress = ref({
  iela: '',
  majas_numurs: '',
  dzivokla_numurs: '',
  pilseta: '',
  pasta_indekss: '',
})
const initialProviderCoords = ref({
  latitude: '',
  longitude: '',
})

const email = ref('')
const loma = ref('')
const form = ref({
  vards: '',
  uzvards: '',
  kontakttalrunis: '',
  lietotajvards: '',
  registracijas_numurs: '',
  iela: '',
  majas_numurs: '',
  dzivokla_numurs: '',
  pilseta: '',
  pasta_indekss: '',
  bankas_konts: '',
  latitude: '',
  longitude: '',
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
  v => !v || (v && v.length >= 1) || 'Reģistrācijas numurs nedrīkst būt tukšs'
]

const streetRules = [
  v => !v || (v && v.length >= 1) || 'Iela nedrīkst būt tukša'
]

const houseNumberRules = [
  v => !v || (v && v.length >= 1) || 'Mājas numurs nedrīkst būt tukšs'
]

const cityRules = [
  v => !v || (v && v.length >= 1) || 'Pilsēta nedrīkst būt tukša'
]

const postalCodeRules = [
  v => !v || (v && v.length >= 1) || 'Pasta indekss nedrīkst būt tukšs'
]

const passwordRules = [
  v => !v || (v && v.length >= 8) || 'Parolei jābūt vismaz 8 simbolu garai'
]

const unpaidReservations = computed(() =>
  reservations.value.filter(item => item.apmaksas_statuss !== 'apmaksata')
)

const activeReservations = computed(() => {
  const now = new Date()
  return reservations.value.filter(item => {
    if (item.apmaksas_statuss !== 'apmaksata') return false
    const end = new Date(item.beigu_laiks)
    return !Number.isNaN(end.getTime()) && end >= now
  })
})

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
    currentClientId.value = userData.klients.klients_id
    form.value.lietotajvards = userData.klients.lietotajvards || ''
    loadReservations(userData.klients.klients_id)
  } else if (userData.loma === 'pakalpojumu_sniedzejs') {
    const sniedzejs = userData.pakalpojumuSniedzejs || userData.pakalpojumu_sniedzejs || null
    if (sniedzejs) {
      form.value.registracijas_numurs = sniedzejs.registracijas_numurs || ''
      form.value.iela = sniedzejs.iela || ''
      form.value.majas_numurs = sniedzejs.majas_numurs || ''
      form.value.dzivokla_numurs = sniedzejs.dzivokla_numurs || ''
      form.value.pilseta = sniedzejs.pilseta || ''
      form.value.pasta_indekss = sniedzejs.pasta_indekss || ''
      form.value.latitude = sniedzejs.latitude ?? ''
      form.value.longitude = sniedzejs.longitude ?? ''
      initialProviderAddress.value = {
        iela: form.value.iela,
        majas_numurs: form.value.majas_numurs,
        dzivokla_numurs: form.value.dzivokla_numurs,
        pilseta: form.value.pilseta,
        pasta_indekss: form.value.pasta_indekss,
      }
      initialProviderCoords.value = {
        latitude: form.value.latitude,
        longitude: form.value.longitude,
      }
    }
  }
})

async function loadReservations(klientsId) {
  if (!klientsId) return
  reservationsLoading.value = true
  reservationsError.value = ''
  try {
    const API_BASE = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000'
    const response = await fetch(`${API_BASE}/api/rezervacijas?klients_id=${klientsId}`)
    const data = await response.json()
    if (!response.ok) {
      reservationsError.value = data?.message || 'Neizdevās ielādēt rezervācijas.'
      return
    }
    reservations.value = data
  } catch (error) {
    reservationsError.value = 'Kļūda: Neizdevās ielādēt rezervācijas.'
  } finally {
    reservationsLoading.value = false
  }
}


async function payReservation(rezervacijaId) {
  if (!currentClientId.value || !rezervacijaId) return
  payingReservationId.value = rezervacijaId
  reservationsError.value = ''
  reservationsSuccess.value = ''
  try {
    const API_BASE = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000'
    const response = await fetch(`${API_BASE}/api/rezervacijas/${rezervacijaId}/pay`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ klients_id: currentClientId.value }),
    })
    const data = await response.json()
    if (!response.ok) {
      reservationsError.value = data?.message || 'Neizdevās apmaksāt rezervāciju.'
      return
    }
    reservationsSuccess.value = 'Rezervācija apmaksāta veiksmīgi.'
    await loadReservations(currentClientId.value)
  } catch {
    reservationsError.value = 'Kļūda: Neizdevās apmaksāt rezervāciju.'
  } finally {
    payingReservationId.value = null
  }
}

function openCancelDialog(rezervacijaId) {
  pendingCancelReservationId.value = rezervacijaId
  cancelDialog.value = true
}

async function cancelReservation() {
  const rezervacijaId = pendingCancelReservationId.value
  if (!currentClientId.value || !rezervacijaId) return
  cancellingReservationId.value = rezervacijaId
  reservationsError.value = ''
  reservationsSuccess.value = ''
  try {
    const API_BASE = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000'
    const response = await fetch(`${API_BASE}/api/rezervacijas/${rezervacijaId}`, {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ klients_id: currentClientId.value }),
    })
    const data = await response.json()
    if (!response.ok) {
      reservationsError.value = data?.message || 'Neizdevās atcelt rezervāciju.'
      return
    }

    reservations.value = reservations.value.filter(item => item.rezervacija_id !== rezervacijaId)
    reservationsSuccess.value = 'Rezervācija atcelta. Transports atkal ir pieejams.'
  } catch {
    reservationsError.value = 'Kļūda: Neizdevās atcelt rezervāciju.'
  } finally {
    cancellingReservationId.value = null
    pendingCancelReservationId.value = null
    cancelDialog.value = false
  }
}
function formatDateTime(value) {
  if (!value) return '—'
  const date = new Date(value)
  return date.toLocaleString('lv-LV')
}

function formatPrice(value) {
  const num = Number(value || 0)
  return `${num.toFixed(2)} €`
}

function buildFullAddress() {
  const parts = [
    form.value.iela,
    form.value.majas_numurs,
    form.value.pilseta,
    form.value.pasta_indekss,
    'Latvia',
  ].filter(part => part && String(part).trim().length > 0)
  return parts.join(', ')
}

function hasMinimalAddress() {
  return Boolean(form.value.iela && form.value.pilseta)
}

function isProviderAddressChanged() {
  return [
    ['iela', form.value.iela, initialProviderAddress.value.iela],
    ['majas_numurs', form.value.majas_numurs, initialProviderAddress.value.majas_numurs],
    ['dzivokla_numurs', form.value.dzivokla_numurs, initialProviderAddress.value.dzivokla_numurs],
    ['pilseta', form.value.pilseta, initialProviderAddress.value.pilseta],
    ['pasta_indekss', form.value.pasta_indekss, initialProviderAddress.value.pasta_indekss],
  ].some(([, current, original]) => String(current ?? '').trim() !== String(original ?? '').trim())
}

function areProviderCoordsChanged() {
  return [
    ['latitude', form.value.latitude, initialProviderCoords.value.latitude],
    ['longitude', form.value.longitude, initialProviderCoords.value.longitude],
  ].some(([, current, original]) => String(current ?? '').trim() !== String(original ?? '').trim())
}

async function geocodeCoordinates(address) {
  if (!address) return null
  geocodeLoading.value = true
  geocodeError.value = ''
  try {
    const url = `https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(address)}`
    const response = await fetch(url)
    const data = await response.json()
    if (!Array.isArray(data) || !data.length) return null
    const result = data[0]
    return {
      lat: result?.lat ? Number(result.lat) : null,
      lng: result?.lon ? Number(result.lon) : null,
    }
  } catch (error) {
    return null
  } finally {
    geocodeLoading.value = false
  }
}

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
      if (form.value.iela) updateData.iela = form.value.iela
      if (form.value.majas_numurs) updateData.majas_numurs = form.value.majas_numurs
      if (form.value.dzivokla_numurs) updateData.dzivokla_numurs = form.value.dzivokla_numurs
      if (form.value.pilseta) updateData.pilseta = form.value.pilseta
      if (form.value.pasta_indekss) updateData.pasta_indekss = form.value.pasta_indekss
      const addressChanged = isProviderAddressChanged()
      const coordsChanged = areProviderCoordsChanged()
      const needsCoords = form.value.latitude === '' || form.value.longitude === ''
      const shouldGeocode = (needsCoords || addressChanged) && !coordsChanged
      if (shouldGeocode) {
        if (!hasMinimalAddress()) {
          geocodeError.value = 'Norādi vismaz ielu un pilsētu, lai automātiski atrastu koordinātes.'
          loading.value = false
          return
        } else {
          const address = buildFullAddress()
          const coords = await geocodeCoordinates(address)
          if (coords && coords.lat !== null && coords.lng !== null) {
            form.value.latitude = coords.lat
            form.value.longitude = coords.lng
            geocodeError.value = ''
          } else if (address) {
            geocodeError.value = 'Neizdevās atrast koordinātes no adreses.'
            loading.value = false
            return
          }
        }
      }
      if (form.value.latitude !== '') updateData.latitude = Number(form.value.latitude)
      if (form.value.longitude !== '') updateData.longitude = Number(form.value.longitude)
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
    if (loma.value === 'pakalpojumu_sniedzejs') {
      initialProviderAddress.value = {
        iela: form.value.iela,
        majas_numurs: form.value.majas_numurs,
        dzivokla_numurs: form.value.dzivokla_numurs,
        pilseta: form.value.pilseta,
        pasta_indekss: form.value.pasta_indekss,
      }
      initialProviderCoords.value = {
        latitude: form.value.latitude,
        longitude: form.value.longitude,
      }
    }
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
  background: rgba(255, 255, 255, 0.96);
  border: 1px solid rgba(148, 163, 184, 0.25);
  border-radius: 22px;
  color: #0f172a;
  backdrop-filter: blur(10px);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.surface:hover {
  box-shadow: 0 18px 44px rgba(15, 23, 42, 0.12);
}

.profile-header {
  background: linear-gradient(140deg, rgba(255, 255, 255, 0.99), rgba(241, 245, 249, 0.92));
  border: 1px solid rgba(148, 163, 184, 0.3);
}

.avatar-icon {
  background: linear-gradient(135deg, rgba(37, 99, 235, 0.14), rgba(99, 102, 241, 0.1));
  border: 1px solid rgba(59, 130, 246, 0.26);
}

.section-title {
  font-weight: 700;
  font-size: 0.82rem;
  letter-spacing: 0.5px;
  color: rgba(15, 23, 42, 0.72);
  text-transform: uppercase;
  opacity: 0.95;
  padding-left: 10px;
  border-left: 3px solid rgba(37, 99, 235, 0.7);
}

.reservation-card {
  border: 1px solid rgba(148, 163, 184, 0.26);
  border-radius: 16px;
  background: rgba(255, 255, 255, 0.95);
}

.reservation-card-unpaid {
  border-color: rgba(245, 158, 11, 0.45);
  background: linear-gradient(135deg, rgba(255, 251, 235, 0.95), rgba(255, 247, 237, 0.88));
}

.profile-reservations,
.profile-reservations :deep(.v-card-title),
.profile-reservations :deep(.v-card-text),
.profile-reservations .text-h5,
.profile-reservations .text-body-2,
.profile-reservations .text-caption,
.profile-reservations .section-title,
.profile-reservations .font-weight-bold {
  color: #0f172a !important;
}

.badge-info {
  display: inline-block;
  padding: 6px 12px;
  background: rgba(37, 99, 235, 0.12);
  color: #1d4ed8;
  border-radius: 20px;
  font-size: 0.82rem;
  font-weight: 600;
  border: 1px solid rgba(59, 130, 246, 0.26);
}

.profile-email {
  font-size: 0.82rem;
  color: rgba(15, 23, 42, 0.62);
}

.logout-btn {
  background: rgba(239, 68, 68, 0.2);
  border: 1px solid rgba(220, 38, 38, 0.38);
  color: #7f1d1d !important;
}

.back-btn {
  border-color: rgba(148, 163, 184, 0.45) !important;
}

.gap-3 {
  gap: 12px !important;
}

.surface :deep(.v-field) {
  border-radius: 12px;
}

.surface :deep(.v-btn) {
  text-transform: none;
  letter-spacing: 0.01em;
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
