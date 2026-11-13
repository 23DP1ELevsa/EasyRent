<template>
  <article class="rental-vehicle" :class="{ 'rental-vehicle--compact': compact, 'is-inactive': vehicle.statuss === 'neaktivs' }">
    <div class="rental-vehicle__visual">
      <img
        :alt="`${vehicle.marka} ${vehicle.modelis}`"
        class="rental-vehicle__image"
        height="360"
        loading="lazy"
        :src="getVehicleImage(vehicle)"
        width="480"
      >
      <span class="rental-vehicle__status" :class="{ 'is-available': available }"><span aria-hidden="true" />{{ available ? t('map.available') : vehicle.statuss === 'neaktivs' ? t('map.inactive') : t('map.busy') }}</span>
      <button
        v-if="Number(vehicle.atsauksmju_skaits) > 0"
        :aria-label="t('map.reviews')"
        class="rental-vehicle__rating"
        type="button"
        @click="$emit('reviews')"
      >
        <v-icon color="amber-darken-2" size="17">mdi-star</v-icon>{{ Number(vehicle.videjais_vertejums || 0).toFixed(1) }} <span>({{ vehicle.atsauksmju_skaits }})</span>
      </button>
    </div>
    <div class="rental-vehicle__body">
      <div class="rental-vehicle__type">{{ vehicle.veids?.nosaukums || '—' }}</div>
      <h3>{{ vehicle.marka }} {{ vehicle.modelis }}</h3>
      <button class="rental-vehicle__provider" type="button" @click="$emit('select-provider')">
        <v-icon size="16">mdi-storefront-outline</v-icon>{{ provider?.name }}
      </button>
      <div class="rental-vehicle__address"><v-icon size="16">mdi-map-marker-outline</v-icon>{{ [provider?.address, provider?.city].filter(Boolean).join(', ') }}</div>
      <div class="rental-vehicle__details">
        <span v-for="detail in visibleDetails" :key="detail.label"><v-icon size="17">{{ detail.isRegistration ? 'mdi-card-text-outline' : detail.label === t('map.vehicleFields.gearbox') ? 'mdi-car-shift-pattern' : 'mdi-gas-station-outline' }}</v-icon><span><small>{{ detail.label }}</small>{{ detail.value }}</span></span>
      </div>
      <div class="rental-vehicle__price">
        <div><strong>{{ price }}</strong><span> / {{ t('map.perDay') }}</span></div>
      </div>
      <div class="rental-vehicle__actions">
        <v-btn v-if="showDetails" variant="outlined" @click="$emit('details')">{{ t('map.vehicleDetails') }}</v-btn>
        <v-btn
          v-if="canReserve"
          color="primary"
          :disabled="!available"
          @click="$emit('reserve')"
        >{{ t('map.reserve') }}</v-btn>
        <v-btn v-if="canEdit" color="primary" variant="tonal" @click="$emit('edit')">{{ t('map.edit') }}</v-btn>
      </div>
    </div>
  </article>
</template>

<script setup>
  import { computed } from 'vue'
  import { useLocale } from '@/stores/locale'
  import { getVehicleImage } from '@/utils/vehicleVisuals'

  const props = defineProps({
    vehicle: { type: Object, required: true },
    provider: { type: Object, default: null },
    details: { type: Array, default: () => [] },
    available: Boolean,
    canReserve: Boolean,
    canEdit: Boolean,
    compact: Boolean,
    showDetails: { type: Boolean, default: true },
  })
  defineEmits(['details', 'reviews', 'reserve', 'edit', 'select-provider'])
  const { t, getIntlLocale } = useLocale()
  const visibleDetails = computed(() => props.showDetails ? props.details.filter(detail => !detail.isRegistration).slice(0, 2) : props.details)
  const price = computed(() => new Intl.NumberFormat(getIntlLocale(), { style: 'currency', currency: 'EUR' }).format(Number(props.vehicle.dienas_nomas_cena || 0)))
