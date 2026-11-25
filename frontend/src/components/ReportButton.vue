<template>
  <v-btn v-if="targetId" size="small" variant="text" prepend-icon="mdi-flag-outline" @click="open">{{ label('reportAction') }}</v-btn>
  <v-dialog v-model="dialog" :persistent="saving" max-width="500">
    <v-card>
      <v-form @submit.prevent="submit">
        <v-card-title>{{ label('reportAction') }}</v-card-title>
        <v-card-text>
          <p class="mb-3">{{ status(target) }} #{{ targetId }}</p>
          <v-alert v-if="error" type="error" variant="tonal" class="mb-3">{{ error }}</v-alert>
          <v-select v-model="reason" :label="label('reason')" :items="reasons" variant="outlined" />
          <v-textarea v-model="description" :label="label('reportDescription')" minlength="10" maxlength="2000" rows="4" required variant="outlined" />
        </v-card-text>
        <v-card-actions class="justify-end">
          <v-btn :disabled="saving" variant="text" @click="dialog = false">{{ label('cancel') }}</v-btn>
          <v-btn :disabled="description.trim().length < 10" :loading="saving" color="primary" type="submit">{{ label('confirm') }}</v-btn>
        </v-card-actions>
      </v-form>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useAdminFormat } from '@/admin/format'
import { reportReasons } from '@/admin/sections'
import { apiFetch } from '@/services/auth'
import { useNotifications } from '@/stores/notifications'

const props = defineProps({ target: { type: String, required: true }, targetId: { type: [Number, String], default: null } })
const { label, status } = useAdminFormat()
const { notifySuccess } = useNotifications()
const reasons = computed(() => reportReasons.map(value => ({ value, title: status(value) })))
const dialog = ref(false)
const description = ref('')
const reason = ref('other')
const error = ref('')
const saving = ref(false)

function open () {
  description.value = ''
  reason.value = 'other'
  error.value = ''
  dialog.value = true
}

async function submit () {
  if (saving.value || description.value.trim().length < 10) return
  saving.value = true
  error.value = ''
  try {
    const response = await apiFetch('/api/reports', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ objekta_veids: props.target, objekta_id: props.targetId, iemesls: reason.value, apraksts: description.value.trim() }),
    })
    const data = await response.json()
    if (!response.ok) {
      error.value = Object.values(data.errors ?? {})[0]?.[0] || data.message || label('saveError')
      return
    }
    dialog.value = false
    notifySuccess(label('reportSent'))
  } catch {
    error.value = label('saveError')
  } finally {
    saving.value = false
  }
}
</script>
