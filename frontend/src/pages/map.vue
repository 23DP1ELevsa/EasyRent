<template>
  <div class="map-page">
    <header v-if="viewMode === 'list'" class="map-toolbar" :inert="isMobile && panelOpen ? true : undefined">
      <div class="map-toolbar__main">
        <div class="map-toolbar__title">
          <h1>{{ copy.catalogTitle }}</h1>
          <span aria-live="polite">{{ t('map.resultCount', { count: catalogVehicles.length }) }}</span>
        </div>
        <MapViewSwitch v-model="viewMode" class="desktop-view-switch" />
        <v-btn
          :aria-expanded="listPanelOpen"
          class="toolbar-filter"
          prepend-icon="mdi-tune-variant"
          variant="tonal"
          @click="openMapTools"
        >
          {{ copy.filters }}<span v-if="activeFilterKeys.length > 0" class="toolbar-count">{{ activeFilterKeys.length }}</span>
        </v-btn>
        <v-select
          v-model="sortOrder"
          class="toolbar-sort"
          density="compact"
          hide-details
          item-title="title"
          item-value="value"
          :items="sortOptions"
          :label="copy.sortBy"
          variant="outlined"
        />
        <v-btn
          v-if="isProvider"
          class="toolbar-add"
          color="primary"
          prepend-icon="mdi-plus"
          @click="providerDialog = true"
        >{{ copy.addVehicle }}</v-btn>
      </div>
      <div v-if="activeFilterKeys.length > 0 || selectedPoint" :aria-label="copy.activeFilters" class="active-filters">
        <v-chip
          v-for="key in activeFilterKeys"
          :key="key"
          closable
          color="primary"
          size="small"
          variant="tonal"
          @click:close="removeFilter(key)"
        >{{ filterChipLabel(key) }}</v-chip>
        <v-chip
          v-if="selectedPoint && !filters.companyId"
          closable
          size="small"
          variant="outlined"
          @click:close="clearPointSelection"
        >{{ selectedPoint.name }}</v-chip>
        <v-btn size="small" variant="text" @click="resetFilters">{{ copy.resetFilters }}</v-btn>
      </div>
    </header>
    <div :aria-busy="isWorkspaceLoading" class="map-body" :class="{ 'has-panel': viewMode === 'map' && mapPanelOpen }">
      <div v-show="viewMode === 'map'" ref="mapViewport" class="map-viewport" :inert="isMobile && panelOpen ? true : undefined">

        <div id="map" class="map-canvas" />

        <v-btn
          v-if="!mapPanelOpen"
          ref="panelToggle"
          aria-controls="rental-workspace"
          :aria-expanded="mapPanelOpen"
          class="map-menu-btn"
          color="primary"
          prepend-icon="mdi-tune-variant"
          @click="openMapTools"
        >{{ copy.filters }}<span v-if="activeFilterKeys.length > 0" class="toolbar-count">{{ activeFilterKeys.length }}</span></v-btn>

        <button
          :aria-label="copy.nearMe"
          class="map-locate-btn"
          :disabled="locating"
          :title="copy.nearMe"
          type="button"
          @click.stop="locateUser"
          @pointerdown.stop
          @touchstart.stop
        ><v-icon size="20">mdi-crosshairs-gps</v-icon></button>

      </div>
      <button
        v-if="isMobile && panelOpen"
        :aria-label="copy.closePanel"
        class="panel-scrim"
        tabindex="-1"
        type="button"
        @click="panelOpen = false"
      />
      <section
        v-show="viewMode === 'map' && mapPanelOpen"
        id="rental-workspace"
        ref="panelElement"
        :aria-label="copy.drawerTitle"
        :aria-modal="isMobile ? true : undefined"
        class="map-workspace"
        :role="isMobile ? 'dialog' : 'region'"
        tabindex="-1"
        @keydown="handlePanelKeydown"
      >
        <div class="workspace-view-switch"><MapViewSwitch v-model="viewMode" class="desktop-view-switch" /></div>
        <div class="drawer-header">
          <div class="drawer-header-main"><h2 class="header-title">{{ copy.drawerTitle }}</h2><p class="header-subtitle">{{ copy.mapSubtitle }}</p></div>
          <v-btn
            :aria-label="copy.closePanel"
            class="header-close-btn"
            icon="mdi-close"
            variant="text"
            @click="panelOpen = false"
          />
        </div>
        <div class="map-search">
          <v-text-field
            v-model="filters.q"
            clearable
            density="comfortable"
            hide-details
            :label="copy.searchPlaceholder"
            prepend-inner-icon="mdi-magnify"
            variant="outlined"
            @click:clear="filters.q = ''"
          />
        </div>
        <div ref="panelScroll" class="drawer-scroll">
          <div aria-live="polite" class="workspace-stats">
            <div><strong>{{ filteredPoints.length }}</strong><span>{{ copy.stats.points }}</span></div>
            <div><strong>{{ filteredPoints.reduce((sum, point) => sum + point.vehicles.length, 0) }}</strong><span>{{ copy.stats.vehicles }}</span></div>
            <div><strong>{{ filteredPoints.reduce((sum, point) => sum + point.availableCount, 0) }}</strong><span>{{ copy.stats.available }}</span></div>
          </div>
          <div class="workspace-tools">
            <v-select
              v-model="sortOrder"
              class="workspace-sort"
              density="compact"
              hide-details
              item-title="title"
              item-value="value"
              :items="sortOptions"
              :label="copy.sortBy"
              variant="outlined"
            />
            <v-btn :aria-label="copy.reset" icon="mdi-backup-restore" variant="text" @click="resetMap" />
          </div>
          <v-btn
            v-if="isProvider && viewMode === 'map'"
            block
            class="toolbar-add mb-4"
            color="primary"
            prepend-icon="mdi-plus"
            @click="providerDialog = true"
          >{{ copy.addVehicle }}</v-btn>
          <div v-if="activeFilterKeys.length > 0 || selectedPoint" :aria-label="copy.activeFilters" class="active-filters">
            <v-chip
              v-for="key in activeFilterKeys"
              :key="key"
              closable
              color="primary"
              size="small"
              variant="tonal"
              @click:close="removeFilter(key)"
            >{{ filterChipLabel(key) }}</v-chip>
            <v-chip
              v-if="selectedPoint && !filters.companyId"
              closable
              size="small"
              variant="outlined"
              @click:close="clearPointSelection"
            >{{ selectedPoint.name }}</v-chip>
            <v-btn size="small" variant="text" @click="resetFilters">{{ copy.resetFilters }}</v-btn>
          </div>
          <RentalFilterGroups
            v-model="filters"
            v-bind="filterOptions"
            :show-search="false"
          />
          <div class="points-section">
            <div v-if="isWorkspaceLoading" class="dialog-state" role="status"><v-progress-circular indeterminate size="22" />{{ copy.loading }}</div>
            <div v-else-if="errorText" class="dialog-state" role="alert">{{ errorText }}<v-btn variant="text" @click="loadTransport">{{ copy.retry }}</v-btn></div>
            <div v-else-if="filteredPoints.length === 0" class="dialog-state">{{ copy.noResults }}<v-btn variant="text" @click="resetFilters">{{ copy.resetFilters }}</v-btn></div>
            <RentalPointList v-else :points="filteredPoints" :selected-id="selectedPointId" @select="selectPoint" />
          </div>
          <section v-if="selectedPoint && catalogVehicles.length > 0" ref="panelCatalog" class="panel-catalog">
            <div class="catalog-heading">
              <div><h2>{{ selectedPoint ? selectedPoint.name : copy.stats.vehicles }}</h2><p v-if="selectedPoint">{{ selectedPoint.address }} &middot; {{ selectedPoint.city }}</p><p v-if="selectedPoint && !hasPointCoordinates(selectedPoint.id)">{{ copy.noMapCoords }}</p></div>
              <v-btn
                v-if="selectedPoint"
                :aria-label="copy.allResults"
                icon="mdi-close"
                size="small"
                variant="text"
                @click="clearPointSelection"
              />
            </div>
            <v-btn
              block
              class="mb-4"
              prepend-icon="mdi-storefront-outline"
              variant="tonal"
              @click="openCompany(selectedPoint.id)"
            >{{ copy.viewProvider }}</v-btn>
            <div class="vehicle-grid vehicle-grid--compact">
              <RentalVehicleCard
                v-for="vehicle in pagedSelectedPointVehicles"
                :key="vehicle.transportlidzeklis_id"
                :available="isVehicleAvailable(vehicle)"
                :can-edit="isProvider && isOwnVehicle(vehicle)"
                :can-reserve="isClient || !user"
                :compact="viewMode === 'map'"
                :details="buildVehicleDetails(vehicle)"
                :provider="vehicleProviders.get(vehicle.transportlidzeklis_id)"
                :vehicle="vehicle"
                @details="detailVehicle = vehicle"
                @edit="openEditVehicle(vehicle)"
                @reserve="openReservation(vehicle)"
                @reviews="openReviews(vehicle)"
                @select-provider="selectPoint(vehicleProviders.get(vehicle.transportlidzeklis_id)?.id)"
              />
            </div>
            <div v-if="selectedPointVehiclePageCount > 1" class="vehicle-pagination">
              <v-btn
                :aria-label="copy.previousPage"
                :disabled="selectedPointVehiclePage <= 1"
                icon="mdi-chevron-left"
                size="small"
                variant="text"
                @click="selectedPointVehiclePage -= 1"
              />
              <span aria-live="polite" class="vehicle-pagination__info">{{ selectedPointVehiclePage }} / {{ selectedPointVehiclePageCount }}</span>
              <v-btn
                :aria-label="copy.nextPage"
                :disabled="selectedPointVehiclePage >= selectedPointVehiclePageCount"
                icon="mdi-chevron-right"
                size="small"
                variant="text"
                @click="selectedPointVehiclePage += 1"
              />
            </div>
          </section>
        </div>
      </section>

      <section v-show="viewMode === 'list'" :aria-label="copy.listView" class="list-catalog">
        <div class="catalog-heading">
          <div><h2>{{ selectedPoint ? selectedPoint.name : copy.allVehicles }}</h2><p v-if="selectedPoint">{{ selectedPoint.address }} &middot; {{ selectedPoint.city }}</p><p v-else>{{ copy.catalogSubtitle }}</p></div>
          <v-btn
            v-if="selectedPointId !== null || filters.companyId"
            prepend-icon="mdi-arrow-left"
            size="small"
            variant="tonal"
            @click="clearPointSelection"
          >{{ copy.allResults }}</v-btn>
        </div>
        <div v-if="isWorkspaceLoading" class="dialog-state" role="status"><v-progress-circular color="primary" indeterminate size="22" />{{ copy.loading }}</div>
        <div v-else-if="errorText" class="dialog-state" role="alert">{{ errorText }}<v-btn variant="text" @click="loadTransport">{{ copy.retry }}</v-btn></div>
        <div v-else-if="catalogVehicles.length === 0" class="dialog-state"><v-icon>mdi-magnify-close</v-icon>{{ copy.noResults }}<v-btn variant="text" @click="resetFilters">{{ copy.resetFilters }}</v-btn></div>
        <template v-else>
          <div class="vehicle-grid">
            <RentalVehicleCard
              v-for="vehicle in pagedSelectedPointVehicles"
              :key="vehicle.transportlidzeklis_id"
              :available="isVehicleAvailable(vehicle)"
              :can-edit="isProvider && isOwnVehicle(vehicle)"
              :can-reserve="isClient || !user"
              :compact="viewMode === 'map'"
              :details="buildVehicleDetails(vehicle)"
              :provider="vehicleProviders.get(vehicle.transportlidzeklis_id)"
              :vehicle="vehicle"
              @details="detailVehicle = vehicle"
              @edit="openEditVehicle(vehicle)"
              @reserve="openReservation(vehicle)"
              @reviews="openReviews(vehicle)"
              @select-provider="selectPoint(vehicleProviders.get(vehicle.transportlidzeklis_id)?.id)"
            />
          </div>
          <div v-if="selectedPointVehiclePageCount > 1" class="vehicle-pagination">
            <v-btn
              :aria-label="copy.previousPage"
              :disabled="selectedPointVehiclePage <= 1"
              icon="mdi-chevron-left"
              size="small"
              variant="text"
              @click="selectedPointVehiclePage -= 1"
            />
            <span aria-live="polite" class="vehicle-pagination__info">{{ selectedPointVehiclePage }} / {{ selectedPointVehiclePageCount }}</span>
            <v-btn
              :aria-label="copy.nextPage"
              :disabled="selectedPointVehiclePage >= selectedPointVehiclePageCount"
              icon="mdi-chevron-right"
              size="small"
              variant="text"
              @click="selectedPointVehiclePage += 1"
            />
          </div>
        </template>
      </section>
    </div>
    <div class="mobile-view-switch" :inert="isMobile && panelOpen ? true : undefined"><MapViewSwitch v-model="viewMode" /></div>

    <RentalSlideOver v-model="listPanelOpen" :is-mobile="isMobile" return-focus=".toolbar-filter" :title="copy.filters">
      <RentalFilterGroups v-model="draftFilters" v-bind="filterOptions" />
      <template #footer>
        <v-btn variant="text" @click="draftFilters = createRentalFilters()">{{ copy.reset }}</v-btn>
        <v-btn color="primary" :disabled="!hasValidPriceRange(draftFilters) || isWorkspaceLoading" @click="applyListFilters">{{ t('map.showResults', { count: draftResultCount }) }}</v-btn>
      </template>
    </RentalSlideOver>
    <RentalSlideOver
      v-if="isProvider"
      v-model="providerDialog"
      :is-mobile="isMobile"
      return-focus=".toolbar-add"
      :title="copy.addVehicle"
      width="540"
    >
      <div class="provider-panel">
        <div class="text-subtitle-2 font-weight-bold mb-2">{{ copy.addVehicleType }}</div>
        <v-form class="d-flex flex-column ga-3 mb-5" @submit.prevent="submitVehicleType">
          <v-text-field
            v-model="newVehicleType.nosaukums"
            density="compact"
            :hint="copy.vehicleTypeHint"
            :label="copy.newVehicleType"
            persistent-hint
            variant="outlined"
          />
          <v-btn
            block
            color="primary"
            :loading="vehicleTypeLoading"
            type="submit"
            variant="tonal"
          >
            {{ copy.saveNewType }}
          </v-btn>
        </v-form>

        <div v-if="transportTypes.length > 0" class="mb-5">
          <div class="text-subtitle-2 font-weight-bold mb-2">{{ copy.existingVehicleTypes }}</div>
          <v-select
            v-model="editingVehicleTypeId"
            clearable
            density="compact"
            item-title="title"
            item-value="value"
            :items="vehicleTypeSelectOptions"
            :label="copy.selectVehicleType"
            variant="outlined"
          />
          <div class="d-flex justify-end mt-2">
            <v-btn
              :disabled="!selectedVehicleTypeToEdit"
              size="small"
              variant="tonal"
              @click="startEditingVehicleType"
            >
              {{ copy.editSelectedType }}
            </v-btn>
          </div>
        </div>

        <v-divider class="mb-4" />
        <div class="text-subtitle-2 font-weight-bold mb-2">{{ copy.addVehicleItem }}</div>
        <v-form @submit.prevent="submitVehicle">
          <v-select
            v-model="newVehicle.veids_id"
            density="compact"
            item-title="title"
            item-value="value"
            :items="typeOptions"
            :label="copy.vehicleFields.type"
            variant="outlined"
          />
          <v-text-field v-model="newVehicle.marka" density="compact" :label="copy.vehicleFields.brand" variant="outlined" />
          <v-text-field v-model="newVehicle.modelis" density="compact" :label="copy.vehicleFields.model" variant="outlined" />
          <v-select
            v-model="newVehicle.atrumkarba"
            density="compact"
            :items="gearboxOptions"
            :label="copy.vehicleFields.gearbox"
            variant="outlined"
          />
          <v-select
            v-model="newVehicle.degvielas_veids"
            density="compact"
            :items="fuelOptions"
            :label="copy.vehicleFields.fuel"
            variant="outlined"
          />
          <v-text-field
            v-model.number="newVehicle.dienas_nomas_cena"
            density="compact"
            :label="copy.vehicleFields.dailyPrice"
            min="0"
            type="number"
            variant="outlined"
          />
          <v-select
            v-model="newVehicle.statuss"
            density="compact"
            :items="statusOptions"
            :label="copy.vehicleFields.status"
            variant="outlined"
          />
          <v-text-field
            v-model="newVehicle.registracijas_numurs"
            density="compact"
            :label="copy.vehicleFields.registrationNumber"
            variant="outlined"
          />
          <v-btn
            block
            class="mt-2"
            color="primary"
            :loading="providerLoading"
            type="submit"
          >
            {{ copy.save }}
          </v-btn>
        </v-form>
      </div>
    </RentalSlideOver>

    <v-dialog max-width="600" :model-value="!!detailVehicle" @update:model-value="value => { if (!value) detailVehicle = null }">
      <v-card v-if="detailVehicle" class="rental-dialog__card">
        <v-card-title>{{ copy.vehicleDetails }}</v-card-title>
        <v-card-text>
          <RentalVehicleCard
            :available="isVehicleAvailable(detailVehicle)"
            :can-edit="isProvider && isOwnVehicle(detailVehicle)"
            :can-reserve="isClient || !user"
            :details="buildVehicleDetails(detailVehicle)"
            :provider="vehicleProviders.get(detailVehicle.transportlidzeklis_id)"
            :show-details="false"
            :vehicle="detailVehicle"
            @details="detailVehicle = null"
            @edit="openEditVehicle(detailVehicle); detailVehicle = null"
            @reserve="openReservation(detailVehicle); detailVehicle = null"
            @reviews="openReviews(detailVehicle); detailVehicle = null"
            @select-provider="selectPoint(vehicleProviders.get(detailVehicle.transportlidzeklis_id)?.id); detailVehicle = null"
          />
          <v-btn
            block
            class="mt-3"
            prepend-icon="mdi-star-outline"
            variant="text"
            @click="openReviews(detailVehicle); detailVehicle = null"
          >{{ copy.reviews }}</v-btn>
          <v-btn block class="mt-2" variant="tonal" @click="openCompany(vehicleProviders.get(detailVehicle.transportlidzeklis_id)?.id)">{{ copy.viewProvider }}</v-btn>
        </v-card-text>
        <v-card-actions><v-spacer /><v-btn @click="detailVehicle = null">{{ copy.close }}</v-btn></v-card-actions>
      </v-card>
    </v-dialog>

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
              {{ activeVehicle.veids?.nosaukums || '—' }} • {{ formatPrice(activeVehicle.dienas_nomas_cena) }} / {{ copy.perDay }}
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
          <v-btn color="error" :disabled="paymentLoading" variant="text" @click="cancelDialog = true">{{ copy.cancelReservation }}</v-btn>
          <v-btn :disabled="paymentLoading" variant="text" @click="payLater">{{ copy.payLater }}</v-btn>
          <v-btn color="primary" :loading="paymentLoading" @click="confirmPayment">{{ copy.payNow }}</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="cancelDialog" max-width="420">
      <v-card class="rental-dialog__card">
        <v-card-title class="font-weight-bold">{{ copy.confirmTitle }}</v-card-title>
        <v-divider />
        <v-card-text class="pa-4">
          {{ copy.confirmCancelReservation }}
        </v-card-text>
        <v-card-actions class="px-4 pb-4">
          <v-spacer />
          <v-btn :disabled="paymentLoading" variant="text" @click="cancelDialog = false">{{ copy.no }}</v-btn>
          <v-btn color="error" :loading="paymentLoading" @click="cancelPendingReservation">{{ copy.yesCancel }}</v-btn>
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
            :items="gearboxOptions"
            :label="copy.vehicleFields.gearbox"
            variant="outlined"
          />
          <v-select
            v-model="editVehicle.degvielas_veids"
            density="compact"
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

    <v-dialog v-model="vehicleTypeDialog" max-width="420">
      <v-card class="rental-dialog__card">
        <v-card-title class="font-weight-bold">{{ copy.editVehicleTypeTitle }}</v-card-title>
        <v-divider />
        <v-card-text class="pa-4 d-flex flex-column ga-3">
          <v-text-field
            v-model="editingVehicleTypeName"
            density="compact"
            hide-details
            :label="copy.vehicleTypeName"
            variant="outlined"
          />
        </v-card-text>
        <v-card-actions class="px-4 pb-4">
          <v-spacer />
          <v-btn :disabled="vehicleTypeEditLoading" variant="text" @click="cancelEditingVehicleType">{{ copy.cancel }}</v-btn>
          <v-btn color="primary" :loading="vehicleTypeEditLoading" @click="saveVehicleType">{{ copy.save }}</v-btn>
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

  </div>
