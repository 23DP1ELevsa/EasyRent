<template>
  <div v-if="data" class="admin-dashboard">
    <div class="metric-grid">
      <div v-for="metric in metrics" :key="metric.key" class="metric">
        <div class="metric-top"><v-icon :color="metric.color" :icon="metric.icon" size="21" /><span>{{ label(metric.key) }}</span></div>
        <strong>{{ metric.value }}</strong>
        <div v-if="metric.breakdown" class="metric-breakdown">
          <span v-for="part in metric.breakdown" :key="part.key">{{ part.label }} <b>{{ part.value }}</b></span>
        </div>
      </div>
    </div>

    <div class="dashboard-columns">
      <section v-for="chart in charts" :key="chart.field" class="dashboard-panel">
        <div class="dashboard-panel__head">
          <h2>{{ label(chart.title) }}</h2>
          <v-icon :color="chart.color" :icon="chart.icon" size="20" />
        </div>
        <div class="chart" role="group" :aria-label="label(chart.title)">
          <div class="chart-axis" aria-hidden="true"><span>{{ number(chart.max) }}</span><span>0</span></div>
          <div class="chart-bars" :class="`chart-bars--${chart.color}`" :style="{ '--days': data.series.length }">
            <span v-for="point in data.series" :key="point.date" class="chart-bar-slot" :title="`${date(point.date + 'T00:00:00', false)} · ${number(point[chart.field])}`">
              <span :style="{ height: `${point[chart.field] / chart.max * 100}%` }" class="chart-bar">
                <span class="chart-value">{{ date(point.date + 'T00:00:00', false) }} · {{ number(point[chart.field]) }}</span>
              </span>
            </span>
          </div>
        </div>
        <div class="chart-dates" aria-hidden="true">
          <span v-for="day in axisDays" :key="day">{{ date(day + 'T00:00:00', false) }}</span>
        </div>
      </section>
    </div>

    <div class="dashboard-columns">
      <section class="dashboard-panel">
        <div class="dashboard-panel__head">
          <h2>{{ label('recentBookings') }}</h2>
          <v-btn :to="sectionPath('bookings')" :aria-label="label('bookings')" icon="mdi-arrow-right" size="small" variant="text" />
        </div>
        <p v-if="!data.recent_bookings.length" class="empty-list">{{ label('empty') }}</p>
        <ul v-else class="recent-list">
          <li v-for="booking in data.recent_bookings" :key="booking.rezervacija_id">
            <div class="recent-icon"><v-icon icon="mdi-calendar-outline" size="18" /></div>
            <div class="recent-text"><strong>{{ vehicleName(booking.transporta_snapshot ?? booking.transportlidzeklis) || '—' }}</strong><span>#{{ booking.rezervacija_id }} · {{ date(booking.izveides_datums) }}</span></div>
            <v-chip :color="stateColor(booking.apmaksas_statuss)" size="small" variant="tonal">{{ status(booking.apmaksas_statuss) }}</v-chip>
          </li>
        </ul>
      </section>
      <section class="dashboard-panel">
        <div class="dashboard-panel__head">
          <h2>{{ label('recentActions') }}</h2>
          <v-btn :to="sectionPath('audit')" :aria-label="label('audit')" icon="mdi-arrow-right" size="small" variant="text" />
        </div>
        <p v-if="!data.recent_actions.length" class="empty-list">{{ label('empty') }}</p>
        <ul v-else class="recent-list">
          <li v-for="action in data.recent_actions" :key="action.darbiba_id">
            <div class="recent-icon"><v-icon icon="mdi-shield-check-outline" size="18" /></div>
            <div class="recent-text"><strong>{{ status(action.darbiba) }}</strong><span>{{ format(action.administrator, 'person') }} · {{ date(action.created_at) }}</span></div>
            <span class="recent-target">#{{ action.objekta_id }}</span>
          </li>
        </ul>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { stateColor, useAdminFormat } from './format'
import { sectionPath, vehicleName } from './sections'

const props = defineProps({ data: { type: Object, required: true } })
const { label, status, format, date, number, formatPrice } = useAdminFormat()

