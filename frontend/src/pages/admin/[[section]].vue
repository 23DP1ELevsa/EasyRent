<template>
  <v-layout class="admin-layout">
    <v-navigation-drawer :model-value="mdAndUp || drawer" :permanent="mdAndUp" :temporary="!mdAndUp" width="252"
      class="admin-sidebar" @update:model-value="value => { if (!mdAndUp) drawer = value }">
      <div class="admin-brand">
        <RouterLink :to="ADMIN_ROUTE" aria-label="EasyRent"><BrandLogo /></RouterLink>
        <span>{{ label('administration') }}</span>
      </div>
      <v-list nav density="comfortable" :aria-label="label('navigation')" class="admin-navigation">
        <v-list-item v-for="(definition, key) in sections" :key="key" :to="sectionPath(key)" :active="section === key"
          :prepend-icon="definition.icon" :title="label(key)" rounded="lg" @click="closeMobileDrawer" />
      </v-list>
      <template #append>
        <div class="admin-sidebar__footer">
          <v-list-item :to="HOME_ROUTE" prepend-icon="mdi-arrow-top-right" :title="label('publicSite')" rounded="lg" />
        </div>
      </template>
    </v-navigation-drawer>

    <v-app-bar class="admin-bar" elevation="0" height="64">
      <v-btn v-if="!mdAndUp" :aria-label="label('navigation')" :aria-expanded="drawer" icon="mdi-menu" @click="drawer = !drawer" />
      <div class="admin-bar__section"><v-icon :icon="definition.icon" size="18" />{{ label(section) }}</div>
      <v-spacer />
      <v-menu location="bottom end">
        <template #activator="{ props }">
          <v-btn v-bind="props" size="small" variant="text" :aria-label="t('app.language.select')">{{ locale.toUpperCase() }}</v-btn>
        </template>
        <v-list density="compact">
          <v-list-item v-for="language in supportedLanguages" :key="language.code" :title="language.nativeName"
            :active="locale === language.code" @click="setLocale(language.code)" />
        </v-list>
      </v-menu>
      <v-btn :aria-label="t(dark ? 'app.theme.light' : 'app.theme.dark')" :icon="dark ? 'mdi-weather-sunny' : 'mdi-weather-night'" size="small" @click="toggleTheme" />
      <v-btn :aria-label="t('app.auth.logout')" icon="mdi-logout" size="small" @click="logout" />
    </v-app-bar>

    <v-main>
      <div class="admin-content">
        <header class="admin-content__header">
          <h1>{{ label(section) }}</h1>
          <v-btn-toggle v-if="section === 'dashboard'" v-model="days" mandatory density="compact" variant="outlined" divided
            :aria-label="label('period')" color="primary">
            <v-btn v-for="range in [7, 30, 90]" :key="range" :value="range">{{ label('days', { count: range }) }}</v-btn>
          </v-btn-toggle>
          <v-btn v-if="section === 'categories'" color="primary" prepend-icon="mdi-plus" @click="openCategory()">{{ label('newCategory') }}</v-btn>
        </header>

        <v-alert v-if="error" type="error" variant="tonal" class="mb-4">
          {{ error }}
          <v-btn size="small" variant="text" @click="refresh">{{ label('retry') }}</v-btn>
        </v-alert>
        <v-alert v-if="success" type="success" variant="tonal" class="mb-4" closable @click:close="success = ''">{{ success }}</v-alert>

        <template v-if="section === 'dashboard'">
          <v-skeleton-loader v-if="loading" type="card, card" :aria-label="label('loading')" />
          <AdminDashboard v-else-if="dashboard" :data="dashboard" />
        </template>

        <section v-else class="admin-table-panel" :aria-label="label(section)">
          <div class="admin-filters">
            <v-text-field v-model="filters.q" :label="label('search')" prepend-inner-icon="mdi-magnify" density="compact" variant="outlined" hide-details clearable />
            <template v-for="filter in definition.filters" :key="filter.key">
              <v-select v-if="filter.options" v-model="filters[filter.key]" :label="label(filter.label)" :items="choices(filter.options)"
                clearable hide-details variant="outlined" density="compact" />
              <v-text-field v-else v-model="filters[filter.key]" :label="label(filter.label)" :type="filter.type"
                :min="filter.type === 'number' ? 1 : undefined" :persistent-placeholder="filter.type === 'date'"
                clearable hide-details density="compact" variant="outlined" />
            </template>
            <v-btn icon="mdi-filter-off-outline" :aria-label="label('reset')" :title="label('reset')" size="small" variant="text" @click="clearFilters" />
          </div>

          <v-progress-linear v-if="loading" indeterminate color="primary" :aria-label="label('loading')" />
          <v-table v-if="rows.length || loading" class="records-table" :aria-busy="loading" :aria-label="label(section)" density="comfortable">
            <thead>
              <tr>
                <th v-for="column in definition.columns" :key="column.key" scope="col" :aria-sort="sortAria(column)">
                  <button v-if="column.sort" type="button" class="sort-button" @click="sortBy(column.sort)">
                    {{ label(column.label) }}
                    <v-icon :icon="sortKey === column.sort ? (direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down') : 'mdi-unfold-more-horizontal'" size="15" />
                  </button>
                  <span v-else>{{ label(column.label) }}</span>
                </th>
                <th scope="col">{{ label('action') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row[definition.id]">
                <td v-for="column in definition.columns" :key="column.key">
                  <v-chip v-if="column.format === 'state'" :color="stateColor(column.read(row))" size="small" variant="tonal">
                    {{ format(column.read(row), column.format) }}
                  </v-chip>
                  <span v-else class="record-cell" :title="String(format(column.read(row), column.format))">{{ format(column.read(row), column.format) }}</span>
                </td>
                <td><v-btn :aria-label="`${label('openDetails')} #${row[definition.id]}`" color="primary" size="small" variant="text" @click="openDetails(row)">{{ label('openDetails') }}</v-btn></td>
              </tr>
            </tbody>
          </v-table>
          <div v-else-if="!error" class="table-empty"><v-icon icon="mdi-inbox-outline" size="24" />{{ label('empty') }}</div>
          <div class="admin-pagination">
            <v-select v-model="perPage" :items="[10, 25, 50, 100]" :label="label('rowCount')" hide-details density="compact" variant="outlined" />
            <span>{{ label('tablePage', { page, total: lastPage }) }}</span>
            <v-pagination v-model="page" :length="lastPage" :total-visible="4" density="compact" />
          </div>
        </section>
      </div>
    </v-main>

    <v-dialog v-model="detailOpen" :max-width="760" scrollable>
      <v-card v-if="selected">
        <v-card-title>{{ label('details') }} · #{{ selected[definition.id] }}</v-card-title>
        <v-card-text>
          <v-alert v-if="detailError" type="error" variant="tonal">{{ detailError }}</v-alert>
          <v-skeleton-loader v-if="detailLoading" type="article" :aria-label="label('loading')" />
          <AdminRecordDetails v-else :section="section" :row="selected" :details="details" @revoke="openRevoke" @restriction-page="loadDetails" />
        </v-card-text>
        <v-card-actions class="flex-wrap">
          <v-btn v-if="section === 'users' && !selected.removed_at && !detailError" color="warning" variant="tonal" @click="openRestriction">{{ label('restrict') }}</v-btn>
          <v-btn v-if="section === 'categories'" color="primary" @click="openCategory(selected)">{{ label('editCategory') }}</v-btn>
          <v-btn v-if="section === 'categories'" color="warning" variant="tonal" @click="openAvailability">{{ label(selected.deactivated_at ? 'restore' : 'deactivate') }}</v-btn>
          <v-btn v-if="section === 'vehicles' || section === 'reviews'" color="warning" variant="tonal" @click="openVisibility">{{ label(selected.moderation_hidden_at ? 'restore' : 'hide') }}</v-btn>
          <v-btn v-if="section === 'reports' && openReport" color="primary" variant="tonal" @click="openDecision">{{ label('decide') }}</v-btn>
          <v-spacer />
          <v-btn @click="detailOpen = false">{{ label('close') }}</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog :model-value="Boolean(change)" :max-width="540" :persistent="saving" @update:model-value="value => { if (!value) change = null }">
      <v-card v-if="change">
        <v-form @submit.prevent="submitChange">
          <v-card-title>{{ label(change.title) }}<span v-if="change.id"> · #{{ change.id }}</span></v-card-title>
          <v-card-text class="d-grid">
            <v-alert v-if="changeError" type="error" variant="tonal" class="mb-3">{{ changeError }}</v-alert>
            <template v-if="change.kind === 'restriction'">
              <v-select v-model="change.data.veids" :label="label('restrictionType')" :items="choices(restrictionTypes)" variant="outlined" />
              <v-select v-model="change.data.iemesla_kods" :label="label('reasonCategory')" :items="choices(reportReasons)" clearable variant="outlined" />
              <v-select v-model="change.data.duration" :label="label('duration')" :items="choices(durations)" variant="outlined" />
              <v-text-field v-if="change.data.duration === 'custom'" v-model="change.date" type="datetime-local" :label="label('end')" persistent-placeholder required variant="outlined" />
            </template>
            <template v-if="change.kind === 'category'">
              <v-text-field v-model="change.data.nosaukums" :label="label('categoryName')" required maxlength="50" variant="outlined" />
              <v-text-field v-model="change.data.tips" :label="label('slug')" maxlength="50" variant="outlined" />
            </template>
            <v-select v-if="change.kind === 'report'" v-model="change.data.statuss" :label="label('status')" :items="choices(change.options)" variant="outlined" />
            <v-textarea v-model="change.data.iemesls" :label="label(change.kind === 'report' ? 'decision' : 'reason')" required rows="3" maxlength="2000" variant="outlined" />
          </v-card-text>
          <v-card-actions class="justify-end">
            <v-btn :disabled="saving" variant="text" @click="change = null">{{ label('cancel') }}</v-btn>
            <v-btn :loading="saving" :disabled="!change.data.iemesls.trim()" color="primary" type="submit" variant="flat">{{ label('confirm') }}</v-btn>
          </v-card-actions>
        </v-form>
      </v-card>
    </v-dialog>
  </v-layout>
</template>

<script setup>
import { computed, inject, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'
import AdminDashboard from '@/admin/AdminDashboard.vue'
import AdminRecordDetails from '@/admin/AdminRecordDetails.vue'
import { stateColor, useAdminFormat } from '@/admin/format'
import { durations, reportReasons, restrictionTypes, sections, sectionPath } from '@/admin/sections'
import BrandLogo from '@/components/BrandLogo.vue'
import { ADMIN_ROUTE, AUTH_ROUTE, HOME_ROUTE } from '@/router/paths'
import { apiFetch, logoutRequest } from '@/services/auth'
import { useLocale } from '@/stores/locale'

const route = useRoute()
const router = useRouter()
const { mdAndUp } = useDisplay()
const { t, locale, setLocale, supportedLanguages } = useLocale()
const { label, status, format } = useAdminFormat()
const drawer = ref(false)
const { themeMode, toggleTheme } = inject('easyRentAppearance')
const dark = computed(() => themeMode.value === 'dark')
const section = computed(() => Object.hasOwn(sections, route.params.section) ? route.params.section : 'dashboard')
const definition = computed(() => sections[section.value])
const days = ref(30)
const filters = ref({})
const sortKey = ref(null)
const direction = ref('desc')
const perPage = ref(25)
const page = ref(1)
const lastPage = ref(1)
const loading = ref(true)
const error = ref('')
const success = ref('')
const dashboard = ref(null)
const rows = ref([])
const selected = ref(null)
const details = ref(null)
const detailOpen = ref(false)
const detailLoading = ref(false)
const detailError = ref('')
const change = ref(null)
const saving = ref(false)
const changeError = ref('')
let listRequest, detailRequest, timer

const openReport = computed(() => ['jauns', 'izskatisana'].includes(selected.value?.statuss))
const choices = options => options.map(option => typeof option === 'object'
  ? { title: status(option.key), value: option.value }
  : { title: typeof option === 'number' ? String(option) : status(option), value: option })

watch([section, () => route.query], () => {
  detailRequest?.abort()
  detailOpen.value = false
  change.value = null
  rows.value = []
  dashboard.value = null
  success.value = ''
  sortKey.value = definition.value.id ?? null
  direction.value = 'desc'
  clearFilters(route.query)
}, { immediate: true })

watch([filters, sortKey, direction, perPage], () => {
  page.value = 1
  queueRefresh()
}, { deep: true })
watch([page, days, locale], queueRefresh)
onUnmounted(() => {
  clearTimeout(timer)
  listRequest?.abort()
  detailRequest?.abort()
})

function clearFilters (query = {}) {
  const allowed = ['q', ...(definition.value.filters ?? []).map(filter => filter.key)]
  filters.value = Object.fromEntries(allowed.filter(key => typeof query[key] === 'string').map(key => [key, query[key]]))
  if (section.value === 'reports' && !Object.keys(filters.value).length && query === route.query) filters.value.status = 'open'
  page.value = 1
  queueRefresh(0)
}

function queueRefresh (wait = 160) {
  clearTimeout(timer)
  listRequest?.abort()
  loading.value = true
  timer = setTimeout(refresh, typeof wait === 'number' ? wait : 160)
}

async function json (path, options) {
  const response = await apiFetch(`/api/admin/${path}`, { cache: 'no-store', ...options })
  const data = await response.json()
  if (!response.ok) throw new Error(Object.values(data.errors ?? {})[0]?.[0] || data.message || label('error'))
  return data
}

async function refresh () {
  clearTimeout(timer)
  listRequest?.abort()
  const controller = new AbortController()
  listRequest = controller
  const current = section.value
  loading.value = true
  error.value = ''
  try {
    const query = new URLSearchParams()
    if (current === 'dashboard') {
      query.set('days', days.value)
    } else {
      for (const [key, value] of Object.entries(filters.value)) {
        if (value !== null && value !== undefined && String(value).trim()) query.set(key, String(value).trim())
      }
      query.set('page', page.value)
      query.set('per_page', perPage.value)
      query.set('direction', direction.value)
      if (sortKey.value) query.set('sort', sortKey.value)
    }
    const data = await json(`${current}?${query}`, { signal: controller.signal })
    if (controller.signal.aborted) return
    if (current === 'dashboard') {
      dashboard.value = data
    } else {
      lastPage.value = Math.max(1, data.last_page)
      if (page.value > lastPage.value) page.value = lastPage.value
      rows.value = data.data
    }
  } catch (failure) {
    if (!controller.signal.aborted) {
      error.value = failure instanceof TypeError || failure instanceof SyntaxError ? label('error') : failure.message
      rows.value = []
    }
  } finally {
    if (listRequest === controller && !controller.signal.aborted) loading.value = false
  }
}

function sortAria (column) {
  if (!column.sort) return undefined
  return sortKey.value === column.sort ? (direction.value === 'asc' ? 'ascending' : 'descending') : 'none'
}

function sortBy (key) {
  direction.value = sortKey.value === key && direction.value === 'asc' ? 'desc' : 'asc'
  sortKey.value = key
}

function closeMobileDrawer () {
  if (!mdAndUp.value) drawer.value = false
}

async function logout () {
  if (await logoutRequest()) await router.replace(AUTH_ROUTE)
}

function openDetails (row) {
  selected.value = row
  details.value = null
  detailError.value = ''
  detailOpen.value = true
  loadDetails()
}

async function loadDetails (restrictionPage = 1) {
  if (!['users', 'vehicles', 'reports'].includes(section.value)) return
  detailRequest?.abort()
  const controller = new AbortController()
  detailRequest = controller
  const current = section.value
  detailLoading.value = true
  detailError.value = ''
  try {
    const data = await json(`${current}/${selected.value[definition.value.id]}?restriction_page=${restrictionPage}`, { signal: controller.signal })
    if (controller.signal.aborted) return
    details.value = data
    selected.value = data.user ?? data.report ?? data
  } catch (failure) {
    if (!controller.signal.aborted) detailError.value = failure instanceof TypeError ? label('error') : failure.message
  } finally {
    if (detailRequest === controller) detailLoading.value = false
  }
}

function prepareChange (title, kind, path, method, data = {}, id = selected.value?.[definition.value.id]) {
  changeError.value = ''
  change.value = { title, kind, path, method, data: { iemesls: '', ...data }, id }
}

function openRestriction () {
  prepareChange('restrict', 'restriction', `users/${selected.value.persona_id}/restrictions`, 'POST', { veids: restrictionTypes[0], duration: '7d' })
}

function openRevoke (row) {
  prepareChange('revoke', 'revoke', `users/${selected.value.persona_id}/restrictions/${row.ierobezojums_id}`, 'PATCH', {}, row.ierobezojums_id)
}

function openCategory (row) {
  const data = { nosaukums: row?.nosaukums ?? '', tips: row?.tips ?? '' }
  prepareChange(row ? 'editCategory' : 'newCategory', 'category', row ? `categories/${row.veids_id}` : 'categories', row ? 'PUT' : 'POST', data, row ? row.veids_id : null)
}

function openAvailability () {
  const row = selected.value
  const active = Boolean(row.deactivated_at)
  prepareChange(active ? 'restore' : 'deactivate', 'availability', `categories/${row.veids_id}/availability`, 'PATCH', { active })
}

function openVisibility () {
  const row = selected.value
  const hidden = !row.moderation_hidden_at
  prepareChange(hidden ? 'hide' : 'restore', 'visibility', `${section.value}/${row[definition.value.id]}/visibility`, 'PATCH', { hidden })
}

function openDecision () {
  const options = selected.value.statuss === 'jauns' ? ['izskatisana', 'atrisinats', 'noraidits'] : ['atrisinats', 'noraidits']
  prepareChange('decide', 'report', `reports/${selected.value.zinojums_id}`, 'PATCH', { statuss: options[0] })
  change.value.options = options
}

async function submitChange () {
  if (!change.value || saving.value || !change.value.data.iemesls.trim()) return
  const { path, method, data, date } = change.value
  saving.value = true
  changeError.value = ''
  try {
    const payload = { ...data }
    if (data.duration === 'custom') payload.beigu_laiks = new Date(date).toISOString().replace(/\.\d{3}Z$/, '+00:00')
    await json(path, { method, body: JSON.stringify(payload), headers: { 'Content-Type': 'application/json' } })
    change.value = null
    detailOpen.value = false
    success.value = label('saved')
    await refresh()
  } catch (failure) {
    changeError.value = failure instanceof TypeError || failure instanceof RangeError ? label('saveError') : failure.message
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.admin-layout { min-height: 100dvh; background: var(--er-bg); color: var(--er-text); }
.admin-sidebar { background: var(--er-night); color: var(--er-night-text); border-color: var(--er-night-border); }
.admin-brand { display: grid; gap: 8px; padding: 21px 20px 20px; border-bottom: 1px solid var(--er-night-border); }
.admin-brand a { justify-self: start; color: inherit; text-decoration: none; }
.admin-brand > span { color: var(--er-night-muted); font-size: .75rem; font-weight: 700; }
.admin-navigation { background: transparent !important; color: var(--er-night-text) !important; border: 0; border-radius: 0; padding: 12px; }
.admin-navigation :deep(.v-list-item) { margin-bottom: 3px; min-height: 43px; }
.admin-navigation :deep(.v-list-item--active) { background: rgb(58 174 120 / 24%); color: #d8ffe9; }
.admin-navigation :deep(.v-list-item-title), .admin-sidebar__footer :deep(.v-list-item-title) { white-space: normal; font-size: .83rem; line-height: 1.25; font-weight: 680; }
.admin-sidebar__footer { padding: 12px; border-top: 1px solid var(--er-night-border); }
.admin-bar { background: rgb(var(--v-theme-surface)); border-bottom: 1px solid var(--er-stroke); padding-inline: 12px; }
.admin-bar__section { display: flex; align-items: center; gap: 8px; font-size: .83rem; color: var(--er-text-muted); font-weight: 750; margin-inline-start: 10px; min-width: 0; }
.admin-content { padding: clamp(14px, 2vw, 28px); max-width: 1680px; margin-inline: auto; min-width: 0; }
.admin-content__header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 19px; }
h1 { font: 760 clamp(1.5rem, 3vw, 2.25rem)/1.1 var(--er-display-font); letter-spacing: -.035em; }
.admin-table-panel { min-width: 0; max-width: 100%; border: 1px solid var(--er-stroke); border-radius: 16px; background: rgb(var(--v-theme-surface)); overflow: hidden; }
.admin-filters { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; padding: 13px; }
.admin-filters > .v-input { flex: 1 1 145px; max-width: 246px; }
.admin-pagination { display: flex; justify-content: end; align-items: center; flex-wrap: wrap; gap: 6px 13px; padding: 10px 13px; color: var(--er-text-muted); font-size: .76rem; }
.admin-pagination .v-input { max-width: 152px; }
.records-table { border-block: 1px solid var(--er-stroke); }
.records-table thead { background: var(--er-bg-soft); }
.records-table :deep(th) { font-size: .77rem; font-weight: 780; white-space: nowrap; }
.records-table :deep(td) { font-size: .8rem; }
.record-cell { display: block; min-width: max-content; max-width: 240px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.record-cell[title] { min-width: 0; }
.sort-button { display: flex; align-items: center; gap: 5px; min-height: 40px; font: inherit; color: inherit; }
.table-empty { display: grid; gap: 8px; place-content: center; justify-items: center; min-height: 126px; color: var(--er-text-muted); border-block: 1px solid var(--er-stroke); }
@media (max-width: 759px) {
  .records-table :deep(th:last-child), .records-table :deep(td:last-child) { position: sticky; right: 0; z-index: 1; box-shadow: -1px 0 var(--er-stroke); }
  .records-table :deep(th:last-child) { background: var(--er-bg-soft); }
  .records-table :deep(td:last-child) { background: rgb(var(--v-theme-surface)); }
}
@media (max-width: 599px) { .admin-filters > .v-input { max-width: none; flex-basis: 130px; } .admin-pagination { justify-content: center; } }
</style>
