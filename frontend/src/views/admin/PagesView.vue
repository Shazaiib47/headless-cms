<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError } from '@/api/http'
import { pagesApi } from '@/api/pages'
import PaginationBar from '@/components/common/PaginationBar.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { useAuthStore } from '@/stores/auth'
import type { PageStatus, PageSummary, PaginationMeta } from '@/types/api'

interface Filters {
  search: string
  status: PageStatus | ''
  sort: string
  page: number
}

const PER_PAGE = 10
const DEFAULT_SORT = '-updated_at'
const SORT_OPTIONS = [
  { value: '-updated_at', label: 'Recently updated' },
  { value: 'updated_at', label: 'Least recently updated' },
  { value: 'title', label: 'Title A to Z' },
  { value: '-title', label: 'Title Z to A' },
  { value: '-created_at', label: 'Newest created' },
]

// Query-string values are user-editable, so every one is validated.
function firstString(value: unknown): string {
  const first = Array.isArray(value) ? value[0] : value
  return typeof first === 'string' ? first : ''
}

function parseStatus(value: unknown): PageStatus | '' {
  const status = firstString(value)
  return status === 'draft' || status === 'published' ? status : ''
}

function parseSort(value: unknown): string {
  const sort = firstString(value)
  return SORT_OPTIONS.some((option) => option.value === sort) ? sort : DEFAULT_SORT
}

function parsePage(value: unknown): number {
  const page = Number.parseInt(firstString(value), 10)
  return Number.isFinite(page) && page >= 1 ? page : 1
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString(undefined, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}

function errorText(error: unknown): string {
  if (error instanceof ApiError) {
    return error.status === 403 ? 'You do not have permission to do that.' : error.message
  }
  return 'Something went wrong. Please try again.'
}

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const filters = computed<Filters>(() => ({
  search: firstString(route.query.search).trim().slice(0, 100),
  status: parseStatus(route.query.status),
  sort: parseSort(route.query.sort),
  page: parsePage(route.query.page),
}))
const hasFilters = computed(() => filters.value.search !== '' || filters.value.status !== '')

const searchInput = ref(filters.value.search)
const pages = ref<PageSummary[]>([])
const meta = ref<PaginationMeta | null>(null)
const loading = ref(true)
const error = ref('')
const notice = ref('')
const actionError = ref('')
const deletingId = ref<number | null>(null)

let requestId = 0
let searchTimer: ReturnType<typeof setTimeout> | undefined

const statusMessage = computed(() => {
  if (loading.value) return 'Loading pages'
  if (error.value || !meta.value) return ''
  return `Showing ${pages.value.length} of ${meta.value.total} pages`
})

async function load(): Promise<void> {
  const id = ++requestId
  loading.value = true
  error.value = ''

  try {
    const result = await pagesApi.list({ ...filters.value, per_page: PER_PAGE })
    if (id !== requestId) return // a newer request superseded this one

    // The requested page no longer exists (e.g. its last row was deleted).
    if (
      result.data.length === 0 &&
      result.meta.last_page > 0 &&
      result.meta.current_page > result.meta.last_page
    ) {
      await updateQuery({ page: result.meta.last_page }, true)
      return
    }

    pages.value = result.data
    meta.value = result.meta
  } catch (caught) {
    if (id !== requestId) return
    error.value = errorText(caught)
  } finally {
    if (id === requestId) loading.value = false
  }
}

function updateQuery(changes: Partial<Filters>, replace = false) {
  const next = { ...filters.value, ...changes }
  const query: Record<string, string> = {}

  if (next.search) query.search = next.search
  if (next.status) query.status = next.status
  if (next.sort !== DEFAULT_SORT) query.sort = next.sort
  if (next.page > 1) query.page = String(next.page)

  return replace ? router.replace({ query }) : router.push({ query })
}

function onStatusChange(event: Event) {
  const status = parseStatus((event.target as HTMLSelectElement).value)
  void updateQuery({ status, page: 1 })
}

function onSortChange(event: Event) {
  const sort = parseSort((event.target as HTMLSelectElement).value)
  void updateQuery({ sort, page: 1 })
}

function changePage(page: number) {
  void updateQuery({ page })
}

function clearFilters() {
  searchInput.value = ''
  void updateQuery({ search: '', status: '', page: 1 })
}

async function remove(page: PageSummary): Promise<void> {
  if (!window.confirm(`Delete "${page.title}"? This cannot be undone.`)) return

  deletingId.value = page.id
  actionError.value = ''
  notice.value = ''

  try {
    await pagesApi.remove(page.id)
    notice.value = `Deleted "${page.title}".`
    await load()
  } catch (caught) {
    actionError.value = errorText(caught)
  } finally {
    deletingId.value = null
  }
}

// Reload whenever the effective filters change (not on unrelated route changes).
watch(
  () => JSON.stringify(filters.value),
  () => {
    if (route.name === 'admin-pages') void load()
  },
  { immediate: true },
)

// Debounce typing in the search box before it reaches the URL.
watch(searchInput, (value) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    const trimmed = value.trim()
    if (trimmed !== filters.value.search) void updateQuery({ search: trimmed, page: 1 }, true)
  }, 300)
})

