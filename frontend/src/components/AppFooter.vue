<template>
  <footer class="site-footer">
    <div class="site-footer__grid">
      <div class="site-footer__intro">
        <RouterLink aria-label="EasyRent" class="site-footer__brand" :to="HOME_ROUTE"><BrandLogo /></RouterLink>
        <p>{{ t('app.footer.summary') }}</p>
        <a
          aria-label="EasyRent GitHub"
          class="site-footer__github"
          href="https://github.com/23DP1ELevsa/EasyRent"
          rel="noopener noreferrer"
          target="_blank"
        ><v-icon size="21">mdi-github</v-icon><span>GitHub</span><v-icon size="14">mdi-arrow-top-right</v-icon></a>
      </div>
      <nav :aria-label="t('app.footer.users')">
        <h2>{{ t('app.footer.users') }}</h2>
        <RouterLink :to="HOME_ROUTE">{{ t('app.nav.home') }}</RouterLink>
        <RouterLink :to="MAP_ROUTE">{{ t('app.nav.map') }}</RouterLink>
      </nav>
      <nav :aria-label="t('app.footer.providers')">
        <h2>{{ t('app.footer.providers') }}</h2>
        <RouterLink :to="MAP_ROUTE">{{ t('app.footer.addVehicle') }}</RouterLink>
        <RouterLink :to="MAP_ROUTE">{{ t('app.footer.manageAvailability') }}</RouterLink>
      </nav>
      <div class="site-footer__locale">
        <v-icon size="22">mdi-map-marker-outline</v-icon>
        <p>{{ t('app.brandSubtitle') }}</p>
      </div>
    </div>
    <div class="site-footer__copyright">© {{ year }} EasyRent</div>
  </footer>
</template>

<script setup>
  import { computed } from 'vue'
  import BrandLogo from '@/components/BrandLogo.vue'
  import { HOME_ROUTE, MAP_ROUTE } from '@/router/paths'
  import { useLocale } from '@/stores/locale'

  const { t } = useLocale()
  const year = computed(() => new Date().getFullYear())
</script>

<style scoped>
.site-footer { background: var(--er-night); color: var(--er-night-text); }
.site-footer__grid { max-width: var(--er-content-width); margin: auto; padding: 56px 20px; display: grid; grid-template-columns: 1.3fr 1fr 1fr .85fr; gap: 40px; }
.site-footer__brand { display: inline-flex; text-decoration: none; margin-bottom: 20px; }
.site-footer__intro p { max-width: 280px; color: var(--er-night-muted); font-size: .875rem; line-height: 1.8; }
.site-footer nav { display: flex; flex-direction: column; align-items: flex-start; gap: 12px; }
.site-footer h2 { margin: 0 0 8px; font-size: .75rem; text-transform: uppercase; letter-spacing: .1em !important; font-weight: 800; color: var(--er-primary); }
.site-footer nav a { text-decoration: none; font-size: .875rem; line-height: 1.6; transition: color .18s; }
.site-footer nav a:hover, .site-footer__github:hover { color: var(--er-primary); }
.site-footer__github { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; font-size: .75rem; margin-top: 20px; transition: color .18s; }
.site-footer__locale { color: var(--er-night-muted); font-size: .875rem; line-height: 1.8; }
.site-footer__locale .v-icon { color: var(--er-primary); margin-bottom: 14px; }
.site-footer__copyright { border-top: 1px solid var(--er-night-border); padding: 22px 20px; text-align: center; color: var(--er-night-muted); font-size: .75rem; }
@media (max-width: 959px) { .site-footer__grid { grid-template-columns: 1fr 1fr; gap: 32px; padding-block: 40px; } }
@media (max-width: 599px) { .site-footer__grid { gap: 32px 20px; } .site-footer__intro { grid-column: 1 / -1; } .site-footer__locale { grid-column: 1 / -1; display: flex; align-items: center; gap: 10px; } .site-footer__locale .v-icon { margin: 0; } }
</style>
