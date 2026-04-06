<template>
	<div class="map-page">
		<div id="map" class="map-canvas"></div>

		<v-btn class="map-menu-btn" icon size="large" color="primary" variant="elevated" @click="drawer = true">
			<v-icon>mdi-menu</v-icon>
		</v-btn>

		<v-btn
			class="map-hud-toggle"
			icon
			size="large"
			variant="elevated"
			:aria-label="hudVisible ? 'Paslēpt kartes informāciju' : 'Parādīt kartes informāciju'"
			@click="toggleHud"
		>
			<v-icon>{{ hudVisible ? 'mdi-eye-off-outline' : 'mdi-information-outline' }}</v-icon>
		</v-btn>

		<v-expand-transition>
			<div v-show="hudVisible" class="map-hud">
				<div class="map-hud__head">
					<div class="soft-eyebrow map-hud__eyebrow">EasyRent Live karte</div>
					<v-btn
						icon
						variant="text"
						size="small"
						class="map-hud__close"
						aria-label="Paslēpt kartes informāciju"
						@click="toggleHud"
					>
						<v-icon>mdi-close</v-icon>
					</v-btn>
				</div>
				<div class="map-hud__title">Atrodi tuvāko un piemērotāko nomas punktu.</div>
				<div class="map-hud__copy">Filtrē pēc cenas, veida un pieejamības, pēc tam rezervē tieši no kartes.</div>
				<div class="map-hud__stats">
					<div class="map-hud__stat">
						<strong>{{ filteredPoints.length }}</strong>
						<span>Punkti</span>
					</div>
					<div class="map-hud__stat">
						<strong>{{ transportItems.length }}</strong>
						<span>Transporti</span>
					</div>
					<div class="map-hud__stat">
						<strong>{{ selectedPoint ? selectedPoint.availableCount : filteredPoints.reduce((sum, point) => sum + point.availableCount, 0) }}</strong>
						<span>Pieejami</span>
					</div>
				</div>
			</div>
		</v-expand-transition>

		<div class="floating-actions">
			<v-btn size="small" class="action-btn" @click="locateUser">
				Man tuvumā
			</v-btn>
			<v-btn size="small" class="action-btn" @click="resetMap">
				Atiestatīt
			</v-btn>
		</div>

		<v-navigation-drawer v-model="drawer" temporary location="left" width="360" class="map-drawer">
			<div class="drawer-header">
				<div class="drawer-header-main">
					<div class="text-h6 font-weight-bold header-title">Karte un īres punkti</div>
					<div class="text-caption header-subtitle">Filtri, punkti un rezervācijas vienuviet.</div>
					<v-chip v-if="user" color="primary" size="small" variant="tonal" class="mt-2 map-role-chip">
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
									<v-select
										v-model="reservationStartTime"
										:items="availableStartTimeOptions"
										label="No laiks"
										variant="outlined"
										density="compact"
									/>
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
									<v-select
										v-model="reservationEndTime"
										:items="availableEndTimeOptions"
										label="Līdz laikam"
										variant="outlined"
										density="compact"
									/>
								</v-col>
								<v-col cols="12">
									<div class="text-caption opacity-70">Laika josla: {{ userTimezone }}</div>
								</v-col>
							</v-row>
						</v-expansion-panel-text>
					</v-expansion-panel>

					<v-expansion-panel value="points" class="surface mt-4">
						<v-expansion-panel-title>
							<div class="d-flex align-center justify-space-between w-100 pr-2">
								<div class="text-subtitle-1 font-weight-bold">Īres punkti</div>
								<div class="d-flex align-center ga-2">
									<v-chip size="small" color="primary" variant="tonal">{{ filteredPoints.length }}</v-chip>
									<v-chip
										size="small"
										color="amber"
										variant="tonal"
										v-if="overallReviewCount > 0"
									>
										★ {{ overallReviewAverage.toFixed(1) }} ({{ overallReviewCount }})
									</v-chip>
								</div>
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
												<div class="vehicle-rating mt-1">
													<v-rating
														:model-value="Number(vehicle.videjais_vertejums || 0)"
														length="5"
														readonly
														half-increments
														size="small"
														active-color="amber"
														empty-icon="mdi-star-outline"
														full-icon="mdi-star"
													/>
													<span class="text-caption opacity-80">
														{{ Number(vehicle.videjais_vertejums || 0).toFixed(1) }} ({{ Number(vehicle.atsauksmju_skaits || 0) }})
													</span>
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
													size="small"
													variant="tonal"
													@click="openReviews(vehicle)"
												>
													Atsauksmes
												</v-btn>
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
							<div class="text-subtitle-2 font-weight-bold mb-2">Pievienot transporta veidu</div>
							<v-form class="d-flex flex-column ga-3 mb-5" @submit.prevent="submitVehicleType">
								<v-text-field
									v-model="newVehicleType.nosaukums"
									label="Jaunais transporta veids"
									variant="outlined"
									density="compact"
									hint="Piemēram, Kvadracikls, Laiva vai Piekabe"
									persistent-hint
								/>
								<v-btn type="submit" block color="primary" variant="tonal" :loading="vehicleTypeLoading">
									Saglabāt jauno veidu
								</v-btn>
							</v-form>

							<v-divider class="mb-4" />
							<div class="text-subtitle-2 font-weight-bold mb-2">Pievienot transportlīdzekli</div>
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
					<div class="text-body-2">
						Izvēlieties, vai rezervāciju apmaksāt tagad vai vēlāk.
					</div>
					<div v-if="pendingReservation" class="mt-3 text-caption opacity-70">
						Rezervācijas summa: {{ formatPrice(pendingReservation.kopa_summa) }}
					</div>
				</v-card-text>
				<v-card-actions class="px-4 pb-4">
					<v-spacer />
					<v-btn variant="text" color="error" :disabled="paymentLoading" @click="cancelDialog = true">Atcelt rezervāciju</v-btn>
					<v-btn variant="text" :disabled="paymentLoading" @click="payLater">Maksāšu vēlāk</v-btn>
					<v-btn color="primary" :loading="paymentLoading" @click="confirmPayment">Apmaksāt</v-btn>
				</v-card-actions>
			</v-card>
		</v-dialog>

		<v-dialog v-model="cancelDialog" max-width="420">
			<v-card>
				<v-card-title class="font-weight-bold">Drošības apstiprinājums</v-card-title>
				<v-divider />
				<v-card-text class="pa-4">
					Vai tiešām vēlaties atcelt šo neapmaksāto rezervāciju?
				</v-card-text>
				<v-card-actions class="px-4 pb-4">
					<v-spacer />
					<v-btn variant="text" :disabled="paymentLoading" @click="cancelDialog = false">Nē</v-btn>
					<v-btn color="error" :loading="paymentLoading" @click="cancelPendingReservation">Jā, atcelt</v-btn>
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
				</v-card-text>
				<v-card-actions class="px-4 pb-4">
					<v-spacer />
					<v-btn variant="text" @click="editDialog = false">Atcelt</v-btn>
					<v-btn color="primary" :loading="editLoading" @click="saveVehicle">Saglabāt</v-btn>
				</v-card-actions>
			</v-card>
		</v-dialog>

		<v-dialog v-model="reviewDialog" max-width="760">
			<v-card>
				<v-card-title class="font-weight-bold d-flex align-center justify-space-between">
					<div>
						<div>Atsauksmes</div>
						<div v-if="reviewVehicle" class="text-caption opacity-70">
							{{ reviewVehicle.marka }} {{ reviewVehicle.modelis }}
						</div>
					</div>
					<v-chip size="small" color="amber" variant="tonal" v-if="reviewStats.count > 0">
						★ {{ reviewStats.average.toFixed(1) }} ({{ reviewStats.count }})
					</v-chip>
				</v-card-title>
				<v-divider />
				<v-card-text class="pa-4">
					<v-alert v-if="!user" type="info" variant="tonal" density="compact" class="mb-3">
						Atsauksmes var pievienot tikai autorizēti klienti.
					</v-alert>
					<v-alert v-else-if="!isClient" type="warning" variant="tonal" density="compact" class="mb-3">
						Pakalpojumu sniedzējs atsauksmes pievienot nevar.
					</v-alert>

					<v-card v-if="canReview" class="mb-4" variant="outlined">
						<v-card-text class="pa-4 d-flex flex-column ga-3">
							<div class="text-subtitle-2 font-weight-bold">
								{{ ownReview ? 'Rediģēt savu atsauksmi' : 'Pievienot atsauksmi' }}
							</div>
							<v-rating
								v-model="reviewForm.vertejums"
								length="5"
								:half-increments="false"
								active-color="amber"
								empty-icon="mdi-star-outline"
								full-icon="mdi-star"
							/>
							<v-textarea
								v-model="reviewForm.komentars"
								label="Komentārs"
								rows="3"
								auto-grow
								counter="2000"
								variant="outlined"
								density="compact"
							/>
							<div class="d-flex justify-end">
								<v-btn color="primary" :loading="reviewSaving" @click="submitReview">
									{{ ownReview ? 'Saglabāt izmaiņas' : 'Pievienot atsauksmi' }}
								</v-btn>
							</div>
						</v-card-text>
					</v-card>

					<div class="text-subtitle-2 font-weight-bold mb-2">Visas atsauksmes</div>
					<div v-if="reviewLoading" class="text-body-2">Ielādē atsauksmes...</div>
					<div v-else-if="!vehicleReviews.length" class="text-body-2 opacity-70">Šim transportlīdzeklim vēl nav atsauksmju.</div>
					<div v-else class="d-flex flex-column ga-3">
						<v-card v-for="review in vehicleReviews" :key="review.atsauksme_id" variant="outlined">
							<v-card-text class="pa-3">
								<div class="d-flex align-center justify-space-between ga-3">
									<div class="text-subtitle-2 font-weight-bold">{{ reviewerName(review) }}</div>
									<div class="text-caption opacity-70">{{ formatReviewDate(review.datums || review.updated_at) }}</div>
								</div>
								<div class="d-flex align-center ga-2 mt-1">
									<v-rating
										:model-value="Number(review.vertejums || 0)"
										length="5"
										readonly
										size="small"
										active-color="amber"
										empty-icon="mdi-star-outline"
										full-icon="mdi-star"
									/>
									<span class="text-caption">{{ review.vertejums }}/5</span>
								</div>
								<div class="mt-2 text-body-2">{{ review.komentars || 'Komentārs nav pievienots.' }}</div>
							</v-card-text>
						</v-card>
					</div>
				</v-card-text>
				<v-card-actions class="px-4 pb-4">
					<v-spacer />
					<v-btn variant="text" v-if="!user" @click="router.push(AUTH_ROUTE)">Pieslēgties</v-btn>
					<v-btn color="primary" variant="text" @click="reviewDialog = false">Aizvērt</v-btn>
				</v-card-actions>
			</v-card>
		</v-dialog>

	</div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { AUTH_ROUTE, buildCompanyRoute } from '@/router/paths'
