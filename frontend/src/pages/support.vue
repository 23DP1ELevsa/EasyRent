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
        <TurnstileCaptcha v-if="captchaEnabled" ref="contactCaptcha" v-model="contactToken" action="contact" />
        <v-btn color="primary" :disabled="!contactReady" :loading="sending" type="submit">{{ label('supportSend') }}</v-btn>
      </v-form>
    </v-card>
    <v-card class="pa-5 mt-4" variant="outlined">
      <h2 class="mb-3">{{ label('invoiceRecovery') }}</h2>
      <v-alert v-if="invoiceFeedback" class="mb-4" :type="invoiceError ? 'error' : 'info'" variant="tonal" role="status">{{ invoiceFeedback }}</v-alert>
      <v-form @submit.prevent="recoverInvoice">
        <v-text-field v-model="invoice.booking_id" :label="label('bookingId')" min="1" type="number" required variant="outlined" />
        <v-text-field v-model="invoice.email" :label="label('email')" autocomplete="email" type="email" maxlength="255" required variant="outlined" />
        <TurnstileCaptcha v-if="captchaEnabled" ref="invoiceCaptcha" v-model="invoiceToken" action="invoice_recovery" />
        <v-btn color="primary" :disabled="!invoiceReady" :loading="recovering" type="submit">{{ label('invoiceSend') }}</v-btn>
      </v-form>
    </v-card>
  </v-container>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useAdminFormat } from '@/admin/format'
import TurnstileCaptcha from '@/components/TurnstileCaptcha.vue'
import { getStoredUser } from '@/services/auth'
import { captchaErrorKey, useTurnstile } from '@/services/turnstile'
import { useLocale } from '@/stores/locale'

const { label, format } = useAdminFormat()
const { t } = useLocale()
const { enabled: captchaEnabled, token: contactToken, widget: contactCaptcha, ready: contactReady, post: postContact } = useTurnstile()
const { token: invoiceToken, widget: invoiceCaptcha, ready: invoiceReady, post: postInvoice } = useTurnstile()
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
  if (sending.value || !contactReady.value) return
  sending.value = true
  error.value = ''
  sent.value = false
  try {
    const response = await postContact('/api/contact', form)
    const data = await response.json()
    if (!response.ok) {
      error.value = Object.values(data.errors ?? {})[0]?.[0] || data.message || label('saveError')
      return
    }
    sent.value = true
    form.comment = ''
  } catch (error_) {
    const key = captchaErrorKey(error_)
    error.value = key ? t(key) : label('saveError')
  } finally {
    sending.value = false
  }
}
async function recoverInvoice () {
  if (recovering.value || !invoiceReady.value) return
  recovering.value = true
  invoiceFeedback.value = ''
  invoiceError.value = false
  try {
    const response = await postInvoice('/api/support/invoice', invoice)
    const data = await response.json()
    invoiceError.value = !response.ok
    invoiceFeedback.value = Object.values(data.errors ?? {})[0]?.[0] || data.message || label('saveError')
  } catch (error_) {
    const key = captchaErrorKey(error_)
    invoiceError.value = true
    invoiceFeedback.value = key ? t(key) : label('saveError')
  } finally {
    recovering.value = false
  }
}
</script>

<style scoped>
.support-page { max-width: 680px; padding-block: 36px; }
</style>
