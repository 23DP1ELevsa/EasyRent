<template>
  <div class="company-page">
    <section class="company-banner">
      <v-container>
        <v-btn class="company-back-btn" variant="text" @click="router.push(MAP_ROUTE)">
          <v-icon start>mdi-arrow-left</v-icon>{{ copy.backToMap }}
        </v-btn>
        <template v-if="company && !loading && !errorText">
          <div class="company-hero">
            <div>
              <h1>{{ company.name }}</h1>
              <div class="text-body-2 opacity-80 mb-1">{{ company.address }}</div>
              <div class="text-body-2 opacity-80">{{ company.city }}</div>
              <div v-if="company.phone" class="text-body-2 mt-2">
                <a class="company-phone-link" :href="`tel:${company.phone}`">
                  {{ copy.phoneLabel }}: {{ company.phone }}
                </a>
              </div>
            </div>
            <div class="company-hero__stats">
              <div class="metric-pill">
                <strong>{{ vehicles.length }}</strong>
                <span>{{ copy.stats.totalVehicles }}</span>
              </div>
              <div class="metric-pill">
                <strong>{{ vehicles.filter(vehicle => isVehicleAvailable(vehicle)).length }}</strong>
                <span>{{ copy.stats.availableNow }}</span>
              </div>
              <div class="metric-pill">
                <strong>{{ vehicles.length > 0 ? formatPrice(Math.min(...vehicles.map(vehicle => Number(vehicle.dienas_nomas_cena || 0)))) : '0.00 €' }}</strong>
                <span>{{ copy.stats.startingPrice }}</span>
              </div>
            </div>
          </div>

        </template>
        <h1 v-else class="company-banner__title">{{ loading ? copy.loadingCompany : copy.vehiclesTitle }}</h1>
      </v-container>
    </section>
    <v-container class="company-content">
      <div v-if="loading" class="company-loading" role="status">
        <v-progress-circular color="primary" indeterminate size="32" />
        <p>{{ copy.loadingCompany }}</p>
      </div>
      <v-alert v-else-if="errorText" type="error" variant="tonal">{{ errorText }}</v-alert>
      <div v-else-if="company" class="company-catalog">
        <aside :aria-label="copy.filters" class="company-controls">
          <div class="company-toolbar d-flex justify-end flex-wrap ga-2 mb-3">
            <v-btn color="primary" variant="tonal" @click="showFilters = !showFilters">
              <v-icon start>{{ showFilters ? 'mdi-filter-minus' : 'mdi-filter-plus' }}</v-icon>
              {{ showFilters ? copy.closeFilters : copy.filters }}
            </v-btn>
            <v-btn color="primary" variant="tonal" @click="showSorting = !showSorting">
              <v-icon start>{{ showSorting ? 'mdi-sort-variant-remove' : 'mdi-sort-variant' }}</v-icon>
              {{ showSorting ? copy.closeSorting : copy.sort }}
            </v-btn>
          </div>

          <v-expand-transition>
            <v-card v-if="showFilters" class="mb-4 company-panel-card">
              <v-card-text class="pb-2">
                <v-row dense>
                  <v-col cols="12">
                    <v-text-field
                      v-model="vehicleFilters.q"
                      clearable
                      density="compact"
                      :label="copy.filterLabels.search"
                      variant="outlined"
                    />
                  </v-col>
                  <v-col cols="12">
                    <v-select
                      v-model="vehicleFilters.typeId"
                      clearable
                      density="compact"
                      item-title="title"
                      item-value="value"
                      :items="vehicleTypeOptions"
                      :label="copy.filterLabels.type"
                      variant="outlined"
                    />
                  </v-col>
                  <v-col cols="12">
                    <v-select
                      v-model="vehicleFilters.gearbox"
                      clearable
                      density="compact"
                      item-title="title"
                      item-value="value"
                      :items="gearboxOptions"
                      :label="copy.filterLabels.gearbox"
                      variant="outlined"
                    />
                  </v-col>
                  <v-col cols="12">
                    <v-select
                      v-model="vehicleFilters.fuel"
                      clearable
                      density="compact"
                      item-title="title"
                      item-value="value"
                      :items="fuelOptions"
                      :label="copy.filterLabels.fuel"
                      variant="outlined"
                    />
                  </v-col>
                  <v-col cols="6">
                    <v-text-field
                      v-model.number="vehicleFilters.minPrice"
                      density="compact"
                      :label="copy.filterLabels.minPrice"
                      min="0"
                      type="number"
                      variant="outlined"
                    />
                  </v-col>
                  <v-col cols="6">
                    <v-text-field
                      v-model.number="vehicleFilters.maxPrice"
                      density="compact"
                      :label="copy.filterLabels.maxPrice"
                      min="0"
                      type="number"
                      variant="outlined"
                    />
                  </v-col>
                  <v-col class="d-flex align-center" cols="12">
                    <v-switch
                      v-model="vehicleFilters.onlyAvailable"
                      density="compact"
                      hide-details
                      inset
                      :label="copy.filterLabels.onlyAvailable"
                    />
                  </v-col>
                  <v-col class="d-flex justify-end" cols="12">
                    <v-btn class="toolbar-secondary-btn" size="default" variant="text" @click="resetVehicleFilters">{{ copy.clear }}</v-btn>
                  </v-col>
                </v-row>
              </v-card-text>
            </v-card>
          </v-expand-transition>

          <v-expand-transition>
            <v-card v-if="showSorting" class="mb-4 company-panel-card">
              <v-card-text class="pb-2">
                <v-row dense>
                  <v-col cols="12">
                    <v-select
                      v-model="vehicleSorting.sortBy"
                      density="compact"
                      item-title="title"
                      item-value="value"
                      :items="sortOptions"
                      :label="copy.sortBy"
                      variant="outlined"
                    />
                  </v-col>
                  <v-col class="d-flex align-center justify-end" cols="12">
                    <v-btn class="toolbar-secondary-btn" size="default" variant="text" @click="resetVehicleSorting">{{ copy.resetSorting }}</v-btn>
                  </v-col>
                </v-row>
              </v-card-text>
            </v-card>
          </v-expand-transition>

        </aside>
        <section :aria-label="copy.vehiclesTitle" class="company-results">
          <div class="company-results__heading">
            <h2>{{ copy.vehiclesTitle }}</h2>
            <v-chip color="primary" variant="tonal">{{ filteredSortedVehicles.length }}/{{ vehicles.length }}</v-chip>
          </div>
          <v-alert v-if="vehicles.length === 0" type="info" variant="tonal">{{ copy.noVehicles }}</v-alert>
          <v-alert v-else-if="filteredSortedVehicles.length === 0" type="info" variant="tonal">{{ copy.noVehiclesByFilter }}</v-alert>
          <div v-if="filteredSortedVehicles.length > 0" class="vehicles-grid">
            <v-card
              v-for="vehicle in filteredSortedVehicles"
              :key="vehicle.transportlidzeklis_id"
              class="vehicle-card"
              variant="outlined"
            >
              <div class="vehicle-card__visual">
                <img
                  alt=""
                  height="704"
                  loading="lazy"
                  :src="getVehicleImage(vehicle)"
                  width="944"
                >
                <v-chip class="vehicle-card__status" :color="isVehicleAvailable(vehicle) ? 'primary' : undefined" size="small" variant="flat">{{ isVehicleAvailable(vehicle) ? copy.available : copy.notAvailable }}</v-chip>
              </div>
              <v-card-text>
                <div class="vehicle-card__name">
                  {{ vehicle.marka }} {{ vehicle.modelis }}
                </div>
                <div class="vehicle-card__type">
                  {{ vehicle.veids?.nosaukums || copy.typeNotProvided }}
                </div>
                <div class="vehicle-card__details mb-2">
                  <div
                    v-for="detail in buildVehicleDetails(vehicle)"
                    :key="detail.label"
                    class="vehicle-detail-chip"
                  >
                    <span class="vehicle-detail-chip__label">{{ detail.label }}:</span>
                    <span class="vehicle-detail-chip__value">{{ detail.value }}</span>
                  </div>
                </div>
                <div class="vehicle-card__price">
                  {{ formatPrice(vehicle.dienas_nomas_cena) }} / {{ copy.perDay }}
                </div>
                <div v-if="Number(vehicle.atsauksmju_skaits || 0) > 0" class="vehicle-rating mb-2">
                  <v-rating
                    active-color="amber"
                    empty-icon="mdi-star-outline"
                    full-icon="mdi-star"
                    half-increments
                    length="5"
                    :model-value="Number(vehicle.videjais_vertejums || 0)"
                    readonly
                    size="small"
                  />
                  <span class="text-caption opacity-80">
                    {{ Number(vehicle.videjais_vertejums || 0).toFixed(1) }} ({{ Number(vehicle.atsauksmju_skaits || 0) }})
                  </span>
                </div>
                <div class="vehicle-card__actions">
                  <v-chip
                    :color="isVehicleAvailable(vehicle) ? 'success' : 'error'"
                    size="small"
                    variant="tonal"
                  >
                    {{ isVehicleAvailable(vehicle) ? copy.available : copy.notAvailable }}
                  </v-chip>
                  <v-btn size="small" variant="tonal" @click="openReviews(vehicle)">
                    {{ copy.reviews }}
                  </v-btn>
                  <v-btn
                    v-if="isClient"
                    color="primary"
                    :disabled="!isVehicleAvailable(vehicle)"
                    size="small"
                    @click="openReservation(vehicle)"
                  >
                    {{ copy.reserve }}
                  </v-btn>
                  <v-btn
                    v-else-if="isProvider && isOwnVehicle(vehicle)"
                    size="small"
                    variant="tonal"
                    @click="openEditVehicle(vehicle)"
                  >
                    {{ copy.edit }}
                  </v-btn>
                </div>
              </v-card-text>
            </v-card>
          </div>
        </section>
      </div>

      <v-dialog v-model="reservationDialog" max-width="520">
        <v-card class="rental-dialog__card">
          <v-card-title class="font-weight-bold">{{ copy.reservationTitle }}</v-card-title>
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
            <div class="text-body-2 mb-2">{{ copy.selectPeriod }}</div>
            <v-row class="reservation-period-fields" dense>
              <v-col cols="6">
                <v-menu v-model="startDatePickerMenu" :close-on-content-click="false" location="bottom">
                  <template #activator="{ props }">
                    <v-text-field
                      v-bind="props"
                      density="compact"
                      :label="copy.startDate"
                      :model-value="reservationStartDateDisplay"
                      placeholder="DD/MM/YYYY"
                      prepend-inner-icon="mdi-calendar"
                      readonly
                      variant="outlined"
                    />
                  </template>
                  <v-date-picker
                    class="rental-calendar"
                    color="primary"
                    hide-header
                    :locale="datePickerLocale"
                    :min="minReservationDate"
                    :model-value="reservationStartDate"
                    @update:model-value="onStartDatePicked"
                  />
                </v-menu>
              </v-col>
              <v-col cols="6">
                <v-select
                  v-model="reservationStartTime"
                  density="compact"
                  :items="availableStartTimeOptions"
                  :label="copy.startTime"
                  variant="outlined"
                />
              </v-col>
              <v-col cols="6">
                <v-menu v-model="endDatePickerMenu" :close-on-content-click="false" location="bottom">
                  <template #activator="{ props }">
                    <v-text-field
                      v-bind="props"
                      density="compact"
                      :label="copy.endDate"
                      :model-value="reservationEndDateDisplay"
                      placeholder="DD/MM/YYYY"
                      prepend-inner-icon="mdi-calendar"
                      readonly
                      variant="outlined"
                    />
                  </template>
                  <v-date-picker
                    class="rental-calendar"
                    color="primary"
                    hide-header
                    :locale="datePickerLocale"
                    :min="minReservationDate"
                    :model-value="reservationEndDate"
                    @update:model-value="onEndDatePicked"
                  />
                </v-menu>
              </v-col>
              <v-col cols="6">
                <v-select
                  v-model="reservationEndTime"
                  density="compact"
                  :items="availableEndTimeOptions"
                  :label="copy.endTime"
                  variant="outlined"
                />
              </v-col>
              <v-col cols="12">
                <div class="text-caption opacity-70">{{ copy.timezone }}: {{ userTimezone }}</div>
              </v-col>
            </v-row>

            <div class="reservation-summary mt-2">
              <div><strong>{{ copy.period }}:</strong> {{ reservationPeriodText }}</div>
              <div><strong>{{ copy.billableDays }}:</strong> {{ reservationBillableDays }}</div>
              <div><strong>{{ copy.pricePerDay }}:</strong> {{ activeVehicle ? formatPrice(activeVehicle.dienas_nomas_cena) : '0.00 €' }}</div>
              <div><strong>{{ copy.total }}:</strong> {{ reservationTotal }}</div>
            </div>
          </v-card-text>
          <v-card-actions class="px-4 pb-4">
            <v-spacer />
            <v-btn variant="text" @click="reservationDialog = false">{{ copy.cancel }}</v-btn>
            <v-btn color="primary" :disabled="!isReservationFormValid" :loading="reservationLoading" @click="createReservation">{{ copy.reserve }}</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <v-dialog v-model="paymentDialog" max-width="480">
        <v-card class="rental-dialog__card">
          <v-card-title class="font-weight-bold">{{ copy.paymentTitle }}</v-card-title>
          <v-divider />
          <v-card-text class="pa-4">
            <div class="text-body-2">{{ copy.paymentPrompt }}</div>
            <div v-if="pendingReservation" class="payment-amount mt-3">
              {{ copy.reservationAmount }}: {{ formatPrice(pendingReservation.kopa_summa) }}
            </div>
          </v-card-text>
          <v-card-actions class="px-4 pb-4">
            <v-spacer />
            <v-btn :disabled="paymentLoading" variant="text" @click="payLater">{{ copy.payLater }}</v-btn>
            <v-btn color="primary" :loading="paymentLoading" @click="confirmPayment">{{ copy.payNow }}</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <v-dialog v-model="editDialog" max-width="520">
        <v-card class="rental-dialog__card">
          <v-card-title class="font-weight-bold">{{ copy.editVehicleTitle }}</v-card-title>
          <v-divider />
          <v-card-text class="pa-4">
            <v-select
              v-model="editVehicle.veids_id"
              density="compact"
              item-title="title"
              item-value="value"
              :items="typeOptions"
              :label="copy.vehicleFields.type"
              variant="outlined"
            />
            <v-text-field v-model="editVehicle.marka" density="compact" :label="copy.vehicleFields.brand" variant="outlined" />
            <v-text-field v-model="editVehicle.modelis" density="compact" :label="copy.vehicleFields.model" variant="outlined" />
            <v-select
              v-model="editVehicle.atrumkarba"
              density="compact"
              item-title="title"
              item-value="value"
              :items="gearboxOptions"
              :label="copy.vehicleFields.gearbox"
              variant="outlined"
            />
            <v-select
              v-model="editVehicle.degvielas_veids"
              density="compact"
              item-title="title"
              item-value="value"
              :items="fuelOptions"
              :label="copy.vehicleFields.fuel"
              variant="outlined"
            />
            <v-text-field
              v-model.number="editVehicle.dienas_nomas_cena"
              density="compact"
              :label="copy.vehicleFields.dailyPrice"
              min="0"
              type="number"
              variant="outlined"
            />
            <v-select
              v-model="editVehicle.statuss"
              density="compact"
              :items="statusOptions"
              :label="copy.vehicleFields.status"
              variant="outlined"
            />
            <v-text-field
              v-model="editVehicle.registracijas_numurs"
              density="compact"
              :label="copy.vehicleFields.registrationNumber"
              variant="outlined"
            />
          </v-card-text>
          <v-card-actions class="px-4 pb-4">
            <v-btn
              color="error"
              :disabled="editLoading"
              :loading="deleteVehicleLoading"
              variant="tonal"
              @click="deleteVehicle"
            >
              {{ copy.delete }}
            </v-btn>
            <v-spacer />
            <v-btn variant="text" @click="editDialog = false">{{ copy.cancel }}</v-btn>
            <v-btn color="primary" :disabled="deleteVehicleLoading" :loading="editLoading" @click="saveVehicle">{{ copy.save }}</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <v-dialog v-model="deleteVehicleDialog" max-width="420" persistent>
        <v-card class="rental-dialog__card">
          <v-card-title class="font-weight-bold">{{ copy.deleteVehicleTitle }}</v-card-title>
          <v-divider />
          <v-card-text class="pa-4">
            <div class="text-body-2">{{ copy.deleteVehicleConfirm }}</div>
            <div v-if="editVehicle?.marka || editVehicle?.modelis" class="text-caption opacity-70 mt-2">
              {{ editVehicle.marka }} {{ editVehicle.modelis }}
            </div>
          </v-card-text>
          <v-card-actions class="px-4 pb-4">
            <v-spacer />
            <v-btn :disabled="deleteVehicleLoading" variant="text" @click="cancelDeleteVehicle">{{ copy.cancel }}</v-btn>
            <v-btn color="error" :loading="deleteVehicleLoading" @click="confirmDeleteVehicle">{{ copy.delete }}</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <v-dialog v-model="reviewDialog" max-width="760">
        <v-card class="rental-dialog__card">
          <v-card-title class="font-weight-bold d-flex align-center justify-space-between">
            <div>
              <div>{{ copy.reviews }}</div>
              <div v-if="reviewVehicle" class="text-caption opacity-70">
                {{ reviewVehicle.marka }} {{ reviewVehicle.modelis }}
              </div>
            </div>
            <v-chip v-if="reviewStats.count > 0" color="amber" size="small" variant="tonal">
              ★ {{ reviewStats.average.toFixed(1) }} ({{ reviewStats.count }})
            </v-chip>
          </v-card-title>
          <v-divider />
          <v-card-text class="pa-4">
            <v-alert
              v-if="!user"
              class="mb-3"
              density="compact"
              type="info"
              variant="tonal"
            >
              {{ copy.reviewClientOnly }}
            </v-alert>
            <v-alert
              v-else-if="!isClient"
              class="mb-3"
              density="compact"
              type="warning"
              variant="tonal"
            >
              {{ copy.reviewProviderBlocked }}
            </v-alert>

            <v-card v-if="canReview" class="mb-4" variant="outlined">
              <v-card-text class="pa-4 d-flex flex-column ga-3">
                <div class="text-subtitle-2 font-weight-bold">
                  {{ ownReview ? copy.reviewEditOwn : copy.reviewAdd }}
                </div>
                <v-rating
                  v-model="reviewForm.vertejums"
                  active-color="amber"
                  empty-icon="mdi-star-outline"
                  full-icon="mdi-star"
                  :half-increments="false"
                  length="5"
                />
                <v-textarea
                  v-model="reviewForm.komentars"
                  auto-grow
                  counter="2000"
                  density="compact"
                  :label="copy.reviewComment"
                  rows="3"
                  variant="outlined"
                />
                <div class="d-flex justify-end">
                  <v-btn color="primary" :loading="reviewSaving" @click="submitReview">
                    {{ ownReview ? copy.reviewSaveChanges : copy.reviewAdd }}
                  </v-btn>
                </div>
              </v-card-text>
            </v-card>

            <div class="text-subtitle-2 font-weight-bold mb-2">{{ copy.reviewAll }}</div>
            <div v-if="reviewLoading" class="dialog-state" role="status"><v-progress-circular color="primary" indeterminate size="22" />{{ copy.reviewLoading }}</div>
            <div v-else-if="vehicleReviews.length === 0" class="dialog-state"><v-icon>mdi-comment-outline</v-icon>{{ copy.reviewEmpty }}</div>
            <div v-else class="d-flex flex-column ga-3">
              <v-card v-for="review in vehicleReviews" :key="review.atsauksme_id" variant="outlined">
                <v-card-text class="pa-3">
                  <div class="d-flex align-center justify-space-between ga-3">
                    <div class="text-subtitle-2 font-weight-bold">{{ reviewerName(review) }}</div>
                    <div class="text-caption opacity-70">{{ formatReviewDate(review.datums || review.updated_at) }}</div>
                  </div>
                  <div class="d-flex align-center ga-2 mt-1">
                    <v-rating
                      active-color="amber"
                      empty-icon="mdi-star-outline"
                      full-icon="mdi-star"
                      length="5"
                      :model-value="Number(review.vertejums || 0)"
                      readonly
                      size="small"
                    />
                    <span class="text-caption">{{ review.vertejums }}/5</span>
                  </div>
                  <div class="mt-2 text-body-2">{{ review.komentars || copy.reviewNoComment }}</div>
                </v-card-text>
              </v-card>
            </div>
          </v-card-text>
          <v-card-actions class="px-4 pb-4">
            <v-spacer />
            <v-btn v-if="!user" variant="text" @click="router.push(AUTH_ROUTE)">{{ copy.login }}</v-btn>
            <v-btn color="primary" variant="text" @click="reviewDialog = false">{{ copy.close }}</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

    </v-container>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useLocale } from '@/stores/locale'
