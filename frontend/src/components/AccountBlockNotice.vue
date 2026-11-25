<template>
  <v-alert v-if="block" type="error" variant="tonal" role="alert" class="my-4">
    <strong>{{ label('blocked') }}</strong>
    <p class="block-reason"><strong v-if="block.reason_code">{{ status(block.reason_code) }} · </strong>{{ block.reason }}</p>
    <p>{{ block.ends_at ? label('blockedUntil', { date: date(block.ends_at) }) : label('blockedForever') }}</p>
    <p class="mt-2">{{ label('supportDetail') }}</p>
    <v-btn class="mt-2" color="error" variant="outlined" :to="SUPPORT_ROUTE">{{ label('support') }}</v-btn>
  </v-alert>
</template>

<script setup>
import { useAdminFormat } from '@/admin/format'
import { SUPPORT_ROUTE } from '@/router/paths'
defineProps({ block: { type: Object, default: null } })
const { label, date, status } = useAdminFormat()
</script>

<style scoped>
.block-reason { white-space: pre-wrap; overflow-wrap: anywhere; margin-block: 4px; }
</style>
