<template>
  <v-app :class="['app-shell', themeModeClass]">
    <div class="app-shell__glow app-shell__glow--left"></div>
    <div class="app-shell__glow app-shell__glow--right"></div>
    <v-app-bar height="72" class="appbar" flat>
      <v-container class="appbar__inner d-flex align-center">
        <v-btn icon variant="text" class="d-md-none me-2" @click="drawer = true" aria-label="Atvērt izvēlni">
          <v-icon>mdi-menu</v-icon>
        </v-btn>

        <div class="brand-wrap d-flex align-center" role="button" style="cursor:pointer" @click="goHome">
          <v-avatar size="34" class="me-2" variant="tonal">
            <v-icon>mdi-car</v-icon>
          </v-avatar>
          <div class="brand">
            <div class="brand__title">EasyRent</div>
            <div class="brand__subtitle d-none d-sm-block">Transporta noma Latvijā</div>
          </div>
        </div>

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
          <v-btn icon variant="text" class="theme-toggle" :aria-label="themeToggleLabel" @click="toggleTheme">
            <v-icon>{{ themeIcon }}</v-icon>
          </v-btn>

          <v-btn variant="text" class="nav-btn" @click="goHome">
            <v-icon start>mdi-home</v-icon>
            Sākums
          </v-btn>

          <v-btn variant="text" class="nav-btn" @click="goMap">
            <v-icon start>mdi-map</v-icon>
            Karte
          </v-btn>

          <v-divider vertical class="mx-2" />

          <template v-if="user">
            <v-menu>
              <template #activator="{ props }">
                <v-btn class="auth-btn" rounded="xl" elevation="6" v-bind="props">
                  <v-icon start>mdi-account</v-icon>
                  {{ getUserDisplayName() }}
                </v-btn>
              </template>

              <v-list>
                <v-list-item 
                  title="Mans profils" 
                  prepend-icon="mdi-account-circle"
                  @click="goProfile"
                />
                <v-list-item 
                  title="Izlogoties" 
                  prepend-icon="mdi-logout"
                  @click="logout"
                />
              </v-list>
            </v-menu>
          </template>

          <v-btn v-else class="auth-btn" rounded="xl" elevation="6" @click="goAuth">
            Login/Register
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
          <div class="text-caption opacity-70">Izvēlne</div>
        </div>

        <v-spacer />

        <v-btn icon variant="text" @click="drawer = false" aria-label="Aizvērt izvēlni">
          <v-icon>mdi-close</v-icon>
        </v-btn>
      </div>

      <v-divider />

      <v-list nav density="comfortable">
        <v-list-item title="Sākums" prepend-icon="mdi-home" @click="goHomeFromDrawer" />
        <v-list-item title="Karte" prepend-icon="mdi-map" @click="goMapFromDrawer" />
        <v-list-item :title="themeToggleLabel" :prepend-icon="themeIcon" @click="toggleTheme" />
        <v-divider />
        <template v-if="user">
          <v-list-item title="Mans profils" prepend-icon="mdi-account-circle" @click="goProfileFromDrawer" />
          <v-list-item title="Izlogoties" prepend-icon="mdi-logout" @click="logout" />
        </template>
        <v-list-item v-else title="Login/Register" prepend-icon="mdi-account" @click="goAuthFromDrawer" />
      </v-list>
    </v-navigation-drawer>

    <v-main class="main app-main">
      <HomePage v-if="view === 'home' && route.path === '/'" />
      <AuthPage v-else-if="view === 'auth' && route.path === '/auth'" @auth-success="onAuthSuccess" />
      <RouterView v-else />
    </v-main>

    <v-footer v-if="route.path === '/'" class="footer" flat>
      <v-container>
        <v-row class="py-10" align="start">
          <v-col cols="12" md="4">
            <div class="footer-brand d-flex align-center mb-3">
              <v-avatar size="34" class="me-2" variant="tonal">
                <v-icon>mdi-car</v-icon>
              </v-avatar>
              <div>
                <div class="text-subtitle-1 font-weight-black">EasyRent</div>
                <div class="text-caption opacity-70">Transporta noma Latvijā</div>
              </div>
            </div>
            <div class="text-body-2 opacity-80 mb-4">
              Vienota platforma, lai atrastu, salīdzinātu un rezervētu transportu.
            </div>

            <div class="d-flex align-center ga-2">
              <v-btn icon variant="text" size="small" disabled aria-label="Instagram"><v-icon>mdi-instagram</v-icon></v-btn>
              <v-btn icon variant="text" size="small" disabled aria-label="LinkedIn"><v-icon>mdi-linkedin</v-icon></v-btn>
            </div>
          </v-col>

          <v-col cols="6" sm="4" md="2">
            <div class="footer-title">Lietotājiem</div>
            <div class="footer-link" @click="goHome">Sākums</div>
            <div class="footer-link" @click="goMap">Karte</div>
            <div class="footer-link is-disabled">Rezervācijas</div>
          </v-col>

          <v-col cols="6" sm="4" md="3">
            <div class="footer-title">Pakalpojumu sniedzējiem</div>
            <div class="footer-link" @click="goMap">Pievienot transportu</div>
            <div class="footer-link" @click="goMap">Pārvaldīt pieejamību</div>
            <div class="footer-link is-disabled">Pasūtījumi</div>
          </v-col>

          <v-col cols="12" sm="4" md="3">
            <div class="footer-title">Juridiski</div>
            <div class="footer-link is-disabled">Noteikumi</div>
            <div class="footer-link is-disabled">Privātums</div>
            <div class="footer-link is-disabled">Sīkdatnes</div>
          </v-col>
        </v-row>

        <v-divider class="footer-divider" />

        <div class="d-flex flex-column flex-md-row align-center justify-space-between py-4 gap-2">
          <div class="text-caption opacity-70">© {{ year }} EasyRent</div>
          <div class="d-flex flex-wrap justify-center ga-4 text-caption opacity-70">
            <span class="footer-mini is-disabled">Palīdzība</span>
            <span class="footer-mini is-disabled">Kontakti</span>
            <span class="footer-mini is-disabled">Cookie settings</span>
          </div>
        </div>
      </v-container>
    </v-footer>
  </v-app>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useTheme } from 'vuetify'
