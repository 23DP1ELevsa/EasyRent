<template>
  <div class="vehicle-photo" :class="{ 'vehicle-photo--contain': contain }">
    <div v-if="!src || failed" class="vehicle-photo__placeholder">
      <v-icon aria-hidden="true" size="28">mdi-image-off-outline</v-icon>
      <span>{{ t('vehiclePhotos.noPhoto') }}</span>
    </div>
    <template v-else>
      <div v-if="!loaded" aria-hidden="true" class="vehicle-photo__skeleton" />
      <img
        :key="src"
        :alt="alt"
        :class="{ 'is-loaded': loaded }"
        decoding="async"
        :loading="eager ? 'eager' : 'lazy'"
        :src="src"
        @error="failed = true"
        @load="loaded = true"
      >
    </template>
  </div>
</template>

<script setup>
  import { ref, watch } from 'vue'
  import { useLocale } from '@/stores/locale'

  const props = defineProps({ src: { type: String, default: null }, alt: { type: String, default: '' }, contain: Boolean, eager: Boolean })
  const { t } = useLocale()
  const loaded = ref(false)
  const failed = ref(false)
  watch(() => props.src, () => {
    loaded.value = false
    failed.value = false
  })
</script>

<style scoped>
.vehicle-photo { position: relative; width: 100%; height: var(--vehicle-photo-height, auto); aspect-ratio: var(--vehicle-photo-ratio, 4 / 3); overflow: hidden; background: var(--er-bg-soft); }
img, .vehicle-photo__placeholder, .vehicle-photo__skeleton { position: absolute; inset: 0; width: 100%; height: 100%; }
img { object-fit: cover; opacity: 0; }
img.is-loaded { opacity: 1; }
.vehicle-photo--contain img { object-fit: contain; }
.vehicle-photo__placeholder { display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 7px; color: var(--er-text-muted); font-size: .78rem; text-align: center; }
.vehicle-photo__skeleton { background: rgba(var(--v-theme-on-surface), .06); animation: photo-pulse 1.35s ease-in-out infinite alternate; }
@keyframes photo-pulse { to { opacity: .25; } }
@media (prefers-reduced-motion: reduce) { .vehicle-photo__skeleton { animation: none; } }
</style>
