<template>
  <div class="home-page">
    <section aria-labelledby="home-title" class="home-hero">
      <img
        :alt="content.hero.imageAlt"
        class="home-hero__image"
        fetchpriority="high"
        height="1088"
        src="@/assets/hero.jpg"
        width="1920"
      >
      <div class="home-hero__overlay" />
      <div class="home-container home-hero__content">
        <p class="home-eyebrow">EasyRent · {{ t('app.brandSubtitle') }}</p>
        <h1 id="home-title">{{ content.hero.slogan }}</h1>
        <p class="home-hero__description">{{ content.hero.description }}</p>
        <v-btn
          class="home-hero__link"
          rounded="pill"
          size="large"
          variant="outlined"
          @click="goMap"
        >
          {{ content.hero.ctaMap }}
          <v-icon end>mdi-arrow-right</v-icon>
        </v-btn>
        <div class="home-search-card">
          <div class="home-search-card__icon"><v-icon size="30">mdi-map-search-outline</v-icon></div>
          <div class="home-search-card__copy">
            <strong>{{ content.steps.items[0].title }}</strong>
            <p>{{ content.steps.items[0].text }}</p>
          </div>
          <v-btn
            class="home-search-card__button"
            color="primary"
            rounded="xl"
            size="x-large"
            @click="goMap"
          >
            <v-icon start>mdi-magnify</v-icon>
            {{ content.hero.ctaMap }}
            <v-icon end>mdi-arrow-right</v-icon>
          </v-btn>
        </div>
      </div>
    </section>
    <section class="home-metrics">
      <div class="home-container home-metrics__grid">
        <div v-for="(item, index) in content.metrics" :key="`metric-${index}`" class="home-metric">
          <strong>{{ item.title }}</strong>
          <p>{{ item.text }}</p>
        </div>
      </div>
    </section>
    <section aria-labelledby="categories-title" class="home-container home-section">
      <h2 id="categories-title">{{ content.categories.title }}</h2>
      <p class="home-section__subtitle">{{ content.categories.subtitle }}</p>
      <div class="home-category-grid">
        <button
          v-for="(item, index) in content.categories.items"
          :key="`category-${index}`"
          class="home-category"
          type="button"
          @click="goMap"
        >
          <img
            alt=""
            height="704"
            loading="lazy"
            :src="categoryImages[index]"
            width="944"
          >
          <div class="home-category__overlay" />
          <div class="home-category__content">
            <div>
              <v-icon size="24">{{ item.icon }}</v-icon>
              <h3>{{ item.title }}</h3>
              <p>{{ item.text }}</p>
            </div>
            <span class="home-category__arrow"><v-icon>mdi-arrow-right</v-icon></span>
          </div>
        </button>
      </div>
      <p class="home-section__note">{{ content.categories.note }}</p>
    </section>
    <section aria-labelledby="why-title" class="home-why">
      <div class="home-container home-section">
        <h2 id="why-title">{{ content.why.title }}</h2>
        <p class="home-section__subtitle">{{ content.why.subtitle }}</p>
        <div class="home-three-grid">
          <article v-for="(item, index) in content.why.items" :key="`why-${index}`" class="home-why__card">
            <v-icon size="40">{{ item.icon }}</v-icon>
            <h3>{{ item.title }}</h3>
            <p>{{ item.text }}</p>
          </article>
        </div>
        <p class="home-section__note">{{ content.why.note }}</p>
      </div>
    </section>
    <section aria-labelledby="steps-title" class="home-container home-section">
      <h2 id="steps-title">{{ content.steps.title }}</h2>
      <p class="home-section__subtitle">{{ content.steps.subtitle }}</p>
      <div class="home-three-grid home-steps">
        <article v-for="(item, index) in content.steps.items" :key="`step-${index}`">
          <span class="home-step-number">0{{ item.step }}</span>
          <h3>{{ item.title }}</h3>
          <p>{{ item.text }}</p>
        </article>
      </div>
      <p class="home-section__note">{{ content.steps.note }}</p>
    </section>
    <section aria-labelledby="faq-title" class="home-container home-faq home-section">
      <div>
        <p class="home-eyebrow">EasyRent</p>
        <h2 id="faq-title">{{ content.faq.title }}</h2>
        <p class="home-section__subtitle">{{ content.faq.subtitle }}</p>
      </div>
      <v-expansion-panels class="home-faq__panels" variant="accordion">
        <v-expansion-panel v-for="(item, index) in content.faq.items" :key="`faq-${index}`" elevation="0">
          <v-expansion-panel-title>{{ item.question }}</v-expansion-panel-title>
          <v-expansion-panel-text>{{ item.answer }}</v-expansion-panel-text>
        </v-expansion-panel>
      </v-expansion-panels>
    </section>
  </div>
