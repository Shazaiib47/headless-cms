<script setup lang="ts">
import SafeLink from '@/components/render/SafeLink.vue'
import type { HeroData } from '@/types/blocks'

// The hero image is not rendered yet: image URLs arrive with the media library.
defineProps<{ data: HeroData }>()
</script>

<template>
  <section class="rounded-2xl bg-slate-900 px-6 py-16 text-center text-white sm:px-12">
    <h2 class="text-3xl font-bold tracking-tight sm:text-5xl">{{ data.heading }}</h2>
    <p v-if="data.subheading" class="mx-auto mt-4 max-w-2xl text-lg text-slate-300">
      {{ data.subheading }}
    </p>
    <SafeLink
      v-if="data.button_label && data.button_url"
      :url="data.button_url"
      class="mt-8 inline-block rounded-md bg-white px-6 py-3 font-semibold text-slate-900 hover:bg-slate-100"
    >
      {{ data.button_label }}
    </SafeLink>
  </section>
</template>
