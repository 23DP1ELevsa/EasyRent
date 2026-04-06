<template>
  <AuthPage @auth-success="handleAuthSuccess" />
</template>

<script setup>
import { useRouter } from 'vue-router'
import AuthPage from '@/components/AuthPage.vue'
import { HOME_ROUTE } from '@/router/paths'

const router = useRouter()

function handleAuthSuccess() {
  const userData = localStorage.getItem('user')

  if (userData) {
    window.dispatchEvent(new CustomEvent('user-updated', { detail: JSON.parse(userData) }))
  }

  router.push(HOME_ROUTE)
  window.scrollTo({ top: 0, behavior: 'smooth' })
}
</script>