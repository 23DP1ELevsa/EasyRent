<template>
  <span class="address-navigation">
    <component
      :is="mobile ? VBottomSheet : VMenu"
      v-if="links"
      v-model="open"
      v-bind="overlayProps"
    >
      <template #activator="{ props: activatorProps }">
        <button
          v-bind="activatorProps"
          :aria-label="fullAddress ? `${copy.directions}: ${fullAddress}` : copy.directions"
          class="address-navigation__trigger"
          :title="copy.directions"
          type="button"
          @keydown.esc="closeFromTrigger"
        >
          <span>{{ fullAddress || copy.directions }}</span>
          <v-icon aria-hidden="true" icon="mdi-navigation-outline" size="17" />
        </button>
      </template>

      <v-list
        :aria-label="copy.openInApp"
        class="address-navigation__list"
        :density="mobile ? 'comfortable' : 'compact'"
        :role="mobile ? undefined : 'menu'"
      >
        <v-list-subheader>{{ copy.openInApp }}</v-list-subheader>
        <v-list-item
          v-for="app in apps"
          :key="app.id"
          :href="links[app.id]"
          rel="noopener noreferrer"
          :role="mobile ? undefined : 'menuitem'"
          :target="mobile ? undefined : '_blank'"
          :title="app.name"
          @click.stop="open = false"
        >
          <template #prepend>
            <span aria-hidden="true" class="address-navigation__icon" :class="`address-navigation__icon--${app.id}`">
              <v-icon :icon="app.icon" size="20" />
            </span>
          </template>
        </v-list-item>
        <template v-if="fullAddress">
          <v-divider class="my-1" />
          <v-list-item
            :role="mobile ? undefined : 'menuitem'"
            tag="button"
            :title="copy.copyAddress"
            type="button"
            @click.stop="copyAddress"
          >
            <template #prepend>
              <span aria-hidden="true" class="address-navigation__icon"><v-icon icon="mdi-content-copy" size="19" /></span>
            </template>
          </v-list-item>
        </template>
      </v-list>
    </component>
    <slot v-else />
  </span>
</template>

<script setup>
  import { computed, ref, watch } from 'vue'
  import { useDisplay } from 'vuetify'
  import { VBottomSheet, VMenu } from 'vuetify/components'
  import { useLocale } from '@/stores/locale'
  import { useNotifications } from '@/stores/notifications'
  import { buildNavigationLinks, formatPublicAddress } from '@/utils/addressNavigation'

  const props = defineProps({
    address: { type: String, default: '' },
    city: { type: String, default: '' },
    latitude: { type: [Number, String], default: null },
    longitude: { type: [Number, String], default: null },
    postalCode: { type: String, default: '' },
  })

  const apps = [
    { id: 'googleMaps', name: 'Google Maps', icon: 'mdi-google-maps' },
    { id: 'waze', name: 'Waze', icon: 'mdi-waze' },
    { id: 'appleMaps', name: 'Apple Maps', icon: 'mdi-apple' },
  ]
  const { mobile } = useDisplay({ mobileBreakpoint: 768 })
  const { t } = useLocale()
  const { notifySuccess, notifyError } = useNotifications()
  const copy = computed(() => t('addressNavigation'))
  const fullAddress = computed(() => formatPublicAddress(props))
  const links = computed(() => buildNavigationLinks(props))
  const open = ref(false)
  const overlayProps = computed(() => mobile.value
    ? { 'aria-label': copy.value.openInApp }
    : { location: 'bottom start', offset: 6, minWidth: 220, maxWidth: 320 })

  watch(mobile, () => {
    open.value = false
  })

  function closeFromTrigger (event) {
    if (open.value) {
      event.stopPropagation()
      open.value = false
    }
  }

  async function copyAddress () {
    open.value = false
    try {
      await navigator.clipboard.writeText(fullAddress.value)
      notifySuccess(copy.value.addressCopied)
    } catch {
      notifyError(copy.value.copyFailed)
    }
  }
</script>

<style scoped>
.address-navigation { display: inline-block; min-width: 0; max-width: 100%; }
.address-navigation__trigger { display: inline-flex; align-items: center; gap: 6px; min-height: 28px; max-width: 100%; border-radius: 6px; color: inherit; text-align: start; overflow-wrap: anywhere; }
.address-navigation__trigger:hover span { text-decoration: underline; text-underline-offset: 3px; }
.address-navigation__trigger:focus-visible { outline: 2px solid var(--er-primary-strong); outline-offset: 2px; }
.address-navigation__trigger .v-icon { flex-shrink: 0; color: var(--er-primary-strong); }
.address-navigation__list { background: var(--er-surface); color: var(--er-text); border: 1px solid var(--er-stroke); border-radius: 14px; padding: 6px; box-shadow: var(--er-shadow-lg); }
.address-navigation__list :deep(.v-list-item) { width: 100%; text-align: start; border-radius: 8px; }
.address-navigation__icon { display: grid; place-items: center; width: 28px; height: 28px; margin-inline-end: 12px; border-radius: 8px; color: var(--er-text-muted); }
.address-navigation__icon--googleMaps { color: #b92c26; background: #fce8e6; }
.address-navigation__icon--waze { color: #102347; background: #a7e6ff; }
.address-navigation__icon--appleMaps { color: #145db5; background: #e4f3ec; }
@media (max-width: 767px) {
  .address-navigation__trigger { min-height: 44px; }
  .address-navigation__list { border-radius: 20px 20px 0 0; padding: 8px max(12px, env(safe-area-inset-right)) calc(12px + env(safe-area-inset-bottom)) max(12px, env(safe-area-inset-left)); }
}
</style>
