<template>
  <div class="bg">
    <v-container class="fill-height py-12">
      <div class="page-wrap">
        <v-row align="stretch" justify="center">
          <v-col cols="12">
            <v-card class="surface hero-surface" elevation="12">
              <v-card-text class="pa-6 pa-md-8">
                <v-row align="stretch" class="hero-row">
                  <v-col cols="12" md="7" class="text-center text-md-left hero-copy-col">
                    <div class="hero-title mb-2">EasyRent</div>
                    <div class="hero-slogan mb-4">{{ content.hero.slogan }}</div>
                    <div class="text-body-1 text-md-h6 opacity-90 mb-5 hero-desc">
                      {{ content.hero.description }}
                    </div>

                    <div class="d-flex flex-column flex-sm-row justify-center justify-md-start ga-3">
                      <v-btn size="x-large" rounded="xl" elevation="6" @click="goMap">
                        <v-icon start>mdi-map</v-icon>
                        {{ content.hero.ctaMap }}
                      </v-btn>
                    </div>
                  </v-col>

                  <v-col cols="12" md="5" class="mt-4 mt-md-0">
                    <div class="hero-aside">
                      <div class="hero-media">
                        <v-img src="@/assets/hero.jpg" :alt="content.hero.imageAlt" class="hero-img" aspect-ratio="16/9" contain />
                      </div>
                    </div>
                  </v-col>
                </v-row>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>

        <v-row class="mt-6 metrics-row" dense>
          <v-col v-for="(item, index) in content.metrics" :key="`metric-${index}`" cols="12" sm="6" lg="3" class="metric-col">
            <div class="metric-pill">
              <strong>{{ item.title }}</strong>
              <span>{{ item.text }}</span>
            </div>
          </v-col>
        </v-row>

        <v-row class="section-shell content-section" align="start">
          <v-col cols="12" md="4" lg="3">
            <div class="section-lead sticky-section">
              <div class="section-title mb-4">
                <div class="text-h5 font-weight-bold">{{ content.categories.title }}</div>
                <div class="text-body-2 opacity-80">{{ content.categories.subtitle }}</div>
              </div>

              <div class="section-note">{{ content.categories.note }}</div>
            </div>
          </v-col>

          <v-col cols="12" md="8" lg="9">
            <v-row dense align="stretch">
              <v-col v-for="(item, index) in content.categories.items" :key="`category-${index}`" cols="12" sm="6" lg="4" class="feature-col">
                <v-card class="surface pa-4 feature-card" elevation="10">
                  <v-icon size="28" class="mb-2">{{ item.icon }}</v-icon>
                  <div class="text-h6 mb-1">{{ item.title }}</div>
                  <div class="text-body-2 opacity-80 card-desc">{{ item.text }}</div>
                </v-card>
              </v-col>
            </v-row>
          </v-col>
        </v-row>

        <v-row class="section-shell content-section" align="start">
          <v-col cols="12" md="4" lg="3">
            <div class="section-lead sticky-section">
              <div class="section-title mb-6 text-center text-md-left">
                <div class="text-h5 font-weight-bold">{{ content.why.title }}</div>
                <div class="text-body-2 opacity-80">{{ content.why.subtitle }}</div>
              </div>

              <div class="section-note">{{ content.why.note }}</div>
            </div>
          </v-col>

          <v-col cols="12" md="8" lg="9">
            <v-row dense align="stretch">
              <v-col v-for="(item, index) in content.why.items" :key="`why-${index}`" cols="12" sm="6" lg="4" class="feature-col">
                <v-card class="surface pa-5 feature-card" elevation="10">
                  <div class="d-flex align-center mb-3">
                    <v-avatar size="38" variant="tonal" class="me-3">
                      <v-icon>{{ item.icon }}</v-icon>
                    </v-avatar>
                    <div class="text-h6 mb-0">{{ item.title }}</div>
                  </div>
                  <div class="text-body-2 opacity-80 card-desc">{{ item.text }}</div>
                </v-card>
              </v-col>
            </v-row>
          </v-col>
        </v-row>

        <v-row class="section-shell content-section" align="start">
          <v-col cols="12" md="4" lg="3">
            <div class="section-lead sticky-section">
              <div class="section-title mb-4">
                <div class="text-h5 font-weight-bold">{{ content.steps.title }}</div>
                <div class="text-body-2 opacity-80">{{ content.steps.subtitle }}</div>
              </div>

              <div class="section-note">{{ content.steps.note }}</div>
            </div>
          </v-col>

          <v-col cols="12" md="8" lg="9">
            <v-row dense align="stretch">
              <v-col v-for="(item, index) in content.steps.items" :key="`step-${index}`" cols="12" sm="6" lg="4" class="feature-col">
                <v-card class="surface pa-4 feature-card" elevation="10">
                  <div class="d-flex align-center mb-2">
                    <v-avatar size="34" class="me-3" variant="tonal">{{ item.step }}</v-avatar>
                    <div class="text-h6">{{ item.title }}</div>
                  </div>
                  <div class="text-body-2 opacity-80 card-desc">{{ item.text }}</div>
                </v-card>
              </v-col>
            </v-row>
          </v-col>
        </v-row>

        <v-row class="section-shell content-section faq-section" align="start">
          <v-col cols="12" md="4" lg="3">
            <div class="section-lead sticky-section">
              <div class="section-title mb-4">
                <div class="text-h5 font-weight-bold">{{ content.faq.title }}</div>
                <div class="text-body-2 opacity-80">{{ content.faq.subtitle }}</div>
              </div>
            </div>
          </v-col>

          <v-col cols="12" md="8" lg="9">
            <v-expansion-panels variant="accordion" class="surface-panels faq-panels">
              <v-expansion-panel v-for="(item, index) in content.faq.items" :key="`faq-${index}`">
                <v-expansion-panel-title>{{ item.question }}</v-expansion-panel-title>
                <v-expansion-panel-text class="opacity-90">{{ item.answer }}</v-expansion-panel-text>
              </v-expansion-panel>
            </v-expansion-panels>
          </v-col>
        </v-row>
      </div>
    </v-container>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useLocale } from '@/stores/locale'
