<template>
  <v-app>
    <v-app-bar height="72" class="appbar" flat>
      <v-container class="d-flex align-center">
        <v-btn icon variant="text" class="d-md-none me-2" @click="drawer = true" aria-label="Atvērt izvēlni">
          <v-icon>mdi-menu</v-icon>
        </v-btn>

        <div class="d-flex align-center" role="button" style="cursor:pointer" @click="goHome">
          <v-avatar size="34" class="me-2" variant="tonal">
            <v-icon>mdi-car</v-icon>
          </v-avatar>
          <div class="brand">
            <div class="brand__title">EasyRent</div>
            <div class="brand__subtitle d-none d-sm-block">Transporta noma Latvijā</div>
          </div>
        </div>

        <v-spacer />

        <div class="d-none d-md-flex align-center ga-1">
          <v-btn variant="text" @click="goHome">
            <v-icon start>mdi-home</v-icon>
            Sākums
          </v-btn>

          <v-btn variant="text" disabled>
            <v-icon start>mdi-map</v-icon>
            Karte
          </v-btn>

          <v-divider vertical class="mx-2" />

          <v-btn class="auth-btn" rounded="xl" elevation="6" @click="goAuth">
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
        <v-list-item title="Karte" subtitle="Drīzumā" prepend-icon="mdi-map" disabled />
        <v-list-item title="Login/Register" prepend-icon="mdi-account" @click="goAuthFromDrawer" />
      </v-list>
    </v-navigation-drawer>

    <v-main class="main">
      <HomePage v-if="view === 'home'" />
      <AuthPage v-else @auth-success="onAuthSuccess" />
    </v-main>

    <v-footer class="footer" flat>
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
            <div class="footer-link is-disabled">Karte</div>
            <div class="footer-link is-disabled">Rezervācijas</div>
          </v-col>

          <v-col cols="6" sm="4" md="3">
            <div class="footer-title">Pakalpojumu sniedzējiem</div>
            <div class="footer-link is-disabled">Pievienot transportu</div>
            <div class="footer-link is-disabled">Pārvaldīt pieejamību</div>
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
import { ref, computed } from 'vue'
import HomePage from './components/HomePage.vue'
import AuthPage from './components/AuthPage.vue'

const drawer = ref(false)
const view = ref('home')
const year = computed(() => new Date().getFullYear())

function goHome() {
  view.value = 'home'
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

function goAuth() {
  view.value = 'auth'
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

function goHomeFromDrawer() {
  drawer.value = false
  setTimeout(goHome, 50)
}

function goAuthFromDrawer() {
  drawer.value = false
  setTimeout(goAuth, 50)
}

function onAuthSuccess() {
  goHome()
}
</script>

<style>
.appbar {
  background: rgba(15, 23, 42, 0.72);
  backdrop-filter: blur(10px);
  border-bottom: 1px solid rgba(255, 255, 255, 0.10);
}

.auth-btn {
  background: rgba(255,255,255,0.12);
  border: 1px solid rgba(255,255,255,0.16);
  color: rgba(255,255,255,0.92);
}

.footer {
  background: rgba(15, 23, 42, 0.92);
  border-top: 1px solid rgba(255, 255, 255, 0.10);
}

.footer-title {
  font-weight: 800;
  margin-bottom: 10px;
  color: rgba(255,255,255,0.92);
}

.footer-link {
  font-size: 14px;
  margin: 8px 0;
  color: rgba(255,255,255,0.78);
  cursor: pointer;
}

.footer-link:hover { color: rgba(255,255,255,0.95); }

.footer-divider { border-color: rgba(255,255,255,0.12) !important; }

.footer-mini { cursor: pointer; }
.footer-mini:hover { color: rgba(255,255,255,0.95); }

.is-disabled { opacity: 0.55; cursor: default; }
.drawer { background: rgba(255, 255, 255, 0.96); }

.brand__title { font-weight: 800; line-height: 1.05; color: rgba(255,255,255,0.92); }
.brand__subtitle { font-size: 12px; opacity: 0.8; color: rgba(255,255,255,0.78); }
</style>
