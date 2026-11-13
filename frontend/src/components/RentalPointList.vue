<template>
  <section class="rental-points">
    <div class="points-heading"><h2>{{ t('map.rentalPoints') }}</h2><span>{{ points.length }}</span></div>
    <div class="point-rows">
      <button
        v-for="point in points.slice(0, visibleCount)"
        :key="point.id"
        :aria-pressed="point.id === selectedId"
        class="point-row"
        type="button"
        @click="$emit('select', point.id)"
      >
        <span class="point-row__icon"><v-icon size="20">{{ point.id === selectedId ? 'mdi-check' : 'mdi-map-marker-outline' }}</v-icon></span>
        <span class="point-row__copy"><strong>{{ point.name }}</strong><span>{{ [point.city, point.address].filter(Boolean).join(' · ') }}</span></span>
        <span :aria-label="`${point.availableCount} ${t('map.stats.available')} / ${point.vehicles.length} ${t('map.stats.vehicles')}`" class="point-row__count">{{ point.availableCount }}<small>/{{ point.vehicles.length }}</small></span>
      </button>
    </div>
    <v-btn
      v-if="visibleCount < points.length"
      block
      class="mt-3"
      variant="text"
      @click="visibleCount += 5"
    >{{ t('map.showMore') }}<v-icon end>mdi-chevron-down</v-icon></v-btn>
  </section>
</template>

<script setup>
  import { ref, watch } from 'vue'
  import { useLocale } from '@/stores/locale'

  const props = defineProps({
    points: { type: Array, required: true },
    selectedId: { type: [Number, String], default: null },
  })
  defineEmits(['select'])
  const { t } = useLocale()
  const visibleCount = ref(5)
  watch(() => props.points, () => {
    visibleCount.value = 5
  })
  watch(() => props.selectedId, id => {
    const index = props.points.findIndex(point => point.id === id)
    if (index >= visibleCount.value) visibleCount.value = index + 1
  })
</script>

<style scoped>
.points-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
.points-heading h2 { font-size: 1rem; }
.points-heading > span { color: var(--er-text-muted); font-size: .8rem; }
.point-rows { display: grid; gap: 8px; }
.point-row { display: flex; align-items: center; gap: 10px; width: 100%; min-height: 72px; padding: 12px; text-align: start; border: 1px solid var(--er-stroke); border-radius: 14px; background: var(--er-surface); color: var(--er-text); cursor: pointer; }
.point-row[aria-pressed='true'] { border-color: var(--er-primary-strong); background: var(--er-primary-soft); }
.point-row__icon { display: grid; place-items: center; flex: 0 0 32px; height: 36px; color: var(--er-primary-strong); }
.point-row__copy { display: grid; flex: 1; min-width: 0; gap: 4px; overflow-wrap: anywhere; }
.point-row__copy strong { font-size: .85rem; }
.point-row__copy > span { color: var(--er-text-muted); font-size: .7rem; line-height: 1.5; }
.point-row__count { flex-shrink: 0; font-size: .9rem; font-weight: 800; color: var(--er-primary-strong); }
.point-row__count small { color: var(--er-text-muted); font-weight: 500; }
</style>