import { useNotifications } from '@/stores/notifications'

const router = useRouter()
const API_BASE = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000'
const { notifyError, notifySuccess, notifyWarning, notifyInfo } = useNotifications()

// Lapas pamatstāvoklis: lietotājs, dati, ielāde un panelis.
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

// Palīgfunkcijas datuma/laika vērtību sadalīšanai un apvienošanai.
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

// Computed lauki sinhronizē rezervācijas no/līdz datumu un laiku ar UI.
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

// Pieejamie laiki tiek ierobežoti pēc šodienas laika un izvēlētā perioda.
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

const reservationDialog = ref(false)
const reservationLoading = ref(false)
const reservationError = ref('')
const activeVehicle = ref(null)
const pendingReservation = ref(null)

const paymentDialog = ref(false)
const paymentLoading = ref(false)
const paymentError = ref('')
const paymentSuccess = ref('')
const cancelDialog = ref(false)
const startDatePickerMenu = ref(false)
const endDatePickerMenu = ref(false)

const providerLoading = ref(false)
const providerError = ref('')
const providerSuccess = ref('')
const vehicleTypeLoading = ref(false)
const vehicleTypeError = ref('')
const vehicleTypeSuccess = ref('')

const editDialog = ref(false)
const editLoading = ref(false)
const editError = ref('')
const editVehicle = ref({})

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
const hudVisible = ref(true)

