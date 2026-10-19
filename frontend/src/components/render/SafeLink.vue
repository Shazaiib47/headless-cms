<script setup lang="ts">
import { computed } from 'vue'
import { isExternal, safeHref } from '@/utils/url'

const props = defineProps<{ url: string | null }>()

// An unsafe or malformed URL renders as plain text, never as a link.
const href = computed(() => safeHref(props.url))
const rel = computed(() =>
  href.value && isExternal(href.value) ? 'noopener noreferrer' : undefined,
)
</script>

<template>
  <a v-if="href" :href="href" :rel="rel"><slot /></a>
  <span v-else><slot /></span>
</template>
