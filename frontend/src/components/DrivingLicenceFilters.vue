<template>
  <div :aria-label="t('vehicleLicence.category')" class="licence-options" role="group">
    <button
      v-for="option in licenceOptions"
      :key="option.value"
      :aria-pressed="selection.includes(option.value)"
      type="button"
      @click="toggle(option.value)"
    >{{ option.title }}</button>
  </div>
</template>

<script setup>
  import { useLocale } from '@/stores/locale'
  import { useVehicleLabels } from '@/utils/vehicleLabels'

  const selection = defineModel({ type: Array, required: true })
  const { t } = useLocale()
  const { licenceOptions } = useVehicleLabels()

  function toggle (value) {
    selection.value = selection.value.includes(value) ? selection.value.filter(item => item !== value) : [...selection.value, value]
  }
</script>

<style scoped>
.licence-options { display: flex; flex-wrap: wrap; gap: 6px; }
button { min-width: 38px; min-height: 36px; padding: 4px 9px; border: 1px solid var(--er-stroke); border-radius: 12px; color: var(--er-text); font-size: .78rem; cursor: pointer; }
button[aria-pressed='true'] { border-color: var(--er-primary-strong); background: var(--er-primary-soft); color: var(--er-primary-strong); font-weight: 700; }
</style>
