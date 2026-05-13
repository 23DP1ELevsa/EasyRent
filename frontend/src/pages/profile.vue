<template>
  <div class="profile-bg">
    <v-container class="py-10 py-sm-16 profile-shell">
      <v-row justify="center">
        <!-- Profile Header Card -->
        <v-col cols="12" md="11" lg="10" xl="9">
          <v-card class="surface profile-header mb-6" elevation="12">
            <v-card-text class="pa-8">
              <div class="d-flex align-center justify-space-between flex-wrap ga-4">
                <div>
                  <div class="text-h4 font-weight-bold mb-2">{{ form.vards }} {{ form.uzvards }}</div>
                  <div class="text-body-2 opacity-80 d-flex align-center ga-2 flex-wrap">
                    <span v-if="loma === 'klients'" class="badge-info badge-info-provider">🔹 {{ copy.roles.client }}</span>
                    <span v-else class="badge-info badge-info-provider">🏢 {{ copy.roles.provider }}</span>
                    <span class="profile-email">{{ email }}</span>
                  </div>
                </div>
                <v-avatar size="80" variant="tonal" class="avatar-icon">
                  <v-icon size="48">{{ loma === 'klients' ? 'mdi-account' : 'mdi-briefcase' }}</v-icon>
                </v-avatar>
              </div>

              <div class="profile-stat-grid mt-6">
                <div class="metric-pill">
                  <strong>{{ loma === 'klients' ? activeReservations.length : providerActiveReservations.length }}</strong>
                  <span>{{ loma === 'klients' ? copy.stats.activePaid : copy.stats.bookedNow }}</span>
                </div>
                <div class="metric-pill">
                  <strong>{{ loma === 'klients' ? unpaidReservations.length : providerPendingReservations.length }}</strong>
                  <span>{{ loma === 'klients' ? copy.stats.unpaid : copy.stats.awaitingPayment }}</span>
                </div>
                <div class="metric-pill">
                  <strong>{{ form.kontakttalrunis || '—' }}</strong>
                  <span>{{ copy.stats.primaryContact }}</span>
                </div>
              </div>
            </v-card-text>
          </v-card>

          <!-- Edit Form Card -->
          <v-card class="surface" elevation="12">
            <v-card-title class="pa-6 pa-sm-8">
              <div>
                <div class="text-h5 font-weight-bold">{{ copy.page.title }}</div>
                <div class="text-caption opacity-70 mt-1">{{ copy.page.subtitle }}</div>
              </div>
            </v-card-title>

            <v-divider />

            <v-card-text class="pa-6 pa-sm-8">
              <!-- Success/Error Messages -->
              <v-form ref="formRef" @submit.prevent="updateProfile">
                <!-- Basic Information Section -->
                <div class="section-title mb-4">{{ copy.sections.basic }}</div>
                
                <v-row dense class="mb-6">
                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model="form.vards"
                      :label="copy.fields.firstName"
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
                      :label="copy.fields.lastName"
                      variant="outlined"
                      density="compact"
                      :error="!!fieldErrors.uzvards"
                      :error-messages="fieldErrors.uzvards ? [fieldErrors.uzvards] : []"
                    />
                  </v-col>

                  <v-col cols="12">
                    <v-text-field
                      v-model="email"
                      :label="copy.fields.email"
                      variant="outlined"
                      density="compact"
                      disabled
                      :hint="copy.hints.emailLocked"
                    />
                  </v-col>

                  <v-col cols="12">
                    <v-text-field
                      v-model="form.kontakttalrunis"
                      :label="copy.fields.phone"
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
                <div class="section-title mb-4">{{ copy.sections.bank }}</div>

                <v-row dense class="mb-6">
                  <v-col cols="12">
                    <v-text-field
                      v-model="form.bankas_konts"
                      :label="copy.fields.iban"
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
                  <div class="section-title mb-4">{{ copy.sections.client }}</div>

                  <v-row dense class="mb-6">
                    <v-col cols="12">
                      <v-text-field
                        v-model="form.lietotajvards"
                        :label="copy.fields.username"
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
                  <div class="section-title mb-4">{{ copy.sections.provider }}</div>

                  <v-row dense class="mb-6">
                    <v-col cols="12">
                      <v-text-field
                        v-model="form.registracijas_numurs"
                        :label="copy.fields.registrationNumber"
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
                        :label="copy.fields.street"
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
                        :label="copy.fields.houseNumber"
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
                        :label="copy.fields.apartmentNumber"
                        variant="outlined"
                        density="compact"
                        :error="!!fieldErrors.dzivokla_numurs"
                        :error-messages="fieldErrors.dzivokla_numurs ? [fieldErrors.dzivokla_numurs] : []"
                      />
                    </v-col>

                    <v-col cols="12" sm="6">
                      <v-text-field
                        v-model="form.pilseta"
                        :label="copy.fields.city"
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
                        :label="copy.fields.postalCode"
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
                        :label="copy.fields.latitude"
                        variant="outlined"
                        density="compact"
                        placeholder="56.9496"
                      />
                    </v-col>

                    <v-col cols="12" sm="6">
                      <v-text-field
                        v-model="form.longitude"
                        :label="copy.fields.longitude"
                        variant="outlined"
                        density="compact"
                        placeholder="24.1052"
                      />
                    </v-col>

                    <v-col cols="12">
                      <div v-if="geocodeLoading" class="text-caption opacity-70">{{ copy.hints.geocodeLoading }}</div>
                      <div v-else-if="geocodeError" class="text-caption text-error">{{ geocodeError }}</div>
                    </v-col>
                  </v-row>
                </template>

                <!-- Password Section -->
                <v-divider class="my-6" />
                <div class="section-title mb-4">{{ copy.sections.password }}</div>

                <v-row dense class="mb-8">
                  <v-col cols="12">
                    <v-text-field
                      v-model="form.password"
                      type="password"
                      :label="copy.fields.newPassword"
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
                      {{ copy.actions.save }}
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
                      {{ copy.actions.logout }}
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
                      {{ copy.actions.back }}
                    </v-btn>
                  </v-col>
                </v-row>
              </v-form>
            </v-card-text>
          </v-card>

          <v-card v-if="showReservationsSection" class="surface mt-6 profile-reservations" elevation="12">
            <v-card-title class="pa-6 pa-sm-8">
              <div>
                <div class="text-h5 font-weight-bold">{{ copy.reservations.title }}</div>
                <div class="text-caption opacity-70 mt-1">{{ reservationsSubtitle }}</div>
              </div>
            </v-card-title>
            <v-divider />
            <v-card-text class="pa-6 pa-sm-8">
              <v-alert
                v-if="isClient && unpaidReservations.length"
                type="warning"
                variant="tonal"
                class="mb-4"
              >
                {{ copy.reservations.unpaidAlert.replace('{count}', unpaidReservations.length) }}
              </v-alert>

              <div v-if="reservationsLoading" class="text-body-2 opacity-70">{{ copy.reservations.loading }}</div>
              <div v-else-if="!reservations.length" class="text-body-2 opacity-70">
                {{ copy.reservations.empty }}
              </div>

              <template v-else>
                <div class="section-title mb-3">{{ activeReservationsTitle }}</div>

                <div v-if="!visibleActiveReservations.length" class="text-body-2 opacity-70 mb-4">{{ activeReservationsEmptyText }}</div>

                <div v-else class="d-flex flex-column ga-3 mb-6">
                  <v-card
                    v-for="item in visibleActiveReservations"
                    :key="`active-${item.rezervacija_id}`"
                    class="reservation-card"
                    elevation="4"
                  >
                    <v-card-text class="pa-4">
                      <div class="reservation-card-layout">
                        <div class="reservation-card-main">
                          <div class="reservation-card-title font-weight-bold">
                            {{ item.transportlidzeklis?.marka }} {{ item.transportlidzeklis?.modelis }}
                          </div>
                          <div class="reservation-card-meta text-caption opacity-70">
                            {{ reservationCounterpartyName(item) }}
                          </div>
                          <div v-if="reservationVehicleDetails(item)" class="reservation-card-meta text-caption opacity-70">
                            {{ reservationVehicleDetails(item) }}
                          </div>
                          <div class="reservation-card-meta text-caption opacity-70">
                            {{ formatDateTime(item.sakuma_laiks) }} - {{ formatDateTime(item.beigu_laiks) }}
                          </div>
                        </div>
                        <div class="reservation-card-price">
                          <div class="reservation-card-amount font-weight-bold">{{ formatPrice(item.kopa_summa) }}</div>
                          <v-chip size="small" color="success" variant="tonal">{{ copy.reservations.paid }}</v-chip>
                        </div>
                      </div>
                    </v-card-text>
                  </v-card>
                </div>

                <div class="section-title mb-3">{{ pendingReservationsTitle }}</div>

                <div v-if="!visiblePendingReservations.length" class="text-body-2 opacity-70">{{ pendingReservationsEmptyText }}</div>

                <div v-else class="d-flex flex-column ga-3">
                  <v-card
                    v-for="item in visiblePendingReservations"
                    :key="`unpaid-${item.rezervacija_id}`"
                    class="reservation-card reservation-card-unpaid"
                    elevation="4"
                  >
                    <v-card-text class="pa-4">
                      <div class="reservation-card-layout mb-3">
                        <div class="reservation-card-main">
                          <div class="reservation-card-title font-weight-bold">
                            {{ item.transportlidzeklis?.marka }} {{ item.transportlidzeklis?.modelis }}
                          </div>
                          <div class="reservation-card-meta text-caption opacity-70">
                            {{ reservationCounterpartyName(item) }}
                          </div>
                          <div v-if="reservationVehicleDetails(item)" class="reservation-card-meta text-caption opacity-70">
                            {{ reservationVehicleDetails(item) }}
                          </div>
                          <div class="reservation-card-meta text-caption opacity-70">
                            {{ formatDateTime(item.sakuma_laiks) }} - {{ formatDateTime(item.beigu_laiks) }}
                          </div>
                        </div>
                        <div class="reservation-card-price">
                          <div class="reservation-card-amount font-weight-bold">{{ formatPrice(item.kopa_summa) }}</div>
                          <v-chip size="small" color="warning" variant="tonal">{{ copy.reservations.unpaidStatus }}</v-chip>
                        </div>
                      </div>

                      <template v-if="isClient">
                        <div class="reservation-card-actions">
                          <v-btn
                            color="primary"
                            variant="outlined"
                            size="small"
                            :loading="payingReservationId === item.rezervacija_id"
                            @click="payReservation(item.rezervacija_id)"
                          >
                            {{ copy.reservations.payNow }}
                          </v-btn>
                          <v-btn
                            color="error"
                            variant="text"
                            size="small"
                            :loading="cancellingReservationId === item.rezervacija_id"
                            @click="openCancelDialog(item.rezervacija_id)"
                          >
                            {{ copy.reservations.cancelReservation }}
                          </v-btn>
                        </div>
                      </template>
                    </v-card-text>
                  </v-card>
                </div>

                <div class="section-title mt-6 mb-3">{{ reservationHistoryTitle }}</div>

                <div v-if="!reservationHistory.length" class="text-body-2 opacity-70">{{ reservationHistoryEmptyText }}</div>

                <div v-else class="d-flex flex-column ga-4">
                  <v-card
                    v-for="item in paginatedReservationHistory"
                    :key="`history-${item.rezervacija_id}`"
                    class="reservation-card reservation-card-history"
                    elevation="4"
                  >
                    <v-card-text class="pa-4">
                      <div class="reservation-card-layout">
                        <div class="reservation-card-main">
                          <div class="reservation-card-title font-weight-bold">
                            {{ item.transportlidzeklis?.marka }} {{ item.transportlidzeklis?.modelis }}
                          </div>
                          <div class="reservation-card-meta text-caption opacity-70">
                            {{ reservationCounterpartyName(item) }}
                          </div>
                          <div v-if="reservationVehicleDetails(item)" class="reservation-card-meta text-caption opacity-70">
                            {{ reservationVehicleDetails(item) }}
                          </div>
                          <div class="reservation-card-meta text-caption opacity-70">
                            {{ copy.reservations.bookedAt }}: {{ reservationBookedAt(item) }}
                          </div>
                          <div class="reservation-card-meta text-caption opacity-70">
                            {{ formatDateTime(item.sakuma_laiks) }} - {{ formatDateTime(item.beigu_laiks) }}
                          </div>
                        </div>
                        <div class="reservation-card-price">
                          <div class="reservation-card-amount font-weight-bold">{{ formatPrice(item.kopa_summa) }}</div>
                          <v-chip size="small" color="info" variant="tonal">{{ copy.reservations.historyDone }}</v-chip>
                        </div>
                      </div>
                    </v-card-text>
                  </v-card>

                  <v-pagination
                    v-if="reservationHistoryPageCount > 1"
                    v-model="reservationHistoryPage"
                    :length="reservationHistoryPageCount"
                    :total-visible="5"
                    rounded="circle"
                    density="comfortable"
                    class="reservation-history-pagination align-self-center"
                  />
                </div>
              </template>

              <v-dialog v-model="cancelDialog" max-width="420">
                <v-card>
                  <v-card-title class="font-weight-bold">{{ copy.confirm.title }}</v-card-title>
                  <v-divider />
                  <v-card-text class="pa-4">
                    {{ copy.confirm.cancelReservation }}
                  </v-card-text>
                  <v-card-actions class="px-4 pb-4">
                    <v-spacer />
                    <v-btn variant="text" :disabled="cancellingReservationId !== null" @click="cancelDialog = false">{{ copy.confirm.no }}</v-btn>
                    <v-btn color="error" :loading="cancellingReservationId !== null" @click="cancelReservation">{{ copy.confirm.yesCancel }}</v-btn>
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
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useLocale } from '@/stores/locale'
import { HOME_ROUTE } from '@/router/paths'
import { useNotifications } from '@/stores/notifications'
import { clearAuthSession, getApiBase, getAuthHeaders, getStoredToken, setAuthSession, syncCurrentUser } from '@/services/auth'

