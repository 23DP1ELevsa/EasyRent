<template>
  <div class="company-page">
    <v-container class="py-8">
      <v-btn variant="text" class="mb-4 company-back-btn" @click="router.push(MAP_ROUTE)">
        <v-icon start>mdi-arrow-left</v-icon>
        Atpakaļ uz karti
      </v-btn>

      <v-card class="surface" elevation="10">
        <v-card-text class="pa-6">
          <div v-if="loading" class="text-body-1">Ielādē kompāniju...</div>
          <v-alert v-else-if="errorText" type="error" variant="tonal">{{ errorText }}</v-alert>
          <template v-else-if="company">
            <div class="company-hero mb-6">
              <div>
                <div class="soft-eyebrow mb-4">Pakalpojumu sniedzējs</div>
                <div class="text-h4 font-weight-bold mb-2">{{ company.name }}</div>
                <div class="text-body-2 opacity-80 mb-1">{{ company.address }}</div>
                <div class="text-body-2 opacity-80">{{ company.city }}</div>
              </div>
              <div class="company-hero__stats">
                <div class="metric-pill">
                  <strong>{{ vehicles.length }}</strong>
                  <span>Kopējais transports</span>
                </div>
                <div class="metric-pill">
                  <strong>{{ vehicles.filter(vehicle => isVehicleAvailable(vehicle)).length }}</strong>
                  <span>Pieejami šobrīd</span>
                </div>
                <div class="metric-pill">
                  <strong>{{ vehicles.length ? formatPrice(Math.min(...vehicles.map(vehicle => Number(vehicle.dienas_nomas_cena || 0)))) : '0.00 €' }}</strong>
                  <span>Sākuma cena dienā</span>
                </div>
              </div>
            </div>

            <div class="d-flex align-center justify-space-between mb-3">
              <div class="text-h6 font-weight-bold">Transports</div>
              <v-chip color="primary" variant="tonal">{{ filteredSortedVehicles.length }}/{{ vehicles.length }}</v-chip>
            </div>

            <v-alert v-if="!vehicles.length" type="info" variant="tonal">
              Šai kompānijai pašlaik nav pieejama transporta.
            </v-alert>

            <template v-else>
                <div class="company-toolbar d-flex justify-end flex-wrap ga-2 mb-3">
                <v-btn color="primary" variant="tonal" @click="showFilters = !showFilters">
                  <v-icon start>{{ showFilters ? 'mdi-filter-minus' : 'mdi-filter-plus' }}</v-icon>
                  {{ showFilters ? 'Aizvērt filtrus' : 'Filtri' }}
                </v-btn>
                <v-btn color="primary" variant="tonal" @click="showSorting = !showSorting">
                  <v-icon start>{{ showSorting ? 'mdi-sort-variant-remove' : 'mdi-sort-variant' }}</v-icon>
                  {{ showSorting ? 'Aizvērt kārtošanu' : 'Kārtot' }}
                </v-btn>
              </div>

              <v-expand-transition>
                <v-card v-if="showFilters" class="mb-4" variant="outlined">
                  <v-card-text class="pb-2">
                    <v-row dense>
                      <v-col cols="12" md="6">
                        <v-text-field
                          v-model="vehicleFilters.q"
                          label="Meklēt pēc markas vai modeļa"
                          variant="outlined"
                          density="compact"
                          clearable
                        />
                      </v-col>
                      <v-col cols="12" sm="6" md="3">
                        <v-select
                          v-model="vehicleFilters.typeId"
                          :items="vehicleTypeOptions"
                          item-title="title"
                          item-value="value"
                          label="Transporta veids"
                          variant="outlined"
                          density="compact"
                          clearable
                        />
                      </v-col>
                      <v-col cols="6" md="3">
                        <v-text-field
                          v-model.number="vehicleFilters.minPrice"
                          label="Cena no (€)"
                          variant="outlined"
                          density="compact"
                          type="number"
                          min="0"
                        />
                      </v-col>
                      <v-col cols="6" md="3">
                        <v-text-field
                          v-model.number="vehicleFilters.maxPrice"
                          label="Cena līdz (€)"
                          variant="outlined"
                          density="compact"
                          type="number"
                          min="0"
                        />
                      </v-col>
                      <v-col cols="12" md="4" class="d-flex align-center">
                        <v-switch
                          v-model="vehicleFilters.onlyAvailable"
                          label="Tikai pieejamie"
                          inset
                          density="compact"
                          hide-details
                        />
                      </v-col>
                      <v-col cols="12" md="2" class="d-flex align-center justify-end">
                        <v-btn variant="text" size="small" @click="resetVehicleFilters">Notīrīt</v-btn>
                      </v-col>
                    </v-row>
                  </v-card-text>
                </v-card>
              </v-expand-transition>

              <v-expand-transition>
                <v-card v-if="showSorting" class="mb-4" variant="outlined">
                  <v-card-text class="pb-2">
                    <v-row dense>
                      <v-col cols="12" md="6">
                        <v-select
                          v-model="vehicleSorting.sortBy"
                          :items="sortOptions"
                          item-title="title"
                          item-value="value"
                          label="Kārtot pēc"
                          variant="outlined"
                          density="compact"
                        />
                      </v-col>
                      <v-col cols="12" md="6" class="d-flex align-center justify-end">
                        <v-btn variant="text" size="small" @click="resetVehicleSorting">Atiestatīt kārtošanu</v-btn>
                      </v-col>
                    </v-row>
                  </v-card-text>
                </v-card>
              </v-expand-transition>

              <v-alert v-if="!filteredSortedVehicles.length" type="info" variant="tonal" class="mb-2">
                Pēc izvēlētajiem filtriem transports netika atrasts.
              </v-alert>
            </template>

            <div v-if="filteredSortedVehicles.length" class="vehicles-grid">
              <v-card
                v-for="vehicle in filteredSortedVehicles"
                :key="vehicle.transportlidzeklis_id"
                class="vehicle-card"
                variant="outlined"
              >
                <v-card-text>
                  <div class="text-subtitle-1 font-weight-bold">
                    {{ vehicle.marka }} {{ vehicle.modelis }}
                  </div>
                  <div class="text-body-2 opacity-80 mb-1">
                    {{ vehicle.veids?.nosaukums || 'Transporta veids nav norādīts' }}
                  </div>
                  <div class="text-body-2 mb-2">
                    {{ formatPrice(vehicle.dienas_nomas_cena) }} / dienā
                  </div>
                  <v-chip
                    size="small"
                    :color="isVehicleAvailable(vehicle) ? 'success' : 'error'"
                    variant="tonal"
                  >
                    {{ isVehicleAvailable(vehicle) ? 'Pieejams' : 'Nav pieejams' }}
                  </v-chip>
                  <v-btn
                    v-if="isClient"
                    class="mt-3"
                    size="small"
                    color="primary"
                    :disabled="!isVehicleAvailable(vehicle)"
                    @click="openReservation(vehicle)"
                  >
                    Rezervēt
                  </v-btn>
                </v-card-text>
              </v-card>
            </div>
          </template>
        </v-card-text>
      </v-card>

      <v-dialog v-model="reservationDialog" max-width="520">
        <v-card>
          <v-card-title class="font-weight-bold">Rezervācija</v-card-title>
          <v-divider />
          <v-card-text class="pa-4">
            <div v-if="activeVehicle">
              <div class="text-subtitle-2 font-weight-bold mb-2">
                {{ activeVehicle.marka }} {{ activeVehicle.modelis }}
              </div>
              <div class="text-caption opacity-70 mb-3">
                {{ activeVehicle.veids?.nosaukums || '—' }} • {{ formatPrice(activeVehicle.dienas_nomas_cena) }} / dienā
              </div>
            </div>
            <div class="text-body-2 mb-2">Izvēlieties nomas periodu.</div>
            <v-row dense>
              <v-col cols="6">
                <v-menu v-model="startDatePickerMenu" :close-on-content-click="false" location="bottom">
                  <template #activator="{ props }">
                    <v-text-field
                      v-bind="props"
                      :model-value="reservationStartDateDisplay"
                      label="No datums"
                      variant="outlined"
                      density="compact"
                      placeholder="DD/MM/YYYY"
                      prepend-inner-icon="mdi-calendar"
                      readonly
                    />
                  </template>
                  <v-date-picker
                    :model-value="reservationStartDate"
                    @update:model-value="onStartDatePicked"
                    :min="minReservationDate"
                    color="primary"
                    locale="lv"
                    hide-header
                  />
                </v-menu>
              </v-col>
              <v-col cols="6">
                <v-select v-model="reservationStartTime" :items="availableStartTimeOptions" label="No laiks" variant="outlined" density="compact" />
              </v-col>
              <v-col cols="6">
                <v-menu v-model="endDatePickerMenu" :close-on-content-click="false" location="bottom">
                  <template #activator="{ props }">
                    <v-text-field
                      v-bind="props"
                      :model-value="reservationEndDateDisplay"
                      label="Līdz datumam"
                      variant="outlined"
                      density="compact"
                      placeholder="DD/MM/YYYY"
                      prepend-inner-icon="mdi-calendar"
                      readonly
                    />
                  </template>
                  <v-date-picker
                    :model-value="reservationEndDate"
                    @update:model-value="onEndDatePicked"
                    :min="minReservationDate"
                    color="primary"
                    locale="lv"
                    hide-header
                  />
                </v-menu>
              </v-col>
              <v-col cols="6">
                <v-select v-model="reservationEndTime" :items="availableEndTimeOptions" label="Līdz laikam" variant="outlined" density="compact" />
              </v-col>
              <v-col cols="12">
                <div class="text-caption opacity-70">Laika josla: {{ userTimezone }}</div>
              </v-col>
            </v-row>

            <div class="reservation-summary mt-2">
              <div><strong>Periods:</strong> {{ reservationPeriodText }}</div>
              <div><strong>Apmaksas dienas:</strong> {{ reservationBillableDays }}</div>
              <div><strong>Cena par dienu:</strong> {{ activeVehicle ? formatPrice(activeVehicle.dienas_nomas_cena) : '0.00 €' }}</div>
              <div><strong>Kopā:</strong> {{ reservationTotal }}</div>
            </div>
          </v-card-text>
          <v-card-actions class="px-4 pb-4">
            <v-spacer />
            <v-btn variant="text" @click="reservationDialog = false">Atcelt</v-btn>
            <v-btn color="primary" :loading="reservationLoading" :disabled="!isReservationFormValid" @click="createReservation">Rezervēt</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <v-dialog v-model="paymentDialog" max-width="480">
        <v-card>
          <v-card-title class="font-weight-bold">Apmaksa</v-card-title>
          <v-divider />
          <v-card-text class="pa-4">
            <div class="text-body-2">Izvēlieties, vai rezervāciju apmaksāt tagad vai vēlāk.</div>
            <div v-if="pendingReservation" class="mt-3 text-caption opacity-70">
              Rezervācijas summa: {{ formatPrice(pendingReservation.kopa_summa) }}
            </div>
          </v-card-text>
          <v-card-actions class="px-4 pb-4">
            <v-spacer />
            <v-btn variant="text" :disabled="paymentLoading" @click="payLater">Maksāšu vēlāk</v-btn>
            <v-btn color="primary" :loading="paymentLoading" @click="confirmPayment">Apmaksāt</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

    </v-container>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { AUTH_ROUTE, MAP_ROUTE } from '@/router/paths'