import { MAP_ROUTE } from '@/router/paths'

const router = useRouter()
const { t } = useLocale()
const content = computed(() => t('home'))

function goMap() {
  router.push(MAP_ROUTE)
  window.scrollTo({ top: 0, behavior: 'smooth' })
}
</script>

<style scoped>
.bg {
  min-height: calc(100vh - 72px - 64px);
  background: var(--er-page-bg);
}

.page-wrap { width: min(1380px, 100%); margin: 0 auto; }

.surface-panels { width: 100%; }

.faq-panels {
  width: 100%;
  max-width: 100%;
}

.surface {
  background: var(--er-surface);
  border: 1px solid var(--er-stroke);
  border-radius: 28px;
  color: var(--er-text);
  backdrop-filter: blur(14px);
  box-shadow: var(--er-shadow-md);
}

.surface :deep(.v-card-text),
.surface :deep(.v-card-title),
.surface :deep(.v-card-subtitle) { color: var(--er-text); }

.hero-surface {
  border-radius: 24px;
}

.section-title {
  padding-left: 14px;
  color: var(--er-text);
  border-left: 3px solid rgba(15, 118, 110, 0.72);
}
.section-head { color: var(--er-text); }

.surface-panels :deep(.v-expansion-panel) {
  background: var(--er-panel-strong);
  border: 1px solid var(--er-stroke);
  color: var(--er-text);
}
.surface-panels :deep(.v-expansion-panel + .v-expansion-panel) { margin-top: 10px; }

.feature-col { display: flex; }

.content-section {
  margin-top: 72px;
}

.section-shell {
  row-gap: 20px;
}

.section-lead {
  display: grid;
  gap: 18px;
}

.sticky-section {
  position: sticky;
  top: 96px;
}

.section-note {
  padding: 18px 20px;
  border-radius: 22px;
  background: var(--er-panel-soft);
  border: 1px solid var(--er-stroke);
  color: var(--er-text-muted);
  line-height: 1.65;
}

.hero-copy-col {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 6px;
}

.feature-card {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  border: 1px solid rgba(21, 48, 71, 0.1);
  border-radius: 22px;
  transition: transform 0.22s ease, box-shadow 0.22s ease;
  background: var(--er-card-gradient);
}

.feature-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 18px 40px rgba(25, 41, 55, 0.12);
}
.card-desc { flex-grow: 1; min-height: 56px; }

.hero-aside {
  display: grid;
  gap: 16px;
  height: 100%;
}

.hero-mini-card strong {
  color: var(--er-text);
}

.hero-mini-card span {
  color: var(--er-text-muted);
  font-size: 0.92rem;
  line-height: 1.5;
}

.hero-title {
  font-weight: 900;
  letter-spacing: -0.05em;
  line-height: 0.98;
  font-size: clamp(46px, 6vw, 82px);
  color: var(--er-text);
}

.hero-slogan {
  font-weight: 800;
  letter-spacing: -0.04em;
  line-height: 1.02;
  font-size: clamp(24px, 3vw, 40px);
  color: color-mix(in srgb, var(--er-text) 92%, transparent);
}

.hero-desc { max-width: 680px; margin: 0 auto; }
@media (min-width: 960px) { .hero-desc { margin: 0; } }

.hero-tag {
  padding: 10px 14px;
  border-radius: 999px;
  background: var(--er-card-soft);
  border: 1px solid var(--er-stroke);
  color: var(--er-text);
  font-size: 0.85rem;
  font-weight: 700;
}

.hero-media {
  width: 100%;
  border-radius: 24px;
  overflow: hidden;
}

.hero-img {
  width: 100%;
}

.metric-col {
  display: flex;
}

.metric-pill {
  width: 100%;
  min-height: 100%;
  display: flex;
  flex-direction: column;
  justify-content: flex-start;
  gap: 8px;
  padding: 18px 20px;
  border-radius: 20px;
  background: var(--er-panel-soft);
  border: 1px solid var(--er-stroke);
}

.metric-pill strong {
  display: block;
  font-size: 1.02rem;
  line-height: 1.2;
  color: var(--er-text);
}

.metric-pill span {
  display: block;
  color: var(--er-text-muted);
  line-height: 1.5;
}

.metrics-row {
  margin-bottom: 40px;
}

.faq-section {
  margin-top: 84px;
  margin-bottom: 72px;
}

@media (max-width: 1260px) {
  .page-wrap {
    width: min(1200px, 100%);
  }
}

@media (max-width: 960px) {
  .content-section {
    margin-top: 56px;
  }

  .faq-section {
    margin-top: 64px;
    margin-bottom: 56px;
  }

  .sticky-section {
    position: static;
  }

  .hero-copy-col {
    gap: 2px;
  }

  .hero-mini-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 600px) {
  .content-section {
    margin-top: 44px;
  }

  .faq-section {
    margin-top: 52px;
    margin-bottom: 44px;
  }

  .hero-img :deep(.v-responsive__sizer) {
    padding-bottom: 72% !important;
  }

  .hero-media :deep(img) {
    max-height: 340px;
    object-fit: contain;
  }

  .hero-tags {
    justify-content: center;
  }
}
</style>