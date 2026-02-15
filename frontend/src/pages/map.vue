<template>
	<div class="map-page">
		<v-container fluid class="py-6">
			<div class="page-header">
				<div>
					<div class="text-h4 font-weight-bold">Karte un īres punkti</div>
					<div class="text-body-2 opacity-80">
						Skati punktus, filtrē transportu un veic rezervāciju tiešsaistē.
					</div>
				</div>
				<div class="d-flex align-center ga-2">
					<v-chip v-if="user" color="primary" variant="tonal">
						{{ roleLabel }}
					</v-chip>
					<v-btn variant="outlined" @click="goHome">
						<v-icon start>mdi-arrow-left</v-icon>
						Uz sākumu
					</v-btn>
				</div>
			</div>

			<v-row class="mt-2" dense>
				<v-col cols="12" lg="4">
					<v-card class="surface pa-4" elevation="10">
						<div class="text-subtitle-1 font-weight-bold mb-2">Filtri</div>
						<v-text-field
							v-model="filters.q"
							label="Meklēt pēc nosaukuma vai pilsētas"
							variant="outlined"
							density="compact"
						/>
						<v-select
							v-model="filters.companyId"
							:items="companyOptions"
							item-title="title"
							item-value="value"
							label="Kompānija"
							variant="outlined"
							density="compact"
							clearable
						/>
						<v-select
							v-model="filters.typeId"
							:items="typeOptions"
							item-title="title"
							item-value="value"
							label="Transporta veids"
							variant="outlined"
							density="compact"
							clearable
						/>
						<v-row dense>
							<v-col cols="6">
								<v-text-field
									v-model.number="filters.minPrice"
									label="Cena no (€)"
									variant="outlined"
									density="compact"
									type="number"
									min="0"
								/>
							</v-col>
							<v-col cols="6">
								<v-text-field
									v-model.number="filters.maxPrice"
									label="Cena līdz (€)"
									variant="outlined"
									density="compact"
									type="number"
									min="0"
								/>
							</v-col>
						</v-row>
						<v-switch
							v-model="filters.onlyAvailable"
							label="Rādīt tikai pieejamo"
							inset
							density="compact"
						/>
						<v-divider class="my-3" />
						<div class="text-subtitle-2 font-weight-bold mb-2">Rezervācijas laiks</div>
						<v-row dense>
							<v-col cols="7">
								<v-text-field
									v-model="reservation.date"
									label="Datums"
									variant="outlined"
									density="compact"
									type="date"
								/>
							</v-col>
							<v-col cols="5">
								<v-text-field
									v-model="reservation.time"
									label="Laiks"
									variant="outlined"
									density="compact"
									type="time"
								/>
							</v-col>
							<v-col cols="12">
								<v-text-field
									v-model.number="reservation.days"
									label="Dienu skaits"
									variant="outlined"
									density="compact"
									type="number"
									min="1"
								/>
							</v-col>
						</v-row>
					</v-card>

					<v-card class="surface mt-4" elevation="10">
						<div class="d-flex align-center justify-space-between pa-4">
							<div class="text-subtitle-1 font-weight-bold">Īres punkti</div>
							<v-chip size="small" color="primary" variant="tonal">{{ filteredPoints.length }}</v-chip>
						</div>
						<v-divider />
						<div class="list-wrap">
							<div v-if="loading" class="pa-4 text-body-2">Ielādē...</div>
							<div v-else-if="errorText" class="pa-4 text-error">{{ errorText }}</div>
							<div v-else-if="!filteredPoints.length" class="pa-4 text-body-2 opacity-70">Nav rezultātu.</div>
							<div v-else class="pa-4 d-flex flex-column ga-3">
								<v-card
									v-for="point in filteredPoints"
									:key="point.id"
									class="point-card"
									elevation="4"
									@click="selectPoint(point.id)"
								>
									<v-card-text class="pa-4">
										<div class="d-flex align-center justify-space-between">
											<div>
												<div class="text-subtitle-1 font-weight-bold">{{ point.name }}</div>
												<div class="text-caption opacity-70">{{ point.address }}</div>
												<div class="text-caption opacity-70">{{ point.city }}</div>
											</div>
											<v-chip size="small" :color="point.availableCount ? 'success' : 'grey'" variant="tonal">
												{{ point.availableCount }}/{{ point.vehicles.length }}
											</v-chip>
										</div>
										<div class="text-caption mt-2" v-if="!point.hasCoords">Nav koordināšu kartē</div>
									</v-card-text>
								</v-card>
							</div>
						</div>
					</v-card>

					<v-card v-if="isProvider" class="surface mt-4 pa-4" elevation="10">
						<div class="text-subtitle-1 font-weight-bold mb-2">Pievienot transportu</div>
						<v-alert v-if="providerError" type="error" variant="tonal" class="mb-3" density="compact">
							{{ providerError }}
						</v-alert>
						<v-alert v-if="providerSuccess" type="success" variant="tonal" class="mb-3" density="compact">
							{{ providerSuccess }}
						</v-alert>
						<v-form @submit.prevent="submitVehicle">
							<v-select
								v-model="newVehicle.veids_id"
								:items="typeOptions"
								item-title="title"
								item-value="value"
								label="Transporta veids"
								variant="outlined"
								density="compact"
								:disabled="typeOptions.length === 0"
							/>
							<v-text-field v-model="newVehicle.marka" label="Marka" variant="outlined" density="compact" />
							<v-text-field v-model="newVehicle.modelis" label="Modelis" variant="outlined" density="compact" />
							<v-text-field v-model="newVehicle.registracijas_numurs" label="Reģistrācijas nr." variant="outlined" density="compact" />
							<v-text-field v-model="newVehicle.adrese" label="Adrese" variant="outlined" density="compact" />
							<v-text-field v-model.number="newVehicle.dienas_nomas_cena" label="Cena (€ / diena)" type="number" min="0" variant="outlined" density="compact" />
							<v-select
								v-model="newVehicle.statuss"
								:items="statusOptions"
								label="Statuss"
								variant="outlined"
								density="compact"
							/>
							<v-btn type="submit" block color="primary" :loading="providerLoading" class="mt-2">
								Pievienot
							</v-btn>
						</v-form>
					</v-card>
				</v-col>

				<v-col cols="12" lg="8">
					<v-card class="surface map-card" elevation="10">
						<div id="map" class="map-canvas" />
						<div class="floating-actions">
							<v-btn size="small" variant="tonal" @click="locateUser">
								Man tuvumā
							</v-btn>
							<v-btn size="small" variant="tonal" @click="resetMap">
								Atstatīt
							</v-btn>
						</div>
					</v-card>

					<v-card v-if="selectedPoint" class="surface mt-4" elevation="10">
						<v-card-title class="d-flex align-center justify-space-between">
							<div>
								<div class="text-h6 font-weight-bold">{{ selectedPoint.name }}</div>
								<div class="text-caption opacity-70">{{ selectedPoint.address }} • {{ selectedPoint.city }}</div>
							</div>
							<v-btn icon variant="text" @click="selectedPointId = null" aria-label="Aizvērt">
								<v-icon>mdi-close</v-icon>
							</v-btn>
						</v-card-title>
						<v-divider />
						<v-card-text class="pa-4">
							<div v-if="!selectedPoint.vehicles.length" class="text-body-2 opacity-70">Nav transporta.</div>
							<div v-else class="d-flex flex-column ga-3">
								<div
									v-for="vehicle in selectedPoint.vehicles"
									:key="vehicle.transportlidzeklis_id"
									class="vehicle-row"
									:class="{ 'is-unavailable': !isVehicleAvailable(vehicle) }"
								>
									<div>
										<div class="font-weight-bold">
											{{ vehicle.marka }} {{ vehicle.modelis }}
										</div>
										<div class="text-caption opacity-70">
											{{ vehicle.veids?.nosaukums || '—' }} • {{ formatPrice(vehicle.dienas_nomas_cena) }} / dienā
										</div>
									</div>
									<div class="d-flex align-center ga-2">
										<v-chip
											size="small"
											:color="isVehicleAvailable(vehicle) ? 'success' : 'grey'"
											variant="tonal"
										>
											{{ isVehicleAvailable(vehicle) ? 'Pieejams' : 'Aizņemts' }}
										</v-chip>
										<v-btn
											v-if="isClient"
											size="small"
											color="primary"
											:disabled="!isVehicleAvailable(vehicle)"
											@click="openReservation(vehicle)"
										>
											Rezervēt
										</v-btn>
										<v-btn
											v-else-if="isProvider && isOwnVehicle(vehicle)"
											size="small"
											variant="tonal"
											@click="openEditVehicle(vehicle)"
										>
											Rediģēt
										</v-btn>
									</div>
								</div>
							</div>
						</v-card-text>
					</v-card>
				</v-col>
			</v-row>
		</v-container>

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
					<v-text-field v-model="reservation.date" label="Datums" type="date" variant="outlined" density="compact" />
					<v-text-field v-model="reservation.time" label="Laiks" type="time" variant="outlined" density="compact" />
					<v-text-field v-model.number="reservation.days" label="Dienu skaits" type="number" min="1" variant="outlined" density="compact" />
					<div class="text-body-2 mt-2">
						Summa: <strong>{{ reservationTotal }}</strong>
					</div>
					<v-alert v-if="reservationError" type="error" variant="tonal" class="mt-3" density="compact">
						{{ reservationError }}
					</v-alert>
				</v-card-text>
				<v-card-actions class="px-4 pb-4">
					<v-spacer />
					<v-btn variant="text" @click="reservationDialog = false">Atcelt</v-btn>
					<v-btn color="primary" :loading="reservationLoading" @click="createReservation">Rezervēt</v-btn>
				</v-card-actions>
			</v-card>
		</v-dialog>

		<v-dialog v-model="paymentDialog" max-width="480">
			<v-card>
				<v-card-title class="font-weight-bold">Apmaksa</v-card-title>
				<v-divider />
				<v-card-text class="pa-4">
					<div class="text-body-2">
						Vai tiešām vēlaties apmaksāt rezervāciju?
					</div>
					<div v-if="pendingReservation" class="mt-3 text-caption opacity-70">
						Rezervācijas summa: {{ formatPrice(pendingReservation.kopa_summa) }}
					</div>
					<v-alert v-if="paymentError" type="error" variant="tonal" class="mt-3" density="compact">
						{{ paymentError }}
					</v-alert>
					<v-alert v-if="paymentSuccess" type="success" variant="tonal" class="mt-3" density="compact">
						{{ paymentSuccess }}
					</v-alert>
				</v-card-text>
				<v-card-actions class="px-4 pb-4">
					<v-spacer />
					<v-btn variant="text" @click="paymentDialog = false">Aizvērt</v-btn>
					<v-btn color="primary" :loading="paymentLoading" @click="confirmPayment">Maksāt</v-btn>
				</v-card-actions>
			</v-card>
		</v-dialog>

		<v-dialog v-model="editDialog" max-width="520">
			<v-card>
				<v-card-title class="font-weight-bold">Rediģēt transportu</v-card-title>
				<v-divider />
				<v-card-text class="pa-4">
					<v-text-field v-model.number="editVehicle.dienas_nomas_cena" label="Cena (€ / diena)" type="number" min="0" variant="outlined" density="compact" />
					<v-select
						v-model="editVehicle.statuss"
						:items="statusOptions"
						label="Statuss"
						variant="outlined"
						density="compact"
					/>
					<v-alert v-if="editError" type="error" variant="tonal" class="mt-3" density="compact">
						{{ editError }}
					</v-alert>
				</v-card-text>
				<v-card-actions class="px-4 pb-4">
					<v-spacer />
					<v-btn variant="text" @click="editDialog = false">Atcelt</v-btn>
					<v-btn color="primary" :loading="editLoading" @click="saveVehicle">Saglabāt</v-btn>
				</v-card-actions>
			</v-card>
		</v-dialog>

		<v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3500">
			{{ snackbar.text }}
		</v-snackbar>
	</div>