import { AUTH_ROUTE, MAP_ROUTE } from '@/router/paths'
import { useNotifications } from '@/stores/notifications'
import { getAuthHeaders, syncCurrentUser } from '@/services/auth'
  import { getVehicleImage } from '@/utils/vehicleVisuals'

const API_BASE = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000'
const route = useRoute()
const router = useRouter()
const { t, getIntlLocale, getDatePickerLocale } = useLocale()
const userTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'Europe/Riga'
const { notifyError, notifySuccess, notifyWarning, notifyInfo } = useNotifications()
const copy = computed(() => t('company'))
const datePickerLocale = computed(() => getDatePickerLocale())
const intlLocale = computed(() => getIntlLocale())

// Lapas pamatstāvoklis: kompānija, transports un rezervācijas dialogi.
const user = ref(null)
const loading = ref(false)
const errorText = ref('')
const transportItems = ref([])
const transportTypes = ref([])
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
const editDialog = ref(false)
const editLoading = ref(false)
const editError = ref('')
const editVehicle = ref({})
const deleteVehicleLoading = ref(false)
const deleteVehicleDialog = ref(false)
const reviewDialog = ref(false)
const reviewLoading = ref(false)
const reviewSaving = ref(false)
const reviewError = ref('')
const reviewSuccess = ref('')
const reviewVehicle = ref(null)
const vehicleReviews = ref([])
const reviewForm = ref({
  vertejums: 0,
  komentars: '',
})

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

