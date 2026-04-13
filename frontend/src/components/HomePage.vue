<template>
  <div class="bg">
    <v-container class="fill-height py-12">
      <div class="page-wrap">

        <!-- HERO: ievads (teksts + foto) -->
        <v-row align="stretch" justify="center">
          <v-col cols="12">
            <v-card class="surface hero-surface" elevation="12">
              <v-card-text class="pa-6 pa-md-8">
                <v-row align="stretch" class="hero-row">
                  <v-col cols="12" md="7" class="text-center text-md-left hero-copy-col">

                    <div class="hero-title mb-2">EasyRent</div>

                    <div class="hero-slogan mb-4">
                      Atrast un iznomāt transportu — vienkārši un ātri
                    </div>

                    <div class="text-body-1 text-md-h6 opacity-90 mb-5 hero-desc">
                      EasyRent ir platforma, kur vienuviet varēsi apskatīt pieejamos transportlīdzekļus,
                      salīdzināt cenas un rezervēt izvēlēto variantu.
                    </div>

                    <div class="d-flex flex-column flex-sm-row justify-center justify-md-start ga-3">
                      <v-btn size="x-large" rounded="xl" elevation="6" @click="goMap">
                        <v-icon start>mdi-map</v-icon>
                        Pāriet uz karti
                      </v-btn>
                    </div>
                  </v-col>

                  <v-col cols="12" md="5" class="mt-4 mt-md-0">
                    <div class="hero-aside">
                      <div class="hero-media">
                        <v-img src="@/assets/hero.jpg" alt="EasyRent galvenais attēls ar transporta nomas vizuālo noformējumu" class="hero-img" aspect-ratio="16/9" contain/>
                      </div>
                    </div>
                  </v-col>
                </v-row>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>

        <v-row class="mt-6 metrics-row" dense>
          <v-col cols="12" sm="6" lg="3" class="metric-col">
            <div class="metric-pill">
              <strong>24/7</strong>
              <span>Piekļuve kartei un pieejamībai jebkurā laikā</span>
            </div>
          </v-col>
          <v-col cols="12" sm="6" lg="3" class="metric-col">
            <div class="metric-pill">
              <strong>Viss vienuviet</strong>
              <span>Piedāvājumu salīdzināšana bez pārlēkšanas starp lapām</span>
            </div>
          </v-col>
          <v-col cols="12" sm="6" lg="3" class="metric-col">
            <div class="metric-pill">
              <strong>Dažādi veidi</strong>
              <span>Auto, motocikli, laivas un cits transports</span>
            </div>
          </v-col>
          <v-col cols="12" sm="6" lg="3" class="metric-col">
            <div class="metric-pill">
              <strong>Vienots profils</strong>
              <span>Rezervācijas, maksājumi un konta dati vienā skata līmenī</span>
            </div>
          </v-col>
        </v-row>

        <!-- TRANSPORTA KATEGORIJAS -->
        <v-row class="mt-10 section-shell" align="start">
          <v-col cols="12" md="4" lg="3">
            <div class="section-lead sticky-section">
              <div class="section-title mb-4">
                <div class="text-h5 font-weight-bold">Kādi transportlīdzekļi būs pieejami</div>
                <div class="text-body-2 opacity-80">
                  Piemēri kategorijām, ko vēlāk rādīsim kartē un sarakstā.
                </div>
              </div>

              <div class="section-note">
                Platforma paredzēta ne tikai klasiskajiem auto, bet arī ģimenes, darba un specifiskiem transporta scenārijiem.
              </div>
            </div>
          </v-col>

          <v-col cols="12" md="8" lg="9">
            <v-row dense align="stretch">
              <v-col cols="12" sm="6" lg="4" class="feature-col">
                <v-card class="surface pa-4 feature-card" elevation="10">
                  <v-icon size="28" class="mb-2">mdi-car</v-icon>
                  <div class="text-h6 mb-1">Vieglās automašīnas</div>
                  <div class="text-body-2 opacity-80 card-desc">
                    Ikdienai un braucieniem pa Latviju (sedans, hečbeks, universāls).
                  </div>
                </v-card>
              </v-col>

              <v-col cols="12" sm="6" lg="4" class="feature-col">
                <v-card class="surface pa-4 feature-card" elevation="10">
                  <v-icon size="28" class="mb-2">mdi-car-estate</v-icon>
                  <div class="text-h6 mb-1">SUV / Apvidus</div>
                  <div class="text-body-2 opacity-80 card-desc">
                    Komfortam, ģimenei un garākiem maršrutiem.
                  </div>
                </v-card>
              </v-col>

              <v-col cols="12" sm="6" lg="4" class="feature-col">
                <v-card class="surface pa-4 feature-card" elevation="10">
                  <v-icon size="28" class="mb-2">mdi-van-utility</v-icon>
                  <div class="text-h6 mb-1">Mikroautobusi</div>
                  <div class="text-body-2 opacity-80 card-desc">
                    Pārvākšanās, kravas un komandas braucieniem.
                  </div>
                </v-card>
              </v-col>

              <v-col cols="12" sm="6" lg="4" class="feature-col">
                <v-card class="surface pa-4 feature-card" elevation="10">
                  <v-icon size="28" class="mb-2">mdi-motorbike</v-icon>
                  <div class="text-h6 mb-1">Motocikli</div>
                  <div class="text-body-2 opacity-80 card-desc">
                    Sezonas braucieniem un hobijam.
                  </div>
                </v-card>
              </v-col>

              <v-col cols="12" sm="6" lg="4" class="feature-col">
                <v-card class="surface pa-4 feature-card" elevation="10">
                  <v-icon size="28" class="mb-2">mdi-bicycle</v-icon>
                  <div class="text-h6 mb-1">E-skrejriteņi / velo</div>
                  <div class="text-body-2 opacity-80 card-desc">
                    Ātrai pārvietošanai pilsētā.
                  </div>
                </v-card>
              </v-col>

              <v-col cols="12" sm="6" lg="4" class="feature-col">
                <v-card class="surface pa-4 feature-card" elevation="10">
                  <v-icon size="28" class="mb-2">mdi-car-wrench</v-icon>
                  <div class="text-h6 mb-1">Speciālie piedāvājumi</div>
                  <div class="text-body-2 opacity-80 card-desc">
                    Īpaši auto, akcijas, ilgtermiņa noma (drīzumā).
                  </div>
                </v-card>
              </v-col>
            </v-row>
          </v-col>
        </v-row>

        <!-- KĀPĒC EASYRENT? -->
        <v-row class="mt-12 section-shell" align="start">
          <v-col cols="12" md="4" lg="3">
            <div class="section-lead sticky-section">
              <div class="section-title mb-6 text-center text-md-left">
                <div class="text-h5 font-weight-bold">Kāpēc EasyRent?</div>
                <div class="text-body-2 opacity-80">
                  Vienkārša platforma, lai atrastu, salīdzinātu un rezervētu transportu Latvijā.
                </div>
              </div>

              <div class="section-note">
                Mērķis ir nevis tikai glīts saraksts, bet reāli noderīga plūsma, kur lietotājs ātri atrod sev piemērotāko variantu.
              </div>
            </div>
          </v-col>

          <v-col cols="12" md="8" lg="9">
            <v-row dense align="stretch">
              <v-col cols="12" sm="6" lg="4" class="feature-col">
                <v-card class="surface pa-5 feature-card" elevation="10">
                  <div class="d-flex align-center mb-3">
                    <v-avatar size="38" variant="tonal" class="me-3">
                      <v-icon>mdi-map-search</v-icon>
                    </v-avatar>
                    <div class="text-h6 mb-0">Meklēšana kartē</div>
                  </div>
                  <div class="text-body-2 opacity-80 card-desc">
                    Redzēsi transportu kartē, izvēlēsies tuvāko un ātri salīdzināsi piedāvājumus.
                  </div>
                </v-card>
              </v-col>

              <v-col cols="12" sm="6" lg="4" class="feature-col">
                <v-card class="surface pa-5 feature-card" elevation="10">
                  <div class="d-flex align-center mb-3">
                    <v-avatar size="38" variant="tonal" class="me-3">
                      <v-icon>mdi-filter-check</v-icon>
                    </v-avatar>
                    <div class="text-h6 mb-0">Filtri</div>
                  </div>
                  <div class="text-body-2 opacity-80 card-desc">
                    Cena, veids, ātrumkārba, degviela, statuss un citi filtri — viss vienuviet.
                  </div>
                </v-card>
              </v-col>

              <v-col cols="12" sm="12" lg="4" class="feature-col">
                <v-card class="surface pa-5 feature-card" elevation="10">
                  <div class="d-flex align-center mb-3">
                    <v-avatar size="38" variant="tonal" class="me-3">
                      <v-icon>mdi-shield-check</v-icon>
                    </v-avatar>
                    <div class="text-h6 mb-0">Drošība</div>
                  </div>
                  <div class="text-body-2 opacity-80 card-desc">
                    Pārskatāms statuss un rezervācijas.
                  </div>
                </v-card>
              </v-col>
            </v-row>
          </v-col>
        </v-row>

        <!-- KĀ TAS STRĀDĀ -->
        <v-row class="mt-12 section-shell" align="start">
          <v-col cols="12" md="4" lg="3">
            <div class="section-lead sticky-section">
              <div class="section-title mb-4">
                <div class="text-h5 font-weight-bold">Kā tas strādās</div>
                <div class="text-body-2 opacity-80">
                  Vienkāršs process no meklēšanas līdz rezervācijai.
                </div>
              </div>

              <div class="section-note">
                Struktūra ir veidota, lai lietotājs saprastu secību jau pirmajās sekundēs un bez berzes nonāktu līdz rezervācijai.
              </div>
            </div>
          </v-col>

          <v-col cols="12" md="8" lg="9">
            <v-row dense align="stretch">
              <v-col cols="12" sm="6" lg="4" class="feature-col">
                <v-card class="surface pa-4 feature-card" elevation="10">
                  <div class="d-flex align-center mb-2">
                    <v-avatar size="34" class="me-3" variant="tonal">1</v-avatar>
                    <div class="text-h6">Atrodi kartē</div>
                  </div>
                  <div class="text-body-2 opacity-80 card-desc">
                    Izvēlies atrašanās vietu un apskati pieejamos transportlīdzekļus.
                  </div>
                </v-card>
              </v-col>

              <v-col cols="12" sm="6" lg="4" class="feature-col">
                <v-card class="surface pa-4 feature-card" elevation="10">
                  <div class="d-flex align-center mb-2">
                    <v-avatar size="34" class="me-3" variant="tonal">2</v-avatar>
                    <div class="text-h6">Filtrē un salīdzini</div>
                  </div>
                  <div class="text-body-2 opacity-80 card-desc">
                    Marka, cena dienā, ātrumkārba, degviela, statuss u.c. filtri.
                  </div>
                </v-card>
              </v-col>

              <v-col cols="12" sm="12" lg="4" class="feature-col">
                <v-card class="surface pa-4 feature-card" elevation="10">
                  <div class="d-flex align-center mb-2">
                    <v-avatar size="34" class="me-3" variant="tonal">3</v-avatar>
                    <div class="text-h6">Rezervē un apmaksā</div>
                  </div>
                  <div class="text-body-2 opacity-80 card-desc">
                    Izvēlies laiku, apstiprini rezervāciju un seko maksājuma statusam.
                  </div>
                </v-card>
              </v-col>
            </v-row>
          </v-col>
        </v-row>

        <v-row class="mt-12 mb-12" align="start">
          <v-col cols="12" lg="5">
            <div class="section-title mb-4">
              <div class="text-h5 font-weight-bold">Biežākie jautājumi</div>
              <div class="text-body-2 opacity-80">
                Īsas atbildes, lai lietotājam uzreiz skaidrs, kas būs.
              </div>
            </div>

            <v-expansion-panels variant="accordion" class="surface-panels">
              <v-expansion-panel>
                <v-expansion-panel-title>
                  Vai es redzēšu pieejamību reālajā laikā?
                </v-expansion-panel-title>
                <v-expansion-panel-text class="opacity-90">
                  Jā. Vēlāk statuss (pieejams/aizņemts) un rezervācijas laiki nāks no datubāzes,
                  un kartē redzēsi tikai to, kas tiešām ir brīvs.
                </v-expansion-panel-text>
              </v-expansion-panel>

              <v-expansion-panel>
                <v-expansion-panel-title>
                  Kādi filtri būs?
                </v-expansion-panel-title>
                <v-expansion-panel-text class="opacity-90">
                  Cena dienā, marka/modelis, degvielas veids, ātrumkārba, transporta veids,
                  atrašanās vieta un citi.
                </v-expansion-panel-text>
              </v-expansion-panel>

              <v-expansion-panel>
                <v-expansion-panel-title>
                  Vai būs lietotāju profili?
                </v-expansion-panel-title>
                <v-expansion-panel-text class="opacity-90">
                  Jā. Klienti un pakalpojumu sniedzēji būs atsevišķas lomas,
                  ar autorizāciju un pārvaldību.
                </v-expansion-panel-text>
              </v-expansion-panel>

              <v-expansion-panel>
                <v-expansion-panel-title>
                  Kā notiks rezervācijas izveide?
                </v-expansion-panel-title>
                <v-expansion-panel-text class="opacity-90">
                  Lietotājs izvēlēsies transportlīdzekli, norādīs nomas sākuma un beigu laiku,
                  un sistēma automātiski pārbaudīs, vai tas konkrētajā periodā ir pieejams.
                </v-expansion-panel-text>
              </v-expansion-panel>

              <v-expansion-panel>
                <v-expansion-panel-title>
                  Vai rezervāciju varēs apmaksāt uzreiz?
                </v-expansion-panel-title>
                <v-expansion-panel-text class="opacity-90">
                  Jā. Sistēmā ir paredzēta rezervācijas apmaksa uzreiz pēc izveides,
                  kā arī iespēja atstāt to neapmaksātu un pabeigt vēlāk profilā.
                </v-expansion-panel-text>
              </v-expansion-panel>

              <v-expansion-panel>
                <v-expansion-panel-title>
                  Vai būs iespējams pievienot atsauksmes?
                </v-expansion-panel-title>
                <v-expansion-panel-text class="opacity-90">
                  Jā. Autorizēti klienti varēs atstāt vērtējumu un komentāru par izmantoto transportlīdzekli,
                  lai citi lietotāji redzētu reālu pieredzi un vidējo novērtējumu.
                </v-expansion-panel-text>
              </v-expansion-panel>

              <v-expansion-panel>
                <v-expansion-panel-title>
                  Ko varēs darīt pakalpojumu sniedzējs?
                </v-expansion-panel-title>
                <v-expansion-panel-text class="opacity-90">
                  Pakalpojumu sniedzējs varēs pievienot jaunus transportlīdzekļus, pārvaldīt to datus,
                  norādīt transporta veidus un sekot līdzi saviem piedāvājumiem kartē.
                </v-expansion-panel-text>
              </v-expansion-panel>

              <v-expansion-panel>
                <v-expansion-panel-title>
                  Vai kartē būs redzami visi nomas punkti?
                </v-expansion-panel-title>
                <v-expansion-panel-text class="opacity-90">
                  Jā. Kartē būs redzami visi pievienotie pakalpojumu sniedzēju punkti,
                  un lietotājs varēs atlasīt tikai sev atbilstošos pēc filtriem un pieejamības.
                </v-expansion-panel-text>
              </v-expansion-panel>

              <v-expansion-panel>
                <v-expansion-panel-title>
                  Vai es redzēšu transporta cenu pirms rezervācijas?
                </v-expansion-panel-title>
                <v-expansion-panel-text class="opacity-90">
                  Jā. Pirms rezervācijas būs redzama dienas cena, izvēlētais periods,
                  aprēķinātais dienu skaits un kopējā summa par visu nomu.
                </v-expansion-panel-text>
              </v-expansion-panel>
            </v-expansion-panels>
          </v-col>

          <v-col cols="12" lg="7">
            <div class="section-title mb-4">
              <div class="text-h5 font-weight-bold">Sazināties ar mums</div>
              <div class="text-body-2 opacity-80">
                Atstāj savu ziņu — tā tiks nosūtīta uz e-pastu.
              </div>
            </div>

            <v-card class="surface" elevation="12">
              <v-card-text class="pa-6 pa-md-8">
                <v-form ref="formRef" @submit.prevent="submitContact">
                  <v-row dense>
                    <v-col cols="12" md="6">
                      <v-text-field v-model="name" label="Vārds" variant="outlined" :rules="nameRules" required />
                    </v-col>

                    <v-col cols="12" md="6">
                      <v-text-field v-model="email" label="E-pasts" variant="outlined" :rules="emailRules" required />
                    </v-col>

                    <v-col cols="12">
                      <v-textarea v-model="comment" label="Komentārs" variant="outlined" :rules="commentRules" rows="4" auto-grow required />
                    </v-col>

                    <v-col cols="12" class="d-flex flex-column flex-sm-row ga-3">
                      <v-btn type="submit" size="large" rounded="xl" elevation="6" :loading="loading">
                        <v-icon start>mdi-send</v-icon>
                        Nosūtīt ziņu
                      </v-btn>

                      <v-btn size="large" rounded="xl" variant="tonal" :disabled="loading" @click="resetForm">
                        <v-icon start>mdi-refresh</v-icon>
                        Notīrīt
                      </v-btn>
                    </v-col>

                    <v-col cols="12">
                      <div class="text-caption opacity-70">
                        Ziņa tiks nosūtīta, kad backendā būs sakonfigurēts e-pasta sūtīšanas serviss (SMTP).
                      </div>
                    </v-col>
                  </v-row>
                </v-form>
              </v-card-text>
            </v-card>

          </v-col>
        </v-row>

      </div>
    </v-container>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { MAP_ROUTE } from '@/router/paths'