</template>

<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const router = useRouter()
const API_BASE = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000'

const user = ref(null)
const transportItems = ref([])
const transportTypes = ref([])
const loading = ref(false)
const errorText = ref('')

const filters = ref({
	q: '',
	typeId: null,
	companyId: null,
	minPrice: null,
	maxPrice: null,
	onlyAvailable: false,
})

const reservation = ref({
	date: new Date().toISOString().slice(0, 10),
	time: '10:00',
	days: 1,
})

const reservationDialog = ref(false)
const reservationLoading = ref(false)
const reservationError = ref('')
const activeVehicle = ref(null)
const pendingReservation = ref(null)

const paymentDialog = ref(false)
const paymentLoading = ref(false)
const paymentError = ref('')
const paymentSuccess = ref('')

const providerLoading = ref(false)
const providerError = ref('')
const providerSuccess = ref('')

const editDialog = ref(false)
const editLoading = ref(false)
const editError = ref('')
const editVehicle = ref({})

const snackbar = ref({ show: false, text: '', color: 'error' })

const newVehicle = ref({
	veids_id: null,
	marka: '',
	modelis: '',
	registracijas_numurs: '',
	adrese: '',
	dienas_nomas_cena: null,
	statuss: 'pieejams',
})

const statusOptions = ['pieejams', 'aiznemts', 'neaktivs']

