<template>
  <v-app :class="['app-shell', themeModeClass]">
    <div class="app-shell__glow app-shell__glow--left"></div>
    <div class="app-shell__glow app-shell__glow--right"></div>
    <v-app-bar height="72" class="appbar" flat>
      <v-container class="appbar__inner d-flex align-center">
        <v-btn icon variant="text" class="d-md-none me-2" @click="drawer = true" :aria-label="t('app.nav.openMenu')">
          <v-icon>mdi-menu</v-icon>
        </v-btn>

        <button type="button" class="brand-wrap d-flex align-center" @click="goHome">
          <v-avatar size="34" class="me-2" variant="tonal">
            <v-icon>mdi-car</v-icon>
          </v-avatar>
          <div class="brand">
            <div class="brand__title">EasyRent</div>
            <div class="brand__subtitle d-none d-sm-block">{{ t('app.brandSubtitle') }}</div>
          </div>
        </button>

        <v-spacer />

        <v-btn
          icon
          variant="text"
          class="theme-toggle d-md-none me-2"
          :aria-label="themeToggleLabel"
          @click="toggleTheme"
        >
          <v-icon>{{ themeIcon }}</v-icon>
        </v-btn>

        <div class="d-none d-md-flex align-center ga-1 nav-cluster">
          <v-menu location="bottom end">
            <template #activator="{ props }">
              <v-btn variant="text" class="lang-btn" v-bind="props" :aria-label="t('app.language.selectAria')">
                <span>{{ currentLanguage.nativeName }}</span>
                <v-icon size="18">mdi-chevron-down</v-icon>
              </v-btn>
            </template>

            <v-list>
              <v-list-item
                v-for="language in supportedLanguages"
                :key="language.code"
                :active="language.code === locale"
                @click="setLocale(language.code)"
              >
                <v-list-item-title>{{ language.nativeName }}</v-list-item-title>
              </v-list-item>
            </v-list>
          </v-menu>

          <v-btn icon variant="text" class="theme-toggle" :aria-label="themeToggleLabel" @click="toggleTheme">
            <v-icon>{{ themeIcon }}</v-icon>
          </v-btn>

          <v-btn variant="text" class="nav-btn" @click="goHome">
            <v-icon start>mdi-home</v-icon>
            {{ t('app.nav.home') }}
          </v-btn>

          <v-btn variant="text" class="nav-btn" @click="goMap">
            <v-icon start>mdi-map</v-icon>
            {{ t('app.nav.map') }}
          </v-btn>

          <v-divider vertical class="mx-2" />

          <template v-if="user">
            <v-menu>
              <template #activator="{ props }">
                <v-btn variant="text" class="profile-menu-btn" rounded="xl" v-bind="props">
                  <v-icon start>mdi-account</v-icon>
                  {{ getUserDisplayName() }}
                </v-btn>
              </template>

              <v-list>
                <v-list-item 
                  :title="t('app.auth.profile')" 
                  prepend-icon="mdi-account-circle"
                  @click="goProfile"
                />
                <v-list-item 
                  :title="t('app.auth.logout')" 
                  prepend-icon="mdi-logout"
                  @click="logout"
                />
              </v-list>
            </v-menu>
          </template>

          <v-btn v-else class="auth-btn" rounded="xl" elevation="6" @click="goAuth">
            {{ t('app.auth.loginRegister') }}
          </v-btn>
        </div>
      </v-container>
    </v-app-bar>

    <v-navigation-drawer v-model="drawer" temporary location="left" width="300" class="drawer">
      <div class="pa-4 d-flex align-center">
        <v-avatar size="36" class="me-2" variant="tonal">
          <v-icon>mdi-car</v-icon>
        </v-avatar>
        <div>
          <div class="text-subtitle-1 font-weight-bold">EasyRent</div>
          <div class="text-caption opacity-70">{{ t('app.nav.menu') }}</div>
        </div>

        <v-spacer />

        <v-btn icon variant="text" @click="drawer = false" :aria-label="t('app.nav.closeMenu')">
          <v-icon>mdi-close</v-icon>
        </v-btn>
      </div>

      <v-divider />

      <v-list nav density="comfortable">
        <v-list-item :title="t('app.nav.home')" prepend-icon="mdi-home" @click="goHomeFromDrawer" />
        <v-list-item :title="t('app.nav.map')" prepend-icon="mdi-map" @click="goMapFromDrawer" />
        <v-list-subheader>{{ t('app.language.select') }}</v-list-subheader>
        <v-list-item
          v-for="language in supportedLanguages"
          :key="`drawer-${language.code}`"
          :active="language.code === locale"
          @click="setLocale(language.code)"
        >
          <v-list-item-title>{{ language.nativeName }}</v-list-item-title>
        </v-list-item>
        <v-list-item :title="themeToggleLabel" :prepend-icon="themeIcon" @click="toggleTheme" />
        <v-divider />
        <template v-if="user">
          <v-list-item :title="t('app.auth.profile')" prepend-icon="mdi-account-circle" @click="goProfileFromDrawer" />
          <v-list-item :title="t('app.auth.logout')" prepend-icon="mdi-logout" @click="logout" />
        </template>
        <v-list-item v-else :title="t('app.auth.loginRegister')" prepend-icon="mdi-account" @click="goAuthFromDrawer" />
      </v-list>
    </v-navigation-drawer>

    <v-main class="main app-main">
      <RouterView />
    </v-main>

    <v-footer v-if="route.path === HOME_ROUTE" class="footer" flat>
      <v-container class="footer-shell">
        <v-row class="footer-grid" align="start">
          <v-col cols="12" md="4">
            <div class="footer-brand d-flex align-center mb-3">
              <v-avatar size="34" class="me-2" variant="tonal">
                <v-icon>mdi-car</v-icon>
              </v-avatar>
              <div>
                <div class="text-subtitle-1 font-weight-black">EasyRent</div>
                <div class="text-caption footer-copy">{{ t('app.brandSubtitle') }}</div>
              </div>
            </div>
            <div class="text-body-2 footer-copy mb-4">
              {{ t('app.footer.summary') }}
            </div>

            <div class="d-flex align-center ga-2 footer-social">
                <v-btn
                  icon
                  variant="text"
                  size="small"
                  aria-label="GitHub"
                  href="https://github.com/23DP1ELevsa/EasyRent"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  <v-icon>mdi-github</v-icon>
                </v-btn>
            </div>
          </v-col>

          <v-col cols="6" sm="4" md="2">
            <div class="footer-title">{{ t('app.footer.users') }}</div>
            <div class="footer-link" @click="goHome">{{ t('app.nav.home') }}</div>
            <div class="footer-link" @click="goMap">{{ t('app.nav.map') }}</div>
          </v-col>

          <v-col cols="6" sm="4" md="3">
            <div class="footer-title">{{ t('app.footer.providers') }}</div>
            <div class="footer-link" @click="goMap">{{ t('app.footer.addVehicle') }}</div>
            <div class="footer-link" @click="goMap">{{ t('app.footer.manageAvailability') }}</div>
          </v-col>
        </v-row>

        <v-divider class="footer-divider" />

        <div class="d-flex align-center justify-space-between py-3">
          <div class="text-caption footer-copy">© {{ year }} EasyRent</div>
        </div>
      </v-container>
    </v-footer>

    <v-snackbar
      v-model="notificationVisible"
      location="bottom"
      :timeout="notificationState.timeout"
      :content-props="{ class: 'app-notification__content' }"
      class="app-notification"
    >
      <div class="app-notification__body" :class="`is-${notificationState.color}`">
        <div class="app-notification__text">{{ notificationState.text }}</div>
      </div>
    </v-snackbar>
  </v-app>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useTheme } from 'vuetify'
