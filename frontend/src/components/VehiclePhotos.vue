<template>
  <section class="vehicle-photos">
    <h3>{{ t('vehiclePhotos.title') }}</h3>
    <v-progress-linear v-if="loading || saving" :aria-label="t('map.loading')" color="primary" indeterminate />
    <v-alert v-if="error" density="compact" type="error" variant="tonal">{{ error }}</v-alert>
    <VehiclePhoto v-if="!loading && photos.length === 0" class="vehicle-photos__empty" />
    <div v-else class="vehicle-photos__grid">
      <figure v-for="(photo, index) in photos" :key="photo.id">
        <v-chip
          v-if="index === 0"
          class="main-photo"
          color="primary"
          prepend-icon="mdi-star"
          size="small"
          variant="tonal"
        >{{ t('vehiclePhotos.main') }}</v-chip>
        <button :aria-label="t('vehiclePhotos.open')" class="photo-preview" type="button" @click="view(index)">
          <VehiclePhoto :src="photo.url" />
        </button>
        <div v-if="!admin" class="photo-actions">
          <v-btn
            v-if="index > 0"
            :aria-label="t('vehiclePhotos.setMain')"
            :disabled="busy"
            icon="mdi-star-outline"
            size="small"
            variant="text"
            @click="makePrimary(photo.id)"
          />
          <v-btn
            :aria-label="t('vehiclePhotos.replace')"
            :disabled="busy"
            icon="mdi-image-edit-outline"
            size="small"
            variant="text"
            @click="startReplacement(photo.id)"
          />
          <v-btn
            :aria-label="t('vehiclePhotos.remove')"
            :disabled="busy || photos.length <= 1"
            icon="mdi-delete-outline"
            size="small"
            variant="text"
            @click="pendingDelete = photo.id"
          />
        </div>
      </figure>
    </div>
    <p v-if="!admin && photos.length === 1" class="photo-hint">{{ t('vehiclePhotos.lastPhoto') }}</p>
    <div v-if="!admin && replacementId !== null" class="photo-form">
      <h4>{{ t('vehiclePhotos.replace') }}</h4>
      <VehiclePhotoPicker v-model="replacement" v-model:processing="processingReplacement" :disabled="saving" :limit="1" />
      <div class="photo-actions">
        <v-btn :disabled="saving || processingReplacement" size="small" variant="text" @click="replacementId = null">{{ t('map.cancel') }}</v-btn>
        <v-btn
          color="primary"
          :disabled="busy || replacement.length === 0"
          :loading="saving"
          size="small"
          @click="replace"
        >{{ t('vehiclePhotos.replace') }}</v-btn>
      </div>
    </div>
    <div v-else-if="!admin && !loading && photos.length < MAX_VEHICLE_PHOTOS" class="photo-form">
      <VehiclePhotoPicker v-model="files" v-model:processing="processingFiles" :disabled="saving" :limit="MAX_VEHICLE_PHOTOS - photos.length" />
      <v-btn
        color="primary"
        :disabled="busy || files.length === 0"
        :loading="saving"
        size="small"
        @click="upload"
      >{{ t('vehiclePhotos.add') }}</v-btn>
    </div>
    <VehiclePhotoGallery v-model="galleryOpen" v-model:index="galleryIndex" :photos="photos" />
    <v-dialog max-width="380" :model-value="pendingDelete !== null" :persistent="saving" @update:model-value="value => { if (!value) pendingDelete = null }">
      <v-card :title="t('vehiclePhotos.remove')">
        <v-card-actions>
          <v-spacer />
          <v-btn :disabled="saving" variant="text" @click="pendingDelete = null">{{ t('map.cancel') }}</v-btn>
          <v-btn color="error" :loading="saving" @click="remove">{{ t('map.delete') }}</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </section>
</template>

