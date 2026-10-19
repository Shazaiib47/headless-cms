<script setup lang="ts">
import type { ImageBlockData } from '@/types/blocks'

// A placeholder until image URLs arrive with the media library.
defineProps<{ data: ImageBlockData }>()
</script>

<template>
  <figure>
    <div
      role="img"
      :aria-label="data.alt"
      class="flex aspect-video w-full items-center justify-center rounded-lg bg-slate-200 text-sm text-slate-500"
    >
      <span aria-hidden="true">Image not available yet</span>
    </div>
    <figcaption v-if="data.caption" class="mt-2 text-center text-sm text-slate-600">
      {{ data.caption }}
    </figcaption>
  </figure>
</template>