const router = useRouter()
const { t, getIntlLocale } = useLocale()
const { notifyError, notifySuccess } = useNotifications()
const copy = computed(() => t('profile'))
const isClient = computed(() => loma.value === 'klients')
const isProvider = computed(() => loma.value === 'pakalpojumu_sniedzejs')
// Profila lapas pamatstāvoklis un UI darbību statusi.
const formRef = ref(null)
const loading = ref(false)
const errorText = ref('')
const successText = ref('')
const fieldErrors = ref({})
const reservations = ref([])
const reservationsLoading = ref(false)
const reservationsError = ref('')
const reservationsSuccess = ref('')
const reservationHistoryPage = ref(1)
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

watch(errorText, value => {
  if (value) notifyError(value)
})

watch(successText, value => {
  if (value) notifySuccess(value)
})

watch(reservationsError, value => {
  if (value) notifyError(value)
})

watch(reservationsSuccess, value => {
  if (value) notifySuccess(value)
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

// IBAN validācija bankas konta laukam.
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

// Formas validācijas noteikumi pa laukiem.
const nameRules = [
  v => !!v || copy.value.validation.firstNameRequired,
  v => (v && v.trim().length >= 2) || copy.value.validation.firstNameMin
]

const phoneRules = [
  v => !v || (v && v.length >= 6) || copy.value.validation.phoneMin,
  v => !v || (v && v.length <= 20) || copy.value.validation.phoneMax,
  v => !v || /^\+?[0-9 \-()]+$/.test(v) || copy.value.validation.phoneInvalid
]

const ibanRules = [
  v => !v || (v && v.length >= 15) || copy.value.validation.ibanMin,
  v => !v || (v && v.length <= 34) || copy.value.validation.ibanMax,
  v => !v || ibanIsValid(v) || copy.value.validation.ibanInvalid
]

const usernameRules = [
  v => !!v || copy.value.validation.usernameRequired,
  v => (v && v.length >= 3) || copy.value.validation.usernameMin
]

const regNumRules = [
  v => !v || (v && v.length >= 1) || copy.value.validation.regNumRequired
]

const streetRules = [
  v => !v || (v && v.length >= 1) || copy.value.validation.streetRequired
]

const houseNumberRules = [
  v => !v || (v && v.length >= 1) || copy.value.validation.houseRequired
]

const cityRules = [
  v => !v || (v && v.length >= 1) || copy.value.validation.cityRequired
]

const postalCodeRules = [
  v => !v || (v && v.length >= 1) || copy.value.validation.postalRequired
]

function hasUppercase(value) {
  return /[A-Z]/.test(value || '')
}

function hasSpecialCharacter(value) {
  return /[^A-Za-z0-9]/.test(value || '')
}

const passwordRules = [
  v => !v || (v && v.length >= 8) || copy.value.validation.passwordMin,
  v => !v || hasUppercase(v) || copy.value.validation.passwordUppercase,
  v => !v || hasSpecialCharacter(v) || copy.value.validation.passwordSpecial
]

const unpaidReservations = computed(() =>
  reservations.value.filter(item => item.apmaksas_statuss !== 'apmaksata')
)

const providerPendingReservations = computed(() => {
  const now = new Date()
  return reservations.value.filter(item => {
    const end = new Date(item.beigu_laiks)
    return item.apmaksas_statuss !== 'apmaksata' && !Number.isNaN(end.getTime()) && end >= now
  })
})

const activeReservations = computed(() => {
  const now = new Date()
  return reservations.value.filter(item => {
    if (item.apmaksas_statuss !== 'apmaksata') return false
    const end = new Date(item.beigu_laiks)
    return !Number.isNaN(end.getTime()) && end >= now
  })
})

const providerActiveReservations = computed(() => {
  const now = new Date()
  return reservations.value.filter(item => {
    if (item.apmaksas_statuss !== 'apmaksata') return false
    const end = new Date(item.beigu_laiks)
    return !Number.isNaN(end.getTime()) && end >= now
  })
})

const reservationHistory = computed(() => {
  const now = new Date()
  return reservations.value.filter(item => {
    const end = new Date(item.beigu_laiks)
    return !Number.isNaN(end.getTime()) && end < now
  })
})

const reservationHistoryPageSize = 5

const reservationHistoryPageCount = computed(() =>
  Math.max(1, Math.ceil(reservationHistory.value.length / reservationHistoryPageSize))
)

const paginatedReservationHistory = computed(() => {
  const start = (reservationHistoryPage.value - 1) * reservationHistoryPageSize
  return reservationHistory.value.slice(start, start + reservationHistoryPageSize)
})

const showReservationsSection = computed(() => isClient.value || isProvider.value)
const visibleActiveReservations = computed(() => isProvider.value ? providerActiveReservations.value : activeReservations.value)
const visiblePendingReservations = computed(() => isProvider.value ? providerPendingReservations.value : unpaidReservations.value)
const reservationsSubtitle = computed(() => isProvider.value ? copy.value.reservations.providerSubtitle : copy.value.reservations.subtitle)
const activeReservationsTitle = computed(() => isProvider.value ? copy.value.reservations.providerActiveTitle : copy.value.reservations.activeTitle)
const pendingReservationsTitle = computed(() => isProvider.value ? copy.value.reservations.providerPendingTitle : copy.value.reservations.unpaidTitle)
const activeReservationsEmptyText = computed(() => isProvider.value ? copy.value.reservations.noProviderActive : copy.value.reservations.noActive)
const pendingReservationsEmptyText = computed(() => isProvider.value ? copy.value.reservations.noProviderPending : copy.value.reservations.noUnpaid)
const reservationHistoryTitle = computed(() => isProvider.value ? copy.value.reservations.providerHistoryTitle : copy.value.reservations.historyTitle)
const reservationHistoryEmptyText = computed(() => isProvider.value ? copy.value.reservations.noProviderHistory : copy.value.reservations.noHistory)

watch(reservationHistoryPageCount, pageCount => {
  if (reservationHistoryPage.value > pageCount) {
    reservationHistoryPage.value = pageCount
  }
})

// Ielādē lietotāja datus no sesijas un aizpilda formu.
onMounted(() => {
  initializeProfile()
})

async function initializeProfile() {
  const token = getStoredToken()

  if (!token) {
    errorText.value = copy.value.validation.loginRequired
    setTimeout(() => router.push(HOME_ROUTE), 2000)
    return
  }

  const userData = await syncCurrentUser()

  if (!userData) {
    errorText.value = copy.value.validation.loginRequired
    setTimeout(() => router.push(HOME_ROUTE), 2000)
    return
  }

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
    loadReservations()
  }
}

// Ielādē klienta rezervācijas no API.
async function loadReservations(klientsId) {
  if (isClient.value && !klientsId) return
  reservationsLoading.value = true
  reservationsError.value = ''
  try {
    const response = await fetch(`${getApiBase()}/api/rezervacijas`, {
      headers: getAuthHeaders({ Accept: 'application/json' }),
    })
    const data = await response.json()
    if (!response.ok) {
      reservationsError.value = data?.message || copy.value.messages.loadReservationsFailed
      return
    }
    reservations.value = data
  } catch (error) {
    reservationsError.value = copy.value.messages.loadReservationsError
  } finally {
    reservationsLoading.value = false
  }
}


// Veic neapmaksātas rezervācijas apmaksu.
async function payReservation(rezervacijaId) {
  if (!currentClientId.value || !rezervacijaId) return
  payingReservationId.value = rezervacijaId
  reservationsError.value = ''
  reservationsSuccess.value = ''
  try {
    const response = await fetch(`${getApiBase()}/api/rezervacijas/${rezervacijaId}/pay`, {
      method: 'POST',
      headers: getAuthHeaders({ 'Content-Type': 'application/json' }),
    })
    const data = await response.json()
    if (!response.ok) {
      reservationsError.value = data?.message || copy.value.validation.payReservationFailed
      return
    }
    reservationsSuccess.value = copy.value.messages.reservationPaid
    await loadReservations(currentClientId.value)
  } catch {
    reservationsError.value = copy.value.validation.payReservationError
  } finally {
    payingReservationId.value = null
  }
}

function openCancelDialog(rezervacijaId) {
  pendingCancelReservationId.value = rezervacijaId
  cancelDialog.value = true
}

// Atceļ izvēlēto rezervāciju un atjauno sarakstu.
async function cancelReservation() {
  const rezervacijaId = pendingCancelReservationId.value
  if (!currentClientId.value || !rezervacijaId) return
  cancellingReservationId.value = rezervacijaId
  reservationsError.value = ''
  reservationsSuccess.value = ''
  try {
    const response = await fetch(`${getApiBase()}/api/rezervacijas/${rezervacijaId}`, {
      method: 'DELETE',
      headers: getAuthHeaders({ 'Content-Type': 'application/json' }),
    })
    const data = await response.json()
    if (!response.ok) {
      reservationsError.value = data?.message || copy.value.messages.cancelReservationFailed
      return
    }

    reservations.value = reservations.value.filter(item => item.rezervacija_id !== rezervacijaId)
    reservationsSuccess.value = copy.value.messages.reservationCanceled
  } catch {
    reservationsError.value = copy.value.messages.cancelReservationError
  } finally {
    cancellingReservationId.value = null
    pendingCancelReservationId.value = null
    cancelDialog.value = false
  }
}
function formatDateTime(value) {
  if (!value) return '—'
  const date = new Date(value)
  return date.toLocaleString(getIntlLocale())
}

function formatPrice(value) {
  const num = Number(value || 0)
  return `${num.toFixed(2)} €`
}

function normalizeVehicleField(value) {
  if (value === null || value === undefined) return ''
  const normalized = String(value).trim()
  return normalized && normalized !== '-' ? normalized : ''
}

function reservationVehicleDetails(item) {
  const vehicle = item?.transportlidzeklis
  if (!vehicle) return ''

  return [
    [copy.value.vehicleFields.type, vehicle.veids?.nosaukums],
    [copy.value.vehicleFields.gearbox, normalizeVehicleField(vehicle.atrumkarba)],
    [copy.value.vehicleFields.fuel, normalizeVehicleField(vehicle.degvielas_veids)],
    [copy.value.vehicleFields.registrationNumber, normalizeVehicleField(vehicle.registracijas_numurs)],
  ].filter(([, value]) => value).map(([label, value]) => `${label}: ${value}`).join(' • ')
}

function reservationBookedAt(item) {
  return formatDateTime(item?.izveides_datums || item?.rezervacijas_datums || item?.sakuma_laiks)
}

function reservationCounterpartyName(item) {
  if (isProvider.value) {
    const clientPersona = item?.klients?.persona
    const fullName = `${clientPersona?.vards || ''} ${clientPersona?.uzvards || ''}`.trim()
    return fullName || copy.value.roles.client
  }

  const providerPersona = item?.transportlidzeklis?.sniedzejs?.persona
  const fullName = `${providerPersona?.vards || ''} ${providerPersona?.uzvards || ''}`.trim()
  return fullName || copy.value.roles.provider
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

// Nosūta profila izmaiņas serverim un atjauno lokālo lietotāju.
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
    const token = getStoredToken()
    
    if (!token) {
      throw new Error('Sesija beidzas. Lūdzu, piesakieties atkārtoti.')
    }

    const user = await syncCurrentUser()
    const API_BASE = getApiBase()

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
      if (form.value.pilseta) updateData.pilseta = form.value.pilseta
      if (form.value.pasta_indekss) updateData.pasta_indekss = form.value.pasta_indekss
      const apartmentNumber = String(form.value.dzivokla_numurs ?? '').trim()
      const initialApartmentNumber = String(initialProviderAddress.value.dzivokla_numurs ?? '').trim()
      if (apartmentNumber !== initialApartmentNumber) {
        updateData.dzivokla_numurs = apartmentNumber || null
      }
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

    const response = await fetch(`${API_BASE}/api/profile/${user.persona_id}`, {
      method: 'PUT',
      headers: getAuthHeaders({
        'Content-Type': 'application/json',
      }),
      body: JSON.stringify(updateData),
    })
    
    let result
    const contentType = response.headers.get('content-type')
    if (contentType?.includes('application/json')) {
      result = await response.json()
    } else {
      result = { message: await response.text() }
    }

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

    setAuthSession({ token, persona: result.persona })
    
    successText.value = copy.value.messages.profileUpdated
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
    errorText.value = error?.message || 'Kļūda: Neizdevās savienot ar serveri'
  } finally {
    loading.value = false
  }
}