<script setup>
  import { computed, onMounted, onUnmounted, ref } from 'vue'
  import VehiclePhoto from '@/components/VehiclePhoto.vue'
  import VehiclePhotoGallery from '@/components/VehiclePhotoGallery.vue'
  import VehiclePhotoPicker from '@/components/VehiclePhotoPicker.vue'
  import { apiFetch } from '@/services/auth'
  import { useLocale } from '@/stores/locale'
  import { MAX_VEHICLE_PHOTOS } from '@/utils/vehiclePhotoUpload'

  const props = defineProps({ vehicleId: { type: [Number, String], required: true }, admin: Boolean })
  const emit = defineEmits(['changed'])
  const { t } = useLocale()
  const photos = ref([])
  const files = ref([])
  const replacement = ref([])
  const replacementId = ref(null)
  const loading = ref(false)
  const saving = ref(false)
  const processingFiles = ref(false)
  const processingReplacement = ref(false)
  const error = ref('')
  const pendingDelete = ref(null)
  const galleryOpen = ref(false)
  const galleryIndex = ref(0)
  const busy = computed(() => loading.value || saving.value || processingFiles.value || processingReplacement.value)
  const endpoint = props.admin ? `/api/admin/vehicles/${props.vehicleId}/photos` : `/api/transport/${props.vehicleId}/photos`
  const controller = new AbortController()

  onMounted(load)
  onUnmounted(() => {
    controller.abort()
    releaseImages()
  })

  function releaseImages () {
    for (const { url } of photos.value) {
      if (url) URL.revokeObjectURL(url)
    }
  }

  async function checked (url, options, fallback) {
    const response = await apiFetch(url, { ...options, signal: controller.signal })
    if (!response.ok) {
      const result = await response.json().catch(() => null)
      throw new Error(response.status < 500 && result?.message ? result.message : t(fallback))
    }
    return response
  }

  async function load () {
    loading.value = true
    error.value = ''
    try {
      const list = await (await checked(endpoint, {}, 'vehiclePhotos.loadFailed')).json()
      const images = await Promise.all(list.map(async ({ foto_id: id }) => {
        const blob = await checked(`${endpoint}/${id}`, {}, 'vehiclePhotos.loadFailed')
          .then(response => response.blob())
          .catch(() => null)
        return { id, blob }
      }))
      if (controller.signal.aborted) return
      releaseImages()
      photos.value = images.map(({ id, blob }) => ({ id, url: blob ? URL.createObjectURL(blob) : null }))
      if (images.some(image => !image.blob)) error.value = t('vehiclePhotos.loadFailed')
    } catch (error_) {
      if (!controller.signal.aborted) error.value = error_ instanceof TypeError ? t('vehiclePhotos.loadFailed') : error_.message
    } finally {
      loading.value = false
    }
  }

  function view (index) {
    galleryIndex.value = index
    galleryOpen.value = true
  }

  function startReplacement (id) {
    replacement.value = []
    replacementId.value = id
  }

  async function change (url, options, onSaved) {
    if (busy.value) return
    saving.value = true
    error.value = ''
    try {
      await checked(url, options, 'vehiclePhotos.saveFailed')
      if (controller.signal.aborted) return
      onSaved?.()
      emit('changed')
      await load()
    } catch (error_) {
      if (!controller.signal.aborted) error.value = error_ instanceof TypeError ? t('vehiclePhotos.saveFailed') : error_.message
    } finally {
      saving.value = false
    }
  }

  function upload () {
    if (files.value.length === 0) return
    const body = new FormData()
    for (const file of files.value) body.append('photos[]', file)
    return change(endpoint, { method: 'POST', body }, () => {
      files.value = []
    })
  }

  function replace () {
    if (replacement.value.length === 0 || replacementId.value === null) return
    const body = new FormData()
    body.append('photo', replacement.value[0])
    return change(`${endpoint}/${replacementId.value}`, { method: 'POST', body }, () => {
      replacement.value = []
      replacementId.value = null
    })
  }

  function makePrimary (id) {
    return change(`${endpoint}/${id}/primary`, { method: 'PATCH' })
  }

  async function remove () {
    if (pendingDelete.value === null) return
    await change(`${endpoint}/${pendingDelete.value}`, { method: 'DELETE' })
    pendingDelete.value = null
  }
</script>

<style scoped>
.vehicle-photos { margin-top: 16px; display: grid; gap: 10px; min-width: 0; }
.vehicle-photos h3 { font: 700 1rem/1.3 var(--er-display-font); }
.vehicle-photos__grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
.vehicle-photos__empty { --vehicle-photo-height: 100px; border-radius: 8px; }
figure { position: relative; min-width: 0; overflow: hidden; border: 1px solid var(--er-stroke); border-radius: 8px; }
.main-photo { position: absolute; left: 4px; top: 4px; z-index: 1; background: var(--er-surface); }
.photo-preview { display: block; width: 100%; cursor: zoom-in; }
.photo-preview:focus-visible { outline: 2px solid var(--er-primary-strong); outline-offset: -2px; }
.photo-form { display: grid; gap: 8px; justify-items: start; }
.photo-form > .photo-picker { width: 100%; }
.photo-hint { color: var(--er-text-muted); font-size: .78rem; }
.photo-actions { display: flex; flex-wrap: wrap; justify-content: center; }
</style>
