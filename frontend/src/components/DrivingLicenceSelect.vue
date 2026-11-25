<template>
  <v-select
    v-model="category"
    class="driving-licence-select"
    density="compact"
    :items="licenceOptions"
    :label="t('vehicleLicence.category')"
    :placeholder="t('vehicleLicence.unspecified')"
    variant="outlined"
  />
</template>

<script setup>
  import { useLocale } from '@/stores/locale'
  import { useVehicleLabels } from '@/utils/vehicleLabels'

  const category = defineModel({ type: String, default: null })
  const { t } = useLocale()
  const { licenceOptions } = useVehicleLabels()
</script>