// Notīra sesiju un pārvirza uz sākumlapu.
function logout() {
  clearAuthSession()
  router.push(HOME_ROUTE)
}
</script>

<style scoped>
.profile-bg {
  min-height: calc(100vh - 72px - 64px);
  background: var(--er-page-bg);
}

.profile-shell {
  max-width: 1480px;
}

.surface {
  background: var(--er-surface);
  border: 1px solid var(--er-stroke);
  border-radius: 28px;
  color: var(--er-text);
  backdrop-filter: blur(14px);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: var(--er-shadow-md);
}

.surface:hover {
  box-shadow: 0 18px 40px rgba(25, 41, 55, 0.08);
}

.profile-header {
  background:
    radial-gradient(circle at top right, rgba(201, 107, 59, 0.12), transparent 28%),
    linear-gradient(140deg, color-mix(in srgb, var(--er-panel-strong) 94%, transparent), color-mix(in srgb, var(--er-surface) 96%, transparent));
  border: 1px solid var(--er-stroke);
}

.avatar-icon {
	background: linear-gradient(135deg, rgba(15, 118, 110, 0.12), rgba(201, 107, 59, 0.12));
	border: 1px solid rgba(21, 48, 71, 0.12);
}

.profile-stat-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}

.section-title {
  font-weight: 700;
  font-size: 0.82rem;
  letter-spacing: 0.5px;
  color: color-mix(in srgb, var(--er-text) 72%, transparent);
  text-transform: uppercase;
  opacity: 0.95;
  padding-left: 12px;
  border-left: 3px solid rgba(15, 118, 110, 0.72);
}

