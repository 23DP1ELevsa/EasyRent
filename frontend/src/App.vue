<template>
  <v-app :class="['app-shell', themeModeClass, { 'app-shell--home': route.path === HOME_ROUTE }]">
    <v-app-bar
      :absolute="route.path === HOME_ROUTE"
      class="appbar"
      :class="{ 'appbar--home': route.path === HOME_ROUTE }"
      flat
      :height="mdAndUp ? 128 : 112"
    >
      <div class="appbar__inner">
        <div class="utility-bar">
          <div class="utility-controls">
            <v-btn
              :aria-label="themeToggleLabel"
              class="theme-toggle"
              icon
              size="small"
              variant="text"
              @click="toggleTheme"
            >
              <v-icon size="19">{{ themeIcon }}</v-icon>
            </v-btn>
            <div :aria-label="t('app.language.select')" class="language-switch">
              <button
                v-for="language in supportedLanguages"
                :key="language.code"
                :aria-label="language.nativeName"
                :aria-pressed="language.code === locale"
                :class="{ 'is-active': language.code === locale }"
                type="button"
                @click="setLocale(language.code)"
              >
                <span aria-hidden="true">{{ language.flag }}</span>
                {{ language.code.toUpperCase() }}
              </button>
            </div>
          </div>
          <span class="utility-tagline d-none d-sm-flex"><v-icon size="15">mdi-map-marker-outline</v-icon>{{ t('app.brandSubtitle') }}</span>
        </div>
        <div class="navigation-bar">
          <RouterLink aria-label="EasyRent" class="brand-wrap" :to="HOME_ROUTE" @click="scrollToTop"><BrandLogo /></RouterLink>
          <nav :aria-label="t('app.nav.menu')" class="desktop-nav d-none d-md-flex">
            <RouterLink class="nav-link" :class="{ 'is-active': route.path === HOME_ROUTE }" :to="HOME_ROUTE" @click="scrollToTop">{{ t('app.nav.home') }}</RouterLink>
            <RouterLink class="nav-link" :class="{ 'is-active': route.path === MAP_ROUTE || route.path.startsWith('/company/') }" :to="MAP_ROUTE" @click="scrollToTop">{{ t('app.nav.map') }}</RouterLink>
            <template v-if="user">
              <v-menu location="bottom end">
                <template #activator="{ props }">
                  <v-btn class="profile-menu-btn" v-bind="props" :class="{ 'is-active': route.path === PROFILE_ROUTE }" variant="text">
                    <v-icon size="20" start>mdi-account-circle-outline</v-icon>
                    <span class="profile-menu-name">{{ getUserDisplayName() }}</span>
                    <v-icon end size="16">mdi-chevron-down</v-icon>
                  </v-btn>
                </template>
                <v-list>
                  <v-list-item prepend-icon="mdi-account-circle-outline" :title="t('app.auth.profile')" @click="goProfile" />
                  <v-list-item prepend-icon="mdi-logout" :title="t('app.auth.logout')" @click="logout" />
                </v-list>
              </v-menu>
            </template>
            <v-btn
              v-else
              class="auth-btn"
              color="primary"
              rounded="pill"
              variant="flat"
              @click="goAuth"
            >{{ t('app.auth.loginRegister') }}<v-icon end size="18">mdi-arrow-right</v-icon></v-btn>
          </nav>
          <v-btn
            :aria-expanded="drawer"
            :aria-label="t('app.nav.openMenu')"
            class="mobile-menu-toggle d-md-none"
            icon
            variant="text"
            @click="drawer = true"
          ><v-icon>mdi-menu</v-icon></v-btn>
        </div>
      </div>
    </v-app-bar>

    <v-navigation-drawer
      v-model="drawer"
      class="drawer"
      location="right"
      temporary
      width="320"
    >
      <div class="drawer-heading">
        <BrandLogo />
        <v-btn :aria-label="t('app.nav.closeMenu')" icon variant="text" @click="drawer = false"><v-icon>mdi-close</v-icon></v-btn>
      </div>
      <v-divider />
      <v-list class="drawer-links" density="comfortable" nav>
        <v-list-item :active="route.path === HOME_ROUTE" prepend-icon="mdi-home-outline" :title="t('app.nav.home')" @click="goHomeFromDrawer" />
        <v-list-item :active="route.path === MAP_ROUTE" prepend-icon="mdi-map-outline" :title="t('app.nav.map')" @click="goMapFromDrawer" />
        <template v-if="user">
          <v-list-item :active="route.path === PROFILE_ROUTE" prepend-icon="mdi-account-circle-outline" :title="t('app.auth.profile')" @click="goProfileFromDrawer" />
          <v-list-item prepend-icon="mdi-logout" :title="t('app.auth.logout')" @click="logout" />
        </template>
        <v-list-item v-else prepend-icon="mdi-login" :title="t('app.auth.loginRegister')" @click="goAuthFromDrawer" />
      </v-list>
      <div class="drawer-preferences">
        <div class="drawer-language__label">{{ t('app.language.select') }}</div>
        <v-select
          v-model="mobileLocale"
          :aria-label="t('app.language.selectAria')"
          density="comfortable"
          hide-details
          item-title="nativeName"
          item-value="code"
          :items="supportedLanguages"
          variant="outlined"
        />
        <v-btn class="mt-4" :prepend-icon="themeIcon" variant="text" @click="toggleTheme">{{ themeToggleLabel }}</v-btn>
      </div>
    </v-navigation-drawer>

    <v-main class="app-main"><RouterView /><AppFooter /></v-main>

    <v-snackbar
      v-model="notificationVisible"
      class="app-notification"
      :content-props="{ class: 'app-notification__content' }"
      location="bottom"
      :timeout="notificationState.timeout"
    >
      <div aria-live="polite" class="app-notification__body" :class="`is-${notificationState.color}`" role="status">
        <v-icon size="21">{{ { success: 'mdi-check-circle-outline', error: 'mdi-alert-circle-outline', warning: 'mdi-alert-outline', info: 'mdi-information-outline' }[notificationState.color] || 'mdi-information-outline' }}</v-icon>
        <div class="app-notification__text">{{ notificationState.text }}</div>
      </div>
    </v-snackbar>
  </v-app>
