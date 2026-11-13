<template>
  <div class="rental-filters">
    <v-text-field
      v-if="showSearch"
      v-model="model.q"
      clearable
      density="comfortable"
      hide-details
      :label="copy.searchPlaceholder"
      :placeholder="copy.searchPlaceholder"
      prepend-inner-icon="mdi-magnify"
      variant="outlined"
      @click:clear="model.q = ''"
    />
    <section class="filter-section">
      <h3>{{ copy.filterLabels.type }}</h3>
      <div :aria-label="copy.filterLabels.type" class="type-grid" role="group">
        <button
          v-for="type in typeOptions"
          :key="type.value"
          :aria-pressed="model.typeId === type.value"
          type="button"
          @click="model.typeId = model.typeId === type.value ? null : type.value"
        ><v-icon size="24">{{ getTransportTypeIcon(type.title) }}</v-icon><span>{{ type.title }}</span></button>
      </div>
      <p v-if="typeOptions.length === 0" class="filter-hint">{{ copy.noTypes }}</p>
    </section>
    <v-expansion-panels v-model="expanded" class="filter-expansions" multiple variant="accordion">
      <v-expansion-panel value="price">
        <v-expansion-panel-title><span>{{ copy.priceAvailability }}</span><span v-if="priceCount" class="filter-count">{{ priceCount }}</span></v-expansion-panel-title>
        <v-expansion-panel-text>
          <div class="price-inputs">
            <v-text-field
              v-model.number="model.minPrice"
              density="compact"
              hide-details
              :label="copy.filterLabels.minPrice"
              min="0"
              type="number"
              variant="outlined"
            />
            <v-text-field
              v-model.number="model.maxPrice"
              density="compact"
              hide-details
              :label="copy.filterLabels.maxPrice"
              min="0"
              type="number"
              variant="outlined"
            />
          </div>
          <p v-if="!hasValidPriceRange(model)" class="price-error" role="alert">{{ copy.invalidPriceRange }}</p>
          <v-switch
            v-model="model.onlyAvailable"
            color="primary"
            density="compact"
            hide-details
            inset
            :label="copy.quickAvailable"
          />
          <p class="filter-hint">{{ copy.availabilityHint }}</p>
        </v-expansion-panel-text>
      </v-expansion-panel>
      <v-expansion-panel value="advanced">
        <v-expansion-panel-title><span>{{ copy.additionalParameters }}</span><span v-if="advancedCount" class="filter-count">{{ advancedCount }}</span></v-expansion-panel-title>
        <v-expansion-panel-text>
          <div class="advanced-inputs">
            <v-select
              v-model="model.gearbox"
              clearable
              density="compact"
              hide-details
              item-title="title"
              item-value="value"
              :items="gearboxOptions"
              :label="copy.filterLabels.gearbox"
              variant="outlined"
            />
            <v-select
              v-model="model.fuel"
              clearable
              density="compact"
              hide-details
              item-title="title"
              item-value="value"
              :items="fuelOptions"
              :label="copy.filterLabels.fuel"
              variant="outlined"
            />
            <v-select
              v-model="model.companyId"
              clearable
              density="compact"
              hide-details
              item-title="title"
              item-value="value"
              :items="companyOptions"
              :label="copy.filterLabels.provider"
              variant="outlined"
            />
          </div>
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>
  </div>
</template>

<script setup>
  import { computed, ref } from 'vue'
  import { useLocale } from '@/stores/locale'
  import { hasValidPriceRange, normalizePriceFilter } from '@/utils/rentalFilters'
  import { getTransportTypeIcon } from '@/utils/vehicleVisuals'

  const model = defineModel({ type: Object, required: true })
  defineProps({
    typeOptions: { type: Array, default: () => [] },
    companyOptions: { type: Array, default: () => [] },
    gearboxOptions: { type: Array, default: () => [] },
    fuelOptions: { type: Array, default: () => [] },
    showSearch: { type: Boolean, default: true },
  })
  const { t } = useLocale()
  const copy = computed(() => t('map'))
  const expanded = ref([])
  const priceCount = computed(() => [normalizePriceFilter(model.value.minPrice) !== null, normalizePriceFilter(model.value.maxPrice) !== null, model.value.onlyAvailable].filter(Boolean).length)
  const advancedCount = computed(() => [model.value.gearbox, model.value.fuel, model.value.companyId].filter(Boolean).length)
</script>

<style scoped>
.rental-filters { display: grid; gap: 22px; min-width: 0; }
.filter-section h3 { margin-bottom: 12px; font-size: .9rem; }
.type-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
.type-grid button[aria-pressed='true'] { border-color: var(--er-primary-strong); background: var(--er-primary-soft); color: var(--er-primary-strong); }
.type-grid button { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; min-width: 0; min-height: 80px; padding: 10px 5px; border: 1px solid var(--er-stroke); border-radius: 14px; background: var(--er-surface); color: var(--er-text-muted); cursor: pointer; }
.type-grid span { max-width: 100%; font-size: .7rem; font-weight: 700; overflow-wrap: anywhere; }
.filter-expansions { border-block: 1px solid var(--er-stroke); }
.filter-expansions :deep(.v-expansion-panel) { margin: 0 !important; border-radius: 0 !important; background: transparent; }
.filter-expansions :deep(.v-expansion-panel__shadow) { display: none; }
.filter-expansions :deep(.v-expansion-panel-title) { min-height: 58px; padding-inline: 0; font-size: .85rem; }
.filter-expansions :deep(.v-expansion-panel-text__wrapper) { padding: 4px 0 18px; }
.filter-count { display: inline-grid; place-items: center; margin-left: 10px; min-width: 22px; height: 22px; border-radius: 50%; background: var(--er-primary-soft); color: var(--er-primary-strong); font-size: .7rem; }
.price-inputs { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
.advanced-inputs { display: grid; gap: 16px; }
.filter-hint { margin-top: 8px; color: var(--er-text-muted); font-size: .75rem; line-height: 1.6; }
.price-error { margin-top: 8px; color: rgb(var(--v-theme-error)); font-size: .75rem; }
button { transition: background-color .22s ease, border-color .22s ease, color .22s ease; }
@media (prefers-reduced-motion: reduce) { button { transition: none; } }
</style>