import { useNotifications } from '@/stores/notifications'

const API_BASE = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000'
const route = useRoute()
const router = useRouter()
const userTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'Europe/Riga'
const { notifyError, notifySuccess, notifyWarning, notifyInfo } = useNotifications()

// Lapas pamatstāvoklis: kompānija, transports un rezervācijas dialogi.
const user = ref(null)
const loading = ref(false)
const errorText = ref('')
const transportItems = ref([])
const reservationDialog = ref(false)
const reservationLoading = ref(false)
const reservationError = ref('')
const activeVehicle = ref(null)
const pendingReservation = ref(null)

const paymentDialog = ref(false)
const paymentLoading = ref(false)
const paymentError = ref('')
const paymentSuccess = ref('')
const startDatePickerMenu = ref(false)
const endDatePickerMenu = ref(false)

const snackbar = ref({ show: false, text: '', color: 'error' })

watch(reservationError, value => {
  if (value) notifyError(value)
})

watch(paymentError, value => {
  if (value) notifyError(value)
})

watch(paymentSuccess, value => {
  if (value) notifySuccess(value)
})

watch(
  () => snackbar.value.show,
  visible => {
    if (!visible || !snackbar.value.text) return

    if (snackbar.value.color === 'success') {
      notifySuccess(snackbar.value.text)
    } else if (snackbar.value.color === 'warning') {
      notifyWarning(snackbar.value.text)
    } else if (snackbar.value.color === 'info') {
      notifyInfo(snackbar.value.text)
    } else {
      notifyError(snackbar.value.text)
    }

    snackbar.value = { ...snackbar.value, show: false }
  }
)

