<template>
	<div class="map-page">
		<div id="map" class="map-canvas"></div>

		<v-btn class="map-menu-btn" icon size="large" color="primary" variant="elevated" @click="drawer = true">
			<v-icon>mdi-menu</v-icon>
		</v-btn>

		<div class="floating-actions">
			<v-btn size="small" class="action-btn" @click="locateUser">
				Man tuvumā
			</v-btn>
			<v-btn size="small" class="action-btn" @click="resetMap">
				Atstatīt
			</v-btn>
		</div>

		<v-navigation-drawer v-model="drawer" temporary location="left" width="360" class="map-drawer">
			<div class="drawer-header">
				<div class="drawer-header-main">
					<div class="text-h6 font-weight-bold header-title">Karte un īres punkti</div>
					<div class="text-caption header-subtitle">Filtri, punkti un rezervācijas vienuviet.</div>
					<v-chip v-if="user" color="primary" size="small" variant="tonal" class="mt-2 text-black">
						{{ roleLabel }}
					</v-chip>
				</div>
				<v-btn class="header-close-btn" icon variant="text" @click="drawer = false" aria-label="Aizvērt">
					<v-icon>mdi-close</v-icon>
				</v-btn>
			</div>

			<v-divider />

			<div class="drawer-scroll">
				<v-expansion-panels v-model="expandedPanels" multiple variant="accordion" class="drawer-panels">
					<v-expansion-panel value="filters" class="surface">
						<v-expansion-panel-title>
							<div class="text-subtitle-1 font-weight-bold">Filtri</div>
						</v-expansion-panel-title>
						<v-expansion-panel-text>
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
						</v-expansion-panel-text>
					</v-expansion-panel>

					<v-expansion-panel value="points" class="surface mt-4">
						<v-expansion-panel-title>
							<div class="d-flex align-center justify-space-between w-100 pr-2">
								<div class="text-subtitle-1 font-weight-bold">Īres punkti</div>
								<v-chip size="small" color="primary" variant="tonal">{{ filteredPoints.length }}</v-chip>
							</div>
						</v-expansion-panel-title>
						<v-expansion-panel-text>
							<div class="list-wrap">
								<div v-if="loading" class="pa-2 text-body-2">Ielādē...</div>
								<div v-else-if="errorText" class="pa-2 text-error">{{ errorText }}</div>
								<div v-else-if="!filteredPoints.length" class="pa-2 text-body-2 opacity-70">Nav rezultātu.</div>
								<div v-else class="pa-2 d-flex flex-column ga-3">
									<v-card
										v-for="point in filteredPoints"
										:key="point.id"
										class="point-card"
										elevation="4"
										@click="selectPoint(point.id)"
									>
										<v-card-text class="pa-4">
											<div class="point-card-header d-flex align-center justify-space-between">
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

							<v-card v-if="selectedPoint" class="surface mt-4" elevation="10">
								<v-card-title class="selected-point-title d-flex align-center justify-space-between">
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
											:class="{
												'is-available': isVehicleAvailable(vehicle),
												'is-unavailable': !isVehicleAvailable(vehicle) && vehicle.statuss !== 'neaktivs',
												'is-inactive': vehicle.statuss === 'neaktivs',
											}"
										>
											<div class="vehicle-main">
												<div class="font-weight-bold">
													{{ vehicle.marka }} {{ vehicle.modelis }}
												</div>
												<div class="text-caption opacity-70">
													{{ vehicle.veids?.nosaukums || '—' }} • {{ formatPrice(vehicle.dienas_nomas_cena) }} / dienā
												</div>
											</div>
											<div class="vehicle-actions d-flex align-center ga-2">
												<v-chip
													size="small"
													:color="isVehicleAvailable(vehicle) ? 'success' : 'error'"
													:class="{ 'status-chip-unavailable': !isVehicleAvailable(vehicle) }"
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
						</v-expansion-panel-text>
					</v-expansion-panel>

					<v-expansion-panel v-if="isProvider" value="add-vehicle" class="surface mt-4">
						<v-expansion-panel-title>
							<div class="text-subtitle-1 font-weight-bold">Pievienot transportu</div>
						</v-expansion-panel-title>
						<v-expansion-panel-text>
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
								/>
								<v-text-field v-model="newVehicle.marka" label="Marka" variant="outlined" density="compact" />
								<v-text-field v-model="newVehicle.modelis" label="Modelis" variant="outlined" density="compact" />
								<v-select
									v-model="newVehicle.atrumkarba"
									:items="gearboxOptions"
									label="Ātrumkārba"
									variant="outlined"
									density="compact"
								/>
								<v-select
									v-model="newVehicle.degvielas_veids"
									:items="fuelOptions"
									label="Degvielas veids"
									variant="outlined"
									density="compact"
								/>
								<v-text-field
									v-model.number="newVehicle.dienas_nomas_cena"
									label="Dienas nomas cena"
									type="number"
									min="0"
									variant="outlined"
									density="compact"
								/>
								<v-select
									v-model="newVehicle.statuss"
									:items="statusOptions"
									label="Statuss"
									variant="outlined"
									density="compact"
								/>
								<v-text-field
									v-model="newVehicle.registracijas_numurs"
									label="Reģistrācijas numurs"
									variant="outlined"
									density="compact"
								/>
								<v-btn type="submit" block color="primary" :loading="providerLoading" class="mt-2">
									Pievienot
								</v-btn>
							</v-form>
						</v-expansion-panel-text>
					</v-expansion-panel>
				</v-expansion-panels>
			</div>
		</v-navigation-drawer>

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
					<div class="text-body-2 mb-2">Izvēlieties sākuma datumu, laiku un nomas ilgumu.</div>
					<v-row dense>
						<v-col cols="7">
							<v-text-field v-model="reservation.date" label="Sākuma datums" type="date" variant="outlined" density="compact" />
						</v-col>
						<v-col cols="5">
							<v-text-field v-model="reservation.time" label="Sākuma laiks" type="time" variant="outlined" density="compact" />
						</v-col>
						<v-col cols="12">
							<v-text-field v-model.number="reservation.days" label="Dienu skaits" type="number" min="1" variant="outlined" density="compact" />
						</v-col>
					</v-row>

					<div class="reservation-summary mt-2">
						<div><strong>Periods:</strong> {{ reservationPeriodText }}</div>
						<div><strong>Cena par dienu:</strong> {{ activeVehicle ? formatPrice(activeVehicle.dienas_nomas_cena) : '0.00 €' }}</div>
						<div><strong>Kopā:</strong> {{ reservationTotal }}</div>
					</div>
					<v-alert v-if="reservationError" type="error" variant="tonal" class="mt-3" density="compact">
						{{ reservationError }}
					</v-alert>
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
					<v-btn variant="text" @click="closePaymentDialog">Aizvērt</v-btn>
					<v-btn color="primary" :loading="paymentLoading" @click="confirmPayment">Maksāt</v-btn>
				</v-card-actions>
			</v-card>
		</v-dialog>

		<v-dialog v-model="editDialog" max-width="520">
			<v-card>
				<v-card-title class="font-weight-bold">Rediģēt transportu</v-card-title>
				<v-divider />
				<v-card-text class="pa-4">
					<v-select
						v-model="editVehicle.veids_id"
						:items="typeOptions"
						item-title="title"
						item-value="value"
						label="Transporta veids"
						variant="outlined"
						density="compact"
					/>
					<v-text-field v-model="editVehicle.marka" label="Marka" variant="outlined" density="compact" />
					<v-text-field v-model="editVehicle.modelis" label="Modelis" variant="outlined" density="compact" />
					<v-select
						v-model="editVehicle.atrumkarba"
						:items="gearboxOptions"
						label="Ātrumkārba"
						variant="outlined"
						density="compact"
					/>
					<v-select
						v-model="editVehicle.degvielas_veids"
						:items="fuelOptions"
						label="Degvielas veids"
						variant="outlined"
						density="compact"
					/>
					<v-text-field v-model.number="editVehicle.dienas_nomas_cena" label="Cena (€ / diena)" type="number" min="0" variant="outlined" density="compact" />
					<v-select
						v-model="editVehicle.statuss"
						:items="statusOptions"
						label="Statuss"
						variant="outlined"
						density="compact"
					/>
					<v-text-field
						v-model="editVehicle.registracijas_numurs"
						label="Reģistrācijas numurs"
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
const drawer = ref(false)
const expandedPanels = ref([])

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
	atrumkarba: '-',
	degvielas_veids: '-',
	registracijas_numurs: '',
	dienas_nomas_cena: null,
	statuss: 'pieejams',
})