const roleLabel = computed(() => {
	if (!user.value) return 'Viesis'
	return user.value.loma === 'pakalpojumu_sniedzejs' ? 'Pakalpojumu sniedzējs' : 'Klients'
})

const isClient = computed(() => user.value?.loma === 'klients')
const isProvider = computed(() => user.value?.loma === 'pakalpojumu_sniedzejs')

const providerId = computed(() =>
	user.value?.pakalpojumuSniedzejs?.sniedzejs_id || user.value?.pakalpojumu_sniedzejs?.sniedzejs_id || null
)

const typeOptions = computed(() => transportTypes.value.map(type => ({ title: type.nosaukums, value: type.veids_id })))

const points = computed(() => {
	const map = new Map()
	transportItems.value.forEach(item => {
		const sniedzejs = item.sniedzejs
		if (!sniedzejs) return
		const id = sniedzejs.sniedzejs_id
		if (!map.has(id)) {
			const persona = sniedzejs.persona
			const name = persona ? `${persona.vards} ${persona.uzvards}`.trim() : `Pakalpojumu sniedzējs #${id}`
			const address = [sniedzejs.iela, sniedzejs.majas_numurs, sniedzejs.dzivokla_numurs].filter(Boolean).join(' ')
			map.set(id, {
				id,
				name,
				address: address || 'Nav norādīta adrese',
				city: sniedzejs.pilseta || 'Nav norādīta pilsēta',
				lat: sniedzejs.latitude !== null && sniedzejs.latitude !== undefined ? Number(sniedzejs.latitude) : null,
				lng: sniedzejs.longitude !== null && sniedzejs.longitude !== undefined ? Number(sniedzejs.longitude) : null,
				hasCoords: sniedzejs.latitude !== null && sniedzejs.latitude !== undefined && sniedzejs.longitude !== null && sniedzejs.longitude !== undefined,
				vehicles: [],
			})
		}
		map.get(id).vehicles.push(item)
	})

	return Array.from(map.values()).map(point => ({
		...point,
		availableCount: point.vehicles.filter(v => isVehicleAvailable(v)).length,
	}))
})

