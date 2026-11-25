<template>
  <div v-if="row">
    <dl class="record-fields">
      <template v-for="field in fields" :key="field.key">
        <div>
          <dt>{{ label(field.label) }}</dt>
          <dd v-if="field.format === 'changes' && field.read(row)">
            <ul class="value-list">
              <li v-for="(value, key) in field.read(row)" :key="key">
                <strong>{{ label(changeLabels[key] || 'details') }}</strong> {{ changeValue(key, value) }}
              </li>
            </ul>
          </dd>
          <dd v-else-if="field.format === 'state'">
            <v-chip :color="stateColor(field.read(row))" size="small" variant="tonal">{{ format(field.read(row), field.format) }}</v-chip>
          </dd>
          <dd v-else>{{ format(field.read(row), field.format) }}</dd>
        </div>
      </template>
    </dl>

    <template v-if="section === 'users' && details">
      <div class="record-links">
        <v-btn v-for="name in related" :key="name" :to="{ path: sectionPath(name), query: { persona_id: row.persona_id } }" variant="tonal" size="small">
          {{ label(name) }} ({{ number(details[name]) }})
        </v-btn>
      </div>
      <h3>{{ label('restrictionHistory') }}</h3>
      <p v-if="!details.restrictions.data.length" class="text-medium-emphasis mb-3">{{ label('empty') }}</p>
      <div v-for="restriction in details.restrictions.data" :key="restriction.ierobezojums_id" class="restriction-item">
        <div class="d-flex align-center justify-space-between flex-wrap ga-2">
          <strong>{{ status(restriction.veids) }} · #{{ restriction.ierobezojums_id }}</strong>
          <v-chip size="small" :color="restriction.active ? 'warning' : undefined" variant="tonal">
            {{ status(restriction.active ? 'active' : restriction.atcelts_at ? 'revoked' : 'expired') }}
          </v-chip>
        </div>
        <p><strong v-if="restriction.iemesla_kods">{{ status(restriction.iemesla_kods) }} · </strong>{{ restriction.iemesls }}</p>
        <div class="text-caption">{{ date(restriction.sakuma_laiks) }} — {{ restriction.beigu_laiks ? date(restriction.beigu_laiks) : status('indefinite') }}</div>
        <div class="text-caption">{{ label('administratorId') }} #{{ restriction.administrators_id }}</div>
        <template v-if="restriction.atcelts_at">
          <div class="text-caption">{{ label('revokedAt') }} {{ date(restriction.atcelts_at) }} · #{{ restriction.atcela_persona_id }}</div>
          <div>{{ label('revokeReason') }} · {{ restriction.atcelsanas_iemesls }}</div>
        </template>
        <v-btn v-if="restriction.active" color="warning" variant="text" size="small" @click="$emit('revoke', restriction)">{{ label('revoke') }}</v-btn>
      </div>
      <v-pagination v-if="details.restrictions.last_page > 1" :model-value="details.restrictions.current_page" :length="details.restrictions.last_page"
        :total-visible="4" density="compact" @update:model-value="$emit('restriction-page', $event)" />
    </template>

    <template v-if="section === 'vehicles' && details">
      <v-btn v-if="row.sniedzejs?.persona?.persona_id" size="small" variant="tonal" :to="{ path: sectionPath('users'), query: { q: row.sniedzejs.persona.persona_id } }">
        {{ label('owner') }} #{{ row.sniedzejs.persona.persona_id }}
      </v-btn>
      <VehiclePhotos :key="row.transportlidzeklis_id" :vehicle-id="row.transportlidzeklis_id" admin />
    </template>

    <template v-if="section === 'reports' && details">
      <h3 class="mt-3">{{ label('target') }}</h3>
      <p v-if="!details.target" class="text-medium-emphasis">{{ status('unavailable') }}</p>
      <template v-else>
        <p v-if="row.objekta_veids === 'user'">{{ format(details.target, 'person') }} · {{ details.target.epasts || '—' }}</p>
        <p v-if="row.objekta_veids === 'vehicle'">{{ format(details.target, 'vehicle') }} · {{ format(details.target.sniedzejs?.persona, 'person') }}</p>
        <p v-if="row.objekta_veids === 'review'">{{ format(details.target.vertejums, 'rating') }} · {{ details.target.komentars || '—' }}</p>
        <v-btn class="mt-2" variant="tonal" size="small" :to="{ path: sectionPath(targetSection), query: { q: row.objekta_id } }">
          {{ label('openDetails') }} #{{ row.objekta_id }}
        </v-btn>
      </template>
    </template>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import VehiclePhotos from '@/components/VehiclePhotos.vue'
import { stateColor, useAdminFormat } from './format'
import { sections, sectionPath } from './sections'

const props = defineProps({
  section: { type: String, required: true },
  row: { type: Object, default: null },
  details: { type: Object, default: null },
})
defineEmits(['revoke', 'restriction-page'])
const { label, format, date, status, number } = useAdminFormat()
const related = ['bookings', 'vehicles', 'reviews', 'reports', 'audit']
const targetSection = computed(() => ({ user: 'users', vehicle: 'vehicles', review: 'reviews' })[props.row?.objekta_veids])
const changeLabels = { nosaukums: 'categoryName', tips: 'slug', veids: 'restrictionType', ierobezojums_id: 'id', iemesla_kods: 'reason',
  beigu_laiks: 'end', atcelts_at: 'revokedAt', moderation_hidden_at: 'visibility', deactivated_at: 'status', statuss: 'status' }

const fields = computed(() => {
  const record = sections[props.section]
  const result = [...record.columns, ...(record.details ?? [])]
  if (props.section === 'vehicles') {
    result.push(
      { key: 'adrese', label: 'address', read: row => row.adrese },
      { key: 'registracijas_numurs', label: 'plate', read: row => row.registracijas_numurs },
      { key: 'moderation_reason', label: 'reason', read: row => row.moderation_reason },
    )
  }
  return result
})

function changeValue (key, value) {
  if (['statuss', 'veids', 'iemesla_kods'].includes(key)) return value ? status(value) : '—'
  if (key === 'moderation_hidden_at') return value ? date(value) : status('visible')
  if (key === 'deactivated_at') return value ? date(value) : status('active')
  if (key === 'beigu_laiks') return value ? date(value) : status('indefinite')
  if (key === 'atcelts_at') return date(value)
  return format(value)
}
</script>

<style scoped>
.record-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 20px; margin: 0 0 16px; }
.record-fields > div { min-width: 0; padding-bottom: 9px; border-bottom: 1px solid var(--er-stroke); }
dt { color: var(--er-text-muted); font-size: .75rem; font-weight: 750; margin-bottom: 3px; }
dd { margin: 0; overflow-wrap: anywhere; white-space: pre-wrap; }
h3 { font: 700 1rem/1.3 var(--er-display-font); margin-bottom: 12px; }
.record-links { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px; }
.restriction-item { display: grid; gap: 4px; border: 1px solid var(--er-stroke); border-radius: 12px; padding: 12px; margin-bottom: 8px; overflow-wrap: anywhere; }
.restriction-item .v-btn { justify-self: start; }
.value-list { list-style: none; padding: 0; }
.value-list strong { display: block; color: var(--er-text-muted); font-size: .72rem; }
@media (max-width: 480px) { .record-fields { grid-template-columns: 1fr; gap: 8px; } }
</style>