</template>

<script setup>
  import L from 'leaflet'
  import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
  import { useRouter } from 'vue-router'
  import MapViewSwitch from '@/components/MapViewSwitch.vue'
  import RentalFilterGroups from '@/components/RentalFilterGroups.vue'
  import RentalPointList from '@/components/RentalPointList.vue'
  import RentalSlideOver from '@/components/RentalSlideOver.vue'
  import RentalVehicleCard from '@/components/RentalVehicleCard.vue'
  import { AUTH_ROUTE, buildCompanyRoute } from '@/router/paths'
  import { getAuthHeaders, syncCurrentUser } from '@/services/auth'
  import { useLoadingState } from '@/stores/loading'
  import { useLocale } from '@/stores/locale'
  import { useNotifications } from '@/stores/notifications'
  import { activeRentalFilters, createRentalFilters, filterRentalPoints, hasValidPriceRange, sortRentalVehicles } from '@/utils/rentalFilters'
  import 'leaflet/dist/leaflet.css'

  const router = useRouter()
  const { t, getIntlLocale, getDatePickerLocale } = useLocale()
  const API_BASE = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000'
  const { notifyError, notifySuccess, notifyWarning, notifyInfo } = useNotifications()
  const copy = computed(() => t('map'))
  const datePickerLocale = computed(() => getDatePickerLocale())
  const intlLocale = computed(() => getIntlLocale())

  // Lapas pamatstāvoklis: lietotājs, dati, ielāde un panelis.
  const user = ref(null)
  const transportItems = ref([])
  const transportTypes = ref([])
  const loading = ref(false)
  const initialLoading = ref(true)
  const isWorkspaceLoading = computed(() => initialLoading.value || loading.value)
  const errorText = ref('')
  const viewMode = ref('map')
  const mobileQuery = window.matchMedia('(max-width: 767px)')
  const isMobile = ref(mobileQuery.matches)
  const mapPanelOpen = ref(!mobileQuery.matches)
  const listPanelOpen = ref(false)
  const panelOpen = computed({
    get: () => viewMode.value === 'map' && mapPanelOpen.value,
    set: value => {
      mapPanelOpen.value = value
    },
  })
  const providerDialog = ref(false)
  const draftFilters = ref(createRentalFilters())
  const sortOrder = ref('default')
  const userPosition = ref(null)
  const locating = ref(false)
  const mapViewport = ref(null)
  const panelElement = ref(null)
  const panelToggle = ref(null)
  const panelScroll = ref(null)
  const panelCatalog = ref(null)
  const detailVehicle = ref(null)

  const filters = ref(createRentalFilters())
  const activeFilterKeys = computed(() => activeRentalFilters(filters.value))
  const filterOptions = computed(() => ({ typeOptions: typeOptions.value, companyOptions: companyOptions.value, gearboxOptions: publicGearboxOptions.value, fuelOptions: publicFuelOptions.value }))
  const sortOptions = computed(() => copy.value.sortOptions)

  // Izveido sākotnējo rezervācijas periodu (no tagad+30min līdz nākamajai dienai).
  function buildDefaultReservationWindow () {
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

  function toIsoDateFromDate (value) {
    const year = value.getFullYear()
    const month = String(value.getMonth() + 1).padStart(2, '0')
    const day = String(value.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
  }

  function toTimeFromDate (value) {
    const hours = String(value.getHours()).padStart(2, '0')
    const minutes = String(value.getMinutes()).padStart(2, '0')
    return `${hours}:${minutes}`
  }

  function getRoundedCurrentTime () {
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
  function splitDateTime (value) {
    if (!value) return { date: '', time: '00:00' }
    const [date, time] = value.split('T')
    return { date: date || '', time: (time || '00:00').slice(0, 5) }
  }

  function combineDateTime (date, time) {
    if (!date) return ''
    return `${date}T${(time || '00:00').slice(0, 5)}`
  }

  function normalizePickedDate (value) {
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

  function onStartDatePicked (value) {
    const isoDate = normalizePickedDate(value)
    if (!isoDate) return
    reservationStartDate.value = isoDate
    startDatePickerMenu.value = false
  }

  function onEndDatePicked (value) {
    const isoDate = normalizePickedDate(value)
    if (!isoDate) return
    reservationEndDate.value = isoDate
    endDatePickerMenu.value = false
  }

  function toDisplayDate (isoDate) {
    if (!isoDate) return ''
    const [year, month, day] = isoDate.split('-')
    if (!year || !month || !day) return ''
    return `${day}/${month}/${year}`
  }

  function toIsoDate (displayDate) {
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
  const vehicleTypeEditLoading = ref(false)
  const editingVehicleTypeId = ref(null)
  const editingVehicleTypeName = ref('')
  const vehicleTypeDialog = ref(false)

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
  useLoadingState(isWorkspaceLoading, providerLoading, vehicleTypeLoading, vehicleTypeEditLoading, reservationLoading, paymentLoading, editLoading, deleteVehicleLoading, reviewLoading, reviewSaving)

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

      switch (snackbar.value.color) {
        case 'success': {
          notifySuccess(snackbar.value.text)

          break
        }
        case 'warning': {
          notifyWarning(snackbar.value.text)

          break
        }
        case 'info': {
          notifyInfo(snackbar.value.text)

          break
        }
        default: {
          notifyError(snackbar.value.text)
        }
      }

      snackbar.value = { ...snackbar.value, show: false }
    },
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

  const userTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'Europe/Riga'

  const isClient = computed(() => user.value?.loma === 'klients')
  const isProvider = computed(() => user.value?.loma === 'pakalpojumu_sniedzejs')
  const clientId = computed(() => user.value?.klients?.klients_id || null)

  const providerId = computed(() =>
    user.value?.pakalpojumuSniedzejs?.sniedzejs_id || user.value?.pakalpojumu_sniedzejs?.sniedzejs_id || null,
  )

  const typeOptions = computed(() => transportTypes.value.map(type => ({ title: type.nosaukums, value: type.veids_id })))
  const vehicleTypeSelectOptions = computed(() => transportTypes.value.map(type => ({ title: type.nosaukums, value: type.veids_id })))
  const selectedVehicleTypeToEdit = computed(() => transportTypes.value.find(type => type.veids_id === editingVehicleTypeId.value) || null)
  const publicGearboxOptions = computed(() => buildVehicleAttributeOptions(transportItems.value, 'atrumkarba'))
  const publicFuelOptions = computed(() => buildVehicleAttributeOptions(transportItems.value, 'degvielas_veids'))

  function toValidCoordinate (value, type) {
    if (value === null || value === undefined || value === '') return null
    const num = Number(value)
    if (!Number.isFinite(num)) return null
    if (type === 'lat' && (num < -90 || num > 90)) return null
    if (type === 'lng' && (num < -180 || num > 180)) return null
    return num
  }

  function normalizeVehicleField (value) {
    if (value === null || value === undefined) return ''
    const normalized = String(value).trim()
    return normalized && normalized !== '-' ? normalized : ''
  }

  function buildVehicleAttributeOptions (vehicles, fieldName) {
    const map = new Map()
    for (const vehicle of vehicles) {
      const value = normalizeVehicleField(vehicle?.[fieldName])
      if (!value || map.has(value)) continue
      map.set(value, { title: value, value })
    }
    return Array.from(map.values())
  }

  function buildVehicleDetails (vehicle) {
    return [
      { label: copy.value.vehicleFields.gearbox, value: normalizeVehicleField(vehicle?.atrumkarba), isRegistration: false },
      { label: copy.value.vehicleFields.fuel, value: normalizeVehicleField(vehicle?.degvielas_veids), isRegistration: false },
      { label: copy.value.vehicleFields.registrationNumber, value: normalizeVehicleField(vehicle?.registracijas_numurs), isRegistration: true },
    ].filter(detail => detail.value)
  }

  // Pārvērš API transporta sarakstu kompāniju punktos kartei/sarakstam.
  function formatProviderAddress (provider) {
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

  function buildVehicleReviewStats (vehicles) {
    const reviewCount = vehicles.reduce((total, vehicle) => total + Number(vehicle.atsauksmju_skaits || 0), 0)
    if (!reviewCount) {
      return {
        reviewCount: 0,
        reviewAverage: 0,
      }
    }

    const weightedTotal = vehicles.reduce((total, vehicle) => {
      const count = Number(vehicle.atsauksmju_skaits || 0)
      const average = Number(vehicle.videjais_vertejums || 0)
      return total + (average * count)
    }, 0)

    return {
      reviewCount,
      reviewAverage: weightedTotal / reviewCount,
    }
  }

  const points = computed(() => {
    const map = new Map()
    for (const item of transportItems.value) {
      const sniedzejs = item.sniedzejs
      if (!sniedzejs) continue
      const id = sniedzejs.sniedzejs_id
      if (!map.has(id)) {
        const lat = toValidCoordinate(sniedzejs.latitude, 'lat')
        const lng = toValidCoordinate(sniedzejs.longitude, 'lng')
        const persona = sniedzejs.persona
        const name = persona ? `${persona.vards || ''} ${persona.uzvards || ''}`.trim() : `${copy.value.providerFallback} #${id}`
        const address = formatProviderAddress(sniedzejs)
        map.set(id, {
          id,
          name,
          address: address || copy.value.addressMissing,
          city: sniedzejs.pilseta || copy.value.cityMissing,
          geocodeQuery: address && sniedzejs.pilseta ? `${address}, ${sniedzejs.pilseta}, Latvia` : '',
          lat,
          lng,
          hasCoords: lat !== null && lng !== null,
          vehicles: [],
        })
      }
      map.get(id).vehicles.push(item)
    }

    return Array.from(map.values()).map(point => ({
      ...point,
      ...buildVehicleReviewStats(point.vehicles),
      availableCount: point.vehicles.filter(v => isVehicleAvailable(v)).length,
    }))
  })

  const companyOptions = computed(() => points.value.map(point => ({ title: point.name, value: point.id })))

  function pointsForFilters (value) {
    return filterRentalPoints(points.value, value, { isAvailable: isVehicleAvailable, position: userPosition.value, coordinates: pointCoordsOverride.value })
  }
  const filteredPoints = computed(() => pointsForFilters(filters.value))
  const draftResultCount = computed(() => {
    const draftPoints = pointsForFilters(draftFilters.value)
    const selected = draftPoints.find(point => point.id === selectedPointId.value)
    // An explicit provider change replaces the current point selection.
    const scope = !draftFilters.value.companyId && selected ? [selected] : draftPoints
    return scope.reduce((sum, point) => sum + point.vehicles.length, 0)
  })
  const vehicleProviders = computed(() => new Map(points.value.flatMap(point => point.vehicles.map(vehicle => [vehicle.transportlidzeklis_id, point]))))

  const selectedPointId = ref(null)
  const selectedPoint = computed(() => filteredPoints.value.find(point => point.id === selectedPointId.value) || null)
  const catalogVehicles = computed(() => sortRentalVehicles(selectedPoint.value?.vehicles || filteredPoints.value.flatMap(point => point.vehicles), sortOrder.value, isVehicleAvailable, intlLocale.value))
  const selectedPointVehiclePage = ref(1)
  const pointCoordsOverride = ref({})
  const vehiclesPerPage = 12

  const selectedPointVehiclePageCount = computed(() => {
    const total = catalogVehicles.value.length
    return Math.max(1, Math.ceil(total / vehiclesPerPage))
  })

  const pagedSelectedPointVehicles = computed(() => {
    const vehicles = catalogVehicles.value
    const start = (selectedPointVehiclePage.value - 1) * vehiclesPerPage
    return vehicles.slice(start, start + vehiclesPerPage)
  })

  // Missing coordinates are supplied only by a successful address lookup.
  const mappablePoints = computed(() => {
    return filteredPoints.value.map(point => {
      const fallback = pointCoordsOverride.value[point.id]
      const lat = point.hasCoords ? point.lat : fallback?.lat ?? null
      const lng = point.hasCoords ? point.lng : fallback?.lng ?? null
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
  let mapResizeObserver = null
  let mapResizeFrame = null
  let disposed = false
  const geocodeController = new AbortController()
  const attemptedGeocodes = new Set()

  function formatPrice (value) {
    const num = Number(value || 0)
    return `${num.toFixed(2)} €`
  }

  function formatDateTime (value) {
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

  function formatApiDateTime (value) {
    const date = value instanceof Date ? value : new Date(value)
    if (Number.isNaN(date.getTime())) return null
    return date.toISOString()
  }

  function getReservationRange () {
    const start = new Date(reservation.value.startAt)
    const end = new Date(reservation.value.endAt)
    return { start, end }
  }

  function getBillableDays () {
    if (!isReservationFormValid.value) return 1
    const { start, end } = getReservationRange()
    const diffMs = end.getTime() - start.getTime()
    if (!Number.isFinite(diffMs) || diffMs <= 0) return 1
    return Math.max(1, Math.ceil(diffMs / (24 * 60 * 60 * 1000)))
  }

  function isVehicleAvailable (vehicle) {
    if (!vehicle) return false
    if (vehicle.statuss !== 'pieejams') return false
    const now = new Date()
    if (!vehicle.rezervacijas || vehicle.rezervacijas.length === 0) return true
    return !vehicle.rezervacijas.some(res => {
      const rStart = new Date(res.sakuma_laiks)
      const rEnd = new Date(res.beigu_laiks)
      if (Number.isNaN(rStart.getTime()) || Number.isNaN(rEnd.getTime())) return false
      return rStart <= now && rEnd > now && res.apmaksas_statuss === 'apmaksata'
    })
  }

  function upsertReservationInTransportState (reservationItem) {
    if (!reservationItem?.transportlidzeklis_id || !reservationItem?.rezervacija_id) return

    transportItems.value = transportItems.value.map(vehicle => {
      if (vehicle.transportlidzeklis_id !== reservationItem.transportlidzeklis_id) {
        return vehicle
      }

      const currentReservations = Array.isArray(vehicle.rezervacijas) ? vehicle.rezervacijas : []
      const existingIndex = currentReservations.findIndex(
        item => item.rezervacija_id === reservationItem.rezervacija_id,
      )

      if (existingIndex !== -1) {
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

  function openMapTools () {
    if (viewMode.value === 'list') {
      draftFilters.value = { ...filters.value }
      listPanelOpen.value = true
    } else {
      panelOpen.value = true
      nextTick(() => {
        if (panelScroll.value) panelScroll.value.scrollTop = 0
        panelElement.value?.querySelector('input')?.focus()
      })
    }
  }

  function applyListFilters () {
    if (!hasValidPriceRange(draftFilters.value)) return
    const providerChanged = draftFilters.value.companyId !== filters.value.companyId
    if (providerChanged) selectedPointId.value = null
    filters.value = { ...draftFilters.value }
    listPanelOpen.value = false
  }

  function removeFilter (key) {
    filters.value[key] = createRentalFilters()[key]
  }

  function filterChipLabel (key) {
    const value = filters.value[key]
    if (key === 'q') return value.trim()
    if (key === 'typeId') return typeOptions.value.find(option => option.value === value)?.title || copy.value.filterLabels.type
    if (key === 'companyId') return companyOptions.value.find(option => option.value === value)?.title || copy.value.filterLabels.provider
    if (key === 'onlyAvailable') return copy.value.quickAvailable
    if (key === 'nearby') return copy.value.quickNearby
    if (key === 'minPrice' || key === 'maxPrice') return `${copy.value.filterLabels[key]}: ${formatPrice(value)}`
    return `${copy.value.filterLabels[key]}: ${value}`
  }

  function requestLocation (onSuccess) {
    if (!navigator.geolocation) {
      notifyWarning(copy.value.locationFailed)
      return
    }
    locating.value = true
    navigator.geolocation.getCurrentPosition(position => {
      if (disposed) return
      locating.value = false
      userPosition.value = { lat: position.coords.latitude, lng: position.coords.longitude }
      onSuccess(userPosition.value)
    }, () => {
      if (disposed) return
      locating.value = false
      notifyWarning(copy.value.locationFailed)
    }, { timeout: 12_000, maximumAge: 60_000 })
  }

  function clearPointSelection () {
    selectedPointId.value = null
    filters.value.companyId = null
    mapInstance?.closePopup()
  }

  function resetFilters () {
    filters.value = createRentalFilters()
    clearPointSelection()
  }

  function hasPointCoordinates (id) {
    return mappablePoints.value.some(point => point.id === id && point.hasCoords)
  }

  function focusSelectedMarker (animate = false) {
    if (!mapInstance || viewMode.value !== 'map') return
    const marker = markers.find(item => item.options.pointId === selectedPointId.value)
    if (!marker) return
    if (animate) {
      const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
      mapInstance.setView(marker.getLatLng(), Math.max(mapInstance.getZoom(), 13), { animate: !reduced, duration: 0.25 })
    }
    marker.openPopup()
  }

  async function selectPoint (id) {
    if (id === undefined || id === null) return
    selectedPointId.value = id
    selectedPointVehiclePage.value = 1
    if (viewMode.value === 'map') panelOpen.value = true
    await nextTick()
    invalidateMapSize()
    updateMarkerSelection()
    focusSelectedMarker(true)
    // Scroll only the panel; the page and site header stay in place.
    if (panelCatalog.value && panelScroll.value) panelScroll.value.scrollTo({ top: panelCatalog.value.offsetTop - panelScroll.value.offsetTop, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' })
  }

  function handlePanelKeydown (event) {
    if (event.key === 'Escape') panelOpen.value = false
    if (!isMobile.value || event.key !== 'Tab') return
    const focusable = [...panelElement.value.querySelectorAll('button, input, [tabindex="0"]')].filter(element => !element.disabled && element.getClientRects().length > 0)
    const first = focusable[0]
    const last = focusable.at(-1)
    if (event.shiftKey && (document.activeElement === first || document.activeElement === panelElement.value)) {
      event.preventDefault()
      last?.focus()
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault()
      first?.focus()
    }
  }

  function openCompany (companyId) {
    router.push(buildCompanyRoute(companyId))
  }

  function isOwnVehicle (vehicle) {
    return providerId.value && vehicle.sniedzejs_id === providerId.value
  }

  function reviewerName (review) {
    const persona = review?.klients?.persona
    if (!persona) return 'Klients'
    const fullName = `${persona.vards || ''} ${persona.uzvards || ''}`.trim()
    return fullName || 'Klients'
  }

  function formatReviewDate (value) {
    if (!value) return '—'
    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return '—'
    return new Intl.DateTimeFormat(intlLocale.value, {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
    }).format(date)
  }

  function resetReviewForm () {
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

  function startEditingVehicleType () {
    if (!selectedVehicleTypeToEdit.value) return
    editingVehicleTypeName.value = selectedVehicleTypeToEdit.value.nosaukums || ''
    vehicleTypeError.value = ''
    vehicleTypeSuccess.value = ''
    vehicleTypeDialog.value = true
  }

  function cancelEditingVehicleType () {
    editingVehicleTypeName.value = ''
    vehicleTypeDialog.value = false
  }

  async function loadVehicleReviews (transportlidzeklisId) {
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

  async function openReviews (vehicle) {
    reviewVehicle.value = vehicle
    reviewSuccess.value = ''
    reviewError.value = ''
    reviewDialog.value = true
    await loadVehicleReviews(vehicle.transportlidzeklis_id)
  }

  async function submitReview () {
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

  function openReservation (vehicle) {
    if (!user.value) {
      snackbar.value = { show: true, text: copy.value.loginToReserve, color: 'error' }
      router.push(AUTH_ROUTE)
      return
    }
    if (!isClient.value) {
      snackbar.value = { show: true, text: copy.value.messages.reservationClientOnly, color: 'error' }
      return
    }
    activeVehicle.value = vehicle
    reservationError.value = ''
    reservationDialog.value = true
  }

  // Izveido rezervāciju un pēc tam atver apmaksas izvēli.
  async function createReservation () {
    if (!activeVehicle.value || !user.value?.klients?.klients_id) {
      reservationError.value = copy.value.messages.clientDataMissing
      return
    }

    if (!isReservationFormValid.value) {
      reservationError.value = copy.value.messages.invalidPeriod
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
        reservationError.value = result?.message || copy.value.messages.createReservationFailed
        return
      }

      pendingReservation.value = result
      reservationDialog.value = false
      paymentDialog.value = true
    } catch {
      reservationError.value = copy.value.messages.createReservationError
    } finally {
      reservationLoading.value = false
    }
  }

  // Apmaksas darbības atjauno rezervācijas statusu lokālajā stāvoklī.
  async function confirmPayment () {
    if (!pendingReservation.value || !user.value?.klients?.klients_id) return

    paymentLoading.value = true
    paymentError.value = ''
    paymentSuccess.value = ''

    try {
      const response = await fetch(`${API_BASE}/api/rezervacijas/${pendingReservation.value.rezervacija_id}/pay`, {
        method: 'POST',
        headers: getAuthHeaders({ 'Content-Type': 'application/json' }),
      })

      const result = await response.json()
      if (!response.ok) {
        paymentError.value = result?.message || copy.value.messages.paymentFailed
        return
      }

      paymentSuccess.value = copy.value.messages.paymentSuccess
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
    } catch {
      paymentError.value = copy.value.messages.paymentProcessingError
    } finally {
      paymentLoading.value = false
    }
  }

  function closePaymentDialog () {
    paymentDialog.value = false
    if (pendingReservation.value?.apmaksas_statuss !== 'apmaksata') {
      snackbar.value = {
        show: true,
        text: copy.value.messages.reservationCreatedUnpaid,
        color: 'warning',
      }
    }
  }

  function payLater () {
    closePaymentDialog()
  }

  async function cancelPendingReservation () {
    if (!pendingReservation.value?.rezervacija_id || !user.value?.klients?.klients_id) {
      paymentDialog.value = false
      return
    }

    paymentLoading.value = true
    paymentError.value = ''

    try {
      const response = await fetch(`${API_BASE}/api/rezervacijas/${pendingReservation.value.rezervacija_id}`, {
        method: 'DELETE',
        headers: getAuthHeaders({ 'Content-Type': 'application/json' }),
      })
      const data = await response.json()
      if (!response.ok) {
        paymentError.value = data?.message || copy.value.messages.cancelReservationFailed
        return
      }

      pendingReservation.value = null
      paymentDialog.value = false
      cancelDialog.value = false
      snackbar.value = { show: true, text: copy.value.messages.reservationCanceledAvailable, color: 'success' }
      await loadTransport()
    } catch {
      paymentError.value = copy.value.messages.cancelReservationError
    } finally {
      paymentLoading.value = false
    }
  }

  function openEditVehicle (vehicle) {
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

  async function saveVehicle () {
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

  async function deleteVehicle () {
    editDialog.value = false
    nextTick().then(() => {
      deleteVehicleDialog.value = true
    })
  }

  function cancelDeleteVehicle () {
    deleteVehicleDialog.value = false
    nextTick().then(() => {
      editDialog.value = true
    })
  }

  async function confirmDeleteVehicle () {
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

  async function submitVehicle () {
    providerLoading.value = true
    providerError.value = ''
    providerSuccess.value = ''

    if (!providerId.value) {
      providerError.value = copy.value.messages.providerMissing
      providerLoading.value = false
      return
    }

    try {
      const payload = {
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
        headers: getAuthHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify(payload),
      })

      const result = await response.json()
      if (!response.ok) {
        providerError.value = result?.message || copy.value.messages.addVehicleFailed
        return
      }

      providerSuccess.value = copy.value.messages.vehicleAdded
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
    } catch {
      providerError.value = copy.value.messages.addVehicleError
    } finally {
      providerLoading.value = false
    }
  }

  async function submitVehicleType () {
    vehicleTypeLoading.value = true
    vehicleTypeError.value = ''
    vehicleTypeSuccess.value = ''

    const nosaukums = (newVehicleType.value.nosaukums || '').trim()
    if (!nosaukums) {
      vehicleTypeError.value = copy.value.messages.vehicleTypeNameRequired
      vehicleTypeLoading.value = false
      return
    }

    try {
      const response = await fetch(`${API_BASE}/api/transport/veidi`, {
        method: 'POST',
        headers: getAuthHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ nosaukums }),
      })

      const result = await response.json()
      if (!response.ok) {
        vehicleTypeError.value = result?.message || copy.value.messages.addVehicleTypeFailed
        return
      }

      vehicleTypeSuccess.value = copy.value.messages.vehicleTypeAdded
      newVehicleType.value = { nosaukums: '' }
      newVehicle.value.veids_id = result?.veids_id ?? null
      await loadTypes()
    } catch {
      vehicleTypeError.value = copy.value.messages.addVehicleTypeError
    } finally {
      vehicleTypeLoading.value = false
    }
  }

  async function saveVehicleType () {
    if (!editingVehicleTypeId.value) {
      vehicleTypeError.value = copy.value.messages.selectVehicleType
      return
    }

    const nosaukums = (editingVehicleTypeName.value || '').trim()
    if (!nosaukums) {
      vehicleTypeError.value = copy.value.messages.vehicleTypeNameRequired
      return
    }

    vehicleTypeEditLoading.value = true
    vehicleTypeError.value = ''
    vehicleTypeSuccess.value = ''

    try {
      const response = await fetch(`${API_BASE}/api/transport/veidi/${editingVehicleTypeId.value}`, {
        method: 'PUT',
        headers: getAuthHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ nosaukums }),
      })

      const result = await response.json()
      if (!response.ok) {
        vehicleTypeError.value = result?.message || copy.value.messages.updateVehicleTypeFailed
        return
      }

      vehicleTypeSuccess.value = copy.value.messages.updateVehicleTypeSuccess
      cancelEditingVehicleType()
      await loadTypes()
    } catch {
      vehicleTypeError.value = copy.value.messages.updateVehicleTypeError
    } finally {
      vehicleTypeEditLoading.value = false
    }
  }

  // Datu ielāde no API.
  async function loadTransport () {
    loading.value = true
    errorText.value = ''
    try {
      const response = await fetch(`${API_BASE}/api/transport`)
      const data = await response.json()
      if (!response.ok) {
        errorText.value = data?.message || copy.value.messages.loadTransportFailed
        return
      }
      transportItems.value = data
    } catch {
      errorText.value = copy.value.messages.loadTransportError
    } finally {
      loading.value = false
    }
  }

  async function loadTypes () {
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

  function invalidateMapSize () {
    if (!mapInstance || viewMode.value !== 'map' || !mapViewport.value?.clientWidth) return
    mapInstance.invalidateSize({ pan: true, animate: false })
  }

  function updateMarkerSelection () {
    for (const marker of markers) {
      const selected = marker.options.pointId === selectedPointId.value
      marker.getElement()?.classList.toggle('is-selected', selected)
      marker.setZIndexOffset(selected ? 1000 : 0)
    }
  }

  function initMap () {
    if (mapInstance || disposed) return
    mapInstance = L.map('map', { zoomControl: false }).setView([56.8796, 24.6032], 7)
    L.control.zoom({ position: 'bottomright' }).addTo(mapInstance)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(mapInstance)
    mapResizeObserver = new ResizeObserver(() => {
      cancelAnimationFrame(mapResizeFrame)
      mapResizeFrame = requestAnimationFrame(invalidateMapSize)
    })
    mapResizeObserver.observe(mapViewport.value)
  }

  // Pārzīmē marķierus pēc aktīvajiem filtriem un punktiem.
  function renderMarkers () {
    if (!mapInstance || disposed) return
    for (const marker of markers) {
      marker.off()
      marker.remove()
    }
    markers = mappablePoints.value.filter(point => point.hasCoords).map(point => {
      const marker = L.marker([point.lat, point.lng], {
        pointId: point.id,
        icon: L.divIcon({ className: 'marker-point', html: '<span></span>', iconSize: [24, 24], iconAnchor: [12, 12] }),
        title: point.name,
        alt: point.name,
      }).addTo(mapInstance)
      // API data is text, never interpolated HTML. Each button has one handler.
      const content = document.createElement('div')
      content.className = 'map-popup-content'
      const name = document.createElement('strong')
      name.textContent = point.name
      const address = document.createElement('div')
      address.textContent = `${point.address}, ${point.city}`
      const count = document.createElement('div')
      count.textContent = copy.value.popupVehicles.replace('{count}', point.vehicles.length).replace('{available}', point.availableCount)
      const button = document.createElement('button')
      button.type = 'button'
      button.className = 'popup-company-link'
      button.textContent = copy.value.viewProvider
      button.addEventListener('click', () => openCompany(point.id))
      content.append(name, address, count, button)
      marker.bindPopup(content, { autoPan: false })
      marker.on('click', () => selectPoint(point.id))
      return marker
    })
    updateMarkerSelection()
    focusSelectedMarker()
  }

  // Mēģina atrast koordinātas punktiem, kuriem tās nav saglabātas.
  async function resolveMissingPointCoords () {
    const toResolve = points.value.filter(point => !point.hasCoords && !pointCoordsOverride.value[point.id] && !attemptedGeocodes.has(point.id))

    for (const point of toResolve) {
      if (disposed) return
      if (attemptedGeocodes.has(point.id)) continue
      if (!point.geocodeQuery) continue
      attemptedGeocodes.add(point.id)

      try {
        const query = encodeURIComponent(point.geocodeQuery)
        const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${query}`, { signal: geocodeController.signal })
        if (!response.ok) continue
        const result = await response.json()
        const first = result?.[0]
        if (!first || disposed) continue
        const lat = toValidCoordinate(first.lat, 'lat')
        const lng = toValidCoordinate(first.lon, 'lng')
        if (lat === null || lng === null) continue

        pointCoordsOverride.value = {
          ...pointCoordsOverride.value,
          [point.id]: {
            lat,
            lng,
          },
        }
      } catch {
        continue
      }
    }
  }

  function locateUser () {
    requestLocation(position => mapInstance?.setView([position.lat, position.lng], 12))
  }

  function resetMap () {
    if (!mapInstance) return
    mapInstance.setView([56.8796, 24.6032], 7)
  }

  function handleViewportChange (event) {
    isMobile.value = event.matches
    mapPanelOpen.value = !event.matches
    listPanelOpen.value = false
  }

  function handleUserUpdated (event) {
    user.value = event.detail
  }

  onMounted(() => {
    mobileQuery.addEventListener('change', handleViewportChange)
    window.addEventListener('user-updated', handleUserUpdated)
    initMap()
    renderMarkers()
    void syncCurrentUser().then(value => {
      if (!disposed) user.value = value
    })
    void Promise.all([loadTypes(), loadTransport()]).finally(() => {
      if (!disposed) initialLoading.value = false
    })
  })

  onBeforeUnmount(() => {
    disposed = true
    mobileQuery.removeEventListener('change', handleViewportChange)
    window.removeEventListener('user-updated', handleUserUpdated)
    geocodeController.abort()
    mapResizeObserver?.disconnect()
    cancelAnimationFrame(mapResizeFrame)
    clearTimeout(paymentSuccessTimer)
    for (const marker of markers) marker.off()
    markers = []
    mapInstance?.remove()
    mapInstance = null
  })

  watch(points, resolveMissingPointCoords)
  watch([mappablePoints, copy], renderMarkers)

  watch(filteredPoints, () => {
    if (selectedPointId.value !== null && !selectedPoint.value) selectedPointId.value = null
  })
  watch(filters, () => {
    selectedPointVehiclePage.value = 1
  }, { deep: true })
  watch(sortOrder, () => {
    selectedPointVehiclePage.value = 1
  })
  watch(viewMode, () => {
    listPanelOpen.value = false
  })
  watch(() => filters.value.companyId, id => {
    if (id && filteredPoints.value.some(point => point.id === id)) void selectPoint(id)
    else if (!id) selectedPointId.value = null
  })
  watch(selectedPointId, () => {
    selectedPointVehiclePage.value = 1
    updateMarkerSelection()
    if (!selectedPoint.value) mapInstance?.closePopup()
  })
  watch([viewMode, panelOpen], async ([mode, open], previous) => {
    await nextTick()
    invalidateMapSize()
    if (mode === 'map' && previous?.[0] !== mode) focusSelectedMarker(true)
    if (isMobile.value && open) panelElement.value?.focus({ preventScroll: true })
    else if (isMobile.value && previous?.[1]) panelToggle.value?.$el?.focus({ preventScroll: true })
  })

  watch(selectedPointVehiclePageCount, pageCount => {
    if (selectedPointVehiclePage.value > pageCount) {
      selectedPointVehiclePage.value = pageCount
    }
  })

  watch(availableStartTimeOptions, options => {
    if (options.length === 0) return
    if (!options.includes(reservationStartTime.value)) {
      reservationStartTime.value = options[0]
    }
  })

  watch(availableEndTimeOptions, options => {
    if (options.length === 0) return
    if (!options.includes(reservationEndTime.value)) {
      reservationEndTime.value = options[0]
    }
  })

</script>

<style scoped>
.map-page { position: relative; isolation: isolate; display: flex; flex: 1; flex-direction: column; min-height: 0; min-width: 0; width: 100%; color: var(--er-text); overflow: hidden; }
.map-toolbar { z-index: 1; flex-shrink: 0; padding: 14px 24px; border-bottom: 1px solid var(--er-stroke); background: var(--er-surface); }
.map-toolbar__main { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; min-width: 0; }
.map-toolbar__title { flex: 1; min-width: 160px; }
.map-toolbar h1 { font-size: 1.25rem; font-weight: 800; line-height: 1.3; }
.map-toolbar__title > span { display: block; margin-top: 4px; color: var(--er-text-muted); font-size: .75rem; }
.desktop-view-switch { padding: 3px; }
.desktop-view-switch :deep(button) { min-height: 36px; padding-inline: 16px; font-size: .8rem; }
.toolbar-sort { flex: 0 1 200px; min-width: 160px; }
.toolbar-count { display: inline-grid; place-items: center; margin-left: 8px; min-width: 22px; height: 22px; border-radius: 50%; background: var(--er-primary-strong); color: var(--er-on-primary); font-size: .7rem; }
.active-filters { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; padding-top: 12px; max-height: 100px; overflow-y: auto; }
.active-filters :deep(.v-chip) { max-width: 100%; }
.active-filters :deep(.v-chip__content) { overflow: hidden; text-overflow: ellipsis; display: block; }
.map-body { position: relative; display: grid; grid-template-columns: minmax(0, 1fr); flex: 1; min-height: 0; min-width: 0; overflow: hidden; }
.map-body.has-panel { grid-template-columns: 380px minmax(0, 1fr); }
.map-viewport, .list-catalog { grid-row: 1; grid-column: -2 / -1; min-width: 0; min-height: 0; }
.map-viewport { position: relative; background: var(--er-bg-soft); overflow: hidden; isolation: isolate; }
.map-canvas { height: 100%; width: 100%; }
.workspace-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
.workspace-stats > div { display: grid; gap: 5px; text-align: center; }
.workspace-stats strong { color: var(--er-primary-strong); font: 800 1.4rem var(--er-display-font); }
.workspace-stats span { color: var(--er-text-muted); font-size: .7rem; overflow-wrap: anywhere; }
.workspace-view-switch { flex-shrink: 0; padding: 16px 20px 0; }
.workspace-view-switch .desktop-view-switch { width: 100%; }
.workspace-view-switch :deep(button) { flex: 1; }
.workspace-tools { display: flex; align-items: center; gap: 8px; margin-bottom: 20px; }
.workspace-sort { min-width: 0; flex: 1; }
.map-workspace .active-filters { padding-top: 0; padding-bottom: 16px; max-height: none; overflow: visible; }
.map-menu-btn { position: absolute; top: 16px; left: 16px; z-index: 500; min-height: 44px; border-radius: 999px; box-shadow: var(--er-shadow-sm); }
.workspace-stats { padding: 0 0 20px; margin-bottom: 22px; border-bottom: 1px solid var(--er-stroke); }
.map-locate-btn { position: absolute; right: 16px; bottom: 132px; z-index: 500; display: grid; place-items: center; width: 44px; height: 44px; border: 1px solid var(--er-stroke); border-radius: 12px; background: var(--er-surface); color: var(--er-text); box-shadow: var(--er-shadow-sm); cursor: pointer; }
.map-locate-btn:hover { background: var(--er-bg-soft); }
:deep(.leaflet-control-zoom) { border: 1px solid var(--er-stroke) !important; border-radius: 12px; overflow: hidden; box-shadow: var(--er-shadow-sm); margin: 12px 16px 28px !important; }
:deep(.leaflet-control-attribution) { line-height: 16px; max-width: 100%; }
:deep(.leaflet-control-zoom a) { width: 42px; height: 42px; line-height: 42px; background: var(--er-surface); color: var(--er-text); border-color: var(--er-stroke); font-size: 22px; }
:deep(.leaflet-popup-content-wrapper), :deep(.leaflet-popup-tip) { background: var(--er-surface); color: var(--er-text); }
:deep(.leaflet-popup-content) { font-family: var(--er-body-font); line-height: 1.6; }
.map-workspace { grid-column: 1; grid-row: 1; display: flex; flex-direction: column; min-height: 0; min-width: 0; overflow: hidden; border-right: 1px solid var(--er-stroke); background: var(--er-surface); }
.drawer-header { display: flex; justify-content: space-between; align-items: start; gap: 8px; flex-shrink: 0; padding: 22px 20px 16px; }
.drawer-header-main { min-width: 0; }
.header-title { font: 800 1.15rem var(--er-display-font) !important; }
.header-subtitle { color: var(--er-text-muted); margin-top: 6px; font-size: .8rem; }
.header-close-btn { flex-shrink: 0; color: var(--er-text-muted); margin-top: -8px; }
.map-search { flex-shrink: 0; padding: 0 20px 20px; }
.drawer-scroll { position: relative; flex: 1; min-height: 0; overflow-y: auto; overscroll-behavior: contain; padding: 0 20px 24px; scrollbar-width: thin; }
.points-section { margin-top: 26px; }
.panel-catalog { margin-top: 24px; padding-top: 22px; border-top: 1px solid var(--er-stroke); }
.catalog-heading { display: flex; flex-wrap: wrap; align-items: start; justify-content: space-between; gap: 12px; margin-bottom: 20px; }
.catalog-heading h2 { font-size: 1.2rem; font-weight: 800; }
.catalog-heading p { margin-top: 6px; color: var(--er-text-muted); font-size: .8rem; overflow-wrap: anywhere; }
.list-catalog { padding: 28px; overflow-y: auto; overscroll-behavior: contain; background: var(--er-bg); }
.vehicle-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 300px), 1fr)); align-items: stretch; gap: 24px; max-width: 1600px; margin-inline: auto; }
.vehicle-grid--compact { grid-template-columns: minmax(0, 1fr); gap: 16px; }
.vehicle-pagination { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 20px 0; }
.vehicle-pagination__info { color: var(--er-text-muted); font-size: .875rem; }
.reservation-summary { display: grid; gap: 10px; padding: 20px; border-radius: 18px; background: var(--er-primary-soft); border: 1px solid var(--er-stroke); color: var(--er-text); font-size: .875rem; }
.reservation-summary > :last-child { border-top: 1px solid var(--er-stroke); padding-top: 12px; font-size: 1.15rem; font-weight: 800; }
:deep(.marker-point span) { display: block; width: 24px; height: 24px; border-radius: 50%; background: var(--er-primary-strong); border: 3px solid var(--er-marker-border); box-shadow: 0 3px 14px rgb(0 0 0 / 30%); }
:deep(.marker-point.is-selected span) { background: var(--er-secondary); outline: 4px solid var(--er-primary); outline-offset: 3px; }
:deep(.popup-company-link) { margin-top: 10px; padding: 8px 14px; border: 0; border-radius: 999px; background: var(--er-primary-strong); color: var(--er-on-primary); cursor: pointer; font: 700 .8rem var(--er-body-font); }
.mobile-view-switch { display: none; }
@media (min-width: 768px) and (max-width: 1100px) {
 .map-body.has-panel { grid-template-columns: 340px minmax(0, 1fr); }
 .map-toolbar { padding-inline: 16px; }
 .map-toolbar__title { flex-basis: 25%; }
 .toolbar-sort { flex-basis: 170px; }
}
@media (max-width: 767px) {
 .map-toolbar { padding: 10px 12px; }
 .map-toolbar__main { gap: 8px; }
 .map-toolbar h1 { font-size: 1rem; }
 .map-toolbar__title { min-width: 120px; }
 .map-toolbar__title > span { font-size: .7rem; }
 .toolbar-filter { min-height: 44px; }
 .toolbar-sort { flex: 1 1 140px; }
 .toolbar-add { flex: 1 1 auto; min-height: 44px; max-width: 100%; }
 .toolbar-add :deep(.v-btn__content) { white-space: normal; }
 .desktop-view-switch, .workspace-view-switch { display: none; }
 .mobile-view-switch { display: flex; flex-shrink: 0; padding: 8px max(12px, env(safe-area-inset-right)) calc(8px + env(safe-area-inset-bottom)) max(12px, env(safe-area-inset-left)); border-top: 1px solid var(--er-stroke); background: var(--er-surface); }
 .active-filters { max-height: 76px; }
 .map-body.has-panel { grid-template-columns: minmax(0, 1fr); }
 .map-workspace { position: absolute; z-index: 800; inset: auto 0 0; width: 100%; height: 90%; border-radius: 24px 24px 0 0; box-shadow: var(--er-shadow-lg); padding-inline: env(safe-area-inset-left) env(safe-area-inset-right); padding-bottom: env(safe-area-inset-bottom); }
 .panel-scrim { position: absolute; z-index: 750; inset: 0; background: rgb(0 0 0 / 35%); cursor: pointer; }
 .list-catalog { padding: 20px 12px; }
 .vehicle-grid { gap: 20px; }
}
@media (prefers-reduced-motion: reduce) { .map-page :deep(*) { transition-duration: .01ms !important; animation-duration: .01ms !important; scroll-behavior: auto !important; } }

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

.provider-panel :deep(.v-form:not(.mb-5)) { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 20px; }
.provider-panel :deep(.v-form:not(.mb-5) > .v-btn) { grid-column: 1 / -1; }
.provider-panel :deep(.v-input) { min-width: 0; }
@media (max-width: 699px) { .provider-panel :deep(.v-form:not(.mb-5)) { grid-template-columns: minmax(0, 1fr); } }
</style>