const companyOptions = computed(() => points.value.map(point => ({ title: point.name, value: point.id })))

const filteredPoints = computed(() => {
	return points.value.filter(point => {
		if (filters.value.companyId && point.id !== filters.value.companyId) return false
		if (filters.value.q) {
			const q = filters.value.q.toLowerCase()
			if (!point.name.toLowerCase().includes(q) && !point.city.toLowerCase().includes(q)) return false
		}
		const vehicles = point.vehicles.filter(vehicle => passesVehicleFilters(vehicle))
		if (!vehicles.length) return false
		return true
	}).map(point => ({
		...point,
		vehicles: point.vehicles.filter(vehicle => passesVehicleFilters(vehicle)),
		availableCount: point.vehicles.filter(vehicle => isVehicleAvailable(vehicle) && passesVehicleFilters(vehicle)).length,
	}))
})

const selectedPointId = ref(null)
const selectedPoint = computed(() => filteredPoints.value.find(point => point.id === selectedPointId.value) || null)

const reservationTotal = computed(() => {
	if (!activeVehicle.value) return '0 €'
	const days = Math.max(1, Number(reservation.value.days || 1))
	const sum = days * Number(activeVehicle.value.dienas_nomas_cena || 0)
	return formatPrice(sum)
})

let mapInstance = null
let markers = []

