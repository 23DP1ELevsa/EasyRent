<template>
  <div class="bg">
    <v-container class="fill-height py-12">
      <!-- HERO -->
      <v-row align="center" justify="center">
        <v-col cols="12" md="10" lg="9">
          <v-card class="surface" elevation="12">
            <v-card-text class="pa-8 pa-md-12">
              <v-chip variant="tonal" label class="mb-6">
                EasyRent • Transporta noma Latvijā
              </v-chip>

              <div class="text-h3 text-md-h2 font-weight-bold mb-4">
                Atrast un iznomāt transportu — vienkārši un ātri
              </div>

              <div class="text-body-1 text-md-h6 opacity-90 mb-8">
                EasyRent ir platforma, kur vienuviet varēsi apskatīt pieejamos transportlīdzekļus,
                salīdzināt cenas un rezervēt izvēlēto variantu. Pašlaik šī ir tikai dizaina versija —
                vēlāk pieslēgsim karti, filtrus un reālus datus no sistēmas.
              </div>

              <div class="d-flex flex-column flex-sm-row ga-3 mb-6">
                <v-btn size="x-large" rounded="xl" elevation="6" disabled>
                  <v-icon start>mdi-map</v-icon>
                  Pāriet uz karti
                </v-btn>

                <v-btn size="x-large" rounded="xl" variant="tonal" disabled>
                  <v-icon start>mdi-filter-variant</v-icon>
                  Filtri (drīzumā)
                </v-btn>
              </div>

              <div class="text-caption opacity-70">
                Poga “Pāriet uz karti” būs aktīva, kad pievienosim kartes lapu un maršrutu.
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- KO VARĒSI ATRAST -->
      <v-row class="mt-10" justify="center">
        <v-col cols="12" md="10" lg="9">
          <div class="section-title mb-4">
            <div class="text-h5 font-weight-bold">Kādi transportlīdzekļi būs pieejami</div>
            <div class="text-body-2 opacity-80">
              Piemēri kategorijām, ko vēlāk rādīsim kartē un sarakstā.
            </div>
          </div>

          <v-row dense>
            <v-col cols="12" sm="6" md="4">
              <v-card class="surface pa-4" elevation="10">
                <v-icon size="28" class="mb-2">mdi-car</v-icon>
                <div class="text-h6 mb-1">Vieglās automašīnas</div>
                <div class="text-body-2 opacity-80">
                  Ikdienai un braucieniem pa Latviju (sedans, hečbeks, universāls).
                </div>
              </v-card>
            </v-col>

            <v-col cols="12" sm="6" md="4">
              <v-card class="surface pa-4" elevation="10">
                <v-icon size="28" class="mb-2">mdi-car-estate</v-icon>
                <div class="text-h6 mb-1">SUV / Apvidus</div>
                <div class="text-body-2 opacity-80">
                  Komfortam, ģimenei un garākiem maršrutiem.
                </div>
              </v-card>
            </v-col>

            <v-col cols="12" sm="6" md="4">
              <v-card class="surface pa-4" elevation="10">
                <v-icon size="28" class="mb-2">mdi-van-utility</v-icon>
                <div class="text-h6 mb-1">Mikroautobusi</div>
                <div class="text-body-2 opacity-80">
                  Pārvākšanās, kravas un komandas braucieniem.
                </div>
              </v-card>
            </v-col>

            <v-col cols="12" sm="6" md="4">
              <v-card class="surface pa-4" elevation="10">
                <v-icon size="28" class="mb-2">mdi-motorbike</v-icon>
                <div class="text-h6 mb-1">Motocikli</div>
                <div class="text-body-2 opacity-80">
                  Sezonas braucieniem un hobijam (ja pieejami).
                </div>
              </v-card>
            </v-col>

            <v-col cols="12" sm="6" md="4">
              <v-card class="surface pa-4" elevation="10">
                <v-icon size="28" class="mb-2">mdi-bicycle</v-icon>
                <div class="text-h6 mb-1">E-skrejriteņi / velo</div>
                <div class="text-body-2 opacity-80">
                  Ātrai pārvietošanai pilsētā (ja pievienosim kategoriju).
                </div>
              </v-card>
            </v-col>

            <v-col cols="12" sm="6" md="4">
              <v-card class="surface pa-4" elevation="10">
                <v-icon size="28" class="mb-2">mdi-car-wrench</v-icon>
                <div class="text-h6 mb-1">Speciālie piedāvājumi</div>
                <div class="text-body-2 opacity-80">
                  Īpaši auto, akcijas, ilgtermiņa noma (drīzumā).
                </div>
              </v-card>
            </v-col>
          </v-row>
        </v-col>
      </v-row>

      <!-- KĀ TAS STRĀDĀ -->
      <v-row class="mt-12 section-row" justify="center">
        <v-col cols="12" md="10" lg="9">
          <div class="section-title mb-4">
            <div class="text-h5 font-weight-bold">Kā tas strādās</div>
            <div class="text-body-2 opacity-80">
              Vienkāršs process no meklēšanas līdz rezervācijai.
            </div>
          </div>

          <v-row dense>
            <v-col cols="12" md="4">
              <v-card class="surface pa-5" elevation="10">
                <div class="d-flex align-center mb-2">
                  <v-avatar size="34" class="me-3" variant="tonal">1</v-avatar>
                  <div class="text-h6">Atrodi kartē</div>
                </div>
                <div class="text-body-2 opacity-80">
                  Izvēlies atrašanās vietu un apskati pieejamos transportlīdzekļus.
                </div>
              </v-card>
            </v-col>

            <v-col cols="12" md="4">
              <v-card class="surface pa-5" elevation="10">
                <div class="d-flex align-center mb-2">
                  <v-avatar size="34" class="me-3" variant="tonal">2</v-avatar>
                  <div class="text-h6">Filtrē un salīdzini</div>
                </div>
                <div class="text-body-2 opacity-80">
                  Marka, cena dienā, ātrumkārba, degviela, statuss u.c. filtri.
                </div>
              </v-card>
            </v-col>

            <v-col cols="12" md="4">
              <v-card class="surface pa-5" elevation="10">
                <div class="d-flex align-center mb-2">
                  <v-avatar size="34" class="me-3" variant="tonal">3</v-avatar>
                  <div class="text-h6">Rezervē un apmaksā</div>
                </div>
                <div class="text-body-2 opacity-80">
                  Izvēlies laiku, apstiprini rezervāciju un seko maksājuma statusam.
                </div>
              </v-card>
            </v-col>
          </v-row>
        </v-col>
      </v-row>

      <!-- FAQ -->
      <v-row class="mt-12 mb-6 section-row" justify="center">
        <v-col cols="12" md="10" lg="9">
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
          </v-expansion-panels>
        </v-col>
      </v-row>

    <!-- SAZINĀTIES AR MUMS -->
    <v-row class="mt-12 mb-12" justify="center">
    <v-col cols="12" md="10" lg="9">
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
                <v-text-field
                    v-model="name"
                    label="Vārds"
                    variant="outlined"
                    :rules="nameRules"
                    required
                />
                </v-col>

                <v-col cols="12" md="6">
                <v-text-field
                    v-model="email"
                    label="E-pasts"
                    variant="outlined"
                    :rules="emailRules"
                    required
                />
                </v-col>

                <v-col cols="12">
                <v-textarea
                    v-model="comment"
                    label="Komentārs"
                    variant="outlined"
                    :rules="commentRules"
                    rows="4"
                    auto-grow
                    required
                />
                </v-col>

                <v-col cols="12" class="d-flex flex-column flex-sm-row ga-3">
                <v-btn
                    type="submit"
                    size="large"
                    rounded="xl"
                    elevation="6"
                    :loading="loading"
                >
                    <v-icon start>mdi-send</v-icon>
                    Nosūtīt ziņu
                </v-btn>

                <v-btn
                    size="large"
                    rounded="xl"
                    variant="tonal"
                    :disabled="loading"
                    @click="resetForm"
                >
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

        <v-snackbar v-model="snackbar" :timeout="3500">
        {{ snackbarText }}
        <template #actions>
            <v-btn variant="text" @click="snackbar = false">Aizvērt</v-btn>
        </template>
        </v-snackbar>
    </v-col>
    </v-row>
    </v-container>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const formRef = ref(null)

