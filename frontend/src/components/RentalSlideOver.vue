<template>
  <v-dialog
    :aria-label="title"
    class="rental-slide-over"
    :fullscreen="isMobile"
    :model-value="modelValue"
    scrollable
    transition="rental-drawer"
    :width="width"
    @after-leave="restoreFocus"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <v-card class="slide-over-card">
      <header class="slide-over-header">
        <h2>{{ title }}</h2>
        <v-btn :aria-label="t('map.closePanel')" icon="mdi-close" variant="text" @click="$emit('update:modelValue', false)" />
      </header>
      <div class="slide-over-body"><slot /></div>
      <footer v-if="$slots.footer" class="slide-over-footer"><slot name="footer" /></footer>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { watch } from 'vue'
  import { useLocale } from '@/stores/locale'

  const props = defineProps({
    modelValue: Boolean,
    title: { type: String, required: true },
    isMobile: Boolean,
    width: { type: [String, Number], default: 420 },
    returnFocus: { type: String, default: '' },
  })
  defineEmits(['update:modelValue'])
  const { t } = useLocale()
  let previousFocus
  watch(() => props.modelValue, open => {
    if (open) previousFocus = document.activeElement
  })
  function restoreFocus () {
    const target = props.returnFocus ? document.querySelector(props.returnFocus) : previousFocus
    target?.focus({ preventScroll: true })
  }
</script>

<style scoped>
.rental-slide-over :deep(.v-overlay__content) { position: absolute; inset: 0 0 0 auto; margin: 0 !important; max-height: 100% !important; max-width: 100% !important; height: 100%; }
.rental-slide-over :deep(.rental-drawer-enter-active), .rental-slide-over :deep(.rental-drawer-leave-active) { transition: transform .25s ease; }
.rental-slide-over :deep(.rental-drawer-enter-from), .rental-slide-over :deep(.rental-drawer-leave-to) { transform: translateX(100%); }
.rental-slide-over :deep(.slide-over-card) { display: flex; flex-direction: column; height: 100%; max-height: 100%; border-radius: 24px 0 0 24px !important; overflow: hidden !important; }
.slide-over-header { display: flex; align-items: center; justify-content: space-between; flex: 0 0 auto; gap: 12px; padding: 18px 20px; border-bottom: 1px solid var(--er-stroke); }
.slide-over-header h2 { font-size: 1.25rem; }
.slide-over-body { min-height: 0; flex: 1 1 auto; overflow-y: auto; overscroll-behavior: contain; padding: 24px 20px; scroll-padding-block: 24px; }
.slide-over-footer { display: flex; flex-shrink: 0; gap: 12px; padding: 16px 20px calc(16px + env(safe-area-inset-bottom)); border-top: 1px solid var(--er-stroke); background: var(--er-surface); }
.slide-over-footer :deep(.v-btn) { min-height: 46px; min-width: 0; }
.slide-over-footer :deep(.v-btn:last-child) { flex: 1; }
.slide-over-footer :deep(.v-btn__content) { white-space: normal; }
@media (max-width: 767px) {
  .rental-slide-over :deep(.slide-over-card) { border-radius: 0 !important; }
  .slide-over-header { padding-top: max(12px, env(safe-area-inset-top)); }
  .slide-over-body { padding-inline: max(16px, env(safe-area-inset-left)) max(16px, env(safe-area-inset-right)); }
}
@media (prefers-reduced-motion: reduce) { .rental-slide-over :deep(*) { transition-duration: .01ms !important; animation-duration: .01ms !important; } }
</style>
