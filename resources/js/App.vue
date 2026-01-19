<template>
  <v-app>
    <!-- NAVBAR -->
    <v-app-bar
      color="transparent"
      flat
      height="72"
      class="appbar"
    >
      <v-container class="d-flex align-center">
        <v-icon size="28" class="me-2">mdi-car</v-icon>
        <span class="text-h6 font-weight-bold">EasyRent</span>

        <v-spacer />

        <!-- Desktop links -->
        <div class="d-none d-md-flex ga-2">
          <v-btn variant="text" to="/">Sākums</v-btn>
          <v-btn variant="text" disabled>Karte</v-btn>
          <v-btn variant="text" disabled>Par mums</v-btn>
          <v-btn variant="tonal" rounded="xl" disabled>Pieslēgties</v-btn>
        </div>

        <!-- Mobile menu -->
        <v-menu class="d-md-none" location="bottom end">
          <template #activator="{ props }">
            <v-btn icon variant="text" v-bind="props">
              <v-icon>mdi-menu</v-icon>
            </v-btn>
          </template>

          <v-list width="220">
            <v-list-item to="/" title="Sākums" />
            <v-list-item title="Karte" subtitle="Drīzumā" />
            <v-list-item title="Par mums" subtitle="Drīzumā" />
            <v-divider />
            <v-list-item title="Pieslēgties" subtitle="Drīzumā" />
          </v-list>
        </v-menu>
      </v-container>
    </v-app-bar>

    <!-- CONTENT -->
    <v-main>
      <router-view />
    </v-main>

    <!-- FOOTER -->
    <v-footer class="footer" height="64">
      <v-container class="d-flex align-center justify-space-between">
        <span class="text-caption opacity-80">© {{ new Date().getFullYear() }} EasyRent</span>
        <span class="text-caption opacity-60">Laravel • Vue • Vuetify</span>
      </v-container>
    </v-footer>
  </v-app>
</template>

<script setup>
// nav jāstrādā arī bez šī, bet footer gadā izmanto JS
</script>

<style scoped>
.appbar {
  backdrop-filter: blur(10px);
  border-bottom: 1px solid rgba(255,255,255,0.10);
}

.footer {
  border-top: 1px solid rgba(255,255,255,0.08);
  background: rgba(0,0,0,0.15);
}
</style>
