<template>
  <AuthPage @auth-success="handleAuthSuccess" />
</template>

<script setup>
import { useRouter } from 'vue-router'
import AuthPage from '@/components/AuthPage.vue'
import { HOME_ROUTE } from '@/router/paths'
import { getStoredUser } from '@/services/auth'

const router = useRouter()

function handleAuthSuccess() {
  const userData = getStoredUser()

  if (userData) {
    window.dispatchEvent(new CustomEvent('user-updated', { detail: userData }))
  }

  router.push(HOME_ROUTE)
  window.scrollTo({ top: 0, behavior: 'smooth' })
}
</script>