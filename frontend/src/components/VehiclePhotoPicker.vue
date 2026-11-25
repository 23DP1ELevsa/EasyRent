<template>
  <div class="photo-picker">
    <div
      class="photo-picker__drop"
      :class="{ 'is-dragging': dragging }"
      @dragenter.prevent
      @dragleave.self="dragging = false"
      @dragover.prevent="dragging = !unavailable"
      @drop.prevent="onDrop"
    >
      <div>{{ t('vehiclePhotos.drop') }}</div>
      <div class="photo-picker__buttons">
        <v-btn
          :disabled="unavailable"
          prepend-icon="mdi-image-plus-outline"
          size="small"
          type="button"
          variant="tonal"
          @click="fileInput.click()"
        >
          {{ t('vehiclePhotos.choose') }}
        </v-btn>
        <v-btn
          :aria-label="t('vehiclePhotos.camera')"
          class="photo-picker__camera"
          :disabled="unavailable"
          icon="mdi-camera-outline"
          size="small"
          type="button"
          variant="tonal"
          @click="cameraInput.click()"
        />
      </div>
      <input
        ref="fileInput"
        :accept="VEHICLE_PHOTO_ACCEPT"
        hidden
        :multiple="limit > 1"
        type="file"
        @change="onInput"
      >
      <input
        ref="cameraInput"
        accept="image/*"
        capture="environment"
        hidden
        type="file"
        @change="onInput"
      >
    </div>
    <v-progress-linear v-if="processing" :aria-label="t('vehiclePhotos.processing')" color="primary" indeterminate />
    <v-alert v-if="error" density="compact" type="error" variant="tonal">{{ error }}</v-alert>
    <div v-if="previews.length > 0" class="photo-picker__grid">
      <figure v-for="(photo, index) in previews" :key="photo.url">
        <VehiclePhoto :alt="photo.file.name" eager :src="photo.url" />
        <div class="photo-picker__buttons">
          <v-btn
            v-if="selectPrimary"
            :aria-label="t(index === primary ? 'vehiclePhotos.main' : 'vehiclePhotos.setMain')"
            :aria-pressed="index === primary"
            :color="index === primary ? 'primary' : undefined"
            :disabled="disabled || processing"
            :icon="index === primary ? 'mdi-star' : 'mdi-star-outline'"
            size="small"
            type="button"
            variant="text"
            @click="primary = index"
          />
          <v-btn
            :aria-label="t('vehiclePhotos.remove')"
            :disabled="disabled || processing"
            icon="mdi-close"
            size="small"
            type="button"
            variant="text"
            @click="remove(index)"
          />
        </div>
      </figure>
    </div>
  </div>
</template>

<script setup>
  import { computed, onUnmounted, ref, watch } from 'vue'
  import VehiclePhoto from '@/components/VehiclePhoto.vue'
  import { useLocale } from '@/stores/locale'
  import { MAX_VEHICLE_PHOTOS, prepareVehiclePhoto, VEHICLE_PHOTO_ACCEPT } from '@/utils/vehiclePhotoUpload'

  const props = defineProps({
    limit: { type: Number, default: MAX_VEHICLE_PHOTOS },
    selectPrimary: Boolean,
    disabled: Boolean,
  })
  const files = defineModel({ type: Array, required: true })
  const primary = defineModel('primary', { type: Number, default: 0 })
  const processing = defineModel('processing', { type: Boolean, default: false })
  const { t } = useLocale()
  const fileInput = ref(null)
  const cameraInput = ref(null)
  const dragging = ref(false)
  const error = ref('')
  const previews = ref([])
  const unavailable = computed(() => props.disabled || processing.value || files.value.length >= props.limit)
  let disposed = false

  watch(files, list => {
    const previous = new Map(previews.value.map(photo => [photo.file, photo]))
    previews.value = list.map(file => previous.get(file) || { file, url: URL.createObjectURL(file) })
    for (const { file, url } of previous.values()) {
      if (!list.includes(file)) URL.revokeObjectURL(url)
    }
    if (list.length === 0) error.value = ''
  }, { immediate: true })

  onUnmounted(() => {
    disposed = true
    processing.value = false
    for (const { url } of previews.value) URL.revokeObjectURL(url)
  })

  function onInput (event) {
    const list = Array.from(event.target.files)
    event.target.value = ''
    void add(list)
  }

  function onDrop (event) {
    dragging.value = false
    void add(Array.from(event.dataTransfer.files))
  }

  async function add (list) {
    if (list.length === 0 || props.disabled || processing.value) return
    if (list.length + files.value.length > props.limit) {
      error.value = t(props.limit === 1 ? 'vehiclePhotos.chooseOne' : 'vehiclePhotos.limit')
      return
    }

    processing.value = true
    error.value = ''
    const prepared = []
    let failure = ''
    try {
      for (const file of list) {
        try {
          prepared.push(await prepareVehiclePhoto(file))
        } catch (error) {
          failure = t(error.message === 'tooLarge' ? 'vehiclePhotos.tooLarge' : 'vehiclePhotos.invalid')
        }
        if (disposed) return
      }
      if (prepared.length > 0) files.value = [...files.value, ...prepared]
      error.value = failure
    } finally {
      if (!disposed) processing.value = false
    }
  }

  function remove (index) {
    files.value = files.value.filter((_, position) => position !== index)
    if (index < primary.value) primary.value -= 1
    else if (index === primary.value) primary.value = 0
  }
</script>

<style scoped>
.photo-picker { display: grid; gap: 8px; }
.photo-picker__drop { display: grid; gap: 8px; justify-items: center; padding: 12px; border: 1px dashed var(--er-stroke); border-radius: 12px; color: var(--er-text-muted); text-align: center; font-size: .8rem; }
.photo-picker__drop.is-dragging { border-color: var(--er-primary-strong); background: var(--er-primary-soft); }
.photo-picker__buttons { display: flex; align-items: center; flex-wrap: wrap; justify-content: center; gap: 4px; }
.photo-picker__grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
figure { display: grid; overflow: hidden; border: 1px solid var(--er-stroke); border-radius: 8px; }
.photo-picker__camera { display: none; }
@media (pointer: coarse) { .photo-picker__camera { display: inline-flex; } }
</style>
