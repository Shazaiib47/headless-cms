<script setup lang="ts">
import type { PageStatus } from '@/types/api'

defineProps<{ status: PageStatus }>()
</script>

<template>
  <span
    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
    :class="status === 'published' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'"
  >
    {{ status === 'published' ? 'Published' : 'Draft' }}
  </span>
</template>