// Izveido sākotnējo rezervācijas periodu (no tagad+30min līdz nākamajai dienai).
function buildDefaultReservationWindow() {
  const now = new Date()
  now.setSeconds(0, 0)
  now.setMinutes(now.getMinutes() + 30)

  const end = new Date(now)
  end.setDate(end.getDate() + 1)

  const toDateTimeLocal = value => {
    const year = value.getFullYear()
    const month = String(value.getMonth() + 1).padStart(2, '0')
    const day = String(value.getDate()).padStart(2, '0')
    const hours = String(value.getHours()).padStart(2, '0')
    const minutes = String(value.getMinutes()).padStart(2, '0')
    return `${year}-${month}-${day}T${hours}:${minutes}`
  }

  return {
    startAt: toDateTimeLocal(now),
    endAt: toDateTimeLocal(end),
  }
}

const defaultReservationWindow = buildDefaultReservationWindow()

const reservation = ref({
  startAt: defaultReservationWindow.startAt,
  endAt: defaultReservationWindow.endAt,
})

function toIsoDateFromDate(value) {
  const year = value.getFullYear()
  const month = String(value.getMonth() + 1).padStart(2, '0')
  const day = String(value.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function toTimeFromDate(value) {
  const hours = String(value.getHours()).padStart(2, '0')
  const minutes = String(value.getMinutes()).padStart(2, '0')
  return `${hours}:${minutes}`
}

function getRoundedCurrentTime() {
  const now = new Date()
  now.setSeconds(0, 0)
  const remainder = now.getMinutes() % 15
  if (remainder !== 0) {
    now.setMinutes(now.getMinutes() + (15 - remainder))
  }
  return now
}

const minReservationDate = toIsoDateFromDate(new Date())

const timeOptions = Array.from({ length: 96 }, (_, idx) => {
  const hours = String(Math.floor(idx / 4)).padStart(2, '0')
  const minutes = String((idx % 4) * 15).padStart(2, '0')
  return `${hours}:${minutes}`
})

// Palīgfunkcijas datuma/laika pārveidei starp UI un API formātu.
function splitDateTime(value) {
  if (!value) return { date: '', time: '00:00' }
  const [date, time] = value.split('T')
  return { date: date || '', time: (time || '00:00').slice(0, 5) }
}

function combineDateTime(date, time) {
  if (!date) return ''
  return `${date}T${(time || '00:00').slice(0, 5)}`
}

function normalizePickedDate(value) {
  const raw = Array.isArray(value) ? value[0] : value
  if (!raw) return ''
  if (raw instanceof Date) {
    const year = raw.getFullYear()
    const month = String(raw.getMonth() + 1).padStart(2, '0')
    const day = String(raw.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
  }
  return String(raw).slice(0, 10)
}

function onStartDatePicked(value) {
  const isoDate = normalizePickedDate(value)
  if (!isoDate) return
  reservationStartDate.value = isoDate
  startDatePickerMenu.value = false
}

function onEndDatePicked(value) {
  const isoDate = normalizePickedDate(value)
  if (!isoDate) return
  reservationEndDate.value = isoDate
  endDatePickerMenu.value = false
}

function toDisplayDate(isoDate) {
  if (!isoDate) return ''
  const [year, month, day] = isoDate.split('-')
  if (!year || !month || !day) return ''
  return `${day}/${month}/${year}`
}

function toIsoDate(displayDate) {
  if (!displayDate) return ''
  const value = displayDate.trim().replace(/\./g, '/')
  const match = value.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/)
  if (!match) return null

  const day = String(Number(match[1])).padStart(2, '0')
  const month = String(Number(match[2])).padStart(2, '0')
  const year = match[3]
  const candidate = `${year}-${month}-${day}`
  const date = new Date(`${candidate}T00:00:00`)

  if (Number.isNaN(date.getTime())) return null
  if (date.getFullYear() !== Number(year) || date.getMonth() + 1 !== Number(month) || date.getDate() !== Number(day)) {
    return null
  }
  if (candidate < minReservationDate) return null

  return candidate
}

const reservationStartDate = computed({
  get: () => splitDateTime(reservation.value.startAt).date,
  set: value => {
    const { time } = splitDateTime(reservation.value.startAt)
    reservation.value.startAt = combineDateTime(value, time)
  },
})

const reservationStartDateDisplay = computed({
  get: () => toDisplayDate(reservationStartDate.value),
  set: value => {
    if (!value) {
      reservationStartDate.value = ''
      return
    }
    const isoDate = toIsoDate(value)
    if (isoDate) {
      reservationStartDate.value = isoDate
    }
  },
})

const reservationStartTime = computed({
  get: () => splitDateTime(reservation.value.startAt).time,
  set: value => {
    const { date } = splitDateTime(reservation.value.startAt)
    reservation.value.startAt = combineDateTime(date, value)
  },
})

const reservationEndDate = computed({
  get: () => splitDateTime(reservation.value.endAt).date,
  set: value => {
    const { time } = splitDateTime(reservation.value.endAt)
    reservation.value.endAt = combineDateTime(value, time)
  },
})

const reservationEndDateDisplay = computed({
  get: () => toDisplayDate(reservationEndDate.value),
  set: value => {
    if (!value) {
      reservationEndDate.value = ''
      return
    }
    const isoDate = toIsoDate(value)
    if (isoDate) {
      reservationEndDate.value = isoDate
    }
  },
})

const reservationEndTime = computed({
  get: () => splitDateTime(reservation.value.endAt).time,
  set: value => {
    const { date } = splitDateTime(reservation.value.endAt)
    reservation.value.endAt = combineDateTime(date, value)
  },
})

const minStartTimeToday = computed(() => toTimeFromDate(getRoundedCurrentTime()))

const availableStartTimeOptions = computed(() => {
  if (reservationStartDate.value !== minReservationDate) return timeOptions
  return timeOptions.filter(time => time >= minStartTimeToday.value)
})

const availableEndTimeOptions = computed(() => {
  let options = timeOptions

  if (reservationEndDate.value === minReservationDate) {
    options = options.filter(time => time >= minStartTimeToday.value)
  }

  if (reservationEndDate.value === reservationStartDate.value && reservationStartTime.value) {
    options = options.filter(time => time > reservationStartTime.value)
  }

  return options
})

const companyId = computed(() => Number(route.params.id))
const isClient = computed(() => user.value?.loma === 'klients')
const showFilters = ref(false)
const showSorting = ref(false)

const vehicleFilters = ref({
  q: '',
  typeId: null,
  minPrice: null,
  maxPrice: null,
  onlyAvailable: false,
})

const vehicleSorting = ref({
  sortBy: 'availability_desc',
})

const sortOptions = [
  { title: 'Pieejamie vispirms', value: 'availability_desc' },
  { title: 'Cena: no lētākā', value: 'price_asc' },
  { title: 'Cena: no dārgākā', value: 'price_desc' },
  { title: 'Marka A-Z', value: 'brand_asc' },
  { title: 'Marka Z-A', value: 'brand_desc' },
]

// Kompānijas transporta saraksts ar filtru un kārtošanas loģiku.
const vehicles = computed(() =>
  transportItems.value.filter(item => Number(item.sniedzejs_id) === companyId.value)
)

const vehicleTypeOptions = computed(() => {
  const map = new Map()
  vehicles.value.forEach(vehicle => {
    const typeId = vehicle.veids_id
    if (!typeId || map.has(typeId)) return
    map.set(typeId, {
      title: vehicle.veids?.nosaukums || `Veids #${typeId}`,
      value: typeId,
    })
  })
  return Array.from(map.values())
})

const filteredSortedVehicles = computed(() => {
  const q = vehicleFilters.value.q.trim().toLowerCase()

  const filtered = vehicles.value.filter(vehicle => {
    if (q) {
      const haystack = `${vehicle.marka || ''} ${vehicle.modelis || ''}`.toLowerCase()
      if (!haystack.includes(q)) return false
    }

    if (vehicleFilters.value.typeId && vehicle.veids_id !== vehicleFilters.value.typeId) {
      return false
    }

    const price = Number(vehicle.dienas_nomas_cena || 0)
    if (vehicleFilters.value.minPrice !== null && price < Number(vehicleFilters.value.minPrice)) {
      return false
    }
    if (vehicleFilters.value.maxPrice !== null && price > Number(vehicleFilters.value.maxPrice)) {
      return false
    }

    if (vehicleFilters.value.onlyAvailable && !isVehicleAvailable(vehicle)) {
      return false
    }

    return true
  })

  const sorted = [...filtered]
  const sortBy = vehicleSorting.value.sortBy

  sorted.sort((a, b) => {
    if (sortBy === 'price_asc') {
      return Number(a.dienas_nomas_cena || 0) - Number(b.dienas_nomas_cena || 0)
    }

    if (sortBy === 'price_desc') {
      return Number(b.dienas_nomas_cena || 0) - Number(a.dienas_nomas_cena || 0)
    }

    if (sortBy === 'brand_asc') {
      return `${a.marka || ''} ${a.modelis || ''}`.localeCompare(`${b.marka || ''} ${b.modelis || ''}`, 'lv')
    }

    if (sortBy === 'brand_desc') {
      return `${b.marka || ''} ${b.modelis || ''}`.localeCompare(`${a.marka || ''} ${a.modelis || ''}`, 'lv')
    }

    const availableDiff = Number(isVehicleAvailable(b)) - Number(isVehicleAvailable(a))
    if (availableDiff !== 0) return availableDiff
    return Number(a.dienas_nomas_cena || 0) - Number(b.dienas_nomas_cena || 0)
  })

  return sorted
})

const reservationTotal = computed(() => {
  if (!activeVehicle.value) return '0 €'
  const days = getBillableDays()
  const sum = days * Number(activeVehicle.value.dienas_nomas_cena || 0)
  return formatPrice(sum)
})

const reservationBillableDays = computed(() => getBillableDays())

const isReservationFormValid = computed(() => {
  if (!reservation.value.startAt || !reservation.value.endAt) return false

  const { start, end } = getReservationRange()
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return false
  if (end <= start) return false
  if (start <= new Date()) return false
  return true
})

const reservationPeriodText = computed(() => {
  if (!reservation.value.startAt || !reservation.value.endAt) {
    return 'Norādiet periodu no/līdz.'
  }

  const { start, end } = getReservationRange()
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
    return 'Norādiet derīgu periodu no/līdz.'
  }

  if (start <= new Date()) {
    return 'Sākuma laikam jābūt nākotnē.'
  }

  if (end <= start) {
    return 'Beigu laikam jābūt pēc sākuma laika.'
  }

  return `${formatDateTime(start)} → ${formatDateTime(end)}`
})

const company = computed(() => {
  const firstVehicle = vehicles.value[0]
  if (!firstVehicle?.sniedzejs) return null

  const sniedzejs = firstVehicle.sniedzejs
  const persona = sniedzejs.persona
  const name = persona
    ? `${persona.vards || ''} ${persona.uzvards || ''}`.trim()
    : `Pakalpojumu sniedzējs #${sniedzejs.sniedzejs_id}`
  const address = [sniedzejs.iela, sniedzejs.majas_numurs, sniedzejs.dzivokla_numurs]
    .filter(Boolean)
    .join(' ')

  return {
    name,
    address: address || 'Nav norādīta adrese',
    city: sniedzejs.pilseta || 'Nav norādīta pilsēta',
  }
})

function formatPrice(value) {
  const num = Number(value || 0)
  return `${num.toFixed(2)} €`
}

function formatDateTime(value) {
  const date = value instanceof Date ? value : new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  return new Intl.DateTimeFormat('lv-LV', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    hourCycle: 'h23',
    timeZone: userTimezone,
  }).format(date)
}

