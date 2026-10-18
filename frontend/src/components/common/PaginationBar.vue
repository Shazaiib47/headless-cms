<script setup lang="ts">
import type { PaginationMeta } from '@/types/api'

defineProps<{ meta: PaginationMeta }>()
defineEmits<{ change: [page: number] }>()
</script>

<template>
  <nav
    class="flex flex-wrap items-center justify-between gap-3 text-sm text-slate-600"
    aria-label="Pagination"
  >
    <p>
      <template v-if="meta.total > 0">
        Showing {{ meta.from }} to {{ meta.to }} of {{ meta.total }}
      </template>
      <template v-else>No results</template>
    </p>

    <div v-if="meta.last_page > 1" class="flex items-center gap-2">
      <button
        type="button"
        :disabled="meta.current_page <= 1"
        class="rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50"
        @click="$emit('change', meta.current_page - 1)"
      >
        Previous
      </button>
      <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
      <button
        type="button"
        :disabled="meta.current_page >= meta.last_page"
        class="rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50"
        @click="$emit('change', meta.current_page + 1)"
      >
        Next
      </button>
    </div>
  </nav>
</template>
