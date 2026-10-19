<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { ApiError } from '@/api/http'
import { pagesApi } from '@/api/pages'
import { toRenderBlocks } from '@/blocks/render'
import StatusBadge from '@/components/common/StatusBadge.vue'
import BlockRenderer from '@/components/render/BlockRenderer.vue'
import type { PreviewPage } from '@/types/render'

const route = useRoute()

const pageId = computed(() => {
  const raw = Array.isArray(route.params.id) ? route.params.id[0] : route.params.id
  return Number(raw)
})

const page = ref<PreviewPage | null>(null)
const loading = ref(true)
const error = ref('')
let requestId = 0

const blocks = computed(() => (page.value ? toRenderBlocks(page.value.content) : []))

function errorText(caught: unknown): string {
  if (caught instanceof ApiError) {
    if (caught.status === 404) return 'This page does not exist.'
    if (caught.status === 403) return 'You do not have permission to preview this page.'
    return caught.message
  }
  return 'Something went wrong. Please try again.'
}

async function load(): Promise<void> {
  const id = ++requestId
  loading.value = true
  error.value = ''

  try {
    const { data } = await pagesApi.preview(pageId.value)
    if (id !== requestId) return // a newer request superseded this one

    page.value = data
  } catch (caught) {
    if (id !== requestId) return

    page.value = null
    error.value = errorText(caught)
  } finally {
    if (id === requestId) loading.value = false
  }
}

watch(pageId, () => void load(), { immediate: true })

onBeforeUnmount(() => {
  requestId++ // ignore any response still in flight
})
</script>

<template>
  <section>
    <div class="flex flex-wrap items-center justify-between gap-3">
      <RouterLink
        :to="{ name: 'admin-page-edit', params: { id: pageId } }"
        data-test="back"
        class="text-sm text-slate-600 hover:underline"
      >
        ← Back to editor
      </RouterLink>
      <button
        type="button"
        data-test="reload"
        :disabled="loading"
        class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50"
        @click="load"
      >
        Reload preview
      </button>
    </div>

    <p v-if="loading && !page" aria-busy="true" class="mt-6 text-sm text-slate-600">
      Loading preview…
    </p>

    <div
      v-else-if="error"
      role="alert"
      class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
    >
      {{ error }}
    </div>

    <template v-else-if="page">
      <div
        role="status"
        class="mt-4 rounded-lg border px-4 py-3 text-sm"
        :class="
          page.status === 'published'
            ? 'border-green-200 bg-green-50 text-green-900'
            : 'border-amber-200 bg-amber-50 text-amber-900'
        "
      >
        <p class="font-medium">Preview: only signed-in users can see this.</p>
        <p>
          It shows the last saved version.
          <template v-if="page.status === 'published'">This page is live.</template>
          <template v-else>This page is a draft and is not public.</template>
        </p>
      </div>

      <div class="mt-6 flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-semibold">{{ page.title }}</h1>
        <StatusBadge :status="page.status" />
      </div>
      <p class="font-mono text-xs text-slate-500">/{{ page.slug }}</p>

      <article class="mx-auto mt-8 max-w-4xl">
        <BlockRenderer :blocks="blocks" />
      </article>
    </template>
  </section>
</template>