function formatApiDateTime(value) {
  const date = value instanceof Date ? value : new Date(value)
  if (Number.isNaN(date.getTime())) return null
  return date.toISOString()
}

function getReservationRange() {
  const start = new Date(reservation.value.startAt)
  const end = new Date(reservation.value.endAt)
  return { start, end }
}

function getBillableDays() {
  if (!isReservationFormValid.value) return 1
  const { start, end } = getReservationRange()
  const diffMs = end.getTime() - start.getTime()
  if (!Number.isFinite(diffMs) || diffMs <= 0) return 1
  return Math.max(1, Math.ceil(diffMs / (24 * 60 * 60 * 1000)))
}

function isVehicleAvailable(vehicle) {
  if (!vehicle) return false
  if (vehicle.statuss !== 'pieejams') return false
  const { start, end } = getReservationRange()
  if (!vehicle.rezervacijas || !vehicle.rezervacijas.length) return true
  return !vehicle.rezervacijas.some(res => {
    const rStart = new Date(res.sakuma_laiks)
    const rEnd = new Date(res.beigu_laiks)
    return rStart < end && rEnd > start
  })
}

function upsertReservationInTransportState(reservationItem) {
  if (!reservationItem?.transportlidzeklis_id || !reservationItem?.rezervacija_id) return

  transportItems.value = transportItems.value.map(vehicle => {
    if (vehicle.transportlidzeklis_id !== reservationItem.transportlidzeklis_id) {
      return vehicle
    }

    const currentReservations = Array.isArray(vehicle.rezervacijas) ? vehicle.rezervacijas : []
    const existingIndex = currentReservations.findIndex(
      item => item.rezervacija_id === reservationItem.rezervacija_id
    )

    if (existingIndex >= 0) {
      const updatedReservations = [...currentReservations]
      updatedReservations[existingIndex] = {
        ...updatedReservations[existingIndex],
        ...reservationItem,
      }
      return { ...vehicle, rezervacijas: updatedReservations }
    }

    return { ...vehicle, rezervacijas: [...currentReservations, reservationItem] }
  })
}