const name = ref('')
const email = ref('')
const comment = ref('')

const loading = ref(false)
const snackbar = ref(false)
const snackbarText = ref('')

const nameRules = [
  v => !!v || 'Ievadi vārdu',
  v => (v?.length >= 2) || 'Vārdam jābūt vismaz 2 simboli',
]

const emailRules = [
  v => !!v || 'Ievadi e-pastu',
  v => /.+@.+\..+/.test(v) || 'Nepareizs e-pasta formāts',
]

const commentRules = [
  v => !!v || 'Ievadi komentāru',
  v => (v?.length >= 10) || 'Komentāram jābūt vismaz 10 simboli',
]

function resetForm() {
  name.value = ''
  email.value = ''
  comment.value = ''
  formRef.value?.resetValidation()
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
      body: JSON.stringify({
        name: name.value,
        email: email.value,
        comment: comment.value,
      }),
    })

    if (!r.ok) {
      const txt = await r.text()
      throw new Error(txt || 'Neizdevās nosūtīt')
    }

    snackbarText.value = 'Ziņa nosūtīta!'
    snackbar.value = true
    resetForm()
  } catch (e) {
    snackbarText.value = 'Kļūda: ziņu neizdevās nosūtīt (pārbaudi backendu / mail iestatījumus).'
    snackbar.value = true
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.bg {
  min-height: calc(100vh - 72px - 64px);
  background: radial-gradient(1200px circle at 10% 10%, rgba(255,255,255,0.12), transparent 45%),
              radial-gradient(900px circle at 90% 20%, rgba(255,255,255,0.10), transparent 40%),
              linear-gradient(135deg, #0f172a, #111827, #0b1020);
}

/* GALVENAIS FIX:
   ja kāds vecāks flex konteineris mēģina salikt šīs rindas blakus,
   tad katra rinda aizņem 100% un paliek centrēta ar justify="center". */
.section-row {
  flex: 0 0 100%;
  max-width: 100%;
  width: 100%;
}

/* FAQ paneliem 100% platums (lai nekas nesašaurinās dīvaini) */
.surface-panels {
  width: 100%;
}

/* Balta “virsma” visām kartītēm */
.surface {
  background: rgba(255, 255, 255, 0.94);
  border: 1px solid rgba(15, 23, 42, 0.08);
  color: #0f172a;
  backdrop-filter: blur(6px);
}

/* Lai teksts iekšā vienmēr izskatās tumšs un lasāms */
.surface :deep(.v-card-text),
.surface :deep(.v-card-title),
.surface :deep(.v-card-subtitle) {
  color: #0f172a;
}

/* Sadaļu virsraksti uz tumša fona */
.section-title {
  padding-left: 4px;
  color: rgba(255, 255, 255, 0.92);
}

/* FAQ paneļi arī balti */
.surface-panels :deep(.v-expansion-panel) {
  background: rgba(255, 255, 255, 0.94);
  border: 1px solid rgba(15, 23, 42, 0.08);
  color: #0f172a;
}

/* Mazliet atstarpes starp FAQ paneļiem */
.surface-panels :deep(.v-expansion-panel + .v-expansion-panel) {
  margin-top: 10px;
}
</style>
