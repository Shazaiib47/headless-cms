<script setup lang="ts">
import SafeLink from '@/components/render/SafeLink.vue'
import type { CardGridRenderData } from '@/types/render'

defineProps<{ data: CardGridRenderData }>()
</script>

<template>
  <section>
    <h2 v-if="data.heading" class="text-2xl font-bold text-slate-900">{{ data.heading }}</h2>
    <ul class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <li
        v-for="(card, index) in data.cards"
        :key="index"
        class="flex flex-col rounded-lg border border-slate-200 bg-white p-5"
      >
        <img
          v-if="card.image"
          :src="card.image.url"
          :alt="card.image.alt ?? ''"
          loading="lazy"
          decoding="async"
          class="mb-3 aspect-video w-full rounded-md bg-slate-100 object-cover"
        />
        <h3 class="text-lg font-semibold text-slate-900">{{ card.title }}</h3>
        <p v-if="card.text" class="mt-2 text-slate-600">{{ card.text }}</p>
        <SafeLink
          v-if="card.link_label && card.link_url"
          :url="card.link_url"
          class="mt-4 text-sm font-medium text-blue-700 underline"
        >
          {{ card.link_label }}
        </SafeLink>
      </li>
    </ul>
  </section>
</template>