import HomePage from './components/HomePage.vue'
import AuthPage from './components/AuthPage.vue'

const router = useRouter()
const route = useRoute()
const theme = useTheme()
const drawer = ref(false)
const view = ref('home')
const user = ref(null)
const themeMode = ref('light')
const year = computed(() => new Date().getFullYear())
const themeIcon = computed(() => themeMode.value === 'dark' ? 'mdi-weather-sunny' : 'mdi-weather-night')
const themeToggleLabel = computed(() => themeMode.value === 'dark' ? 'Gaišais režīms' : 'Tumšais režīms')
const themeModeClass = computed(() => `theme-${themeMode.value}`)

function applyTheme(mode) {
  themeMode.value = mode === 'dark' ? 'dark' : 'light'
  theme.global.name.value = themeMode.value === 'dark' ? 'easyRentDark' : 'easyRent'
  document.documentElement.setAttribute('data-theme', themeMode.value)
  localStorage.setItem('theme-mode', themeMode.value)
}

function toggleTheme() {
  applyTheme(themeMode.value === 'dark' ? 'light' : 'dark')
}

onMounted(() => {
  const userData = localStorage.getItem('user')
  if (userData) {
    user.value = JSON.parse(userData)
  }

  applyTheme(localStorage.getItem('theme-mode') || 'light')

  // Listen for user profile updates from profile.vue
  window.addEventListener('user-updated', (event) => {
    user.value = event.detail
  })

  // Listen for logout from profile.vue
  window.addEventListener('user-logged-out', () => {
    user.value = null
    goHome()
  })
})

// Watch route changes to keep view in sync for home/auth, but allow RouterView to handle other routes
watch(() => route.path, (newPath) => {
  if (newPath === '/' || newPath === '') {
    view.value = 'home'
  } else if (newPath === '/auth') {
    view.value = 'auth'
  }
  // For other routes like /profile, view stays as is and RouterView handles it
})

function goHome() {
  view.value = 'home'
  router.push('/')
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

function goMap() {
  view.value = 'map'
  router.push('/map')
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

function goAuth() {
  view.value = 'auth'
  router.push('/auth')
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

function goProfile() {
  if (user.value) {
    router.push(`/profile`)
  }
}

function goProfileFromDrawer() {
  drawer.value = false
  setTimeout(goProfile, 50)
}

function goMapFromDrawer() {
  drawer.value = false
  setTimeout(goMap, 50)
}

function goHomeFromDrawer() {
  drawer.value = false
  setTimeout(goHome, 50)
}

function goAuthFromDrawer() {
  drawer.value = false
  setTimeout(goAuth, 50)
}

function logout() {
  localStorage.removeItem('user')
  localStorage.removeItem('token')
  user.value = null
  drawer.value = false
  goHome()
}

function getUserDisplayName() {
  if (!user.value) return 'Profils'
  
  // Ja klients, rādi lietotajvardu
  if (user.value.loma === 'klients' && user.value.klients?.lietotajvards) {
    return user.value.klients.lietotajvards
  }
  
  // Ja pakalpojumu sniedzējs, rādi vārdu
  return user.value.vards || 'Profils'
}

function onAuthSuccess() {
  const userData = localStorage.getItem('user')
  if (userData) {
    user.value = JSON.parse(userData)
  }
  goHome()
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

.nav-btn {
  color: var(--er-text) !important;
  border-radius: 999px !important;
}

.theme-toggle {
  color: var(--er-text) !important;
  border-radius: 999px !important;
}

.nav-btn:hover {
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

.footer {
  background: var(--er-footer-bg);
  border-top: 1px solid var(--er-footer-border);
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

.footer-mini { cursor: pointer; }
.footer-mini:hover { color: var(--er-footer-text-strong); }

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

@media (max-width: 960px) {
  .appbar {
    background: color-mix(in srgb, var(--er-appbar-bg) 94%, transparent) !important;
  }
}
</style>
