<script setup lang="ts">
import type { Role } from '@/types/api'

defineProps<{ role: Role }>()

const styles: Record<Role, string> = {
  admin: 'bg-purple-100 text-purple-800',
  editor: 'bg-blue-100 text-blue-800',
  viewer: 'bg-slate-100 text-slate-700',
}
</script>

<template>
  <span
    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize"
    :class="styles[role]"
  >
    {{ role }}
  </span>
</template>