function openReservation(vehicle) {
  if (!user.value) {
    snackbar.value = { show: true, text: 'Lūdzu, pieslēdzieties, lai rezervētu.', color: 'error' }
    router.push(AUTH_ROUTE)
    return
  }

  if (!isClient.value) {
    snackbar.value = { show: true, text: 'Rezervācijas pieejamas tikai klientiem.', color: 'error' }
    return
  }

  activeVehicle.value = vehicle
  reservationError.value = ''
  reservationDialog.value = true
}

function resetVehicleFilters() {
  vehicleFilters.value = {
    q: '',
    typeId: null,
    minPrice: null,
    maxPrice: null,
    onlyAvailable: false,
  }
}

function resetVehicleSorting() {
  vehicleSorting.value = {
    sortBy: 'availability_desc',
  }
}

// Izveido rezervāciju un atver apmaksas izvēli.
async function createReservation() {
  if (!activeVehicle.value || !user.value?.klients?.klients_id) {
    reservationError.value = 'Nav klienta datu.'
    return
  }

  if (!isReservationFormValid.value) {
    reservationError.value = 'Lūdzu, izvēlieties periodu nākotnē (beigu laiks pēc sākuma laika).'
    return
  }

  reservationLoading.value = true
  reservationError.value = ''
  paymentError.value = ''
  paymentSuccess.value = ''

  try {
    const { start, end } = getReservationRange()
    const startApi = formatApiDateTime(start)
    const endApi = formatApiDateTime(end)

    if (!startApi || !endApi) {
      reservationError.value = 'Kļūda: Neizdevās apstrādāt rezervācijas laiku.'
      return
    }

    const response = await fetch(`${API_BASE}/api/rezervacijas`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Timezone': userTimezone },
      body: JSON.stringify({
        klients_id: user.value.klients.klients_id,
        transportlidzeklis_id: activeVehicle.value.transportlidzeklis_id,
        sakuma_laiks: startApi,
        beigu_laiks: endApi,
      }),
    })

    const result = await response.json()
    if (!response.ok) {
      reservationError.value = result?.message || 'Neizdevās izveidot rezervāciju.'
      return
    }

    pendingReservation.value = result
    reservationDialog.value = false
    paymentDialog.value = true
  } catch {
    reservationError.value = 'Kļūda: Neizdevās izveidot rezervāciju.'
  } finally {
    reservationLoading.value = false
  }
}