watch(editError, value => {
  if (value) notifyError(value)
})

watch(reviewError, value => {
  if (value) notifyError(value)
})

watch(reviewSuccess, value => {
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
const isProvider = computed(() => user.value?.loma === 'pakalpojumu_sniedzejs')
const clientId = computed(() => user.value?.klients?.klients_id || null)
const providerId = computed(() =>
  user.value?.pakalpojumuSniedzejs?.sniedzejs_id || user.value?.pakalpojumu_sniedzejs?.sniedzejs_id || null
)
const showFilters = ref(true)
const showSorting = ref(true)

const vehicleFilters = ref({
  q: '',
  typeId: null,
  gearbox: null,
  fuel: null,
  minPrice: null,
  maxPrice: null,
  onlyAvailable: false,
})

const vehicleSorting = ref({
  sortBy: 'availability_desc',
})

const sortOptions = computed(() => copy.value.sortOptions)
const typeOptions = computed(() => {
  const source = transportTypes.value.length ? transportTypes.value : vehicleTypeOptions.value
  return source.map(type => ({ title: type.nosaukums || type.title, value: type.veids_id || type.value }))
})
const statusOptions = ['pieejams', 'aiznemts', 'neaktivs']

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

const gearboxOptions = computed(() => {
  const map = new Map()
  vehicles.value.forEach(vehicle => {
    const value = normalizeVehicleField(vehicle.atrumkarba)
    if (!value || map.has(value)) return
    map.set(value, { title: value, value })
  })
  return Array.from(map.values())
})

const fuelOptions = computed(() => {
  const map = new Map()
  vehicles.value.forEach(vehicle => {
    const value = normalizeVehicleField(vehicle.degvielas_veids)
    if (!value || map.has(value)) return
    map.set(value, { title: value, value })
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

    if (vehicleFilters.value.gearbox && normalizeVehicleField(vehicle.atrumkarba) !== vehicleFilters.value.gearbox) {
      return false
    }

    if (vehicleFilters.value.fuel && normalizeVehicleField(vehicle.degvielas_veids) !== vehicleFilters.value.fuel) {
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
      return `${a.marka || ''} ${a.modelis || ''}`.localeCompare(`${b.marka || ''} ${b.modelis || ''}`, intlLocale.value)
    }

    if (sortBy === 'brand_desc') {
      return `${b.marka || ''} ${b.modelis || ''}`.localeCompare(`${a.marka || ''} ${a.modelis || ''}`, intlLocale.value)
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

const ownReview = computed(() => {
  if (!clientId.value) return null
  return vehicleReviews.value.find(review => Number(review.klients_id) === Number(clientId.value)) || null
})

const canReview = computed(() => Boolean(user.value?.persona_id && isClient.value))

const reviewStats = computed(() => {
  const count = vehicleReviews.value.length
  if (!count) {
    return { count: 0, average: 0 }
  }

  const sum = vehicleReviews.value.reduce((total, review) => total + Number(review.vertejums || 0), 0)
  return {
    count,
    average: sum / count,
  }
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
  const phone = typeof persona?.kontakttalrunis === 'string' ? persona.kontakttalrunis.trim() : ''
  const name = persona
    ? `${persona.vards || ''} ${persona.uzvards || ''}`.trim()
    : `Pakalpojumu sniedzējs #${sniedzejs.sniedzejs_id}`
  const address = formatCompanyAddress(sniedzejs)

  return {
    name,
    address: address || 'Nav norādīta adrese',
    city: sniedzejs.pilseta || 'Nav norādīta pilsēta',
    phone,
  }
})

function formatPrice(value) {
  const num = Number(value || 0)
  return `${num.toFixed(2)} €`
}

function normalizeVehicleField(value) {
  if (value === null || value === undefined) return ''
  const normalized = String(value).trim()
  return normalized && normalized !== '-' ? normalized : ''
}

function buildVehicleDetails(vehicle) {
  return [
    { label: copy.value.vehicleFields.gearbox, value: normalizeVehicleField(vehicle?.atrumkarba) },
    { label: copy.value.vehicleFields.fuel, value: normalizeVehicleField(vehicle?.degvielas_veids) },
    { label: copy.value.vehicleFields.registrationNumber, value: normalizeVehicleField(vehicle?.registracijas_numurs) },
  ].filter(detail => detail.value)
}

function isOwnVehicle(vehicle) {
  return Boolean(providerId.value && Number(vehicle?.sniedzejs_id) === Number(providerId.value))
}

function reviewerName(review) {
  const persona = review?.klients?.persona
  if (!persona) return 'Klients'
  const fullName = `${persona.vards || ''} ${persona.uzvards || ''}`.trim()
  return fullName || 'Klients'
}

function formatReviewDate(value) {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  return new Intl.DateTimeFormat(intlLocale.value, {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(date)
}

function resetReviewForm() {
  if (ownReview.value) {
    reviewForm.value = {
      vertejums: Number(ownReview.value.vertejums || 0),
      komentars: ownReview.value.komentars || '',
    }
    return
  }

  reviewForm.value = {
    vertejums: 0,
    komentars: '',
  }
}

function formatCompanyAddress(provider) {
  const street = provider?.iela?.trim?.() || ''
  const houseNumber = String(provider?.majas_numurs || '').trim()
  const apartmentNumber = String(provider?.dzivokla_numurs || '').trim()
  const houseWithApartment = apartmentNumber
    ? `${houseNumber}${houseNumber ? '-' : ''}${apartmentNumber}`
    : houseNumber

  if (!street) return houseWithApartment
  if (!houseWithApartment) return street
  if (street.endsWith(houseWithApartment)) return street

  if (houseNumber && apartmentNumber && street.endsWith(houseNumber)) {
    return `${street}-${apartmentNumber}`
  }

  if (houseNumber && street.endsWith(houseNumber)) {
    return street
  }

  return [street, houseWithApartment].filter(Boolean).join(' ')
}

function formatDateTime(value) {
  const date = value instanceof Date ? value : new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  return new Intl.DateTimeFormat(intlLocale.value, {
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
    snackbar.value = { show: true, text: copy.value.loginToReserve, color: 'error' }
    router.push(AUTH_ROUTE)
    return
  }

  if (!isClient.value) {
    snackbar.value = { show: true, text: copy.value.clientOnly, color: 'error' }
    return
  }

  activeVehicle.value = vehicle
  reservationError.value = ''
  reservationDialog.value = true
}

function openEditVehicle(vehicle) {
  editVehicle.value = {
    transportlidzeklis_id: vehicle.transportlidzeklis_id,
    veids_id: vehicle.veids_id,
    marka: vehicle.marka,
    modelis: vehicle.modelis,
    atrumkarba: vehicle.atrumkarba || '-',
    degvielas_veids: vehicle.degvielas_veids || '-',
    dienas_nomas_cena: vehicle.dienas_nomas_cena,
    statuss: vehicle.statuss,
    registracijas_numurs: vehicle.registracijas_numurs,
  }
  editError.value = ''
  editDialog.value = true
}

async function saveVehicle() {
  editLoading.value = true
  editError.value = ''

  try {
    const response = await fetch(`${API_BASE}/api/transport/${editVehicle.value.transportlidzeklis_id}`, {
      method: 'PUT',
      headers: getAuthHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify({
        veids_id: editVehicle.value.veids_id,
        marka: editVehicle.value.marka,
        modelis: editVehicle.value.modelis,
        atrumkarba: editVehicle.value.atrumkarba,
        degvielas_veids: editVehicle.value.degvielas_veids,
        dienas_nomas_cena: editVehicle.value.dienas_nomas_cena,
        statuss: editVehicle.value.statuss,
        registracijas_numurs: editVehicle.value.registracijas_numurs,
      }),
    })
    const result = await response.json()

    if (!response.ok) {
      editError.value = result?.message || copy.value.messages.saveFailed
      return
    }

    editDialog.value = false
    await loadTransport()
  } catch {
    editError.value = copy.value.messages.saveError
  } finally {
    editLoading.value = false
  }
}

async function deleteVehicle() {
  editDialog.value = false
  nextTick().then(() => {
    deleteVehicleDialog.value = true
  })
}

function cancelDeleteVehicle() {
  deleteVehicleDialog.value = false
  nextTick().then(() => {
    editDialog.value = true
  })
}

async function confirmDeleteVehicle() {
  if (!editVehicle.value.transportlidzeklis_id || !providerId.value) {
    editError.value = copy.value.messages.providerMissing
    return
  }

  deleteVehicleDialog.value = false
  deleteVehicleLoading.value = true
  editError.value = ''

  try {
    const response = await fetch(`${API_BASE}/api/transport/${editVehicle.value.transportlidzeklis_id}`, {
      method: 'DELETE',
      headers: getAuthHeaders({ 'Content-Type': 'application/json' }),
    })

    const result = await response.json()
    if (!response.ok) {
      editError.value = result?.message || copy.value.messages.deleteVehicleFailed
      nextTick().then(() => {
        editDialog.value = true
      })
      return
    }

    editDialog.value = false
    snackbar.value = { show: true, text: result?.message || copy.value.messages.vehicleDeleted, color: 'success' }
    await loadTransport()
  } catch {
    editError.value = copy.value.messages.deleteVehicleError
    nextTick().then(() => {
      editDialog.value = true
    })
  } finally {
    deleteVehicleLoading.value = false
  }
}

async function loadVehicleReviews(transportlidzeklisId) {
  reviewLoading.value = true
  reviewError.value = ''

  try {
    const response = await fetch(`${API_BASE}/api/atsauksmes?transportlidzeklis_id=${transportlidzeklisId}`)
    const result = await response.json()

    if (!response.ok) {
      reviewError.value = result?.message || copy.value.messages.reviewLoadFailed
      vehicleReviews.value = []
      return
    }

    vehicleReviews.value = Array.isArray(result?.atsauksmes) ? result.atsauksmes : []
    resetReviewForm()
  } catch {
    reviewError.value = copy.value.messages.reviewLoadError
    vehicleReviews.value = []
  } finally {
    reviewLoading.value = false
  }
}

async function openReviews(vehicle) {
  reviewVehicle.value = vehicle
  reviewSuccess.value = ''
  reviewError.value = ''
  reviewDialog.value = true
  await loadVehicleReviews(vehicle.transportlidzeklis_id)
}

async function submitReview() {
  if (!reviewVehicle.value) return

  if (!canReview.value) {
    reviewError.value = copy.value.messages.reviewOnlyClients
    return
  }

  if (!Number.isInteger(Number(reviewForm.value.vertejums)) || Number(reviewForm.value.vertejums) < 1 || Number(reviewForm.value.vertejums) > 5) {
    reviewError.value = copy.value.messages.reviewRatingRequired
    return
  }

  reviewSaving.value = true
  reviewError.value = ''
  reviewSuccess.value = ''

  try {
    const payload = {
      vertejums: Number(reviewForm.value.vertejums),
      komentars: (reviewForm.value.komentars || '').trim() || null,
    }

    let url = `${API_BASE}/api/atsauksmes`
    let method = 'POST'

    if (ownReview.value?.atsauksme_id) {
      url = `${API_BASE}/api/atsauksmes/${ownReview.value.atsauksme_id}`
      method = 'PUT'
    } else {
      payload.transportlidzeklis_id = reviewVehicle.value.transportlidzeklis_id
    }

    const response = await fetch(url, {
      method,
      headers: getAuthHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify(payload),
    })

    const result = await response.json()
    if (!response.ok) {
      reviewError.value = result?.message || copy.value.messages.reviewSaveFailed
      return
    }

    reviewSuccess.value = result?.message || copy.value.messages.reviewSaved
    await loadVehicleReviews(reviewVehicle.value.transportlidzeklis_id)
    await loadTransport()
  } catch {
    reviewError.value = copy.value.messages.reviewSaveError
  } finally {
    reviewSaving.value = false
  }
}

function resetVehicleFilters() {
  vehicleFilters.value = {
    q: '',
    typeId: null,
    gearbox: null,
    fuel: null,
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
      reservationError.value = copy.value.invalidReservationTime
      return
    }

    const response = await fetch(`${API_BASE}/api/rezervacijas`, {
      method: 'POST',
      headers: getAuthHeaders({ 'Content-Type': 'application/json', 'X-Timezone': userTimezone }),
      body: JSON.stringify({
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
      headers: getAuthHeaders({ 'Content-Type': 'application/json' }),
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

async function loadTransportTypes() {
  try {
    const response = await fetch(`${API_BASE}/api/transport/veidi`)
    const data = await response.json()

    if (!response.ok) {
      transportTypes.value = []
      return
    }

    transportTypes.value = Array.isArray(data) ? data : []
  } catch {
    transportTypes.value = []
  }
}

onMounted(loadTransport)
onMounted(loadTransportTypes)
// Sākotnējā ielāde un lietotāja sesijas nolasīšana.

onMounted(() => {
  syncCurrentUser().then(persona => {
    user.value = persona
  })
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
.company-page { min-height: calc(100vh - 72px); color: var(--er-text); background: var(--er-page-bg); }
.company-page :deep(.v-container) { max-width: 1280px; padding-inline: 20px; }
.company-banner { background: var(--er-night); color: var(--er-night-text); padding: 24px 0 56px; }
.company-back-btn { color: var(--er-night-muted); margin-left: -16px; margin-bottom: 20px; border-radius: 999px; font-size: .875rem; }
.company-back-btn:hover { color: var(--er-primary); }
.company-hero { display: grid; gap: 32px; }
.company-hero h1, .company-banner__title { font-family: var(--er-display-font); font-size: clamp(2.25rem, 5vw, 3rem); font-weight: 900; letter-spacing: -.025em; line-height: 1.1; margin-bottom: 20px; overflow-wrap: anywhere; }
.company-hero > div:first-child > .text-body-2 { color: var(--er-night-muted); opacity: 1 !important; }
.company-phone-link { color: var(--er-night-muted); font-weight: 500; text-decoration: none; }
.company-phone-link:hover { color: var(--er-primary); text-decoration: underline; text-underline-offset: 4px; }
.company-hero__stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; max-width: 680px; }
.metric-pill { display: grid; align-content: start; gap: 8px; border: 1px solid var(--er-night-border); background: transparent; padding: 18px; border-radius: 18px; min-width: 0; }
.metric-pill strong { color: var(--er-primary); font-family: var(--er-display-font); font-size: clamp(1.35rem, 2.8vw, 1.875rem); font-weight: 900; letter-spacing: -.025em; line-height: 1.1; overflow-wrap: anywhere; }
.metric-pill span { font-size: .75rem; color: var(--er-night-muted); line-height: 1.4; }
.company-content { padding-block: 48px 72px; }
.company-loading { display: grid; justify-items: center; gap: 16px; padding: 72px 20px; color: var(--er-text-muted); background: var(--er-surface-strong); border-radius: 26px; }
.company-catalog { display: grid; grid-template-columns: minmax(250px, 300px) minmax(0, 1fr); align-items: start; gap: 32px; }
.company-controls { background: var(--er-surface-strong); border: 1px solid var(--er-stroke); border-radius: 26px; padding: 24px; box-shadow: var(--er-shadow-sm); min-width: 0; }
.company-toolbar { justify-content: flex-start !important; gap: 8px !important; margin-bottom: 20px !important; }
.company-toolbar :deep(.v-btn) { border-radius: 999px; min-width: 0; font-size: .7rem; flex: 1 1 auto; padding-inline: 10px; }
.company-panel-card { background: transparent; color: var(--er-text); border: 0; border-radius: 0; box-shadow: none; overflow: visible; }
.company-panel-card :deep(.v-card-text) { padding: 0; }
.company-panel-card :deep(.v-row) { margin-inline: -4px; }
.company-panel-card :deep(.v-col) { padding-inline: 4px; }
.toolbar-secondary-btn { border-radius: 999px; font-size: .75rem; }
.company-results { min-width: 0; }
.company-results__heading { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; margin: 0 0 24px; }
.company-results__heading h2 { font-family: var(--er-display-font); font-size: 1.875rem; font-weight: 900; letter-spacing: -.025em; }
.vehicles-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 24px; }
.vehicle-card { border: 1px solid var(--er-stroke) !important; border-radius: 26px; color: var(--er-text); background: var(--er-surface-strong); box-shadow: var(--er-shadow-sm); transition: transform .2s ease, box-shadow .2s ease; overflow: hidden; }
.vehicle-card:hover { transform: translateY(-4px); box-shadow: var(--er-shadow-md); }
.vehicle-card__visual { position: relative; aspect-ratio: 4 / 3; overflow: hidden; background: var(--er-panel-soft); }
.vehicle-card__visual img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .5s ease; }
.vehicle-card:hover .vehicle-card__visual img { transform: scale(1.05); }
.vehicle-card__status { position: absolute; top: 16px; left: 16px; font-weight: 800; border-radius: 999px; }
.vehicle-card :deep(.v-card-text) { padding: 20px; }
.vehicle-card__name { font-family: var(--er-display-font); font-size: 1.25rem; font-weight: 800; line-height: 1.2; overflow-wrap: anywhere; margin-bottom: 8px; }
.vehicle-card__type { color: var(--er-primary); text-transform: uppercase; font-size: .7rem; font-weight: 800; letter-spacing: .08em; margin-bottom: 12px; }
.vehicle-card__price { font-family: var(--er-display-font); font-size: 1.35rem; font-weight: 900; margin: 12px 0; }
.vehicle-card__details { display: flex; flex-wrap: wrap; gap: 6px; }
.vehicle-detail-chip { display: inline-flex; gap: 4px; max-width: 100%; padding: 5px 8px; border-radius: 9px; background: var(--er-panel-soft); color: var(--er-text-muted); font-size: .7rem; line-height: 1.4; overflow-wrap: anywhere; }
.vehicle-detail-chip__label { font-weight: 500; }
.vehicle-detail-chip__value { overflow-wrap: anywhere; }
.vehicle-card__actions { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--er-stroke); }
.vehicle-card__actions > .v-chip { display: none; }
.vehicle-card__actions :deep(.v-btn) { border-radius: 999px; font-weight: 800; }
.vehicle-card__actions :deep(.v-btn:last-child) { margin-left: auto; }
.vehicle-rating { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; color: var(--er-text-muted); }
.reservation-summary { display: grid; gap: 10px; padding: 20px; border-radius: 18px; background: var(--er-primary-soft); border: 1px solid var(--er-stroke); color: var(--er-text); font-size: .875rem; }
.reservation-summary > :last-child { border-top: 1px solid var(--er-stroke); padding-top: 12px; margin-top: 2px; font-family: var(--er-display-font); font-size: 1.15rem; font-weight: 800; }
@media (min-width: 1500px) { .vehicles-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
@media (max-width: 959px) {
 .company-catalog { grid-template-columns: minmax(220px, 260px) minmax(0, 1fr); gap: 24px; }
 .vehicles-grid { grid-template-columns: minmax(0, 1fr); }
 .company-controls { padding: 20px; }
}
@media (max-width: 699px) {
 .company-page :deep(.v-container) { padding-inline: 16px; }
 .company-banner { padding: 16px 0 32px; }
 .company-hero { gap: 24px; }
 .metric-pill { padding: 14px 10px; gap: 8px; border-radius: 14px; }
 .metric-pill span { font-size: .65rem; }
 .company-hero__stats { gap: 8px; }
 .company-content { padding-block: 28px 48px; }
 .company-catalog { grid-template-columns: minmax(0, 1fr); gap: 28px; }
 .company-controls { padding: 20px; }
 .company-toolbar :deep(.v-btn) { flex: 0 1 auto; padding-inline: 14px; font-size: .75rem; }
 .company-results__heading { margin-bottom: 20px; }
 .company-results__heading h2 { font-size: 1.5rem; }
 .vehicle-card__visual { aspect-ratio: 16 / 10; }
}
@media (prefers-reduced-motion: reduce) { .vehicle-card, .vehicle-card__visual img { transition: none; } }

.rental-dialog__card :deep(.v-card-title) { display: flex; align-items: flex-start; flex-wrap: wrap; gap: 12px; }
.rental-dialog__card :deep(.v-card-title .v-chip) { flex: 0 0 auto; }
.rental-dialog__card :deep(.v-card-actions) { border-top: 1px solid var(--er-stroke); background: var(--er-panel-soft); }
.rental-dialog__card :deep(.v-card-actions .v-btn) { border-radius: 999px; }
.rental-dialog__card :deep(.v-card-actions .v-spacer) { min-width: 0; }
.rental-dialog__card :deep(.v-card--variant-outlined) { border: 1px solid var(--er-stroke); border-radius: 18px; background: var(--er-surface-strong); box-shadow: none; }
.rental-dialog__card :deep(.v-card--variant-outlined .v-card-text) { padding: 18px !important; }
.dialog-state { display: flex; align-items: center; justify-content: center; flex-wrap: wrap; gap: 12px; padding: 28px 20px; border: 1px dashed var(--er-stroke); border-radius: 18px; background: var(--er-panel-soft); color: var(--er-text-muted); font-size: .875rem; line-height: 1.6; text-align: center; }
.dialog-state .v-icon { color: var(--er-primary); }
.payment-amount { padding: 18px; border-radius: 16px; border: 1px solid var(--er-stroke); background: var(--er-primary-soft); color: var(--er-text); opacity: 1 !important; font-family: var(--er-display-font); font-size: 1rem !important; font-weight: 800; }
.rental-calendar { border: 1px solid var(--er-stroke); border-radius: 20px !important; box-shadow: var(--er-shadow-md); }
.rental-calendar :deep(.v-date-picker-controls) { padding-inline: 12px; }
.rental-calendar :deep(.v-date-picker-month) { padding-inline: 6px; }
.rental-calendar :deep(.v-date-picker-month__days) { grid-template-columns: repeat(7, minmax(0, 1fr)); width: 100%; }
.rental-calendar :deep(.v-date-picker-month__day) { width: auto; min-width: 0; }
@media (max-width: 599px) {
 .rental-dialog__card :deep(.v-card-actions) { gap: 8px; }
 .rental-dialog__card :deep(.v-card-actions .v-btn) { font-size: .75rem; min-height: 40px; flex: 1 1 auto; }
 .rental-dialog__card :deep(.v-card-actions .v-spacer) { display: none; }
 .rental-dialog__card :deep(.v-card--variant-outlined .v-card-text) { padding: 14px !important; }
 .rental-calendar :deep(.v-date-picker-month__day .v-btn) { width: 32px; height: 32px; }
}

</style>