function goHome() {
	router.push('/')
}

function formatPrice(value) {
	const num = Number(value || 0)
	return `${num.toFixed(2)} €`
}

function getReservationRange() {
	const date = reservation.value.date || new Date().toISOString().slice(0, 10)
	const time = reservation.value.time || '10:00'
	const start = new Date(`${date}T${time}:00`)
	const days = Math.max(1, Number(reservation.value.days || 1))
	const end = new Date(start)
	end.setDate(end.getDate() + days)
	return { start, end }
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

function passesVehicleFilters(vehicle) {
	if (filters.value.typeId && vehicle.veids_id !== filters.value.typeId) return false
	if (filters.value.minPrice !== null && Number(vehicle.dienas_nomas_cena) < Number(filters.value.minPrice)) return false
	if (filters.value.maxPrice !== null && Number(vehicle.dienas_nomas_cena) > Number(filters.value.maxPrice)) return false
	if (filters.value.onlyAvailable && !isVehicleAvailable(vehicle)) return false
	return true
}

function selectPoint(id) {
	selectedPointId.value = id
}

function isOwnVehicle(vehicle) {
	return providerId.value && vehicle.sniedzejs_id === providerId.value
}

function openReservation(vehicle) {
	if (!user.value) {
		snackbar.value = { show: true, text: 'Lūdzu, pieslēdzieties, lai rezervētu.', color: 'error' }
		router.push('/auth')
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

async function createReservation() {
	if (!activeVehicle.value || !user.value?.klients?.klients_id) {
		reservationError.value = 'Nav klienta datu.'
		return
	}

	reservationLoading.value = true
	reservationError.value = ''
	paymentError.value = ''
	paymentSuccess.value = ''

	try {
		const { start, end } = getReservationRange()
		const response = await fetch(`${API_BASE}/api/rezervacijas`, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({
				klients_id: user.value.klients.klients_id,
				transportlidzeklis_id: activeVehicle.value.transportlidzeklis_id,
				sakuma_laiks: start.toISOString(),
				beigu_laiks: end.toISOString(),
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
	} catch (error) {
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
		await loadTransport()
	} catch (error) {
		paymentError.value = 'Kļūda: Neizdevās apstrādāt maksājumu.'
	} finally {
		paymentLoading.value = false
	}
}

function openEditVehicle(vehicle) {
	editVehicle.value = {
		transportlidzeklis_id: vehicle.transportlidzeklis_id,
		dienas_nomas_cena: vehicle.dienas_nomas_cena,
		statuss: vehicle.statuss,
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
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({
				sniedzejs_id: providerId.value,
				dienas_nomas_cena: editVehicle.value.dienas_nomas_cena,
				statuss: editVehicle.value.statuss,
			}),
		})
		const result = await response.json()
		if (!response.ok) {
			editError.value = result?.message || 'Neizdevās saglabāt.'
			return
		}
		editDialog.value = false
		await loadTransport()
	} catch (error) {
		editError.value = 'Kļūda: Neizdevās saglabāt.'
	} finally {
		editLoading.value = false
	}
}

async function submitVehicle() {
	providerLoading.value = true
	providerError.value = ''
	providerSuccess.value = ''

	if (!providerId.value) {
		providerError.value = 'Nav pieejami pakalpojumu sniedzēja dati.'
		providerLoading.value = false
		return
	}

	try {
		const payload = {
			sniedzejs_id: providerId.value,
			veids_id: newVehicle.value.veids_id,
			marka: newVehicle.value.marka,
			modelis: newVehicle.value.modelis,
			registracijas_numurs: newVehicle.value.registracijas_numurs,
			adrese: newVehicle.value.adrese,
			dienas_nomas_cena: newVehicle.value.dienas_nomas_cena,
			statuss: newVehicle.value.statuss,
		}

		const response = await fetch(`${API_BASE}/api/transport`, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(payload),
		})

		const result = await response.json()
		if (!response.ok) {
			providerError.value = result?.message || 'Neizdevās pievienot transportu.'
			return
		}

		providerSuccess.value = 'Transports pievienots!'
		newVehicle.value = {
			veids_id: null,
			marka: '',
			modelis: '',
			registracijas_numurs: '',
			adrese: '',
			dienas_nomas_cena: null,
			statuss: 'pieejams',
		}
		await loadTransport()
	} catch (error) {
		providerError.value = 'Kļūda: Neizdevās pievienot transportu.'
	} finally {
		providerLoading.value = false
	}
}

async function loadTransport() {
	loading.value = true
	errorText.value = ''
	try {
		const response = await fetch(`${API_BASE}/api/transport`)
		const data = await response.json()
		if (!response.ok) {
			errorText.value = data?.message || 'Neizdevās ielādēt transportu.'
			return
		}
		transportItems.value = data
	} catch (error) {
		errorText.value = 'Kļūda: Neizdevās ielādēt transportu.'
	} finally {
		loading.value = false
	}
}

async function loadTypes() {
	try {
		const response = await fetch(`${API_BASE}/api/transport/veidi`)
		const data = await response.json()
		if (response.ok) {
			transportTypes.value = data
		}
	} catch {
		transportTypes.value = []
	}
}

function initMap() {
	if (mapInstance) return
	mapInstance = L.map('map', { zoomControl: true }).setView([56.8796, 24.6032], 7)
	L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
		maxZoom: 19,
		attribution: '&copy; OpenStreetMap',
	}).addTo(mapInstance)
}

function renderMarkers() {
	if (!mapInstance) return
	markers.forEach(marker => mapInstance.removeLayer(marker))
	markers = []

	filteredPoints.value.forEach(point => {
		if (!point.hasCoords) return
		const marker = L.marker([point.lat, point.lng], {
			icon: L.divIcon({
				className: 'marker-dot',
				html: '<span></span>',
			}),
		}).addTo(mapInstance)
		marker.on('click', () => selectPoint(point.id))
		markers.push(marker)
	})
}

function locateUser() {
	if (!mapInstance || !navigator.geolocation) return
	navigator.geolocation.getCurrentPosition(position => {
		mapInstance.setView([position.coords.latitude, position.coords.longitude], 12)
	})
}

function resetMap() {
	if (!mapInstance) return
	mapInstance.setView([56.8796, 24.6032], 7)
}

onMounted(async () => {
	const userStr = localStorage.getItem('user')
	if (userStr) user.value = JSON.parse(userStr)
	await loadTypes()
	await loadTransport()
	await nextTick()
	initMap()
	renderMarkers()
})

watch(filteredPoints, () => {
	nextTick().then(renderMarkers)
})
</script>

<style scoped>
.map-page {
	min-height: calc(100vh - 72px - 64px);
	background: radial-gradient(1200px circle at 10% 10%, rgba(255,255,255,0.12), transparent 45%),
							radial-gradient(900px circle at 90% 20%, rgba(255,255,255,0.1), transparent 40%),
							linear-gradient(135deg, #0f172a, #111827, #0b1020);
	color: #fff;
}

.page-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
	flex-wrap: wrap;
}

.surface {
	background: rgba(255, 255, 255, 0.94);
	border: 1px solid rgba(15, 23, 42, 0.08);
	color: #0f172a;
	backdrop-filter: blur(6px);
}

.map-card {
	position: relative;
	overflow: hidden;
	min-height: 420px;
}

.map-canvas {
	height: 420px;
	width: 100%;
}

.floating-actions {
	position: absolute;
	top: 16px;
	left: 16px;
	display: flex;
	gap: 8px;
	z-index: 400;
}

.list-wrap {
	max-height: 440px;
	overflow-y: auto;
}

.point-card {
	cursor: pointer;
	border: 1px solid rgba(15, 23, 42, 0.12);
}

.point-card:hover {
	box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
}

.vehicle-row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	padding: 12px;
	border-radius: 12px;
	border: 1px solid rgba(15, 23, 42, 0.12);
	background: rgba(15, 23, 42, 0.04);
}

.vehicle-row.is-unavailable {
	opacity: 0.6;
	text-decoration: line-through;
}

.marker-dot span {
	display: block;
	width: 18px;
	height: 18px;
	border-radius: 6px;
	background: #3b82f6;
	border: 2px solid #0f172a;
	box-shadow: 0 4px 10px rgba(15, 23, 42, 0.35);
}
</style>
