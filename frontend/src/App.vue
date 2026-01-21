<template>
  <v-app>
    <!-- HEADER -->
    <v-app-bar height="72" class="appbar" flat>
      <v-container class="d-flex align-center">
        <!-- Burger (tikai mobilajiem) -->
        <v-btn
          icon
          variant="text"
          class="d-md-none me-2"
          @click="drawer = true"
          aria-label="Atvērt izvēlni"
        >
          <v-icon>mdi-menu</v-icon>
        </v-btn>

        <!-- Logo / nosaukums -->
        <div class="d-flex align-center">
          <v-avatar size="34" class="me-2" variant="tonal">
            <v-icon>mdi-car</v-icon>
          </v-avatar>
          <div class="brand">
            <div class="brand__title">EasyRent</div>
            <div class="brand__subtitle d-none d-sm-block">Transporta noma Latvijā</div>
          </div>
        </div>

        <v-spacer />

        <!-- NAV (tikai >= md) -->
        <div class="d-none d-md-flex align-center ga-1">
          <v-btn variant="text" @click="goTop">
            <v-icon start>mdi-home</v-icon>
            Sākums
          </v-btn>

          <v-btn variant="text" disabled>
            <v-icon start>mdi-map</v-icon>
            Karte
          </v-btn>

          <v-btn variant="text" disabled>
            <v-icon start>mdi-information-outline</v-icon>
            Par projektu
          </v-btn>

          <v-divider vertical class="mx-2" />

          <v-chip size="small" label variant="tonal">
            Login/Register
          </v-chip>
        </div>
      </v-container>
    </v-app-bar>

    <!-- DRAWER (mobilajiem) -->
    <v-navigation-drawer
      v-model="drawer"
      temporary
      location="left"
      width="300"
      class="drawer"
    >
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
        <v-list-item
          title="Sākums"
          subtitle="Atgriezties uz sākumu"
          prepend-icon="mdi-home"
          @click="goTopFromDrawer"
        />

        <v-list-item
          title="Karte"
          subtitle="Drīzumā"
          prepend-icon="mdi-map"
          disabled
        />

        <v-list-item
          title="Par projektu"
          subtitle="Drīzumā"
          prepend-icon="mdi-information-outline"
          disabled
        />

        <v-list-item
          title="Kontakti"
          subtitle="Drīzumā"
          prepend-icon="mdi-email-outline"
          disabled
        />
      </v-list>

    </v-navigation-drawer>

    <!-- SATURS -->
    <v-main class="main">
      <HomePage />
    </v-main>

    <!-- FOOTER -->
    <v-footer height="64" class="footer" flat>
      <v-container class="d-flex align-center justify-space-between">
        <div class="text-caption opacity-80">
          © {{ year }} EasyRent
        </div>

        <div class="d-none d-sm-flex align-center ga-2 text-caption opacity-80">
          <span>Vue + Vuetify</span>
          <span>•</span>
          <span>Laravel API</span>
        </div>

        <div class="d-flex align-center ga-1">
          <!-- Pagaidām disabled (stilam) -->
          <v-btn icon variant="text" size="small" disabled aria-label="Instagram">
            <v-icon>mdi-instagram</v-icon>
          </v-btn>
          <v-btn icon variant="text" size="small" disabled aria-label="Facebook">
            <v-icon>mdi-facebook</v-icon>
          </v-btn>
          <v-btn icon variant="text" size="small" disabled aria-label="LinkedIn">
            <v-icon>mdi-linkedin</v-icon>
          </v-btn>
        </div>
      </v-container>
    </v-footer>
  </v-app>
</template>

<script setup>
import { ref, computed } from 'vue'
import HomePage from './components/HomePage.vue'

const drawer = ref(false)

const year = computed(() => new Date().getFullYear())

function goTop() {
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

function goTopFromDrawer() {
  drawer.value = false
  // neliels timeout, lai drawer aizveras gludi
  setTimeout(goTop, 50)
}
</script>

<style>
/* Header / Footer stils, lai skaisti sader ar tavu tumšo fonu */
.appbar {
  background: rgba(15, 23, 42, 0.72);
  backdrop-filter: blur(10px);
  border-bottom: 1px solid rgba(255, 255, 255, 0.10);
}

.footer {
  background: rgba(15, 23, 42, 0.72);
  backdrop-filter: blur(10px);
  border-top: 1px solid rgba(255, 255, 255, 0.10);
}

.drawer {
  background: rgba(255, 255, 255, 0.96);
}

.brand__title {
  font-weight: 800;
  line-height: 1.05;
}

.brand__subtitle {
  font-size: 12px;
  opacity: 0.8;
}
</style>