</script>

<style scoped>
.rental-vehicle { display: flex; flex-direction: column; min-width: 0; overflow: hidden; border: 1px solid var(--er-stroke); border-radius: 18px; background: var(--er-surface); color: var(--er-text); box-shadow: var(--er-shadow-sm); }
.rental-vehicle.is-inactive { border-style: dashed; }
.rental-vehicle.is-inactive .rental-vehicle__image { filter: grayscale(.8); }
.rental-vehicle__visual { position: relative; }
.rental-vehicle__image { display: block; width: 100%; height: auto; aspect-ratio: 4 / 3; object-fit: cover; background: var(--er-bg-soft); }
.rental-vehicle__status { position: absolute; display: flex; align-items: center; gap: 6px; top: 14px; left: 14px; max-width: calc(100% - 28px); padding: 7px 10px; border: 1px solid var(--er-stroke); border-radius: 999px; background: var(--er-surface); color: var(--er-text-muted); font-size: .7rem; font-weight: 800; box-shadow: var(--er-shadow-sm); }
.rental-vehicle__status > span { width: 6px; height: 6px; border-radius: 50%; background: currentcolor; }
.rental-vehicle__status.is-available { color: var(--er-primary-strong); }
.rental-vehicle__body { display: flex; flex: 1; flex-direction: column; align-items: stretch; gap: 12px; padding: 22px; }
.rental-vehicle__type { color: var(--er-primary-strong); font-size: .7rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; }
h3 { font-size: 1.3rem; font-weight: 800; line-height: 1.3; }
.rental-vehicle__provider, .rental-vehicle__address { display: flex; align-items: flex-start; gap: 6px; font-size: .8rem; text-align: start; overflow-wrap: anywhere; }
.rental-vehicle__provider { color: var(--er-primary-strong); font-weight: 700; cursor: pointer; }
.rental-vehicle__provider .v-icon, .rental-vehicle__address .v-icon { flex-shrink: 0; }
.rental-vehicle__address { color: var(--er-text-muted); }
.rental-vehicle__details { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-block: 4px; }
.rental-vehicle__details > span { display: flex; align-items: center; gap: 7px; color: var(--er-text); font-size: .8rem; overflow-wrap: anywhere; }
.rental-vehicle__details .v-icon { flex-shrink: 0; color: var(--er-text-muted); }
.rental-vehicle__details small { display: block; color: var(--er-text-muted); font-size: .65rem; margin-bottom: 3px; }
.rental-vehicle__rating { position: absolute; right: 14px; bottom: 14px; display: flex; align-items: center; gap: 4px; padding: 6px 9px; border: 1px solid var(--er-stroke); border-radius: 10px; background: var(--er-surface); color: var(--er-text); font-size: .8rem; font-weight: 800; cursor: pointer; }
.rental-vehicle__rating span { color: var(--er-text-muted); font-weight: 500; }
.rental-vehicle__price { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; margin-top: auto; padding-top: 6px; }
.rental-vehicle__price strong { font-family: var(--er-display-font); font-size: 1.55rem; }
.rental-vehicle__price span { color: var(--er-text-muted); font-size: .75rem; }
.rental-vehicle__actions { display: flex; flex-wrap: wrap; gap: 8px; padding-top: 12px; border-top: 1px solid var(--er-stroke); }
.rental-vehicle__actions .v-btn { flex: 1; min-height: 44px; max-width: 100%; }
.rental-vehicle__actions :deep(.v-btn__content) { white-space: normal; }
.rental-vehicle--compact .rental-vehicle__image { height: 160px; aspect-ratio: auto; }
.rental-vehicle--compact .rental-vehicle__body { padding: 14px; gap: 8px; }
.rental-vehicle--compact h3 { font-size: 1.1rem; }
.rental-vehicle--compact .rental-vehicle__price strong { font-size: 1.25rem; }
</style>
