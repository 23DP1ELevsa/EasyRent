<template>
  <v-dialog
    :aria-label="label"
    class="loading-screen-dialog"
    fullscreen
    :model-value="active"
    no-click-animation
    persistent
    :scrim="false"
    transition="fade-transition"
    :z-index="3000"
  >
    <div
      aria-atomic="true"
      aria-live="polite"
      class="loading-screen"
      role="status"
    >
      <div class="loading-screen__card">
        <div aria-hidden="true" class="loading-screen__brand">Easy<span>Rent</span></div>
        <div aria-hidden="true" class="loading-screen__animation">
          <div class="loading-screen__orbit" />
          <div class="loading-screen__symbol"><v-icon size="28">mdi-timer-sand</v-icon></div>
        </div>
        <div class="loading-screen__label">{{ label }}</div>
        <div aria-hidden="true" class="loading-screen__dots"><span /><span /><span /></div>
      </div>
    </div>
  </v-dialog>
</template>

<script setup>
  defineProps({
    active: Boolean,
    label: { type: String, required: true },
  })
</script>

<style scoped>
.loading-screen-dialog :deep(.v-overlay__content) { width: 100%; height: 100dvh; max-width: 100%; max-height: 100dvh; margin: 0; border-radius: 0; overflow: hidden; }
.loading-screen { position: absolute; inset: 0; display: grid; place-items: center; padding: 20px; background: color-mix(in srgb, var(--er-bg) 16%, transparent); backdrop-filter: blur(2px); -webkit-backdrop-filter: blur(2px); }
.loading-screen__card { display: flex; flex-direction: column; align-items: center; width: min(280px, 100%); padding: 24px; border: 1px solid color-mix(in srgb, var(--er-stroke) 45%, transparent); border-radius: var(--er-radius-lg); background: color-mix(in srgb, var(--er-surface) 38%, transparent); color: var(--er-text); box-shadow: var(--er-shadow-md); text-align: center; }
.loading-screen__brand { font-family: var(--er-display-font); font-size: 24px; font-weight: 900; letter-spacing: -.04em; text-transform: uppercase; }
.loading-screen__brand span { color: var(--er-primary-strong); }
.loading-screen__animation { position: relative; display: grid; place-items: center; width: 90px; height: 90px; margin: 26px 0 22px; }
.loading-screen__orbit { position: absolute; inset: 0; border: 2px solid var(--er-primary-soft); border-top-color: var(--er-primary); border-right-color: var(--er-primary); border-radius: 50%; animation: loading-orbit 1.5s linear infinite; }
.loading-screen__orbit::after { position: absolute; top: 8px; right: 8px; width: 8px; height: 8px; border: 2px solid var(--er-surface); border-radius: 50%; background: var(--er-primary); content: ''; }
.loading-screen__symbol { display: grid; place-items: center; width: 62px; height: 62px; border-radius: 20px; background: color-mix(in srgb, var(--er-primary-soft) 65%, transparent); color: var(--er-primary-strong); animation: loading-breathe 2s ease-in-out infinite; }
.loading-screen__label { font-family: var(--er-display-font); font-size: 1.1rem; font-weight: 700; line-height: 1.4; }
.loading-screen__dots { display: flex; gap: 6px; margin-top: 22px; }
.loading-screen__dots span { width: 5px; height: 5px; border-radius: 50%; background: var(--er-primary); animation: loading-dot 1.2s ease-in-out infinite; }
.loading-screen__dots span:nth-child(2) { animation-delay: .15s; }
.loading-screen__dots span:nth-child(3) { animation-delay: .3s; }
@keyframes loading-orbit { to { transform: rotate(360deg); } }
@keyframes loading-breathe { 50% { transform: translateY(-3px); } }
@keyframes loading-dot { 0%, 80%, 100% { opacity: .3; } 40% { opacity: 1; } }
@media (prefers-reduced-motion: reduce) {
  .loading-screen *, .loading-screen *::after { animation: none; }
}
@media (max-height: 500px) {
  .loading-screen__card { padding: 16px; }
  .loading-screen__animation { width: 64px; height: 64px; margin: 12px 0; }
  .loading-screen__symbol { width: 44px; height: 44px; border-radius: 14px; }
  .loading-screen__dots { margin-top: 12px; }
}
</style>