const statusOptions = ['pieejams', 'aiznemts', 'neaktivs']
const gearboxOptions = ['Automāts', 'Mehānika', '-']
const fuelOptions = ['Benzīns', 'Dīzelis', 'Elektro', '-']

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

function toValidCoordinate(value, type) {
	if (value === null || value === undefined || value === '') return null
	const num = Number(value)
	if (!Number.isFinite(num)) return null
	if (type === 'lat' && (num < -90 || num > 90)) return null
	if (type === 'lng' && (num < -180 || num > 180)) return null
	return num
}

function getPointFallbackCoords(pointId) {
	const baseLat = 56.9496
	const baseLng = 24.1052
	const id = Number(pointId) || 1
	const offset = ((id % 25) - 12) * 0.004
	return {
		lat: baseLat + offset,
		lng: baseLng - offset,
	}
}

const points = computed(() => {
	const map = new Map()
	transportItems.value.forEach(item => {
		const sniedzejs = item.sniedzejs
		if (!sniedzejs) return
		const id = sniedzejs.sniedzejs_id
		if (!map.has(id)) {
			const lat = toValidCoordinate(sniedzejs.latitude, 'lat')
			const lng = toValidCoordinate(sniedzejs.longitude, 'lng')
			const persona = sniedzejs.persona
			const name = persona ? `${persona.vards} ${persona.uzvards}`.trim() : `Pakalpojumu sniedzējs #${id}`
			const address = [sniedzejs.iela, sniedzejs.majas_numurs, sniedzejs.dzivokla_numurs].filter(Boolean).join(' ')
			map.set(id, {
				id,
				name,
				address: address || 'Nav norādīta adrese',
				city: sniedzejs.pilseta || 'Nav norādīta pilsēta',
				lat,
				lng,
				hasCoords: lat !== null && lng !== null,
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
const pointCoordsOverride = ref({})

const mappablePoints = computed(() => {
	return points.value.map(point => {
		const fallback = pointCoordsOverride.value[point.id]
		const synthetic = getPointFallbackCoords(point.id)
		const lat = point.hasCoords ? point.lat : fallback?.lat ?? synthetic.lat
		const lng = point.hasCoords ? point.lng : fallback?.lng ?? synthetic.lng
		const hasCoords = Number.isFinite(lat) && Number.isFinite(lng)

		return {
			...point,
			lat,
			lng,
			hasCoords,
		}
	})
})

const reservationTotal = computed(() => {
	if (!activeVehicle.value) return '0 €'
	const days = Math.max(1, Number(reservation.value.days || 1))
	const sum = days * Number(activeVehicle.value.dienas_nomas_cena || 0)
	return formatPrice(sum)
})

const isReservationFormValid = computed(() => {
	const days = Number(reservation.value.days)
	if (!reservation.value.date || !reservation.value.time) return false
	if (!Number.isFinite(days) || days < 1) return false

	const start = new Date(`${reservation.value.date}T${reservation.value.time}:00`)
	return !Number.isNaN(start.getTime())
})

const reservationPeriodText = computed(() => {
	if (!isReservationFormValid.value) return 'Norādiet derīgu datumu, laiku un dienu skaitu.'
	const { start, end } = getReservationRange()
	return `${formatDateTime(start)} → ${formatDateTime(end)}`
})

let mapInstance = null
let markers = []

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
	}).format(date)
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

	if (!isReservationFormValid.value) {
		reservationError.value = 'Lūdzu, aizpildiet korektu datumu, laiku un dienu skaitu.'
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

function closePaymentDialog() {
	paymentDialog.value = false
	if (pendingReservation.value?.apmaksas_statuss !== 'apmaksata') {
		snackbar.value = {
			show: true,
			text: 'Rezervācija ir izveidota, bet neapmaksāta. To var apmaksāt Profilā.',
			color: 'warning',
		}
	}
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
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({
				sniedzejs_id: providerId.value,
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
			atrumkarba: newVehicle.value.atrumkarba || null,
			degvielas_veids: newVehicle.value.degvielas_veids || null,
			registracijas_numurs: newVehicle.value.registracijas_numurs,
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
			atrumkarba: '-',
			degvielas_veids: '-',
			registracijas_numurs: '',
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
	mapInstance = L.map('map', { zoomControl: false }).setView([56.8796, 24.6032], 7)
	L.control.zoom({ position: 'bottomright' }).addTo(mapInstance)
	L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
		maxZoom: 19,
		attribution: '&copy; OpenStreetMap',
	}).addTo(mapInstance)
}

function renderMarkers() {
	if (!mapInstance) return
	markers.forEach(marker => mapInstance.removeLayer(marker))
	markers = []

	mappablePoints.value.forEach(point => {
		if (!point.hasCoords) return

		const marker = L.marker([point.lat, point.lng], {
			icon: L.divIcon({
				className: 'marker-point',
				html: '<span></span>',
			}),
		}).addTo(mapInstance)

		marker.bindPopup(`
			<div>
				<strong>${point.name}</strong><br/>
				${point.address}, ${point.city}<br/>
				Transporti: ${point.vehicles.length} (${point.availableCount} pieejami)
			</div>
		`)

		marker.on('click', () => selectPoint(point.id))
		markers.push(marker)
	})
}

async function resolveMissingPointCoords() {
	const toResolve = points.value.filter(point => !point.hasCoords && !pointCoordsOverride.value[point.id])

	for (const point of toResolve) {
		if (!point.address || point.address === 'Nav norādīta adrese') continue
		if (!point.city || point.city === 'Nav norādīta pilsēta') continue

		try {
			const query = encodeURIComponent(`${point.address}, ${point.city}, Latvia`)
			const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${query}`)
			if (!response.ok) continue
			const result = await response.json()
			const first = result?.[0]
			if (!first) continue

			pointCoordsOverride.value = {
				...pointCoordsOverride.value,
				[point.id]: {
					lat: Number(first.lat),
					lng: Number(first.lon),
				},
			}
		} catch {
			continue
		}
	}
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
	await resolveMissingPointCoords()
	await nextTick()
	initMap()
	renderMarkers()
})

watch(points, async () => {
	await resolveMissingPointCoords()
	nextTick().then(renderMarkers)
})

watch(drawer, async () => {
	await nextTick()
	mapInstance?.invalidateSize()
})
</script>

<style scoped>
.map-page {
	position: relative;
	height: calc(100vh - 72px);
	width: 100%;
	background: #0f172a;
}

.surface {
	background: rgba(255, 255, 255, 0.94);
	border: 1px solid rgba(15, 23, 42, 0.08);
	color: #0f172a;
	backdrop-filter: blur(6px);
}

.map-canvas {
	height: 100%;
	width: 100%;
}

.map-menu-btn {
	position: absolute;
	top: 16px;
	left: 16px;
	z-index: 500;
}

.floating-actions {
	position: absolute;
	bottom: 32px;
	right: 16px;
	display: flex;
	gap: 8px;
	z-index: 400;
}

.action-btn {
	background: #0b1020;
	color: #f8fafc;
}

.action-btn:hover {
	background: #0f172a;
}


:deep(.leaflet-control-zoom) {
	top: auto;
	bottom: 46px;
	right: 16px;
	left: auto;
}

:deep(.leaflet-top.leaflet-right) {
	top: auto;
	bottom: 46px;
}

.map-drawer {
	background: rgba(255, 255, 255, 0.98);
}

.drawer-header {
	padding: 16px;
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 12px;
	position: relative;
	text-align: center;
}

.drawer-header-main {
	width: 100%;
}

.header-title,
.header-subtitle {
	color: #000;
}

.header-close-btn {
	position: absolute;
	right: 8px;
	top: 8px;
}

.drawer-scroll {
	padding: 16px;
	overflow-y: auto;
	max-height: calc(100vh - 72px - 120px);
}

.drawer-panels,
.drawer-panels :deep(.v-expansion-panel-text__wrapper) {
	width: 100%;
	box-sizing: border-box;
}

.drawer-panels :deep(.v-expansion-panel-title__overlay),
.drawer-panels :deep(.v-expansion-panel-title) {
	border-radius: 12px;
}

.list-wrap {
	max-height: 260px;
	overflow-y: auto;
}

.point-card {
	cursor: pointer;
	border: 1px solid rgba(15, 23, 42, 0.12);
	max-width: 100%;
	overflow: hidden;
}

.point-card:hover {
	box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
}

.point-card-header {
	justify-content: center;
	text-align: center;
	gap: 10px;
	flex-wrap: wrap;
}

.selected-point-title {
	justify-content: center;
	text-align: center;
	position: relative;
	padding-right: 44px;
	word-break: break-word;
}

.selected-point-title :deep(.v-btn) {
	position: absolute;
	right: 8px;
	top: 8px;
}

.vehicle-row {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	text-align: center;
	gap: 12px;
	padding: 12px;
	border-radius: 12px;
	border: 1px solid rgba(15, 23, 42, 0.12);
	background: rgba(15, 23, 42, 0.04);
	max-width: 100%;
	overflow: hidden;
}

.vehicle-row.is-available {
	border-color: rgba(22, 163, 74, 0.65);
	box-shadow: inset 0 0 0 1px rgba(22, 163, 74, 0.35);
}

.vehicle-row.is-unavailable {
	border-color: #dc2626;
	box-shadow: inset 0 0 0 1px #dc2626;
}

.vehicle-row.is-inactive {
	opacity: 0.7;
	text-decoration: line-through;
	border-color: rgba(71, 85, 105, 0.45);
	background: rgba(100, 116, 139, 0.08);
}

.vehicle-main,
.vehicle-actions {
	width: 100%;
	justify-content: center;
	flex-wrap: wrap;
}

.status-chip-unavailable {
	border: 1px solid #dc2626 !important;
	background: #fee2e2 !important;
}

.status-chip-unavailable :deep(.v-chip__content) {
	color: #b91c1c !important;
	font-weight: 600;
}

.reservation-summary {
	padding: 10px 12px;
	border-radius: 10px;
	border: 1px solid rgba(15, 23, 42, 0.12);
	background: rgba(15, 23, 42, 0.04);
	display: flex;
	flex-direction: column;
	gap: 4px;
	font-size: 0.9rem;
}

.drawer-scroll :deep(.v-input),
.drawer-scroll :deep(.v-field),
.drawer-scroll :deep(.v-btn),
.drawer-scroll :deep(.v-card) {
	max-width: 100%;
	box-sizing: border-box;
}

:deep(.marker-point span) {
	display: block;
	width: 18px;
	height: 18px;
	border-radius: 6px;
	background: #2563eb;
	border: 2px solid #0f172a;
	box-shadow: 0 4px 10px rgba(15, 23, 42, 0.35);
}
</style>
