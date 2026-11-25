<template>
  <v-container class="support-page">
    <v-card class="pa-5" variant="outlined">
      <h1 class="mb-3">{{ label('support') }}</h1>
      <p class="mb-5">{{ label('supportDetail') }}</p>
      <v-alert v-if="error || sent" class="mb-4" :type="error ? 'error' : 'success'" variant="tonal" role="status">
        {{ error || label('supportSent') }}
      </v-alert>
      <v-form @submit.prevent="send">
        <v-text-field v-model="form.name" :label="label('name')" autocomplete="name" minlength="2" maxlength="80" required variant="outlined" />
        <v-text-field v-model="form.email" :label="label('email')" autocomplete="email" type="email" maxlength="120" required variant="outlined" />
        <v-textarea v-model="form.comment" :label="label('reportDescription')" minlength="10" maxlength="2000" required variant="outlined" />
        <v-btn color="primary" :loading="sending" type="submit">{{ label('supportSend') }}</v-btn>
      </v-form>
    </v-card>
    <v-card class="pa-5 mt-4" variant="outlined">
      <h2 class="mb-3">{{ label('invoiceRecovery') }}</h2>
      <v-alert v-if="invoiceFeedback" class="mb-4" :type="invoiceError ? 'error' : 'info'" variant="tonal" role="status">{{ invoiceFeedback }}</v-alert>
      <v-form @submit.prevent="recoverInvoice">
        <v-text-field v-model="invoice.booking_id" :label="label('bookingId')" min="1" type="number" required variant="outlined" />
        <v-text-field v-model="invoice.email" :label="label('email')" autocomplete="email" type="email" maxlength="255" required variant="outlined" />
        <v-btn color="primary" :loading="recovering" type="submit">{{ label('invoiceSend') }}</v-btn>
      </v-form>
    </v-card>
  </v-container>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useAdminFormat } from '@/admin/format'
import { apiFetch, getStoredUser } from '@/services/auth'

const { label, format } = useAdminFormat()
const user = getStoredUser()
const form = reactive({ name: user ? format(user, 'person') : '', email: user?.epasts ?? '', comment: '' })
const sent = ref(false)
const error = ref('')
const sending = ref(false)
const invoice = reactive({ booking_id: '', email: form.email })
const recovering = ref(false)
const invoiceError = ref(false)
const invoiceFeedback = ref('')

async function send () {
  if (sending.value) return
  sending.value = true
  error.value = ''
  sent.value = false
  try {
    const response = await apiFetch('/api/contact', {
      auth: false,
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(form),
    })
    const data = await response.json()
    if (!response.ok) {
      error.value = Object.values(data.errors ?? {})[0]?.[0] || data.message || label('saveError')
      return
    }
    sent.value = true
    form.comment = ''
  } catch {
    error.value = label('saveError')
  } finally {
    sending.value = false
  }
}
async function recoverInvoice () {
  if (recovering.value) return
  recovering.value = true
  invoiceFeedback.value = ''
  invoiceError.value = false
  try {
    const response = await apiFetch('/api/support/invoice', {
      auth: false,
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(invoice),
    })
    const data = await response.json()
    invoiceError.value = !response.ok
    invoiceFeedback.value = Object.values(data.errors ?? {})[0]?.[0] || data.message || label('saveError')
  } catch {
    invoiceError.value = true
    invoiceFeedback.value = label('saveError')
  } finally {
    recovering.value = false
  }
}
</script>

<style scoped>
.support-page { max-width: 680px; padding-block: 36px; }
</style>
