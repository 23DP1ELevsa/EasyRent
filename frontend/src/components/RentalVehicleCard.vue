<template>
  <article class="rental-vehicle" :class="{ 'rental-vehicle--compact': compact, 'is-inactive': vehicle.statuss === 'neaktivs' }">
    <img
      :alt="`${vehicle.marka} ${vehicle.modelis}`"
      class="rental-vehicle__image"
      height="320"
      loading="lazy"
      :src="getVehicleImage(vehicle)"
      width="480"
    >
    <div class="rental-vehicle__body">
      <div class="rental-vehicle__type">{{ vehicle.veids?.nosaukums || '—' }}</div>
      <h3>{{ vehicle.marka }} {{ vehicle.modelis }}</h3>
      <button class="rental-vehicle__provider" type="button" @click="$emit('select-provider')">
        <v-icon size="16">mdi-storefront-outline</v-icon>{{ provider?.name }}
      </button>
      <div class="rental-vehicle__address"><v-icon size="16">mdi-map-marker-outline</v-icon>{{ [provider?.address, provider?.city].filter(Boolean).join(', ') }}</div>
      <div class="rental-vehicle__details">
        <span v-for="detail in details" :key="detail.label"><strong>{{ detail.label }}:</strong> {{ detail.value }}</span>
      </div>
      <button v-if="Number(vehicle.atsauksmju_skaits) > 0" class="rental-vehicle__rating" type="button" @click="$emit('reviews')">
        <v-icon color="amber" size="17">mdi-star</v-icon>{{ Number(vehicle.videjais_vertejums || 0).toFixed(1) }} ({{ vehicle.atsauksmju_skaits }}) · {{ t('map.reviews') }}
      </button>
      <div class="rental-vehicle__price">
        <div><strong>{{ price }}</strong><span> / {{ t('map.perDay') }}</span></div>
        <v-chip :color="available ? 'success' : 'error'" size="small" variant="tonal">{{ available ? t('map.available') : vehicle.statuss === 'neaktivs' ? t('map.inactive') : t('map.busy') }}</v-chip>
      </div>
      <div class="rental-vehicle__actions">
        <v-btn v-if="showDetails" size="small" variant="tonal" @click="$emit('details')">{{ t('map.vehicleDetails') }}</v-btn>
        <v-btn
          v-if="canReserve"
          color="primary"
          :disabled="!available"
          size="small"
          @click="$emit('reserve')"
        >{{ t('map.reserve') }}</v-btn>
        <v-btn v-if="canEdit" size="small" variant="tonal" @click="$emit('edit')">{{ t('map.edit') }}</v-btn>
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
  const price = computed(() => new Intl.NumberFormat(getIntlLocale(), { style: 'currency', currency: 'EUR' }).format(Number(props.vehicle.dienas_nomas_cena || 0)))
</script>

<style scoped>
.rental-vehicle { display: flex; flex-direction: column; min-width: 0; overflow: hidden; border: 1px solid var(--er-stroke); border-radius: 18px; background: var(--er-surface); color: var(--er-text); box-shadow: var(--er-shadow-sm); }
.rental-vehicle.is-inactive { opacity: .7; }
.rental-vehicle__image { width: 100%; height: 180px; object-fit: cover; background: var(--er-bg-soft); }
.rental-vehicle__body { display: flex; flex: 1; flex-direction: column; align-items: stretch; gap: 10px; padding: 18px; }
.rental-vehicle__type { color: var(--er-primary-strong); font-size: .7rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; }
h3 { font-size: 1.1rem; font-weight: 800; line-height: 1.3; }
.rental-vehicle__provider, .rental-vehicle__address { display: flex; align-items: flex-start; gap: 6px; font-size: .75rem; text-align: start; overflow-wrap: anywhere; }
.rental-vehicle__provider { color: var(--er-primary-strong); font-weight: 700; cursor: pointer; }
.rental-vehicle__provider .v-icon, .rental-vehicle__address .v-icon { flex-shrink: 0; }
.rental-vehicle__address { color: var(--er-text-muted); }
.rental-vehicle__details { display: flex; flex-wrap: wrap; gap: 6px; }
.rental-vehicle__details span { padding: 5px 7px; border-radius: 8px; background: var(--er-bg-soft); color: var(--er-text-muted); font-size: .65rem; overflow-wrap: anywhere; }
.rental-vehicle__rating { display: flex; align-items: center; gap: 4px; color: var(--er-text-muted); font-size: .75rem; cursor: pointer; text-align: start; }
.rental-vehicle__price { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; margin-top: auto; padding-top: 6px; }
.rental-vehicle__price strong { font-family: var(--er-display-font); font-size: 1.2rem; }
.rental-vehicle__price span { color: var(--er-text-muted); font-size: .75rem; }
.rental-vehicle__actions { display: flex; flex-wrap: wrap; gap: 8px; padding-top: 12px; border-top: 1px solid var(--er-stroke); }
.rental-vehicle__actions .v-btn { flex: 1; min-height: 38px; max-width: 100%; }
.rental-vehicle__actions :deep(.v-btn__content) { white-space: normal; }
.rental-vehicle--compact .rental-vehicle__image { height: 130px; }
.rental-vehicle--compact .rental-vehicle__body { padding: 14px; gap: 8px; }
</style>