.reservation-card {
  border: 1px solid var(--er-stroke);
  border-radius: 20px;
  background: var(--er-card-soft);
}

.reservation-card-layout {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
}

.reservation-card-main {
  min-width: 0;
  flex: 1 1 auto;
}

.reservation-card-title {
  font-family: 'Plus Jakarta Sans', 'Avenir Next', 'Segoe UI', sans-serif;
  font-size: 1.18rem;
  line-height: 1.15;
  letter-spacing: -0.025em;
  margin-bottom: 4px;
}

.reservation-card-meta {
  line-height: 1.35;
}

.reservation-card-price {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 8px;
  flex: 0 0 auto;
  text-align: right;
}

.reservation-card-amount {
  font-family: 'Plus Jakarta Sans', 'Avenir Next', 'Segoe UI', sans-serif;
  font-size: clamp(1rem, 0.98rem + 0.4vw, 1.18rem);
  font-weight: 800;
  line-height: 1.08;
  letter-spacing: -0.035em;
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
  font-feature-settings: 'tnum' 1, 'lnum' 1;
}

.reservation-card-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.reservation-card-unpaid {
  border-color: rgba(245, 158, 11, 0.45);
  background: linear-gradient(135deg, rgba(255, 248, 236, 0.96), rgba(255, 244, 236, 0.88));
  color: var(--er-text);
}