// Keep the box in sync when the URL changes via back/forward.
watch(
  () => filters.value.search,
  (value) => {
    if (value !== searchInput.value.trim()) searchInput.value = value
  },
)

onBeforeUnmount(() => {
  clearTimeout(searchTimer)
  requestId++ // ignore any response still in flight
})
</script>

<template>
  <section>
    <h1 class="text-2xl font-semibold">Pages</h1>
    <p v-if="meta && !error" class="mt-1 text-sm text-slate-600">
      {{ meta.total }} {{ meta.total === 1 ? 'page' : 'pages' }}
    </p>

    <form class="mt-4 grid gap-3 sm:grid-cols-[1fr_auto_auto]" role="search" @submit.prevent>
      <div>
        <label for="page-search" class="sr-only">Search pages</label>
        <input
          id="page-search"
          v-model="searchInput"
          type="search"
          maxlength="100"
          autocomplete="off"
          placeholder="Search by title or slug"
          class="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-300"
        />
      </div>
      <div>
        <label for="page-status" class="sr-only">Filter by status</label>
        <select
          id="page-status"
          :value="filters.status"
          class="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-300"
          @change="onStatusChange"
        >
          <option value="">All statuses</option>
          <option value="draft">Draft</option>
          <option value="published">Published</option>
        </select>
      </div>
      <div>
        <label for="page-sort" class="sr-only">Sort pages</label>
        <select
          id="page-sort"
          :value="filters.sort"
          class="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-300"
          @change="onSortChange"
        >
          <option v-for="option in SORT_OPTIONS" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
      </div>
    </form>

    <p class="sr-only" aria-live="polite">{{ statusMessage }}</p>

    <p
      v-if="notice"
      role="status"
      class="mt-4 rounded-md bg-green-50 px-3 py-2 text-sm text-green-800"
    >
      {{ notice }}
    </p>
    <p
      v-if="actionError"
      role="alert"
      class="mt-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700"
    >
      {{ actionError }}
    </p>

    <div
      v-if="error"
      role="alert"
      class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
    >
      <span>{{ error }}</span>
      <button
        type="button"
        data-test="retry"
        class="rounded-md border border-red-300 bg-white px-3 py-1.5 font-medium text-red-800 hover:bg-red-100"
        @click="load"
      >
        Try again
      </button>
    </div>

    <ul
      v-else-if="loading && !meta"
      aria-busy="true"
      aria-label="Loading pages"
      class="mt-4 divide-y divide-slate-200 overflow-hidden rounded-lg border border-slate-200 bg-white"
    >
      <li v-for="n in 5" :key="n" class="animate-pulse space-y-2 px-4 py-4">
        <div class="h-4 w-1/3 rounded bg-slate-200"></div>
        <div class="h-3 w-1/5 rounded bg-slate-100"></div>
      </li>
    </ul>

    <div
      v-else-if="pages.length === 0"
      class="mt-4 rounded-lg border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-600"
    >
      <template v-if="hasFilters">
        <p>No pages match your filters.</p>
        <button
          type="button"
          data-test="clear-filters"
          class="mt-3 rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 hover:bg-slate-100"
          @click="clearFilters"
        >
          Clear filters
        </button>
      </template>
      <p v-else>No pages yet.</p>
    </div>

    <ul
      v-else
      :aria-busy="loading"
      class="mt-4 divide-y divide-slate-200 overflow-hidden rounded-lg border border-slate-200 bg-white"
      :class="{ 'opacity-60': loading }"
    >
      <li
        v-for="page in pages"
        :key="page.id"
        class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
      >
        <div class="min-w-0">
          <p class="truncate font-medium text-slate-900">{{ page.title }}</p>
          <p class="truncate font-mono text-xs text-slate-500">/{{ page.slug }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-3 text-sm">
          <StatusBadge :status="page.status" />
          <span class="text-slate-500">Updated {{ formatDate(page.updated_at) }}</span>
          <button
            v-if="auth.isAdmin"
            type="button"
            :aria-label="`Delete ${page.title}`"
            :disabled="deletingId === page.id"
            class="rounded-md border border-red-200 px-3 py-1 font-medium text-red-700 hover:bg-red-50 disabled:opacity-60"
            @click="remove(page)"
          >
            {{ deletingId === page.id ? 'Deleting…' : 'Delete' }}
          </button>
        </div>
      </li>
    </ul>

    <PaginationBar
      v-if="meta && !error && pages.length > 0"
      :meta="meta"
      class="mt-4"
      @change="changePage"
    />
  </section>
</template>