</template>

<script setup>
  import { computed } from 'vue'
  import { useRouter } from 'vue-router'
  import bikeImage from '@/assets/cat-bike.jpg'
  import carImage from '@/assets/cat-car.jpg'
  import motoImage from '@/assets/cat-moto.jpg'
  import suvImage from '@/assets/cat-suv.jpg'
  import vanImage from '@/assets/cat-van.jpg'
  import heroImage from '@/assets/hero.jpg'
  import { MAP_ROUTE } from '@/router/paths'
  import { useLocale } from '@/stores/locale'

  const router = useRouter()
  const { t } = useLocale()
  const content = computed(() => t('home'))
  const categoryImages = [carImage, suvImage, vanImage, motoImage, bikeImage, heroImage]

  function goMap () {
    router.push(MAP_ROUTE)
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
</script>

<style scoped>
.home-page { background: var(--er-bg); color: var(--er-text); }
.home-container { width: min(1280px, 100%); margin: 0 auto; padding-inline: 24px; }
.home-hero { position: relative; isolation: isolate; display: flex; align-items: flex-end; min-height: 760px; padding-block: 192px 80px; background: var(--er-night); color: var(--er-night-text); overflow: hidden; }
.home-hero__image, .home-hero__overlay { position: absolute; inset: 0; width: 100%; height: 100%; z-index: -1; }
.home-hero__image { object-fit: cover; }
.home-hero__overlay { background: linear-gradient(90deg, rgba(16, 28, 34, .9), rgba(16, 28, 34, .6) 55%, rgba(16, 28, 34, .3)); }
.home-eyebrow { color: var(--er-primary); font-size: .8rem; font-weight: 800; letter-spacing: .15em; text-transform: uppercase; margin-bottom: 20px; }
.home-hero h1 { max-width: 830px; font-family: var(--er-display-font, 'Archivo', sans-serif); font-size: clamp(2.75rem, 5.6vw, 4.5rem); font-weight: 900; line-height: 1.02; letter-spacing: -.04em; text-wrap: balance; }
.home-hero__description { max-width: 680px; margin-top: 24px; font-size: 1.125rem; line-height: 1.75; color: #b4c4cb; }
.home-hero__link { margin-top: 28px; color: #f3f7f7 !important; border: 2px solid var(--er-primary) !important; font-weight: 800; background: transparent !important; }
.home-hero__link:hover { color: #fff !important; background: var(--er-primary) !important; }
.home-search-card { display: flex; align-items: center; gap: 24px; margin-top: 48px; padding: 24px; border-radius: 26px; background: var(--er-surface-strong); color: var(--er-text); box-shadow: 0 30px 60px -20px rgba(0, 0, 0, .45); }
.home-search-card__icon { display: grid; place-items: center; width: 62px; height: 62px; flex-shrink: 0; border-radius: 18px; color: var(--er-primary); background: var(--er-primary-soft); }
.home-search-card__copy { flex: 1; min-width: 0; }
.home-search-card__copy strong { font-family: var(--er-display-font, 'Archivo', sans-serif); font-size: 1.25rem; font-weight: 800; }
.home-search-card__copy p { margin-top: 4px; color: var(--er-text-muted); }
.home-search-card__button { max-width: 100%; }
.home-metrics { background: var(--er-surface-strong); border-bottom: 1px solid var(--er-stroke); }
.home-metrics__grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); }
.home-metric { padding: 32px 24px; }
.home-metric + .home-metric { border-left: 1px solid var(--er-stroke); }
.home-metric strong { color: var(--er-primary); font-family: var(--er-display-font, 'Archivo', sans-serif); font-size: clamp(1.4rem, 2vw, 1.85rem); font-weight: 900; line-height: 1.2; }
.home-metric p { color: var(--er-text-muted); margin-top: 8px; font-size: .875rem; line-height: 1.6; }
.home-section { padding-block: 96px; }
.home-section h2 { max-width: 900px; font-family: var(--er-display-font, 'Archivo', sans-serif); font-size: clamp(2rem, 3.8vw, 3rem); font-weight: 900; line-height: 1.1; letter-spacing: -.03em; text-wrap: balance; }
.home-section__subtitle { max-width: 690px; margin-top: 16px; font-size: 1.1rem; line-height: 1.7; color: var(--er-text-muted); }
.home-category-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 24px; margin-top: 40px; }
.home-category { position: relative; isolation: isolate; aspect-ratio: 4 / 3; width: 100%; padding: 0; border-radius: 26px; overflow: hidden; text-align: left; color: #f3f7f7; background: #172329; box-shadow: var(--er-shadow-md); }
.home-category img, .home-category__overlay { position: absolute; inset: 0; width: 100%; height: 100%; z-index: -1; }
.home-category img { object-fit: cover; transition: transform .7s ease; }
.home-category__overlay { background: linear-gradient(to top, rgba(13, 25, 32, .96), rgba(13, 25, 32, .15) 85%); }
.home-category__content { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; height: 100%; padding: 24px; }
.home-category__content > div { min-width: 0; }
.home-category h3 { margin-top: 8px; font-family: var(--er-display-font, 'Archivo', sans-serif); font-size: 1.5rem; line-height: 1.2; font-weight: 800; }
.home-category p { color: #c4cfd4; font-size: .85rem; line-height: 1.5; margin-top: 8px; }
.home-category__arrow { display: grid; place-items: center; flex: 0 0 44px; height: 44px; border-radius: 50%; background: var(--er-primary); color: #fff; transition: transform .2s ease; }
.home-category:hover img { transform: scale(1.08); }
.home-category:hover .home-category__arrow { transform: translateX(3px); }
.home-category:focus-visible { outline: 3px solid var(--er-primary); outline-offset: 4px; }
.home-section__note { margin-top: 36px; padding-left: 18px; border-left: 4px solid var(--er-primary); color: var(--er-text-muted); line-height: 1.7; }
.home-why { background: var(--er-night, #172329); color: #f3f7f7; }
.home-why .home-section__subtitle, .home-why .home-section__note { color: #b4c4cb; }
.home-three-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 24px; margin-top: 48px; }
.home-three-grid h3 { font-family: var(--er-display-font, 'Archivo', sans-serif); font-size: 1.5rem; font-weight: 800; line-height: 1.25; margin-top: 24px; }
.home-three-grid p { margin-top: 12px; line-height: 1.7; color: var(--er-text-muted); }
.home-why__card { padding: 32px; border: 1px solid rgba(255, 255, 255, .12); border-radius: 26px; }
.home-why__card .v-icon { color: var(--er-primary); }
.home-why__card p { color: #b4c4cb; }
.home-steps { gap: 48px; }
.home-step-number { font-family: var(--er-display-font, 'Archivo', sans-serif); font-size: 4.5rem; font-weight: 900; line-height: 1; letter-spacing: -.05em; color: var(--er-primary); }
.home-steps h3 { margin-top: 16px; }
.home-faq { display: grid; grid-template-columns: minmax(0, .85fr) minmax(0, 1.15fr); gap: 64px; padding-top: 0; }
.home-faq__panels { align-content: start; }
.home-faq__panels :deep(.v-expansion-panel) { border-radius: 0 !important; border: 0; border-bottom: 1px solid var(--er-stroke); background: transparent; color: var(--er-text); box-shadow: none !important; }
.home-faq__panels :deep(.v-expansion-panel::after) { display: none; }
.home-faq__panels :deep(.v-expansion-panel-title) { padding: 22px 0; font-size: 1rem; font-weight: 800; line-height: 1.5; }
.home-faq__panels :deep(.v-expansion-panel-text__wrapper) { padding: 0 0 22px; color: var(--er-text-muted); line-height: 1.75; }
@media (max-width: 959px) {
  .home-hero { min-height: 690px; padding-block: 160px 56px; }
  .home-metrics__grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .home-metric:nth-child(3) { border-left: 0; }
  .home-category-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .home-why__card { padding: 24px; }
  .home-section { padding-block: 64px; }
  .home-faq { grid-template-columns: 1fr; gap: 24px; padding-top: 0; }
  .home-search-card { gap: 16px; }
  .home-search-card__icon { display: none; }
  .home-steps { gap: 24px; }
}
@media (max-width: 599px) {
  .home-container { padding-inline: 20px; }
  .home-hero { min-height: 0; padding-block: 160px 40px; }
  .home-hero h1 { font-size: clamp(2.4rem, 11vw, 3.2rem); }
  .home-hero__description { font-size: 1rem; }
  .home-eyebrow { font-size: .7rem; line-height: 1.7; }
  .home-search-card { flex-direction: column; align-items: stretch; padding: 20px; border-radius: 22px; }
  .home-search-card__button { width: 100%; font-size: .875rem; }
  .home-metric { padding: 24px 12px; }
  .home-metric strong { font-size: 1.2rem; }
  .home-metric p { font-size: .8rem; }
  .home-section { padding-block: 52px; }
  .home-category-grid, .home-three-grid { grid-template-columns: 1fr; }
  .home-category-grid { gap: 20px; margin-top: 28px; }
  .home-three-grid { margin-top: 32px; }
  .home-category { aspect-ratio: 4 / 3; }
  .home-why__card { padding: 28px; }
  .home-faq { padding-top: 0; }
}
@media (prefers-reduced-motion: reduce) {
  .home-category img, .home-category__arrow { transition: none; }
}
</style>