async function confirmPayment() {
  if (!pendingReservation.value || !user.value?.klients?.klients_id) return

  paymentLoading.value = true
  paymentError.value = ''
  paymentSuccess.value = ''

  // Apstrādā rezervācijas apmaksu un sinhronizē lokālos datus.
  try {
    const response = await fetch(`${API_BASE}/api/rezervacijas/${pendingReservation.value.rezervacija_id}/pay`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ klients_id: user.value.klients.klients_id }),
    })

    const result = await response.json()
    if (!response.ok) {
      paymentError.value = result?.message || 'Maksājums neizdevās.'
      return
    }

    paymentSuccess.value = 'Apmaksāts veiksmīgi!'
    pendingReservation.value = result.rezervacija
    upsertReservationInTransportState(result.rezervacija)
    await loadTransport()

    paymentDialog.value = false
  } catch {
    paymentError.value = 'Kļūda: Neizdevās apstrādāt maksājumu.'
  } finally {
    paymentLoading.value = false
  }
}

function payLater() {
  paymentError.value = ''
  paymentSuccess.value = ''
  paymentDialog.value = false
}

async function loadTransport() {
  if (!Number.isFinite(companyId.value) || companyId.value <= 0) {
    errorText.value = 'Nederīgs kompānijas ID.'
    return
  }

  loading.value = true
  errorText.value = ''

  // Ielādē izvēlētās kompānijas transportu.
  try {
    const response = await fetch(`${API_BASE}/api/transport`)
    const data = await response.json()

    if (!response.ok) {
      errorText.value = data?.message || 'Neizdevās ielādēt kompānijas transportu.'
      return
    }

    transportItems.value = data

    if (!vehicles.value.length) {
      errorText.value = 'Kompānija nav atrasta vai tai nav transporta.'
    }
  } catch {
    errorText.value = 'Kļūda: Neizdevās ielādēt kompānijas transportu.'
  } finally {
    loading.value = false
  }
}