watch(vehicleTypeError, value => {
	if (value) notifyError(value)
})

watch(vehicleTypeSuccess, value => {
	if (value) notifySuccess(value)
})

watch(providerError, value => {
	if (value) notifyError(value)
})

watch(providerSuccess, value => {
	if (value) notifySuccess(value)
})

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

const newVehicleType = ref({
	nosaukums: '',
})

const statusOptions = ['pieejams', 'aiznemts', 'neaktivs']
const gearboxOptions = ['Automāts', 'Mehānika', '-']
const fuelOptions = ['Benzīns', 'Dīzelis', 'Elektro', '-']

const roleLabel = computed(() => {
	if (!user.value) return 'Viesis'
	return user.value.loma === 'pakalpojumu_sniedzejs' ? 'Pakalpojumu sniedzējs' : 'Klients'
})

const userTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'Europe/Riga'

const isClient = computed(() => user.value?.loma === 'klients')
const isProvider = computed(() => user.value?.loma === 'pakalpojumu_sniedzejs')
const clientId = computed(() => user.value?.klients?.klients_id || null)

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

// Pārvērš API transporta sarakstu kompāniju punktos kartei/sarakstam.
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

// Punktiem bez koordinātēm izmanto geokodētas vai sintētiskas rezerves koordinātas.
const mappablePoints = computed(() => {
	return filteredPoints.value.map(point => {
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

const overallReviewCount = computed(() => {
	return transportItems.value.reduce((total, vehicle) => total + Number(vehicle.atsauksmju_skaits || 0), 0)
})

const overallReviewAverage = computed(() => {
	const weightedTotal = transportItems.value.reduce((total, vehicle) => {
		const count = Number(vehicle.atsauksmju_skaits || 0)
		const avg = Number(vehicle.videjais_vertejums || 0)
		return total + (avg * count)
	}, 0)

	if (!overallReviewCount.value) return 0
	return weightedTotal / overallReviewCount.value
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

let mapInstance = null
let markers = []
let paymentSuccessTimer = null

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

// Vienota transporta filtrēšanas loģika sarakstam un kartes punktiem.
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

function openCompany(companyId) {
	router.push(buildCompanyRoute(companyId))
}

function isOwnVehicle(vehicle) {
	return providerId.value && vehicle.sniedzejs_id === providerId.value
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
	return new Intl.DateTimeFormat('lv-LV', {
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

async function loadVehicleReviews(transportlidzeklisId) {
	reviewLoading.value = true
	reviewError.value = ''

	try {
		const response = await fetch(`${API_BASE}/api/atsauksmes?transportlidzeklis_id=${transportlidzeklisId}`)
		const result = await response.json()

		if (!response.ok) {
			reviewError.value = result?.message || 'Neizdevās ielādēt atsauksmes.'
			vehicleReviews.value = []
			return
		}

		vehicleReviews.value = Array.isArray(result?.atsauksmes) ? result.atsauksmes : []
		resetReviewForm()
	} catch (error) {
		reviewError.value = 'Kļūda: Neizdevās ielādēt atsauksmes.'
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
		reviewError.value = 'Atsauksmi drīkst pievienot tikai autorizēts klients.'
		return
	}

	if (!Number.isInteger(Number(reviewForm.value.vertejums)) || Number(reviewForm.value.vertejums) < 1 || Number(reviewForm.value.vertejums) > 5) {
		reviewError.value = 'Lūdzu, izvēlieties vērtējumu no 1 līdz 5 zvaigznēm.'
		return
	}

	reviewSaving.value = true
	reviewError.value = ''
	reviewSuccess.value = ''

	try {
		const payload = {
			persona_id: user.value.persona_id,
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
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(payload),
		})

		const result = await response.json()
		if (!response.ok) {
			reviewError.value = result?.message || 'Neizdevās saglabāt atsauksmi.'
			return
		}

		reviewSuccess.value = result?.message || 'Atsauksme saglabāta.'
		await loadVehicleReviews(reviewVehicle.value.transportlidzeklis_id)
		await loadTransport()
	} catch (error) {
		reviewError.value = 'Kļūda: Neizdevās saglabāt atsauksmi.'
	} finally {
		reviewSaving.value = false
	}
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

// Izveido rezervāciju un pēc tam atver apmaksas izvēli.
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
	} catch (error) {
		reservationError.value = 'Kļūda: Neizdevās izveidot rezervāciju.'
	} finally {
		reservationLoading.value = false
	}
}

// Apmaksas darbības atjauno rezervācijas statusu lokālajā stāvoklī.
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
		upsertReservationInTransportState(result.rezervacija)
		await loadTransport()

		if (paymentSuccessTimer) {
			clearTimeout(paymentSuccessTimer)
		}
		paymentSuccessTimer = setTimeout(() => {
			paymentDialog.value = false
			paymentSuccess.value = ''
			paymentError.value = ''
		}, 1200)
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

function payLater() {
	closePaymentDialog()
}

async function cancelPendingReservation() {
	if (!pendingReservation.value?.rezervacija_id || !user.value?.klients?.klients_id) {
		paymentDialog.value = false
		return
	}

	paymentLoading.value = true
	paymentError.value = ''

	try {
		const response = await fetch(`${API_BASE}/api/rezervacijas/${pendingReservation.value.rezervacija_id}`, {
			method: 'DELETE',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ klients_id: user.value.klients.klients_id }),
		})
		const data = await response.json()
		if (!response.ok) {
			paymentError.value = data?.message || 'Neizdevās atcelt rezervāciju.'
			return
		}

		pendingReservation.value = null
		paymentDialog.value = false
		cancelDialog.value = false
		snackbar.value = { show: true, text: 'Rezervācija atcelta. Transports atkal pieejams.', color: 'success' }
		await loadTransport()
	} catch {
		paymentError.value = 'Kļūda: Neizdevās atcelt rezervāciju.'
	} finally {
		paymentLoading.value = false
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

async function submitVehicleType() {
	vehicleTypeLoading.value = true
	vehicleTypeError.value = ''
	vehicleTypeSuccess.value = ''

	const nosaukums = (newVehicleType.value.nosaukums || '').trim()
	if (!nosaukums) {
		vehicleTypeError.value = 'Ievadiet transporta veida nosaukumu.'
		vehicleTypeLoading.value = false
		return
	}

	try {
		const response = await fetch(`${API_BASE}/api/transport/veidi`, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ nosaukums }),
		})

		const result = await response.json()
		if (!response.ok) {
			vehicleTypeError.value = result?.message || 'Neizdevās pievienot transporta veidu.'
			return
		}

		vehicleTypeSuccess.value = 'Transporta veids pievienots.'
		newVehicleType.value = { nosaukums: '' }
		newVehicle.value.veids_id = result?.veids_id ?? null
		await loadTypes()
	} catch (error) {
		vehicleTypeError.value = 'Kļūda: Neizdevās pievienot transporta veidu.'
	} finally {
		vehicleTypeLoading.value = false
	}
}

// Datu ielāde no API.
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

// Pārzīmē marķierus pēc aktīvajiem filtriem un punktiem.
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
			<div class="map-popup-content">
				<strong>${point.name}</strong><br/>
				${point.address}, ${point.city}<br/>
				Transporti: ${point.vehicles.length} (${point.availableCount} pieejami)<br/>
				<button type="button" class="popup-company-link" data-company-id="${point.id}">Skatīt kompāniju</button>
			</div>
		`)

		marker.on('click', () => selectPoint(point.id))
		marker.on('popupopen', event => {
			const popupRoot = event.popup?.getElement()
			const button = popupRoot?.querySelector('.popup-company-link')
			if (!button) return
			button.addEventListener('click', clickEvent => {
				clickEvent.preventDefault()
				openCompany(point.id)
			})
		})
		markers.push(marker)
	})
}

// Mēģina atrast koordinātas punktiem, kuriem tās nav saglabātas.
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

function getDefaultHudVisibility() {
	return typeof window === 'undefined' ? true : window.innerWidth > 900
}

function toggleHud() {
	hudVisible.value = !hudVisible.value
	localStorage.setItem('map-hud-visible', hudVisible.value ? '1' : '0')
}

// Sākotnējā lapas ielāde un kartes inicializācija.
onMounted(async () => {
	const userStr = localStorage.getItem('user')
	if (userStr) user.value = JSON.parse(userStr)
	const storedHudVisibility = localStorage.getItem('map-hud-visible')
	hudVisible.value = storedHudVisibility === null ? getDefaultHudVisibility() : storedHudVisibility === '1'
	await loadTypes()
	await loadTransport()
	await resolveMissingPointCoords()
	await nextTick()
	initMap()
	renderMarkers()
})

onBeforeUnmount(() => {
	localStorage.setItem('map-hud-visible', hudVisible.value ? '1' : '0')
})

// Watchers uztur karti sinhroni ar datiem, filtriem un UI izmaiņām.
watch(points, async () => {
	await resolveMissingPointCoords()
	nextTick().then(renderMarkers)
})

watch(filteredPoints, () => {
	nextTick().then(renderMarkers)
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
	background: var(--er-page-bg);
}

.surface {
	background: var(--er-surface);
	border: 1px solid var(--er-stroke);
	border-radius: 22px;
	color: var(--er-text);
	backdrop-filter: blur(12px);
	box-shadow: var(--er-shadow-sm);
}

.map-canvas {
	height: 100%;
	width: 100%;
	filter: saturate(0.96) contrast(1.02);
}

.map-menu-btn {
	position: absolute;
	top: 24px;
	left: 16px;
	z-index: 500;
	box-shadow: 0 16px 34px rgba(25, 41, 55, 0.16);
}

.map-hud-toggle {
	position: absolute;
	top: 24px;
	right: 16px;
	z-index: 500;
	background: var(--er-action-surface) !important;
	color: var(--er-text) !important;
	border: 1px solid var(--er-stroke) !important;
	box-shadow: var(--er-shadow-sm);
}

.map-hud {
	position: absolute;
	top: 86px;
	right: 16px;
	max-width: 360px;
	padding: 18px;
	border-radius: 28px;
	background: color-mix(in srgb, var(--er-surface) 88%, transparent);
	border: 1px solid var(--er-stroke);
	box-shadow: var(--er-shadow-md);
	backdrop-filter: blur(18px);
	z-index: 450;
}

.map-hud__head {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 12px;
}

.map-hud__eyebrow {
	margin-bottom: 12px;
}

.map-hud__close {
	margin-right: -6px;
	margin-top: -6px;
}

.map-hud__title {
	font-family: 'Syne', 'Plus Jakarta Sans', sans-serif;
	font-size: clamp(1.4rem, 2vw, 2rem);
	line-height: 1;
	letter-spacing: -0.05em;
	margin-bottom: 10px;
}

.map-hud__copy {
	color: var(--er-text-muted);
	font-size: 0.95rem;
	line-height: 1.5;
	margin-bottom: 16px;
}

.map-hud__stats {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 10px;
}

.map-hud__stat {
	padding: 12px;
	border-radius: 18px;
	background: var(--er-panel-soft);
	border: 1px solid var(--er-stroke);
	display: grid;
	gap: 4px;
}

.map-hud__stat strong {
	font-size: 1.15rem;
	line-height: 1;
}

.map-hud__stat span {
	font-size: 0.78rem;
	color: var(--er-text-muted);
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
	background: var(--er-action-surface);
	color: var(--er-text);
	border: 1px solid var(--er-stroke);
	backdrop-filter: blur(8px);
	box-shadow: var(--er-shadow-sm);
}

.action-btn:hover {
	background: var(--er-action-hover);
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
	background: var(--er-drawer-bg);
}

.drawer-header {
	padding: 16px;
	display: flex;
	align-items: center;
	justify-content: flex-start;
	gap: 12px;
	position: relative;
	text-align: left;
	border-bottom: 1px solid rgba(148, 163, 184, 0.2);
	background: var(--er-header-soft);
}

.drawer-header-main {
	width: 100%;
}

.header-title,
.header-subtitle {
	color: var(--er-text);
}

.map-role-chip {
	color: var(--er-text) !important;
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

.drawer-panels :deep(.v-expansion-panel) {
	border: 1px solid rgba(148, 163, 184, 0.26);
	border-radius: 14px;
	overflow: hidden;
	background: var(--er-panel-soft);
}

.list-wrap {
	max-height: 260px;
	overflow-y: auto;
}

.point-card {
	cursor: pointer;
	border: 1px solid rgba(21, 48, 71, 0.1);
	border-radius: 18px;
	max-width: 100%;
	overflow: hidden;
	transition: transform 0.2s ease, box-shadow 0.2s ease;
	background: var(--er-card-soft);
}

.point-card:hover {
	transform: translateY(-3px);
	box-shadow: 0 18px 34px rgba(25, 41, 55, 0.14);
}

.point-card-header {
	justify-content: center;
	text-align: center;
	gap: 10px;
	flex-wrap: wrap;
}

.selected-point-title {
	justify-content: flex-start;
	text-align: left;
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
	border-radius: 18px;
	border: 1px solid rgba(21, 48, 71, 0.1);
	background: var(--er-card-soft);
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

.vehicle-rating {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 8px;
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
	border-radius: 16px;
	border: 1px solid rgba(21, 48, 71, 0.1);
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
	border-radius: 7px;
	background: linear-gradient(135deg, #0f766e, #c96b3b);
	border: 2px solid var(--er-marker-border);
	box-shadow: 0 8px 18px rgba(25, 41, 55, 0.25);
}

:deep(.map-popup-content) {
	line-height: 1.45;
}

:deep(.popup-company-link) {
	margin-top: 8px;
	padding: 6px 10px;
	border: 0;
	border-radius: 8px;
	background: #0f766e;
	color: var(--er-on-primary);
	cursor: pointer;
	font-weight: 600;
}

:deep(.popup-company-link:hover) {
	background: #115e59;
}

@media (max-width: 900px) {
	.map-hud {
		left: 16px;
		right: 16px;
		max-width: none;
		top: 86px;
	}

	.map-hud__stats {
		grid-template-columns: repeat(3, minmax(0, 1fr));
	}
}

@media (max-width: 640px) {
	.map-page {
		height: calc(100vh - 72px);
	}

	.floating-actions {
		left: 16px;
		right: 16px;
		bottom: 20px;
		justify-content: stretch;
	}

	.floating-actions :deep(.v-btn) {
		flex: 1 1 0;
	}

	.map-hud__stats {
		grid-template-columns: 1fr;
	}
}
</style>