</template>

<script setup>
  import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
  import { useRoute, useRouter } from 'vue-router'
  import { useDisplay, useTheme } from 'vuetify'
  import AppFooter from '@/components/AppFooter.vue'
  import BrandLogo from '@/components/BrandLogo.vue'
  import { AUTH_ROUTE, HOME_ROUTE, MAP_ROUTE, PROFILE_ROUTE } from '@/router/paths'
  import { getStoredUser, logoutRequest, syncCurrentUser } from '@/services/auth'
  import { useLocale } from '@/stores/locale'
  import { useNotifications } from '@/stores/notifications'

  const router = useRouter()
  const route = useRoute()
  const theme = useTheme()
  const { mdAndUp } = useDisplay()
  const { locale, supportedLanguages, setLocale, t } = useLocale()
  const drawer = ref(false)
  const user = ref(null)
  const themeMode = ref('light')
  const mobileLocale = computed({
    get: () => locale.value,
    set: value => setLocale(value),
  })
  const themeIcon = computed(() => themeMode.value === 'dark' ? 'mdi-weather-sunny' : 'mdi-weather-night')
  const themeToggleLabel = computed(() => themeMode.value === 'dark' ? t('app.theme.light') : t('app.theme.dark'))
  const themeModeClass = computed(() => `theme-${themeMode.value}`)
  const { notificationState, setNotificationVisible } = useNotifications()
  const notificationVisible = computed({
    get: () => notificationState.visible,
    set: value => setNotificationVisible(value),
  })

  function applyTheme (mode) {
    themeMode.value = mode === 'dark' ? 'dark' : 'light'
    theme.change(themeMode.value === 'dark' ? 'easyRentDark' : 'easyRent')
    document.documentElement.dataset.theme = themeMode.value
    localStorage.setItem('theme-mode', themeMode.value)
  }

  function toggleTheme () {
    applyTheme(themeMode.value === 'dark' ? 'light' : 'dark')
  }

  function syncUserFromStorage () {
    user.value = getStoredUser()
  }

  function scrollToTop () {
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  function navigateTo (path) {
    router.push(path)
    scrollToTop()
  }

  function closeDrawerAndRun (callback) {
    drawer.value = false
    setTimeout(callback, 50)
  }

  function handleUserUpdated (event) {
    user.value = event.detail
  }

  function handleUserLoggedOut () {
    user.value = null
    navigateTo(HOME_ROUTE)
  }

  onMounted(async () => {
    syncUserFromStorage()
    applyTheme(localStorage.getItem('theme-mode') || 'light')
    user.value = await syncCurrentUser()

    window.addEventListener('user-updated', handleUserUpdated)
    window.addEventListener('user-logged-out', handleUserLoggedOut)
  })

  onBeforeUnmount(() => {
    window.removeEventListener('user-updated', handleUserUpdated)
    window.removeEventListener('user-logged-out', handleUserLoggedOut)
  })

  function goHome () {
    navigateTo(HOME_ROUTE)
  }

  function goMap () {
    navigateTo(MAP_ROUTE)
  }

  function goAuth () {
    navigateTo(AUTH_ROUTE)
  }

  function goProfile () {
    if (user.value) {
      navigateTo(PROFILE_ROUTE)
    }
  }

  function goProfileFromDrawer () {
    closeDrawerAndRun(goProfile)
  }

  function goMapFromDrawer () {
    closeDrawerAndRun(goMap)
  }

  function goHomeFromDrawer () {
    closeDrawerAndRun(goHome)
  }

  function goAuthFromDrawer () {
    closeDrawerAndRun(goAuth)
  }

  async function logout () {
    await logoutRequest()
    user.value = null
    drawer.value = false
    goHome()
  }

  function getUserDisplayName () {
    if (!user.value) return t('app.auth.profile')

    // Ja klients, rādi lietotajvardu
    if (user.value.loma === 'klients' && user.value.klients?.lietotajvards) {
      return user.value.klients.lietotajvards
    }

    // Ja pakalpojumu sniedzējs, rādi vārdu
    return user.value.vards || t('app.auth.profile')
  }
</script>

<style>
.app-shell { background: var(--er-bg); color: var(--er-text); }
.appbar { color: var(--er-night-text) !important; background: var(--er-night) !important; box-shadow: none !important; }
.appbar--home { background: transparent !important; }
.appbar .v-toolbar__content { align-items: stretch; }
.appbar__inner { width: 100%; max-width: var(--er-content-width); margin: 0 auto; padding: 0 20px; }
.utility-bar { display: flex; align-items: center; justify-content: space-between; gap: 16px; height: 44px; border-bottom: 1px solid var(--er-night-border); }
.utility-controls { display: flex; align-items: center; gap: 8px; }
.utility-tagline { align-items: center; gap: 8px; color: var(--er-night-muted); font-size: .75rem; }
.utility-tagline .v-icon { color: var(--er-primary); }
.language-switch { display: flex; gap: 4px; }
.language-switch button { display: inline-flex; align-items: center; gap: 6px; padding: 5px 11px; border-radius: 999px; color: var(--er-night-muted); font-size: .75rem; font-weight: 700; transition: color .18s, background .18s; }
.language-switch button:hover, .language-switch .is-active { color: var(--er-night-text); background: rgb(255 255 255 / 12%); }
.navigation-bar { display: flex; align-items: center; justify-content: space-between; gap: 24px; height: 84px; }
.brand-wrap { display: inline-flex; text-decoration: none; border-radius: 4px; flex-shrink: 0; }
.desktop-nav { align-items: center; gap: 32px; font-size: .875rem; font-weight: 600; }
.nav-link { text-decoration: none; color: var(--er-night-text); padding-block: 12px; transition: color .18s; }
.nav-link:hover, .nav-link.is-active { color: var(--er-primary); }
.auth-btn { padding-inline: 22px !important; height: 42px !important; }
.theme-toggle, .mobile-menu-toggle, .profile-menu-btn { color: var(--er-night-text) !important; }
.profile-menu-btn { padding-inline: 12px !important; }
.profile-menu-btn.is-active { color: var(--er-primary) !important; }
.profile-menu-name { max-width: 180px; overflow: hidden; text-overflow: ellipsis; }
.app-main { min-width: 0; }
.app-shell--home .app-main { padding-top: 0 !important; }
.drawer { --er-text: var(--er-night-text); --er-text-muted: var(--er-night-muted); --er-surface: var(--er-night); --er-stroke: var(--er-night-border); --er-stroke-strong: rgb(255 255 255 / 24%); background: var(--er-night) !important; color: var(--er-night-text) !important; }
.drawer-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 28px 20px; }
.drawer-links { margin: 12px; background: transparent !important; border: 0 !important; }
.drawer-links .v-list-item { margin-block: 4px; border-radius: 12px; min-height: 48px; }
.drawer-preferences { padding: 20px; border-top: 1px solid var(--er-night-border); }
.drawer-language__label { margin-bottom: 12px; font-size: .75rem; letter-spacing: .08em; text-transform: uppercase; color: var(--er-night-muted); font-weight: 700; }
.app-notification { z-index: 2400 !important; }
.app-notification .v-snackbar__wrapper { background: transparent; box-shadow: none; }
.app-notification__content { padding: 0 !important; }
.app-notification__body { display: flex; align-items: center; gap: 12px; width: min(520px, calc(100vw - 32px)); padding: 16px 20px; background: var(--er-surface); color: var(--er-text); border: 1px solid var(--er-stroke); border-radius: 16px; box-shadow: var(--er-shadow-lg); line-height: 1.6; font-size: .875rem; }
.app-notification__body.is-success > .v-icon, .app-notification__body.is-info > .v-icon { color: var(--er-primary); }
.app-notification__body.is-error > .v-icon { color: rgb(var(--v-theme-error)); }
.app-notification__body.is-warning > .v-icon { color: rgb(var(--v-theme-warning)); }
.app-notification__text { min-width: 0; overflow-wrap: anywhere; }
@media (max-width: 959px) { .navigation-bar { height: 68px; } .utility-bar { height: 44px; } }
@media (max-width: 359px) { .appbar__inner { padding-inline: 16px; } .language-switch button { padding-inline: 8px; } }
</style>