const metrics = computed(() => {
  const data = props.data
  const part = (key, value) => ({ key, label: status(key), value: number(value) })
  return [
    { key: 'totalUsers', icon: 'mdi-account-group-outline', color: 'primary', value: number(data.users), breakdown: [
      part('klients', data.roles.klients), part('pakalpojumu_sniedzejs', data.roles.pakalpojumu_sniedzejs),
    ] },
    { key: 'newRegistrations', icon: 'mdi-account-plus-outline', color: 'primary', value: number(data.new_registrations) },
    { key: 'totalVehicles', icon: 'mdi-car-outline', color: 'info', value: number(data.vehicles), breakdown: [
      { key: 'hidden', label: label('hiddenVehicles'), value: number(data.hidden_vehicles) },
    ] },
    { key: 'activeVehicles', icon: 'mdi-check-circle-outline', color: 'success', value: number(data.active_vehicles) },
    { key: 'paymentVolume', icon: 'mdi-credit-card-check-outline', color: 'success', value: formatPrice(data.confirmed_payment_volume) },
    { key: 'bookingStatuses', icon: 'mdi-calendar-check-outline', color: 'info',
      value: number((data.bookings.apmaksata || 0) + (data.bookings.neapmaksata || 0)), breakdown: [
        part('apmaksata', data.bookings.apmaksata), part('neapmaksata', data.bookings.neapmaksata),
      ] },
    { key: 'activeRestrictions', icon: 'mdi-shield-alert-outline', color: 'warning', value: number(data.active_restrictions) },
    { key: 'openReports', icon: 'mdi-flag-outline', color: 'error', value: number(data.open_reports) },
  ]
})

const charts = computed(() => [
  { field: 'registrations', title: 'newRegistrations', color: 'primary', icon: 'mdi-account-plus-outline' },
  { field: 'paid_bookings', title: 'paidBookings', color: 'info', icon: 'mdi-calendar-check-outline' },
].map(chart => ({ ...chart, max: Math.max(1, ...props.data.series.map(point => point[chart.field])) })))

const axisDays = computed(() => {
  const series = props.data.series
  return series.length ? [series[0].date, series[Math.floor((series.length - 1) / 2)].date, series.at(-1).date] : []
})
</script>

<style scoped>
.admin-dashboard { display: grid; gap: 18px; }
.metric-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
.metric, .dashboard-panel { background: rgb(var(--v-theme-surface)); border: 1px solid var(--er-stroke); border-radius: 16px; }
.metric { min-height: 126px; padding: 15px 17px; }
.metric-top { display: flex; align-items: center; gap: 9px; color: var(--er-text-muted); font-weight: 750; font-size: .78rem; line-height: 1.25; }
.metric-top .v-icon { flex-shrink: 0; }
.metric > strong { display: block; margin-top: 11px; color: var(--er-text); font: 700 clamp(1.4rem, 2.5vw, 2rem)/1 var(--er-display-font); overflow-wrap: anywhere; }
.metric-breakdown { display: flex; flex-wrap: wrap; gap: 4px 12px; margin-top: 10px; font-size: .72rem; color: var(--er-text-muted); }
.metric-breakdown b { color: var(--er-text); padding-inline-start: 2px; }
.dashboard-columns { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
.dashboard-panel { min-width: 0; padding: 16px 18px; }
.dashboard-panel__head { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 14px; }
.dashboard-panel__head h2 { font: 680 1.05rem/1.25 var(--er-display-font); }
.chart { display: flex; gap: 10px; height: 108px; }
.chart-axis { display: flex; flex-direction: column; justify-content: space-between; color: var(--er-text-muted); font-size: .7rem; min-width: 18px; }
.chart-bars { flex: 1; display: grid; grid-template-columns: repeat(var(--days), minmax(0, 1fr)); gap: clamp(1px, .3vw, 4px); border-bottom: 1px solid var(--er-stroke); background: repeating-linear-gradient(to bottom, var(--er-stroke) 0 1px, transparent 1px 33.33%); }
.chart-bar-slot { display: flex; align-items: flex-end; justify-content: center; min-width: 0; }
.chart-bar { width: 100%; min-height: 1px; border-radius: 3px 3px 0 0; background: rgb(var(--v-theme-primary)); opacity: .85; }
.chart-value { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip-path: inset(50%); white-space: nowrap; }
.chart-bars--info .chart-bar { background: rgb(var(--v-theme-info)); }
.chart-dates { display: flex; justify-content: space-between; gap: 6px; padding-left: 28px; margin-top: 8px; color: var(--er-text-muted); font-size: .67rem; }
.recent-list { list-style: none; padding: 0; }
.recent-list li { display: flex; align-items: center; gap: 10px; min-height: 54px; border-top: 1px solid var(--er-stroke); padding: 8px 0; }
.recent-icon { display: grid; place-items: center; flex: 0 0 33px; height: 33px; border-radius: 9px; color: rgb(var(--v-theme-primary)); background: var(--er-bg-soft); }
.recent-text { min-width: 0; display: grid; gap: 3px; flex: 1; overflow-wrap: anywhere; font-size: .82rem; }
.recent-text span, .recent-target, .empty-list { color: var(--er-text-muted); font-size: .75rem; }
@media (max-width: 1180px) { .metric-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
@media (max-width: 760px) { .dashboard-columns { grid-template-columns: 1fr; } }
@media (max-width: 540px) { .metric-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 9px; } .metric { padding: 12px; } }
</style>
