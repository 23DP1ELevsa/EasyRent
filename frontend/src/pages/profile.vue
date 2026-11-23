<template>
  <div class="profile-bg">
    <section class="profile-hero">
      <v-container class="profile-shell">
        <div class="profile-heading">
          <div class="profile-heading__main">
            <p v-if="loma" class="profile-role">{{ loma === 'klients' ? copy.roles.client : copy.roles.provider }}</p>
            <h1 class="profile-name">{{ profileName }}</h1>
            <p v-if="profileEmail" class="profile-email">{{ profileEmail }}</p>
          </div>
          <v-btn class="profile-hero__logout" rounded="pill" variant="outlined" @click="logout">
            <v-icon size="18" start>mdi-logout</v-icon>
            {{ copy.actions.logout }}
          </v-btn>
        </div>
        <div v-if="showReservationsSection" class="profile-stat-grid">
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
      </v-container>
    </section>
    <v-container class="profile-shell profile-content">
      <div class="profile-layout" :class="{ 'profile-layout--setup': profileIncomplete || rolePending }">
        <v-alert
          v-if="profileIncomplete || rolePending"
          class="profile-setup-notice"
          color="primary"
          icon="mdi-account-alert-outline"
          :title="copy.setup.title"
          variant="tonal"
        >
          <p>{{ copy.setup.intro }}</p>
          <p v-if="missingLabels.length > 0" class="mt-2">{{ t('profile.setup.missing', { fields: missingLabels.join(', ') }) }}</p>
        </v-alert>

        <v-card v-if="rolePending" class="surface profile-details" elevation="0">
          <v-card-text class="pa-6">
            <v-form @submit.prevent="chooseGoogleRole">
              <v-radio-group v-model="selectedRole" :error-messages="roleError" :label="copy.setup.chooseRole">
                <v-radio :label="copy.roles.client" value="klients" />
                <v-radio :label="copy.roles.provider" value="pakalpojumu_sniedzejs" />
              </v-radio-group>
              <v-btn
                color="primary"
                :loading="loading"
                rounded="xl"
                size="large"
                type="submit"
              >{{ t('common.continue') }}</v-btn>
            </v-form>
          </v-card-text>
        </v-card>

        <!-- Edit Form Card -->
        <v-card v-else-if="persona" class="surface profile-details" elevation="0">
          <v-card-title class="pa-6">
            <div>
              <h2 class="text-h5 font-weight-bold">{{ copy.page.title }}</h2>
              <div class="text-caption opacity-70 mt-1">{{ copy.page.subtitle }}</div>
            </div>
          </v-card-title>

          <v-divider />

          <v-card-text class="pa-6">
            <!-- Success/Error Messages -->
            <v-form ref="formRef" @submit.prevent="updateProfile">
              <!-- Basic Information Section -->
              <div class="section-title mb-4">{{ copy.sections.basic }}</div>

              <v-row class="mb-6" dense>
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model="form.vards"
                    density="compact"
                    :error="!!fieldErrors.vards"
                    :error-messages="fieldErrors.vards ? [fieldErrors.vards] : []"
                    :label="fieldLabel('vards', 'firstName')"
                    :rules="nameRules"
                    variant="outlined"
                  />
                </v-col>

                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model="form.uzvards"
                    density="compact"
                    :error="!!fieldErrors.uzvards"
                    :error-messages="fieldErrors.uzvards ? [fieldErrors.uzvards] : []"
                    :label="copy.fields.lastName"
                    variant="outlined"
                  />
                </v-col>

                <v-col cols="12">
                  <v-text-field
                    v-model="email"
                    density="compact"
                    :error-messages="fieldErrors.epasts"
                    :hint="canEnterEmail ? undefined : copy.hints.emailLocked"
                    :label="fieldLabel('epasts', 'email')"
                    :readonly="!canEnterEmail"
                    :rules="canEnterEmail ? [requiredRule, validEmailRule] : []"
                    type="email"
                    variant="outlined"
                  />
                </v-col>

                <v-col cols="12">
                  <v-text-field
                    v-model="form.kontakttalrunis"
                    density="compact"
                    :error="!!fieldErrors.kontakttalrunis"
                    :error-messages="fieldErrors.kontakttalrunis ? [fieldErrors.kontakttalrunis] : []"
                    :label="fieldLabel('kontakttalrunis', 'phone')"
                    placeholder="+371 26123456"
                    :rules="profileRules(phoneRules)"
                    variant="outlined"
                  />
                </v-col>
              </v-row>

              <!-- IBAN Section -->
              <div class="section-title mb-4">{{ copy.sections.bank }}</div>

              <v-row class="mb-6" dense>
                <v-col cols="12">
                  <v-text-field
                    v-model="form.bankas_konts"
                    density="compact"
                    :error="!!fieldErrors.bankas_konts"
                    :error-messages="fieldErrors.bankas_konts ? [fieldErrors.bankas_konts] : []"
                    :label="fieldLabel('bankas_konts', 'iban')"
                    placeholder="LV00ABCD1234567890123"
                    :rules="profileRules(ibanRules)"
                    variant="outlined"
                  />
                </v-col>
              </v-row>

              <!-- Klients Section -->
              <template v-if="loma === 'klients'">
                <v-divider class="my-6" />
                <div class="section-title mb-4">{{ copy.sections.client }}</div>

                <v-row class="mb-6" dense>
                  <v-col cols="12">
                    <v-text-field
                      v-model="form.lietotajvards"
                      density="compact"
                      :error="!!fieldErrors.lietotajvards"
                      :error-messages="fieldErrors.lietotajvards ? [fieldErrors.lietotajvards] : []"
                      :label="fieldLabel('lietotajvards', 'username')"
                      :rules="usernameRules"
                      variant="outlined"
                    />
                  </v-col>
                </v-row>
              </template>

              <!-- Pakalpojumu sniedzējs Section -->
              <template v-if="loma === 'pakalpojumu_sniedzejs'">
                <v-divider class="my-6" />
                <div class="section-title mb-4">{{ copy.sections.provider }}</div>

                <v-row class="mb-6" dense>
                  <v-col cols="12">
                    <v-text-field
                      v-model="form.registracijas_numurs"
                      density="compact"
                      :error="!!fieldErrors.registracijas_numurs"
                      :error-messages="fieldErrors.registracijas_numurs ? [fieldErrors.registracijas_numurs] : []"
                      :label="fieldLabel('registracijas_numurs', 'registrationNumber')"
                      :rules="profileRules(regNumRules)"
                      variant="outlined"
                    />
                  </v-col>

                  <v-col cols="12">
                    <v-text-field
                      v-model="form.iela"
                      density="compact"
                      :error="!!fieldErrors.iela"
                      :error-messages="fieldErrors.iela ? [fieldErrors.iela] : []"
                      :label="fieldLabel('iela', 'street')"
                      :rules="profileRules(streetRules)"
                      variant="outlined"
                    />
                  </v-col>

                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model="form.majas_numurs"
                      density="compact"
                      :error="!!fieldErrors.majas_numurs"
                      :error-messages="fieldErrors.majas_numurs ? [fieldErrors.majas_numurs] : []"
                      :label="fieldLabel('majas_numurs', 'houseNumber')"
                      :rules="profileRules(houseNumberRules)"
                      variant="outlined"
                    />
                  </v-col>

                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model="form.dzivokla_numurs"
                      density="compact"
                      :error="!!fieldErrors.dzivokla_numurs"
                      :error-messages="fieldErrors.dzivokla_numurs ? [fieldErrors.dzivokla_numurs] : []"
                      :label="copy.fields.apartmentNumber"
                      variant="outlined"
                    />
                  </v-col>

                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model="form.pilseta"
                      density="compact"
                      :error="!!fieldErrors.pilseta"
                      :error-messages="fieldErrors.pilseta ? [fieldErrors.pilseta] : []"
                      :label="fieldLabel('pilseta', 'city')"
                      :rules="profileRules(cityRules)"
                      variant="outlined"
                    />
                  </v-col>

                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model="form.pasta_indekss"
                      density="compact"
                      :error="!!fieldErrors.pasta_indekss"
                      :error-messages="fieldErrors.pasta_indekss ? [fieldErrors.pasta_indekss] : []"
                      :label="fieldLabel('pasta_indekss', 'postalCode')"
                      :rules="profileRules(postalCodeRules)"
                      variant="outlined"
                    />
                  </v-col>

                  <v-col v-if="!profileIncomplete" cols="12" sm="6">
                    <v-text-field
                      v-model="form.latitude"
                      density="compact"
                      :error-messages="fieldErrors.latitude"
                      :label="copy.fields.latitude"
                      placeholder="56.9496"
                      variant="outlined"
                    />
                  </v-col>

                  <v-col v-if="!profileIncomplete" cols="12" sm="6">
                    <v-text-field
                      v-model="form.longitude"
                      density="compact"
                      :error-messages="fieldErrors.longitude"
                      :label="copy.fields.longitude"
                      placeholder="24.1052"
                      variant="outlined"
                    />
                  </v-col>

                  <v-col cols="12">
                    <div v-if="geocodeLoading" class="text-caption opacity-70">{{ copy.hints.geocodeLoading }}</div>
                    <div v-else-if="geocodeError" class="text-caption text-error">{{ geocodeError }}</div>
                  </v-col>
                </v-row>
              </template>

              <!-- Password Section -->
              <template v-if="!profileIncomplete">
                <v-divider class="my-6" />
                <div class="section-title mb-4">{{ copy.sections.password }}</div>

                <v-row class="mb-8" dense>
                  <v-col cols="12">
                    <v-text-field
                      v-model="form.password"
                      density="compact"
                      :error="!!fieldErrors.password"
                      :error-messages="fieldErrors.password ? [fieldErrors.password] : []"
                      :label="copy.fields.newPassword"
                      :rules="passwordRules"
                      type="password"
                      variant="outlined"
                    />
                  </v-col>
                </v-row>
              </template>

              <!-- Action Buttons -->
              <v-row class="gap-3" dense>
                <v-col cols="12" sm="auto">
                  <v-btn
                    elevation="4"
                    :loading="loading"
                    min-width="160"
                    rounded="xl"
                    size="large"
                    type="submit"
                  >
                    <v-icon start>mdi-check</v-icon>
                    {{ profileIncomplete ? copy.setup.saveAndContinue : copy.actions.save }}
                  </v-btn>
                </v-col>

                <v-col v-if="!profileIncomplete" cols="12" sm="auto">
                  <v-btn
                    class="back-btn"
                    min-width="160"
                    rounded="xl"
                    size="large"
                    to="/"
                    variant="outlined"
                  >
                    <v-icon start>mdi-arrow-left</v-icon>
                    {{ copy.actions.back }}
                  </v-btn>
                </v-col>
              </v-row>
            </v-form>

            <section v-if="!profileIncomplete" aria-labelledby="profile-deletion-title">
              <v-divider class="my-6" />
              <h3 id="profile-deletion-title" class="section-title mb-3">{{ copy.deletion.title }}</h3>
              <p class="mb-4">{{ copy.deletion.intro }}</p>
              <v-alert
                v-if="deletionBlock"
                class="mb-4"
                :title="copy.deletion.blocked"
                type="warning"
                variant="tonal"
              >
                <template v-if="deletionBlock.blocking_reservations_count">
                  <p>{{ copy.deletion.blockedReservations }}</p>
                  <p class="mt-2">{{ t('profile.deletion.reservationCount', { count: deletionBlock.blocking_reservations_count }) }}</p>
                </template>
                <p v-if="deletionBlock.blocking_payments_count" class="mt-2">{{ t('profile.deletion.paymentCount', { count: deletionBlock.blocking_payments_count }) }}</p>
                <v-btn v-if="showReservationsSection" class="mt-2" href="#profile-reservations" variant="text">{{ copy.deletion.viewReservations }}</v-btn>
              </v-alert>
              <v-alert v-if="deletionError && !deletionDialog" class="mb-4" type="error" variant="tonal">{{ deletionError }}</v-alert>
              <v-btn
                color="error"
                :disabled="Boolean(deletionBlock)"
                :loading="checkingDeletion"
                rounded="pill"
                variant="outlined"
                @click="openDeletion"
              >
                {{ copy.deletion.delete }}
              </v-btn>
              <v-btn
                v-if="deletionBlock"
                class="ms-2"
                :loading="checkingDeletion"
                variant="text"
                @click="checkDeletion"
              >{{ copy.deletion.checkAgain }}</v-btn>
            </section>

            <v-dialog v-if="!profileIncomplete" v-model="deletionDialog" max-width="540" :persistent="deletingAccount">
              <v-card class="profile-confirmation">
                <v-card-title class="font-weight-bold text-wrap">{{ copy.deletion.confirmTitle }}</v-card-title>
                <v-form @submit.prevent="deleteAccount">
                  <v-card-text class="pa-4">
                    <p class="mb-4">{{ copy.deletion.confirmIntro }}</p>
                    <v-alert v-if="deletionError" class="mb-4" type="error" variant="tonal">{{ deletionError }}</v-alert>
                    <v-text-field
                      v-model="deletionPhrase"
                      autocomplete="off"
                      :disabled="deletingAccount"
                      :label="t('profile.deletion.phraseLabel', { phrase: copy.deletion.phrase })"
                      variant="outlined"
                    />
                    <v-text-field
                      v-if="deletionCheck?.confirmation_method === 'password'"
                      v-model="deletionPassword"
                      autocomplete="current-password"
                      :disabled="deletingAccount"
                      :label="copy.deletion.password"
                      type="password"
                      variant="outlined"
                    />
                    <v-alert v-else-if="deletionCheck?.google_confirmed" type="success" variant="tonal">
                      {{ copy.deletion.googleConfirmed }}
                    </v-alert>
                    <v-btn v-else-if="deletionCheck?.google_available" :loading="startingGoogleDeletion" variant="outlined" @click="startGoogleDeletion">
                      {{ copy.deletion.googleConfirm }}
                    </v-btn>
                    <p v-else>{{ copy.deletion.googleUnavailable }}</p>
                  </v-card-text>
                  <v-card-actions class="px-4 pb-4 flex-wrap ga-2 justify-end">
                    <v-btn :disabled="deletingAccount" variant="text" @click="deletionDialog = false">{{ t('common.cancel') }}</v-btn>
                    <v-btn
                      color="error"
                      :disabled="!canSubmitDeletion"
                      :loading="deletingAccount"
                      type="submit"
                      variant="flat"
                    >
                      {{ copy.deletion.deletePermanently }}
                    </v-btn>
                  </v-card-actions>
                </v-form>
              </v-card>
            </v-dialog>
          </v-card-text>
        </v-card>

        <v-card v-if="showReservationsSection" id="profile-reservations" class="surface profile-reservations" elevation="0">
          <v-card-title class="pa-6">
            <div>
              <h2 class="text-h5 font-weight-bold">{{ copy.reservations.title }}</h2>
              <div class="text-caption opacity-70 mt-1">{{ reservationsSubtitle }}</div>
            </div>
          </v-card-title>
          <v-divider />
          <v-card-text class="pa-6">
            <v-alert
              v-if="isClient && unpaidReservations.length"
              class="mb-4"
              type="warning"
              variant="tonal"
            >
              {{ copy.reservations.unpaidAlert.replace('{count}', unpaidReservations.length) }}
            </v-alert>

            <div v-if="reservationsLoading" class="profile-state" role="status"><v-progress-circular color="primary" indeterminate size="28" width="2" /><span>{{ copy.reservations.loading }}</span></div>
            <div v-else-if="!reservations.length" class="profile-state profile-state--empty"><v-icon color="primary" size="36">mdi-calendar-blank-outline</v-icon><span>{{ copy.reservations.empty }}</span></div>

            <template v-else>
              <div class="section-title mb-3">{{ activeReservationsTitle }} <span class="reservation-section-count">{{ visibleActiveReservations.length }}</span></div>

              <div v-if="!visibleActiveReservations.length" class="reservation-empty mb-4">{{ activeReservationsEmptyText }}</div>

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
                          {{ reservationVehicleName(item) }}
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
                        <v-chip color="success" size="small" variant="tonal">{{ copy.reservations.paid }}</v-chip>
                        <v-btn v-if="item.invoice_available" size="small" variant="text" @click="downloadInvoice(item.rezervacija_id)">{{ copy.invoice.download }}</v-btn>
                      </div>
                    </div>
                  </v-card-text>
                </v-card>
              </div>

              <div class="section-title mb-3">{{ pendingReservationsTitle }} <span class="reservation-section-count">{{ visiblePendingReservations.length }}</span></div>

              <div v-if="!visiblePendingReservations.length" class="reservation-empty">{{ pendingReservationsEmptyText }}</div>

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
                          {{ reservationVehicleName(item) }}
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
                        <v-chip color="warning" size="small" variant="tonal">{{ copy.reservations.unpaidStatus }}</v-chip>
                      </div>
                    </div>

                    <template v-if="isClient">
                      <div class="reservation-card-actions">
                        <v-btn
                          color="primary"
                          :loading="payingReservationId === item.rezervacija_id"
                          size="small"
                          variant="flat"
                          @click="payReservation(item.rezervacija_id)"
                        >
                          {{ copy.reservations.payNow }}
                        </v-btn>
                        <v-btn
                          color="error"
                          :loading="cancellingReservationId === item.rezervacija_id"
                          size="small"
                          variant="text"
                          @click="openCancelDialog(item.rezervacija_id)"
                        >
                          {{ copy.reservations.cancelReservation }}
                        </v-btn>
                      </div>
                    </template>
                  </v-card-text>
                </v-card>
              </div>

              <div class="section-title mt-6 mb-3">{{ reservationHistoryTitle }} <span class="reservation-section-count">{{ reservationHistory.length }}</span></div>

              <div v-if="!reservationHistory.length" class="reservation-empty">{{ reservationHistoryEmptyText }}</div>

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
                          {{ reservationVehicleName(item) }}
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
                        <v-chip color="info" size="small" variant="tonal">{{ copy.reservations.historyDone }}</v-chip>
                        <v-chip :color="item.apmaksas_statuss === 'apmaksata' ? 'success' : 'warning'" size="small" variant="tonal">
                          {{ item.apmaksas_statuss === 'apmaksata' ? copy.reservations.paid : copy.reservations.unpaidStatus }}
                        </v-chip>
                        <v-btn v-if="item.invoice_available" size="small" variant="text" @click="downloadInvoice(item.rezervacija_id)">{{ copy.invoice.download }}</v-btn>
                      </div>
                    </div>
                  </v-card-text>
                </v-card>

                <v-pagination
                  v-if="reservationHistoryPageCount > 1"
                  v-model="reservationHistoryPage"
                  class="reservation-history-pagination align-self-center"
                  density="comfortable"
                  :length="reservationHistoryPageCount"
                  rounded="circle"
                  :total-visible="5"
                />
              </div>
            </template>

            <v-dialog v-model="cancelDialog" max-width="440">
              <v-card class="profile-confirmation">
                <v-card-title class="font-weight-bold">{{ copy.confirm.title }}</v-card-title>
                <v-divider />
                <v-card-text class="pa-4">
                  {{ copy.confirm.cancelReservation }}
                </v-card-text>
                <v-card-actions class="px-4 pb-4">
                  <v-spacer />
                  <v-btn :disabled="cancellingReservationId !== null" variant="text" @click="cancelDialog = false">{{ copy.confirm.no }}</v-btn>
                  <v-btn color="error" :loading="cancellingReservationId !== null" @click="cancelReservation">{{ copy.confirm.yesCancel }}</v-btn>
                </v-card-actions>
              </v-card>
            </v-dialog>

          </v-card-text>
        </v-card>
      </div>
    </v-container>
  </div>
