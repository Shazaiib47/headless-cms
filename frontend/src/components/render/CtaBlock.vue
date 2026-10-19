<script setup lang="ts">
import { computed } from 'vue'
import SafeLink from '@/components/render/SafeLink.vue'
import type { CtaData } from '@/types/blocks'

const props = defineProps<{ data: CtaData }>()

const buttonClass = computed(() =>
  props.data.style === 'secondary'
    ? 'border border-slate-900 text-slate-900 hover:bg-slate-100'
    : 'bg-slate-900 text-white hover:bg-slate-700',
)
</script>

<template>
  <section class="rounded-2xl border border-slate-200 bg-slate-50 px-6 py-10 text-center">
    <h2 class="text-2xl font-bold text-slate-900">{{ data.heading }}</h2>
    <p v-if="data.text" class="mx-auto mt-3 max-w-xl text-slate-600">{{ data.text }}</p>
    <SafeLink
      :url="data.button_url"
      class="mt-6 inline-block rounded-md px-6 py-3 font-semibold"
      :class="buttonClass"
    >
      {{ data.button_label }}
    </SafeLink>
  </section>
</template>