onMounted(loadTransport)
// Sākotnējā ielāde un lietotāja sesijas nolasīšana.

onMounted(() => {
  const userStr = localStorage.getItem('user')
  if (userStr) {
    user.value = JSON.parse(userStr)
  }
})

watch(availableStartTimeOptions, options => {
  if (!options.length) return
  if (!options.includes(reservationStartTime.value)) {
    reservationStartTime.value = options[0]
  }
})

watch(availableEndTimeOptions, options => {
  if (!options.length) return
  if (!options.includes(reservationEndTime.value)) {
    reservationEndTime.value = options[0]
  }
})
</script>

<style scoped>
.company-page {
  min-height: calc(100vh - 72px);
  background: var(--er-page-bg);
}

.surface {
  background: var(--er-surface);
  border: 1px solid var(--er-stroke);
  border-radius: 28px;
  color: var(--er-text);
  backdrop-filter: blur(14px);
  box-shadow: var(--er-shadow-md);
}

.company-back-btn {
  border-radius: 999px;
  background: var(--er-panel-soft);
  border: 1px solid var(--er-stroke);
}

.company-hero {
  display: grid;
  grid-template-columns: minmax(0, 1.25fr) minmax(280px, 0.95fr);
  gap: 18px;
  padding: 22px;
  border-radius: 26px;
  background: var(--er-panel-soft);
  border: 1px solid var(--er-stroke);
}

.company-hero__stats {
  display: grid;
  gap: 12px;
}

.company-toolbar {
  padding: 6px;
  border-radius: 999px;
  background: var(--er-panel-soft);
  border: 1px solid var(--er-stroke);
  width: fit-content;
  margin-left: auto;
}

.vehicles-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 16px;
}

.vehicle-card {
  border-radius: 22px;
  color: var(--er-text);
  border: 1px solid var(--er-stroke) !important;
  background: var(--er-card-soft);
  box-shadow: var(--er-shadow-sm);
}

.reservation-summary {
  padding: 10px 12px;
  border-radius: 16px;
  border: 1px solid var(--er-stroke);
  background: var(--er-card-strong);
  color: var(--er-text);
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 0.9rem;
}

.reservation-summary strong {
	color: var(--er-text);
}

@media (max-width: 960px) {
  .company-hero {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 600px) {
  .company-toolbar {
    width: 100%;
    justify-content: stretch;
  }
}
</style>
