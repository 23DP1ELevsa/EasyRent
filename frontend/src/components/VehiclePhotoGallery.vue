<template>
  <v-dialog v-model="open" :aria-labelledby="titleId" max-width="900">
    <v-card>
      <v-card-title :id="titleId">{{ t('vehiclePhotos.title') }}</v-card-title>
      <v-progress-linear v-if="loading" :aria-label="t('map.loading')" color="primary" indeterminate />
      <v-alert v-if="error" density="compact" type="error" variant="tonal">{{ error }}</v-alert>
      <div
        class="gallery-viewport"
        :class="{ 'is-zoomed': zoomed }"
        @keydown.left.prevent="step(-1)"
        @keydown.right.prevent="step(1)"
        @touchend.passive="onTouchEnd"
        @touchstart.passive="onTouchStart"
      >
        <VehiclePhoto :alt="alt" contain eager :src="activePhoto?.url" />
      </div>
      <v-card-actions
        class="gallery-actions"
        @keydown.left.prevent="step(-1)"
        @keydown.right.prevent="step(1)"
      >
        <template v-if="photos.length > 1">
          <v-btn :aria-label="t('vehiclePhotos.previous')" icon="mdi-chevron-left" @click="step(-1)" />
          <span aria-live="polite">{{ t('vehiclePhotos.position', { current: index + 1, total: photos.length }) }}</span>
          <v-btn :aria-label="t('vehiclePhotos.next')" icon="mdi-chevron-right" @click="step(1)" />
        </template>
        <v-btn
          v-if="activePhoto?.url"
          :aria-label="t(zoomed ? 'vehiclePhotos.zoomOut' : 'vehiclePhotos.zoomIn')"
          :aria-pressed="zoomed"
          :icon="zoomed ? 'mdi-magnify-minus-outline' : 'mdi-magnify-plus-outline'"
          @click="zoomed = !zoomed"
        />
        <v-spacer />
        <v-btn @click="open = false">{{ t('map.close') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { computed, ref, useId, watch } from 'vue'
  import VehiclePhoto from '@/components/VehiclePhoto.vue'
  import { useLocale } from '@/stores/locale'

  const props = defineProps({
    photos: { type: Array, required: true },
    alt: { type: String, default: '' },
    error: { type: String, default: '' },
    loading: Boolean,
  })
  const open = defineModel({ type: Boolean, required: true })
  const index = defineModel('index', { type: Number, default: 0 })
  const { t } = useLocale()
  const titleId = useId()
  const activePhoto = computed(() => props.photos[index.value])
  const zoomed = ref(false)
  let touchStart

  watch([open, () => activePhoto.value?.url], () => {
    zoomed.value = false
    touchStart = null
  })

  function step (direction) {
    const count = props.photos.length
    if (count > 1) index.value = (index.value + direction + count) % count
  }

  function onTouchStart (event) {
    touchStart = event.touches.length === 1 ? event.touches[0] : null
  }

  function onTouchEnd (event) {
    if (!touchStart || zoomed.value) return
    const { clientX, clientY } = event.changedTouches[0]
    const x = clientX - touchStart.clientX
    const y = clientY - touchStart.clientY
    touchStart = null
    if (Math.abs(x) > 45 && Math.abs(x) > Math.abs(y)) step(x > 0 ? -1 : 1)
  }
</script>

<style scoped>
.gallery-viewport { --vehicle-photo-height: min(60vh, 560px); height: var(--vehicle-photo-height); overflow: auto; touch-action: pan-y; }
.gallery-viewport.is-zoomed { touch-action: pan-x pan-y; }
.gallery-viewport.is-zoomed :deep(.vehicle-photo) { width: 180%; height: 180%; }
.gallery-actions { flex-wrap: wrap; gap: 4px; }
</style>