import { useNotifications } from '@/stores/notifications'

const router = useRouter()
const { notifySuccess, notifyError } = useNotifications()

const formRef = ref(null)
const name = ref('')
const email = ref('')
const comment = ref('')
const loading = ref(false)

const nameRules = [v => !!v || 'Ievadi vārdu', v => (v?.length >= 2) || 'Vārdam jābūt vismaz 2 simboli']
const emailRules = [v => !!v || 'Ievadi e-pastu', v => /.+@.+\..+/.test(v) || 'Nepareizs e-pasta formāts']
const commentRules = [v => !!v || 'Ievadi komentāru', v => (v?.length >= 10) || 'Komentāram jābūt vismaz 10 simboli']

function resetForm() {
  name.value = ''
  email.value = ''
  comment.value = ''
  formRef.value?.resetValidation()
}

function goMap() {
  router.push(MAP_ROUTE)
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

async function submitContact() {
  const res = await formRef.value?.validate()
  if (!res?.valid) return

  loading.value = true
  try {
    const API_BASE = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000'
    const r = await fetch(`${API_BASE}/api/contact`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name: name.value, email: email.value, comment: comment.value }),
    })
    if (!r.ok) throw new Error(await r.text())
    notifySuccess('Ziņa nosūtīta!')
    resetForm()
  } catch (e) {
    notifyError('Kļūda: ziņu neizdevās nosūtīt (pārbaudi backendu / mail iestatījumus).')
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.bg {
  min-height: calc(100vh - 72px - 64px);
  background: var(--er-page-bg);
}

.page-wrap { width: min(1380px, 100%); margin: 0 auto; }

.surface-panels { width: 100%; }

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
  margin-bottom: 16px;
}

@media (max-width: 1260px) {
  .page-wrap {
    width: min(1200px, 100%);
  }
}

@media (max-width: 960px) {
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