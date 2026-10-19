<script setup lang="ts">
import type { ImageRenderData } from '@/types/render'

defineProps<{ data: ImageRenderData }>()
</script>

<template>
  <figure>
    <img
      v-if="data.image"
      :src="data.image.url"
      :alt="data.alt"
      :width="data.image.width ?? undefined"
      :height="data.image.height ?? undefined"
      loading="lazy"
      decoding="async"
      class="h-auto w-full rounded-lg bg-slate-100"
    />
    <div
      v-else
      role="img"
      :aria-label="data.alt"
      class="flex aspect-video w-full items-center justify-center rounded-lg bg-slate-200 text-sm text-slate-500"
    >
      <span aria-hidden="true">Image not available</span>
    </div>
    <figcaption v-if="data.caption" class="mt-2 text-center text-sm text-slate-600">
      {{ data.caption }}
    </figcaption>
  </figure>
</template>
