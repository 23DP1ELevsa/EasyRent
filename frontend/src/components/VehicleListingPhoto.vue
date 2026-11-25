<template>
  <button
    v-if="cover"
    ref="trigger"
    :aria-label="t('vehiclePhotos.open')"
    class="listing-photo"
    type="button"
    @click="openGallery"
  >
    <VehiclePhoto :alt="name" :src="cover" />
    <span aria-hidden="true" class="listing-photo__open">
      <v-icon size="17">mdi-image-multiple-outline</v-icon>{{ vehicle.photos_count > 1 ? vehicle.photos_count : '' }}
    </span>
  </button>
  <VehiclePhoto v-else />
  <VehiclePhotoGallery
    v-if="cover"
    v-model="open"
    v-model:index="index"
    :alt="name"
    :error="error"
    :loading="loading"
    :photos="photos"
    @after-leave="trigger?.focus({ preventScroll: true })"
  />
</template>

<script setup>
  import { computed, ref, watch } from 'vue'
  import VehiclePhoto from '@/components/VehiclePhoto.vue'
  import VehiclePhotoGallery from '@/components/VehiclePhotoGallery.vue'
  import { apiFetch } from '@/services/auth'
  import { useLocale } from '@/stores/locale'
  import { getVehicleImage } from '@/utils/vehicleVisuals'

  const props = defineProps({ vehicle: { type: Object, required: true } })
  const { t } = useLocale()
  const cover = computed(() => getVehicleImage(props.vehicle))
  const name = computed(() => `${props.vehicle.marka} ${props.vehicle.modelis}`)
  const trigger = ref(null)
  const open = ref(false)
  const index = ref(0)
  const photos = ref([])
  const loading = ref(false)
  const error = ref('')

  function openGallery () {
    index.value = 0
    open.value = true
  }

  watch([open, cover, () => props.vehicle.photos_count], async ([visible, url, count], _, onCleanup) => {
    photos.value = url ? [{ url }] : []
    index.value = 0
    error.value = ''
    loading.value = false
    if (!visible || !url || count < 2) return
    const controller = new AbortController()
    onCleanup(() => controller.abort())
    loading.value = true
    try {
      const response = await apiFetch(`/api/transport/${props.vehicle.transportlidzeklis_id}/public-photos`, { auth: false, signal: controller.signal })
      if (!response.ok) throw new Error('Failed to load the vehicle gallery')
      const list = await response.json()
      if (!controller.signal.aborted) photos.value = list
    } catch {
      if (!controller.signal.aborted) error.value = t('vehiclePhotos.loadFailed')
    } finally {
      if (!controller.signal.aborted) loading.value = false
    }
  })
</script>

<style scoped>
.listing-photo { display: block; position: relative; width: 100%; cursor: zoom-in; }
.listing-photo:focus-visible { outline: 2px solid var(--er-primary-strong); outline-offset: -2px; }
.listing-photo__open { position: absolute; bottom: 12px; left: 12px; display: flex; align-items: center; gap: 5px; padding: 5px 8px; border-radius: 8px; color: var(--er-text); background: var(--er-surface); font-size: .75rem; }
</style>
