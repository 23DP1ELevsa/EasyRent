<template>
  <div :aria-label="t('turnstile.title')" class="turnstile" role="group">
    <div ref="container" class="turnstile__widget" />
    <div v-if="loading || error" class="turnstile__status my-2" role="status">
      <v-progress-circular
        v-if="loading && !interactive"
        color="primary"
        indeterminate
        size="16"
        width="2"
      />
      <span>{{ t(error || (interactive ? 'turnstile.required' : 'turnstile.checking')) }}</span>
    </div>
    <v-btn
      v-if="error && !loading && online && turnstileSiteKey"
      class="mb-2"
      color="primary"
      size="small"
      type="button"
      variant="tonal"
      @click="reset()"
    >
      {{ t('turnstile.retry') }}
    </v-btn>
  </div>
</template>

<script setup>
  import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
  import { useTheme } from 'vuetify'
  import { loadTurnstile, turnstileSiteKey } from '@/services/turnstile'
  import { useLocale } from '@/stores/locale'

  const props = defineProps({ action: { type: String, required: true } })
  const model = defineModel({ type: String, default: '' })
  const { locale, t } = useLocale()
  const theme = useTheme()
  const colorScheme = computed(() => theme.current.value.dark ? 'dark' : 'light')
  const container = ref(null)
  const online = ref(navigator.onLine)
  const loading = ref(true)
  const interactive = ref(false)
  const error = ref('')
  const size = ref('flexible')
  let api
  let widgetId
  let generation = 0
  let observer

  function remove () {
    generation++
    if (widgetId !== undefined) api.remove(widgetId)
    widgetId = undefined
    model.value = ''
  }

  function fail (key) {
    model.value = ''
    loading.value = false
    interactive.value = false
    error.value = key
    return true
  }

  async function render () {
    remove()
    if (!online.value) return fail('turnstile.offline')
    if (!turnstileSiteKey) return fail('turnstile.configuration')

    const current = generation
    error.value = ''
    interactive.value = false
    loading.value = true
    const active = callback => (...args) => {
      if (current === generation) return callback(...args)
    }
    try {
      api = await loadTurnstile()
      if (current !== generation) return
      widgetId = api.render(container.value, {
        'sitekey': turnstileSiteKey,
        'action': props.action,
        'appearance': 'interaction-only',
        'size': size.value,
        'theme': colorScheme.value,
        'language': locale.value === 'ru' ? 'ru' : 'en',
        'retry': 'never',
        'response-field': false,
        'refresh-expired': 'manual',
        'refresh-timeout': 'never',
        'callback': active(token => {
          model.value = token
          loading.value = false
          interactive.value = false
          error.value = ''
        }),
        'before-interactive-callback': active(() => {
          interactive.value = true
        }),
        'expired-callback': active(() => reset('turnstile.expired')),
        'error-callback': active(() => fail('turnstile.loadFailed')),
        'timeout-callback': active(() => fail('turnstile.timeout')),
        'unsupported-callback': active(() => fail('turnstile.unsupported')),
      })
    } catch {
      if (current === generation) fail('turnstile.loadFailed')
    }
  }

  function reset (message = '') {
    if (!online.value || widgetId === undefined) return render()
    model.value = ''
    error.value = message
    interactive.value = false
    loading.value = true
    try {
      api.reset(widgetId)
    } catch {
      return render()
    }
  }

  function connectionChanged () {
    online.value = navigator.onLine
  }

  watch([() => props.action, locale, colorScheme, online, size], render, { flush: 'post' })
  onMounted(() => {
    size.value = container.value.clientWidth < 300 ? 'compact' : 'flexible'
    if (size.value === 'flexible') void render()
    observer = new ResizeObserver(([entry]) => {
      size.value = entry.contentRect.width < 300 ? 'compact' : 'flexible'
    })
    observer.observe(container.value)
    window.addEventListener('online', connectionChanged)
    window.addEventListener('offline', connectionChanged)
  })
  onBeforeUnmount(() => {
    observer?.disconnect()
    window.removeEventListener('online', connectionChanged)
    window.removeEventListener('offline', connectionChanged)
    remove()
  })
  defineExpose({ reset })
</script>

<style scoped>
.turnstile, .turnstile__widget { min-width: 0; width: 100%; }
.turnstile__status { display: flex; align-items: center; gap: 8px; font-size: .875rem; }
</style>