import { AUTH_ROUTE, HOME_ROUTE, MAP_ROUTE, PROFILE_ROUTE } from '@/router/paths'
import { useLocale } from '@/stores/locale'
import { useNotifications } from '@/stores/notifications'
import { getStoredUser, logoutRequest, syncCurrentUser } from '@/services/auth'

const router = useRouter()
const route = useRoute()
const theme = useTheme()
const { locale, currentLanguage, supportedLanguages, setLocale, t } = useLocale()
const drawer = ref(false)
const user = ref(null)
const themeMode = ref('light')
const year = computed(() => new Date().getFullYear())
const themeIcon = computed(() => themeMode.value === 'dark' ? 'mdi-weather-sunny' : 'mdi-weather-night')
const themeToggleLabel = computed(() => themeMode.value === 'dark' ? t('app.theme.light') : t('app.theme.dark'))
const themeModeClass = computed(() => `theme-${themeMode.value}`)
const { notificationState, setNotificationVisible } = useNotifications()
const notificationVisible = computed({
  get: () => notificationState.visible,
  set: value => setNotificationVisible(value),
})

function applyTheme(mode) {
  themeMode.value = mode === 'dark' ? 'dark' : 'light'
  theme.global.name.value = themeMode.value === 'dark' ? 'easyRentDark' : 'easyRent'
  document.documentElement.setAttribute('data-theme', themeMode.value)
  localStorage.setItem('theme-mode', themeMode.value)
}

