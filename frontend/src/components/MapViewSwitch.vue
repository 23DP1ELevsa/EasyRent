<template>
  <div :aria-label="t('map.viewMode')" class="map-view-switch" role="group">
    <button
      v-for="view in views"
      :key="view.value"
      :aria-pressed="modelValue === view.value"
      :class="{ 'is-active': modelValue === view.value }"
      type="button"
      @click="$emit('update:modelValue', view.value)"
    >
      <v-icon size="19">{{ view.icon }}</v-icon>{{ t(`map.${view.label}`) }}
    </button>
  </div>
</template>

<script setup>
  import { useLocale } from '@/stores/locale'

  defineProps({ modelValue: { type: String, required: true } })
  defineEmits(['update:modelValue'])
  const { t } = useLocale()
  const views = [
    { value: 'map', label: 'mapView', icon: 'mdi-map-outline' },
    { value: 'list', label: 'listView', icon: 'mdi-format-list-bulleted' },
  ]
</script>

<style scoped>
.map-view-switch { display: flex; gap: 4px; padding: 4px; border: 1px solid var(--er-stroke); border-radius: 999px; background: var(--er-bg-soft); }
button { display: flex; align-items: center; justify-content: center; gap: 8px; min-height: 40px; padding: 0 20px; border-radius: 999px; color: var(--er-text-muted); font-size: .875rem; font-weight: 800; cursor: pointer; }
button:hover { color: var(--er-primary-strong); }
button.is-active { background: var(--er-primary-strong); color: var(--er-on-primary); box-shadow: var(--er-shadow-sm); }
@media (max-width: 767px) { .map-view-switch { width: 100%; } button { flex: 1; min-width: 0; } }
</style>
