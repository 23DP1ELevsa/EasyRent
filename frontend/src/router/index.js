/**
 * router/index.ts
 *
 * Automatic routes for `./src/pages/*.vue`
 */

// Composables
import { createRouter, createWebHistory } from 'vue-router'
import { routes } from 'vue-router/auto-routes'
import { googlePending, syncCurrentUser } from '@/services/auth'
import { startLoading } from '@/stores/loading'
import { accountHome, AUTH_ROUTE, FORGOT_PASSWORD_ROUTE, PROFILE_ROUTE, RESET_PASSWORD_ROUTE } from './paths'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

const navigationTasks = new WeakMap()
router.beforeEach(async to => {
  if (to.path === FORGOT_PASSWORD_ROUTE || to.path === RESET_PASSWORD_ROUTE) {
    return
  }

  const googleCallback = ['ok', 'role', 'link'].includes(to.query.google)
  const persona = await syncCurrentUser({ useBearer: !googleCallback })
  const pending = googlePending.value

  if (pending?.purpose === 'role' || persona?.profile_completed === false) {
    if (to.path !== PROFILE_ROUTE || to.query.google) {
      return { path: PROFILE_ROUTE, replace: true }
    }
    return
  }

  if (to.path === PROFILE_ROUTE && to.query.google === 'ok' && persona) {
    return { path: accountHome(persona), replace: true }
  }
  if (to.path === PROFILE_ROUTE && !persona) {
    return { path: AUTH_ROUTE, query: googleCallback ? { google: 'invalid' } : {}, replace: true }
  }
})

router.beforeEach(to => {
  navigationTasks.set(to, startLoading())
})
router.afterEach(to => {
  navigationTasks.get(to)?.()
  navigationTasks.delete(to)
})

// Workaround for https://github.com/vitejs/vite/issues/11804
router.onError((err, to) => {
  navigationTasks.get(to)?.()
  navigationTasks.delete(to)
  if (err?.message?.includes?.('Failed to fetch dynamically imported module')) {
    if (localStorage.getItem('vuetify:dynamic-reload')) {
      console.error('Dynamic import error, reloading page did not fix it', err)
    } else {
      console.log('Reloading page to fix dynamic import error')
      localStorage.setItem('vuetify:dynamic-reload', 'true')
      location.assign(to.fullPath)
    }
  } else {
    console.error(err)
  }
})

router.isReady().then(() => {
  localStorage.removeItem('vuetify:dynamic-reload')
})

export default router