.reservation-card-history {
  border-color: rgba(14, 116, 144, 0.26);
  background: linear-gradient(135deg, rgba(239, 246, 255, 0.98), rgba(232, 244, 255, 0.88));
  color: var(--er-text);
}

.reservation-card-unpaid :deep(.v-card-text),
.reservation-card-history :deep(.v-card-text),
.reservation-card-unpaid .font-weight-bold,
.reservation-card-history .font-weight-bold,
.reservation-card-unpaid .text-caption,
.reservation-card-history .text-caption,
.reservation-card-unpaid .text-body-2 {
  color: inherit !important;
}

:root[data-theme='dark'] .reservation-card-unpaid {
  border-color: rgba(245, 158, 11, 0.34);
  background: linear-gradient(135deg, rgba(78, 48, 12, 0.9), rgba(46, 30, 10, 0.96));
  color: var(--er-text);
}

:root[data-theme='dark'] .reservation-card-history {
  border-color: rgba(56, 189, 248, 0.24);
  background: linear-gradient(135deg, rgba(8, 47, 73, 0.9), rgba(12, 34, 56, 0.96));
  color: var(--er-text);
}

:root[data-theme='dark'] .reservation-card-unpaid :deep(.v-card-text),
:root[data-theme='dark'] .reservation-card-history :deep(.v-card-text),
:root[data-theme='dark'] .reservation-card-unpaid .font-weight-bold,
:root[data-theme='dark'] .reservation-card-history .font-weight-bold,
:root[data-theme='dark'] .reservation-card-unpaid .text-caption,
:root[data-theme='dark'] .reservation-card-history .text-caption,
:root[data-theme='dark'] .reservation-card-unpaid .text-body-2 {
  color: var(--er-text) !important;
}