function toggleTheme() {
  applyTheme(themeMode.value === 'dark' ? 'light' : 'dark')
}

function syncUserFromStorage() {
  user.value = getStoredUser()
}

function scrollToTop() {
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

function navigateTo(path) {
  router.push(path)
  scrollToTop()
}

function closeDrawerAndRun(callback) {
  drawer.value = false
  setTimeout(callback, 50)
}

function handleUserUpdated(event) {
  user.value = event.detail
}

function handleUserLoggedOut() {
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

function goHome() {
  navigateTo(HOME_ROUTE)
}

function goMap() {
  navigateTo(MAP_ROUTE)
}

function goAuth() {
  navigateTo(AUTH_ROUTE)
}

function goProfile() {
  if (user.value) {
    navigateTo(PROFILE_ROUTE)
  }
}

function goProfileFromDrawer() {
  closeDrawerAndRun(goProfile)
}

function goMapFromDrawer() {
  closeDrawerAndRun(goMap)
}

function goHomeFromDrawer() {
  closeDrawerAndRun(goHome)
}

function goAuthFromDrawer() {
  closeDrawerAndRun(goAuth)
}

async function logout() {
  await logoutRequest()
  user.value = null
  drawer.value = false
  goHome()
}

function getUserDisplayName() {
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
.app-shell {
  background: transparent;
  color: var(--er-text);
}

.app-shell__glow {
  position: fixed;
  z-index: 0;
  inset: auto;
  width: 360px;
  height: 360px;
  border-radius: 999px;
  pointer-events: none;
  filter: blur(12px);
}

.app-shell__glow--left {
  top: 96px;
  left: -140px;
  background: rgba(15, 118, 110, 0.08);
}

.app-shell__glow--right {
  top: 220px;
  right: -180px;
  background: rgba(201, 107, 59, 0.08);
}

.appbar {
  background: var(--er-appbar-bg) !important;
  backdrop-filter: blur(18px);
  border-bottom: 1px solid var(--er-appbar-border);
  box-shadow: 0 16px 40px rgba(28, 40, 52, 0.08);
}

.appbar__inner {
  min-height: 72px;
}

.brand-wrap {
  appearance: none;
  background: transparent;
  border: 0;
  padding: 8px 12px 8px 8px;
  border-radius: 18px;
  transition: background-color 0.2s ease;
}

.brand-wrap:hover {
  background: rgba(15, 118, 110, 0.06);
}

.nav-cluster {
  padding: 4px;
  border-radius: 999px;
  background: var(--er-nav-surface);
  border: 1px solid var(--er-appbar-border);
}

.lang-btn {
  color: var(--er-text) !important;
  border-radius: 999px !important;
  text-transform: none;
  gap: 8px;
}

.lang-label {
  font-weight: 700;
}

.lang-flag {
  font-size: 1rem;
  line-height: 1;
}

.nav-btn {
  color: var(--er-text) !important;
  border-radius: 999px !important;
}

.theme-toggle {
  color: var(--er-text) !important;
  border-radius: 999px !important;
}

.nav-btn:hover,
.lang-btn:hover {
  background: rgba(15, 118, 110, 0.08) !important;
}

.theme-toggle:hover {
  background: rgba(15, 118, 110, 0.08) !important;
}

.auth-btn {
  background: var(--er-auth-btn-bg);
  border: 1px solid var(--er-auth-btn-border);
  color: var(--er-auth-btn-text);
  box-shadow: var(--er-auth-btn-shadow);
}

.profile-menu-btn {
  color: var(--er-text) !important;
  border-radius: 999px !important;
  box-shadow: none !important;
}

.profile-menu-btn:hover {
  background: rgba(15, 118, 110, 0.08) !important;
}

.footer {
  background: var(--er-footer-bg);
  border-top: 1px solid var(--er-footer-border);
}

.footer-shell {
  max-width: 1320px;
  padding-top: 8px;
  padding-bottom: 0;
}

.footer-grid {
  padding-top: 28px;
  padding-bottom: 24px;
}

.footer-copy {
  color: var(--er-footer-text);
}

.footer-title {
  font-weight: 800;
  margin-bottom: 10px;
  color: var(--er-footer-text-strong);
}

.footer-link {
  font-size: 14px;
  margin: 8px 0;
  color: var(--er-footer-text);
  cursor: pointer;
  transition: color 0.2s ease;
}

.footer-link:hover { color: var(--er-footer-text-strong); }

.footer-divider { border-color: var(--er-footer-border) !important; }

.footer-social :deep(.v-btn) {
  color: var(--er-footer-text-strong);
}

.is-disabled { opacity: 0.55; cursor: default; }
.drawer {
  background: var(--er-drawer-bg);
}

.brand__title { font-weight: 800; line-height: 1.05; color: var(--er-text); }
.brand__subtitle { font-size: 12px; opacity: 0.8; color: var(--er-text-muted); }

.app-main {
  position: relative;
  z-index: 1;
}

.app-notification {
  z-index: 2400 !important;
}

.app-notification__content {
  padding: 0 !important;
  overflow: visible !important;
  background: transparent !important;
  box-shadow: none !important;
}

.app-notification__body {
  min-width: min(520px, calc(100vw - 32px));
  max-width: min(520px, calc(100vw - 32px));
  padding: 14px 18px;
  border-radius: 18px;
  border: 1px solid var(--er-stroke);
  background: color-mix(in srgb, var(--er-surface) 94%, transparent);
  backdrop-filter: blur(18px);
  box-shadow: 0 18px 40px rgba(25, 41, 55, 0.18);
}

.app-notification__body.is-success {
  border-color: rgba(22, 163, 74, 0.38);
}

.app-notification__body.is-error {
  border-color: rgba(220, 38, 38, 0.38);
}

.app-notification__body.is-warning {
  border-color: rgba(245, 158, 11, 0.42);
}

.app-notification__body.is-info {
  border-color: rgba(14, 116, 144, 0.34);
}

.app-notification__text {
  color: var(--er-text);
  line-height: 1.5;
}

@media (max-width: 960px) {
  .appbar {
    background: color-mix(in srgb, var(--er-appbar-bg) 94%, transparent) !important;
  }
}

@media (max-width: 600px) {
  .app-notification__body {
    min-width: calc(100vw - 24px);
    max-width: calc(100vw - 24px);
    padding: 12px 14px;
  }
}
</style>
