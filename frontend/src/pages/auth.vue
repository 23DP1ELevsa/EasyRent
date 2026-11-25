<template>
  <AuthPage @auth-success="handleAuthSuccess" />
</template>

<script setup>
  import { useRouter } from 'vue-router'
  import AuthPage from '@/components/AuthPage.vue'
  import { accountHome, HOME_ROUTE, PROFILE_ROUTE } from '@/router/paths'
  import { getStoredUser } from '@/services/auth'

  const router = useRouter()

  function handleAuthSuccess ({ google = false } = {}) {
    const userData = getStoredUser()
    if (!userData) return
    const home = google || userData.loma === 'administrators' ? accountHome(userData) : HOME_ROUTE
    const destination = userData?.profile_completed === false ? PROFILE_ROUTE : home
    router.replace(destination)
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
</script>