</template>

<script setup>
  import { computed, onMounted, ref, watch } from 'vue'
  import { useRoute, useRouter } from 'vue-router'
  import { accountHome, AUTH_ROUTE, HOME_ROUTE } from '@/router/paths'
  import { apiFetch, clearAuthSession, getStoredUser, googlePending, logoutRequest, setAuthSession } from '@/services/auth'
  import { useLoadingState } from '@/stores/loading'
  import { useLocale } from '@/stores/locale'
  import { useNotifications } from '@/stores/notifications'
  import { useVehicleLabels } from '@/utils/vehicleLabels'

  const router = useRouter()
  const route = useRoute()
  const { t, getIntlLocale, formatPrice } = useLocale()
  const { attributeLabel, typeLabel } = useVehicleLabels()
  const { notifyError, notifySuccess } = useNotifications()
  const persona = ref(null)
  const rolePending = computed(() => googlePending.value?.purpose === 'role' ? googlePending.value : null)
  const profileIncomplete = computed(() => persona.value?.profile_completed === false)
  const canEnterEmail = computed(() => profileIncomplete.value && !persona.value.epasts)
  const copy = computed(() => t('profile'))
  const selectedRole = ref(null)
  const roleError = ref('')
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
  const deletionCheck = ref(null)
  const deletionDialog = ref(false)
  const deletionError = ref('')
  const deletionPhrase = ref('')
  const deletionPassword = ref('')
  const checkingDeletion = ref(false)
  const deletingAccount = ref(false)
  const startingGoogleDeletion = ref(false)
  const deletionBlock = computed(() => deletionCheck.value?.can_delete === false ? deletionCheck.value : null)
  const canSubmitDeletion = computed(() => deletionCheck.value?.can_delete
    && deletionPhrase.value === copy.value.deletion.phrase
    && (deletionCheck.value.confirmation_method === 'password' ? Boolean(deletionPassword.value) : deletionCheck.value.google_confirmed))

  watch(deletionDialog, open => {
    if (!open) {
      deletionPhrase.value = ''
      deletionPassword.value = ''
    }
  })
  useLoadingState(loading, reservationsLoading, geocodeLoading, () => payingReservationId.value !== null, () => cancellingReservationId.value !== null)
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

  const profileName = computed(() => {
    const name = rolePending.value || form.value
    return [name.vards, name.uzvards].filter(Boolean).join(' ') || copy.value.page.title
  })
  const profileEmail = computed(() => rolePending.value?.epasts || persona.value?.epasts)
  const labelKeys = {
    vards: 'firstName',
    epasts: 'email',
    kontakttalrunis: 'phone',
    bankas_konts: 'iban',
    lietotajvards: 'username',
    registracijas_numurs: 'registrationNumber',
    iela: 'street',
    majas_numurs: 'houseNumber',
    pilseta: 'city',
    pasta_indekss: 'postalCode',
  }
  const missingLabels = computed(() => (persona.value?.profile_missing_fields || [])
    .filter(field => !String((field === 'epasts' ? email.value : form.value[field]) ?? '').trim())
    .map(field => copy.value.fields[labelKeys[field]])
    .filter(Boolean))

  function fieldLabel (field, key) {
    const label = copy.value.fields[key]
    return profileIncomplete.value && (field !== 'epasts' || canEnterEmail.value) ? `${label} *` : label
  }

  const requiredRule = value => !!String(value ?? '').trim() || t('common.required')
  const validEmailRule = value => /.+@.+\..+/.test(value || '') || t('auth.messages.emailInvalid')

  function profileRules (rules) {
    return profileIncomplete.value ? [requiredRule, ...rules] : rules
  }

  async function chooseGoogleRole () {
    if (!selectedRole.value) {
      roleError.value = copy.value.setup.chooseRoleError
      return
    }
    loading.value = true
    roleError.value = ''
    try {
      const response = await apiFetch('/oauth/google/role', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ loma: selectedRole.value }),
      })
      const result = await response.json().catch(() => ({}))
      if (response.ok && result.next) {
        window.location.replace(result.next)
        return
      }
      if (response.status === 410) {
        await router.replace({ path: AUTH_ROUTE, query: { google: 'invalid' } })
        return
      }
      roleError.value = result.errors?.loma?.[0] || copy.value.setup.roleError
    } catch {
      roleError.value = t('common.networkError')
    } finally {
      loading.value = false
    }
  }

  // IBAN validācija bankas konta laukam.
  function ibanIsValid (iban) {
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
    v => (profileIncomplete.value && v === persona.value?.vards)
      || (v && v.trim().length >= 2)
      || copy.value.validation.firstNameMin,
  ]

  const phoneRules = [
    v => !v || (v && v.length >= 6) || copy.value.validation.phoneMin,
    v => !v || (v && v.length <= 20) || copy.value.validation.phoneMax,
    v => !v || /^\+?[0-9 \-()]+$/.test(v) || copy.value.validation.phoneInvalid,
  ]

  const ibanRules = [
    v => !v || (v && v.length >= 15) || copy.value.validation.ibanMin,
    v => !v || (v && v.length <= 34) || copy.value.validation.ibanMax,
    v => !v || ibanIsValid(v) || copy.value.validation.ibanInvalid,
  ]

  const usernameRules = [
    v => !!v || copy.value.validation.usernameRequired,
    v => (v && v.length >= 3) || copy.value.validation.usernameMin,
  ]

  const regNumRules = [
    v => !v || (v && v.length >= 1) || copy.value.validation.regNumRequired,
  ]

  const streetRules = [
    v => !v || (v && v.length >= 1) || copy.value.validation.streetRequired,
  ]

  const houseNumberRules = [
    v => !v || (v && v.length >= 1) || copy.value.validation.houseRequired,
  ]

  const cityRules = [
    v => !v || (v && v.length >= 1) || copy.value.validation.cityRequired,
  ]

  const postalCodeRules = [
    v => !v || (v && v.length >= 1) || copy.value.validation.postalRequired,
  ]

  function hasUppercase (value) {
    return /[A-Z]/.test(value || '')
  }

  function hasSpecialCharacter (value) {
    return /[^A-Za-z0-9]/.test(value || '')
  }

  const passwordRules = [
    v => !v || (v && v.length >= 8) || copy.value.validation.passwordMin,
    v => !v || hasUppercase(v) || copy.value.validation.passwordUppercase,
    v => !v || hasSpecialCharacter(v) || copy.value.validation.passwordSpecial,
  ]

  const unpaidReservations = computed(() => {
    const now = new Date()
    return reservations.value.filter(item => item.apmaksas_statuss !== 'apmaksata' && new Date(item.sakuma_laiks) > now)
  })

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
    Math.max(1, Math.ceil(reservationHistory.value.length / reservationHistoryPageSize)),
  )

  const paginatedReservationHistory = computed(() => {
    const start = (reservationHistoryPage.value - 1) * reservationHistoryPageSize
    return reservationHistory.value.slice(start, start + reservationHistoryPageSize)
  })

  const showReservationsSection = computed(() => !!persona.value && !profileIncomplete.value && (isClient.value || isProvider.value))
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
  onMounted(initializeProfile)

  function initializeProfile () {
    if (rolePending.value) return

    const userData = getStoredUser()
    if (!userData) {
      errorText.value = copy.value.validation.loginRequired
      setTimeout(() => router.push(HOME_ROUTE), 2000)
      return
    }

    persona.value = userData
    initializeDeletion()
    email.value = userData.epasts || ''
    loma.value = userData.loma

    form.value.vards = userData.vards || ''
    form.value.uzvards = userData.uzvards || ''
    form.value.kontakttalrunis = userData.kontakttalrunis || ''
    form.value.bankas_konts = userData.bankas_konts || ''

    if (userData.loma === 'klients' && userData.klients) {
      currentClientId.value = userData.klients.klients_id
      form.value.lietotajvards = userData.klients.lietotajvards || ''
      if (showReservationsSection.value) {
        loadReservations(userData.klients.klients_id)
      }
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
      if (showReservationsSection.value) {
        loadReservations()
      }
    }
  }

  async function checkDeletion () {
    checkingDeletion.value = true
    deletionError.value = ''
    try {
      const response = await apiFetch('/api/profile/deletion-check', { cache: 'no-store' })
      if (!response.ok) throw new Error(copy.value.deletion.checkError)
      deletionCheck.value = await response.json()
      return deletionCheck.value
    } catch {
      deletionError.value = copy.value.deletion.checkError
      return null
    } finally {
      checkingDeletion.value = false
    }
  }

  async function openDeletion () {
    if ((await checkDeletion())?.can_delete) deletionDialog.value = true
  }

  async function initializeDeletion () {
    if (profileIncomplete.value) return

    const { delete_google: callback, ...query } = route.query
    const eligibility = await checkDeletion()
    if (!callback) return

    await router.replace({ query, hash: route.hash })
    if (!eligibility?.can_delete) return

    deletionDialog.value = true
    if (!eligibility.google_confirmed) {
      deletionError.value = callback === 'mismatch'
        ? copy.value.deletion.googleMismatch
        : copy.value.deletion.googleIncomplete
    }
  }

  async function startGoogleDeletion () {
    startingGoogleDeletion.value = true
    deletionError.value = ''
    try {
      const response = await apiFetch('/oauth/google/deletion', { auth: false, method: 'POST' })
      const data = await response.json().catch(() => ({}))
      if (!response.ok || !data.authorization_url) {
        deletionError.value = data.message || copy.value.deletion.googleStartError
        return
      }
      window.location.assign(data.authorization_url)
    } catch {
      deletionError.value = copy.value.deletion.googleStartError
    } finally {
      startingGoogleDeletion.value = false
    }
  }

  async function deleteAccount () {
    if (!canSubmitDeletion.value || deletingAccount.value) return
    deletingAccount.value = true
    deletionError.value = ''
    try {
      const response = await apiFetch('/api/profile/me', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        // The API expects the same confirmation value for every interface language.
        body: JSON.stringify({ confirmation: 'DZĒST', password: deletionPassword.value || undefined }),
      })
      if (response.status === 204) {
        localStorage.clear()
        clearAuthSession({ emitLogout: false })
        await router.replace(HOME_ROUTE)
        window.scrollTo({ top: 0 })
        notifySuccess(copy.value.deletion.success)
        return
      }

      const data = await response.json().catch(() => ({}))
      if (response.status === 409 && data.can_delete === false) {
        deletionCheck.value = { ...deletionCheck.value, ...data }
        deletionDialog.value = false
        return
      }
      if (data.errors?.google) deletionCheck.value.google_confirmed = false
      deletionError.value = data.message || copy.value.deletion.error
    } catch {
      deletionError.value = copy.value.deletion.error
    } finally {
      deletionPassword.value = ''
      deletingAccount.value = false
    }
  }

  async function downloadInvoice (id) {
    try {
      const response = await apiFetch(`/api/rezervacijas/${id}/invoice`, { cache: 'no-store' })
      if (!response.ok) throw new Error(copy.value.invoice.error)
      const url = URL.createObjectURL(await response.blob())
      const link = document.createElement('a')
      link.href = url
      link.download = `EasyRent-${id}.html`
      link.click()
      setTimeout(() => URL.revokeObjectURL(url), 1000)
    } catch {
      notifyError(copy.value.invoice.error)
    }
  }

  // Ielādē klienta rezervācijas no API.
  async function loadReservations (klientsId) {
    if (isClient.value && !klientsId) return
    reservationsLoading.value = true
    reservationsError.value = ''
    try {
      const response = await apiFetch('/api/rezervacijas')
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
  async function payReservation (rezervacijaId) {
    if (!currentClientId.value || !rezervacijaId) return
    payingReservationId.value = rezervacijaId
    reservationsError.value = ''
    reservationsSuccess.value = ''
    try {
      const response = await apiFetch(`/api/rezervacijas/${rezervacijaId}/pay`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
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

  function openCancelDialog (rezervacijaId) {
    pendingCancelReservationId.value = rezervacijaId
    cancelDialog.value = true
  }

  // Atceļ izvēlēto rezervāciju un atjauno sarakstu.
  async function cancelReservation () {
    const rezervacijaId = pendingCancelReservationId.value
    if (!currentClientId.value || !rezervacijaId) return
    cancellingReservationId.value = rezervacijaId
    reservationsError.value = ''
    reservationsSuccess.value = ''
    try {
      const response = await apiFetch(`/api/rezervacijas/${rezervacijaId}`, {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
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
  function formatDateTime (value) {
    if (!value) return '—'
    const date = new Date(value)
    return date.toLocaleString(getIntlLocale())
  }

  function normalizeVehicleField (value) {
    if (value === null || value === undefined) return ''
    const normalized = String(value).trim()
    return normalized && normalized !== '-' ? normalized : ''
  }

  function reservationVehicleDetails (item) {
    const vehicle = item?.transportlidzeklis
    const snapshot = item?.transporta_snapshot
    if (!vehicle && !snapshot) return ''

    return [
      [copy.value.vehicleFields.type, typeLabel(snapshot ? snapshot.veids : vehicle?.veids?.nosaukums)],
      [copy.value.vehicleFields.gearbox, attributeLabel(normalizeVehicleField((snapshot || vehicle)?.atrumkarba))],
      [copy.value.vehicleFields.fuel, attributeLabel(normalizeVehicleField((snapshot || vehicle)?.degvielas_veids))],
      [copy.value.vehicleFields.registrationNumber, normalizeVehicleField(vehicle?.registracijas_numurs)],
    ].filter(([, value]) => value).map(([label, value]) => `${label}: ${value}`).join(' • ')
  }

  function reservationVehicleName (item) {
    const vehicle = item.transporta_snapshot || item.transportlidzeklis
    return [vehicle?.marka, vehicle?.modelis].filter(Boolean).join(' ') || t('vehicle.name')
  }

  function reservationBookedAt (item) {
    return formatDateTime(item?.izveides_datums || item?.rezervacijas_datums || item?.sakuma_laiks)
  }

  function reservationCounterpartyName (item) {
    if (isProvider.value) {
      if (item?.klients?.persona_deleted) return copy.value.deletion.deletedClient
      const clientPersona = item?.klients?.persona
      const fullName = `${clientPersona?.vards || ''} ${clientPersona?.uzvards || ''}`.trim()
      return fullName || copy.value.roles.client
    }

    const provider = item?.transportlidzeklis?.sniedzejs
    if (provider?.persona_deleted) return copy.value.deletion.deletedProvider
    const providerPersona = provider?.persona
    const fullName = `${providerPersona?.vards || ''} ${providerPersona?.uzvards || ''}`.trim()
    return fullName || copy.value.roles.provider
  }

  function buildFullAddress () {
    const parts = [
      form.value.iela,
      form.value.majas_numurs,
      form.value.pilseta,
      form.value.pasta_indekss,
      'Latvia',
    ].filter(part => part && String(part).trim().length > 0)
    return parts.join(', ')
  }

  function hasMinimalAddress () {
    return Boolean(form.value.iela && form.value.pilseta)
  }

  function isProviderAddressChanged () {
    return [
      ['iela', form.value.iela, initialProviderAddress.value.iela],
      ['majas_numurs', form.value.majas_numurs, initialProviderAddress.value.majas_numurs],
      ['dzivokla_numurs', form.value.dzivokla_numurs, initialProviderAddress.value.dzivokla_numurs],
      ['pilseta', form.value.pilseta, initialProviderAddress.value.pilseta],
      ['pasta_indekss', form.value.pasta_indekss, initialProviderAddress.value.pasta_indekss],
    ].some(([, current, original]) => String(current ?? '').trim() !== String(original ?? '').trim())
  }

  function areProviderCoordsChanged () {
    return [
      ['latitude', form.value.latitude, initialProviderCoords.value.latitude],
      ['longitude', form.value.longitude, initialProviderCoords.value.longitude],
    ].some(([, current, original]) => String(current ?? '').trim() !== String(original ?? '').trim())
  }

  async function geocodeCoordinates (address) {
    if (!address) return null
    const controller = new AbortController()
    const timeout = setTimeout(() => controller.abort(), 6000)
    geocodeLoading.value = true
    geocodeError.value = ''
    try {
      const url = `https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(address)}`
      const response = await fetch(url, { signal: controller.signal })
      const data = await response.json()
      if (!Array.isArray(data) || !data.length) return null
      const result = data[0]
      return {
        lat: result?.lat ? Number(result.lat) : null,
        lng: result?.lon ? Number(result.lon) : null,
      }
    } catch {
      return null
    } finally {
      clearTimeout(timeout)
      geocodeLoading.value = false
    }
  }

  // Nosūta profila izmaiņas serverim un atjauno lokālo lietotāju.
  async function updateProfile () {
    errorText.value = ''
    successText.value = ''
    fieldErrors.value = {}

    const valid = await formRef.value?.validate()
    if (!valid?.valid) {
      errorText.value = copy.value.editErrors.checkFields
      return
    }

    loading.value = true
    try {
      const user = persona.value
      const wasIncomplete = profileIncomplete.value

      if (!user?.persona_id) {
        throw new Error(copy.value.editErrors.missingUser)
      }

      // Build update payload with only changed fields
      const updateData = {}

      if (canEnterEmail.value) updateData.epasts = email.value
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
            geocodeError.value = copy.value.editErrors.geocodeAddress
            return
          } else {
            const address = buildFullAddress()
            const coords = await geocodeCoordinates(address)
            if (coords && coords.lat !== null && coords.lng !== null) {
              form.value.latitude = coords.lat
              form.value.longitude = coords.lng
              geocodeError.value = ''
            } else if (address && !wasIncomplete) {
              geocodeError.value = copy.value.editErrors.geocodeFailed
              return
            }
          }
        }
        if (form.value.latitude !== '') updateData.latitude = Number(form.value.latitude)
        if (form.value.longitude !== '') updateData.longitude = Number(form.value.longitude)
      }

      const response = await apiFetch(`/api/profile/${user.persona_id}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
        },
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
            fieldErrors.value[field] = errorMessages[0] || copy.value.editErrors.field
          })
          errorText.value = copy.value.editErrors.checkFields
          return
        }
        throw new Error(result?.message || t('common.networkError'))
      }

      if (!result?.persona) {
        throw new Error(copy.value.editErrors.invalidResponse)
      }

      persona.value = result.persona
      setAuthSession({ persona: result.persona })

      if (wasIncomplete && result.persona.profile_completed) {
        notifySuccess(copy.value.setup.completed)
        await router.replace(accountHome(result.persona))
        return
      }

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
      errorText.value = error?.message || t('common.networkError')
    } finally {
      loading.value = false
    }
  }

  // Notīra sesiju un pārvirza uz sākumlapu.
  async function logout () {
    if (!await logoutRequest()) {
      errorText.value = t('common.logoutError')
    }
  }
</script>

<style scoped>
.profile-bg {
  min-height: calc(100vh - 72px);
  background: var(--er-bg);
  color: var(--er-text);
}

.profile-shell {
  width: 100%;
  max-width: 1280px;
  padding-inline: 20px;
}

.profile-hero {
  padding: 48px 0;
  background: var(--er-night);
  color: var(--er-night-text);
}

.profile-heading {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 24px;
}

.profile-heading__main { min-width: 0; }

.profile-role {
  margin-bottom: 8px;
  color: var(--er-primary);
  font-size: .8rem;
  font-weight: 800;
  letter-spacing: .12em;
  text-transform: uppercase;
}

.profile-name {
  font-family: var(--er-display-font);
  font-size: clamp(2rem, 4vw, 3rem);
  font-weight: 900;
  line-height: 1.12;
  letter-spacing: -.035em;
  overflow-wrap: anywhere;
}

.profile-email {
  margin-top: 12px;
  color: var(--er-night-muted);
  overflow-wrap: anywhere;
}

.profile-hero__logout {
  border-color: var(--er-night-border);
  color: var(--er-night-text);
  flex-shrink: 0;
}

.profile-stat-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
  margin-top: 32px;
}

.metric-pill {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;
  min-width: 0;
  padding: 20px;
  border: 1px solid var(--er-night-border);
  border-radius: 16px;
  background: transparent;
}

.metric-pill strong {
  max-width: 100%;
  color: var(--er-primary);
  font-family: var(--er-display-font);
  font-size: clamp(1.3rem, 2.5vw, 1.8rem);
  font-weight: 900;
  line-height: 1.2;
  overflow-wrap: anywhere;
}

.metric-pill span {
  color: var(--er-night-muted);
  font-size: .75rem;
  line-height: 1.5;
}

.profile-content { padding-block: 48px; }

.profile-layout {
  display: grid;
  grid-template-columns: minmax(0, 420px) minmax(0, 1fr);
  align-items: start;
  gap: 32px;
}

.profile-layout--setup {
  grid-template-columns: minmax(0, 1fr);
  max-width: 760px;
  margin-inline: auto;
}

.profile-setup-notice :deep(.v-alert__content) { color: var(--er-text); }

.surface {
  min-width: 0;
  background: var(--er-surface);
  border: 1px solid var(--er-stroke);
  border-radius: 24px;
  box-shadow: var(--er-shadow-sm) !important;
  color: var(--er-text);
}

.surface :deep(.v-card-title) { white-space: normal; }
.surface :deep(.v-card-title .text-h5) {
  font-family: var(--er-display-font) !important;
  font-size: 1.5rem !important;
  font-weight: 800 !important;
  line-height: 1.25;
  letter-spacing: -.025em !important;
}

.surface :deep(.v-card-title .text-caption) {
  font-size: .85rem !important;
  line-height: 1.6;
  color: var(--er-text-muted);
  opacity: 1 !important;
}

.profile-details :deep(.v-card-text),
.profile-reservations :deep(.v-card-text) { color: var(--er-text); }

.profile-details :deep(.v-row--dense) { row-gap: 4px; }
.profile-details :deep(.v-field) { border-radius: 12px; }
.profile-details :deep(.v-input--density-compact) { --v-input-control-height: 48px; }
.profile-details :deep(.v-field__input) { min-height: 48px; }
.profile-details :deep(.v-label) { font-size: .85rem; }
.profile-details :deep(.v-divider) { opacity: .14; }

.section-title {
  color: var(--er-primary);
  font-family: var(--er-display-font);
  font-size: .9rem;
  font-weight: 800;
  letter-spacing: -.01em;
  line-height: 1.6;
}

.profile-reservations .section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--er-text);
  font-size: 1rem;
}

.reservation-section-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 24px;
  height: 24px;
  padding-inline: 6px;
  border-radius: 99px;
  background: var(--er-bg-soft);
  color: var(--er-text-muted);
  font-size: .72rem;
  font-family: var(--er-body-font);
}

.profile-state {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: 16px;
  min-height: 190px;
  padding: 32px 20px;
  border: 1px dashed var(--er-stroke);
  border-radius: 16px;
  background: var(--er-bg-soft);
  color: var(--er-text-muted);
  text-align: center;
  font-size: .9rem;
}

.reservation-empty {
  padding: 16px;
  border-radius: 12px;
  background: var(--er-bg-soft);
  color: var(--er-text-muted);
  font-size: .85rem;
  line-height: 1.6;
}

.reservation-card {
  overflow: visible;
  border: 1px solid var(--er-stroke);
  border-radius: 16px;
  background: var(--er-surface);
  box-shadow: none !important;
  color: var(--er-text);
}

.reservation-card-layout {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.reservation-card-main {
  min-width: 0;
  flex: 1 1 auto;
  overflow-wrap: anywhere;
}

.reservation-card-title {
  font-size: 1rem;
  line-height: 1.4;
  margin-bottom: 4px;
}

.reservation-card-meta {
  color: var(--er-text-muted);
  font-size: .75rem !important;
  line-height: 1.65;
  opacity: 1 !important;
}

.reservation-card-price {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 8px;
  flex: 0 0 auto;
  max-width: 100%;
  text-align: right;
}

.reservation-card-amount {
  font-family: var(--er-display-font);
  font-size: 1.1rem;
  font-weight: 800;
  letter-spacing: -.025em;
  line-height: 1.4;
  font-variant-numeric: tabular-nums;
  overflow-wrap: anywhere;
}

.reservation-card-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  padding-top: 12px;
  border-top: 1px solid var(--er-stroke);
}

.reservation-card-actions :deep(.v-btn) {
  height: auto;
  min-height: 36px;
  padding-block: 8px;
  border-radius: 99px;
}
.reservation-card-actions :deep(.v-btn__content) { white-space: normal; }
.reservation-card-unpaid { border-color: color-mix(in srgb, var(--er-primary) 40%, var(--er-stroke)); }
.reservation-card-history { background: var(--er-bg-soft); }
.reservation-history-pagination { max-width: 100%; }
.reservation-history-pagination :deep(.v-pagination__list) { flex-wrap: wrap; }
.reservation-history-pagination :deep(.v-btn) { color: var(--er-text); }

.gap-3 { gap: 8px !important; }
.gap-3 :deep(.v-btn) {
  width: 100%;
  min-width: 0 !important;
  height: auto;
  min-height: 44px;
  padding-block: 10px;
}
.gap-3 :deep(.v-btn__content) { white-space: normal; }
.profile-details :deep(.gap-3 .v-col-sm-auto) { flex: 1 1 100%; }
.logout-btn {
  background: var(--er-bg-soft);
  border: 1px solid var(--er-stroke);
  color: var(--er-text) !important;
}
.back-btn { border-color: var(--er-stroke) !important; color: var(--er-text); }

.profile-confirmation {
  border: 1px solid var(--er-stroke);
  border-radius: 24px !important;
  background: var(--er-surface);
  color: var(--er-text);
}
.profile-confirmation :deep(.v-card-title) {
  padding: 24px;
  white-space: normal;
  line-height: 1.4;
}
.profile-confirmation :deep(.v-card-text) { padding: 24px !important; line-height: 1.7; }
.profile-confirmation :deep(.v-card-actions) { flex-wrap: wrap; gap: 8px; padding: 0 24px 24px !important; }
.profile-confirmation :deep(.v-card-actions .v-btn) { height: auto; min-height: 40px; padding-block: 8px; }
.profile-confirmation :deep(.v-btn__content) { white-space: normal; }

@media (max-width: 959px) {
  .profile-layout { grid-template-columns: minmax(0, 1fr); gap: 24px; }
  .profile-content { padding-block: 32px; }
  .profile-details :deep(.gap-3 .v-col-sm-auto) { flex: 1 1 0; }
}

@media (max-width: 599px) {
  .profile-shell { padding-inline: 16px; }
  .profile-hero { padding-block: 32px; }
  .profile-stat-grid { grid-template-columns: 1fr; margin-top: 24px; }
  .metric-pill { padding: 16px; gap: 4px; }
  .metric-pill strong { font-size: 1.5rem; }
  .profile-heading { gap: 20px; }
  .reservation-card-layout { flex-direction: column; gap: 12px; }
  .reservation-card-price { flex-direction: row; flex-wrap: wrap; align-items: center; justify-content: space-between; text-align: left; width: 100%; }
  .reservation-card-actions { flex-direction: column; align-items: stretch; }
  .profile-details :deep(.gap-3 .v-col-sm-auto) { flex: 1 1 100%; }
  .surface :deep(.v-card-title), .surface > :deep(.v-card-text) { padding: 20px !important; }
}
</style>