.profile-reservations,
.profile-reservations :deep(.v-card-title),
.profile-reservations :deep(.v-card-text),
.profile-reservations .text-h5,
.profile-reservations .text-body-2,
.profile-reservations .text-caption,
.profile-reservations .section-title,
.profile-reservations .font-weight-bold {
  color: var(--er-text) !important;
}

.reservation-history-pagination :deep(.v-btn) {
  color: var(--er-text);
}

.badge-info {
  display: inline-block;
  padding: 6px 12px;
  background: rgba(15, 118, 110, 0.12);
  color: #115e59;
  border-radius: 20px;
  font-size: 0.82rem;
  font-weight: 600;
  border: 1px solid rgba(15, 118, 110, 0.18);
}

.badge-info-provider {
  background: rgba(255, 255, 255, 0.05);
  border-color: rgba(255, 255, 255, 0.08);
  color: color-mix(in srgb, var(--er-text) 62%, transparent);
}

.profile-email {
  font-size: 0.82rem;
  color: color-mix(in srgb, var(--er-text) 62%, transparent);
}

.logout-btn {
  background: rgba(201, 107, 59, 0.14);
  border: 1px solid rgba(201, 107, 59, 0.28);
  color: #9a4f2c !important;
}

.back-btn {
  border-color: rgba(21, 48, 71, 0.2) !important;
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

  .profile-stat-grid {
    grid-template-columns: 1fr;
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

  .reservation-card-layout {
    flex-direction: column;
    align-items: flex-start;
  }

  .reservation-card-price {
    align-items: flex-start;
    text-align: left;
    width: 100%;
  }

  .reservation-card-title {
    font-size: 1.08rem;
  }

  .reservation-card-amount {
    font-size: 1.08rem;
  }

  .reservation-card-actions {
    flex-direction: column;
    align-items: stretch;
  }

  .reservation-card-actions :deep(.v-btn) {
    width: 100%;
  }
}
</style>
