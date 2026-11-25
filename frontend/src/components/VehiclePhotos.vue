<template>
  <section class="vehicle-photos">
    <h3>{{ label('photos') }}</h3>
    <v-progress-linear v-if="loading" indeterminate color="primary" :aria-label="label('loading')" />
    <v-alert v-if="error" type="error" variant="tonal" density="compact">{{ error }}</v-alert>
    <p v-if="!loading && !photos.length" class="text-medium-emphasis">{{ label('noPhotos') }}</p>
    <div v-else class="vehicle-photos__grid">
      <figure v-for="photo in photos" :key="photo.id">
        <a :href="photo.url" target="_blank" rel="noopener noreferrer"><img :src="photo.url" :alt="`${label('photos')} #${photo.id}`"></a>
        <v-btn v-if="!admin" :aria-label="label('removePhoto')" icon="mdi-delete-outline" size="x-small" variant="tonal" @click="pendingDelete = photo.id" />
      </figure>
    </div>
    <v-form v-if="!admin && photos.length < 4" class="vehicle-photos__form" @submit.prevent="upload">
      <v-file-input v-model="files" :label="label('addPhotos')" :hint="label('photoHint')" persistent-hint variant="outlined" density="compact"
        accept="image/jpeg,image/png,image/webp" multiple />
      <v-btn :disabled="!files?.length || loading" :loading="saving" color="primary" size="small" type="submit">{{ label('addPhotos') }}</v-btn>
    </v-form>
    <v-dialog :model-value="pendingDelete !== null" max-width="380" :persistent="saving" @update:model-value="value => { if (!value) pendingDelete = null }">
      <v-card :title="label('removePhoto')">
        <v-card-actions>
          <v-spacer />
          <v-btn :disabled="saving" variant="text" @click="pendingDelete = null">{{ label('cancel') }}</v-btn>
          <v-btn :loading="saving" color="error" @click="remove">{{ label('confirm') }}</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </section>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import { apiFetch } from '@/services/auth'
import { useAdminFormat } from '@/admin/format'

const props = defineProps({ vehicleId: { type: [Number, String], required: true }, admin: Boolean })
const { label } = useAdminFormat()
const photos = ref([])
const files = ref([])
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const pendingDelete = ref(null)
const endpoint = props.admin ? `/api/admin/vehicles/${props.vehicleId}/photos` : `/api/transport/${props.vehicleId}/photos`
const controller = new AbortController()

function releaseImages () {
  photos.value.forEach(photo => URL.revokeObjectURL(photo.url))
  photos.value = []
}

onMounted(load)
onUnmounted(() => {
  controller.abort()
  releaseImages()
})

async function checked (url, options = {}) {
  const response = await apiFetch(url, { ...options, signal: controller.signal })
  if (!response.ok) {
    const result = await response.json().catch(() => null)
    throw new Error(Object.values(result?.errors ?? {})[0]?.[0] || result?.message || label('error'))
  }
  return response
}

async function load () {
  loading.value = true
  error.value = ''
  try {
    const list = await (await checked(endpoint)).json()
    const images = await Promise.all(list.map(async ({ foto_id: id }) => ({
      id,
      blob: await (await checked(`${endpoint}/${id}`)).blob(),
    })))
    if (controller.signal.aborted) return
    releaseImages()
    photos.value = images.map(({ id, blob }) => ({ id, url: URL.createObjectURL(blob) }))
  } catch (failure) {
    if (!controller.signal.aborted) error.value = failure instanceof TypeError ? label('error') : failure.message
  } finally {
    loading.value = false
  }
}

async function upload () {
  if (saving.value || !files.value?.length) return
  const body = new FormData()
  for (const file of files.value) body.append('photos[]', file)
  saving.value = true
  error.value = ''
  try {
    await checked(endpoint, { method: 'POST', body })
    files.value = []
    await load()
  } catch (failure) {
    if (!controller.signal.aborted) error.value = failure instanceof TypeError ? label('saveError') : failure.message
  } finally {
    saving.value = false
  }
}

async function remove () {
  if (saving.value || pendingDelete.value === null) return
  saving.value = true
  error.value = ''
  try {
    await checked(`${endpoint}/${pendingDelete.value}`, { method: 'DELETE' })
    await load()
  } catch (failure) {
    if (!controller.signal.aborted) error.value = failure instanceof TypeError ? label('saveError') : failure.message
  } finally {
    pendingDelete.value = null
    saving.value = false
  }
}
</script>

<style scoped>
.vehicle-photos { margin-top: 16px; display: grid; gap: 9px; min-width: 0; }
.vehicle-photos h3 { font: 700 1rem/1.3 var(--er-display-font); }
.vehicle-photos__grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
.vehicle-photos figure { display: grid; justify-items: end; gap: 4px; min-width: 0; }
.vehicle-photos figure a { display: block; width: 100%; }
.vehicle-photos img { display: block; width: 100%; aspect-ratio: 4 / 3; object-fit: cover; border-radius: 8px; }
.vehicle-photos__form { display: grid; gap: 6px; }
.vehicle-photos__form .v-btn { justify-self: start; }
</style>
