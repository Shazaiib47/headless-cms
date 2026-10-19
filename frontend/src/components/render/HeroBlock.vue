<script setup lang="ts">
import SafeLink from '@/components/render/SafeLink.vue'
import type { HeroRenderData } from '@/types/render'

defineProps<{ data: HeroRenderData }>()
</script>

<template>
  <section
    class="relative overflow-hidden rounded-2xl bg-slate-900 px-6 py-16 text-center text-white sm:px-12"
  >
    <!-- Decorative: the heading carries the meaning. -->
    <img
      v-if="data.image"
      :src="data.image.url"
      alt=""
      loading="lazy"
      decoding="async"
      class="absolute inset-0 h-full w-full object-cover opacity-40"
    />
    <div class="relative">
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
    </div>
  </section>
</template>
