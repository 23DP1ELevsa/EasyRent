<template>
  <span class="brand-logo">
    <span class="brand-logo__word">Easy<span>Rent</span></span>
    <span aria-hidden="true" class="brand-logo__line" />
  </span>
</template>

<style scoped>
.brand-logo { display: inline-flex; flex-direction: column; gap: 7px; line-height: 1; }
.brand-logo__word { color: var(--er-night-text); font-family: var(--er-display-font); font-size: 25px; font-weight: 900; letter-spacing: -.035em; text-transform: uppercase; }
.brand-logo__word > span { color: var(--er-primary); }
.brand-logo__line { height: 3px; border-radius: 999px; background: linear-gradient(90deg, var(--er-night-text) 50%, var(--er-primary)); }
</style>
