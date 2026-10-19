<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError, type ValidationErrors } from '@/api/http'
import { mediaApi } from '@/api/media'
import FormField from '@/components/common/FormField.vue'
import PaginationBar from '@/components/common/PaginationBar.vue'
import { buttonClass, inputClass } from '@/components/common/formClasses'
import MediaUploader from '@/components/media/MediaUploader.vue'
import { useAuthStore } from '@/stores/auth'
import type { MediaItem, PaginationMeta } from '@/types/api'
import { formatBytes } from '@/utils/format'

interface Filters {
  search: string
  page: number
}

const PER_PAGE = 24

// Query-string values are user-editable, so every one is validated.
function firstString(value: unknown): string {
  const first = Array.isArray(value) ? value[0] : value
  return typeof first === 'string' ? first : ''
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
    return error.status === 403 ? 'You do not have permission to do that.' : error.firstError()
  }
  return 'Something went wrong. Please try again.'
}

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const filters = computed<Filters>(() => ({
  search: firstString(route.query.search).trim().slice(0, 100),
  page: parsePage(route.query.page),
}))
const hasFilters = computed(() => filters.value.search !== '')
const readonly = computed(() => !auth.canEdit)

const searchInput = ref(filters.value.search)
const images = ref<MediaItem[]>([])
const meta = ref<PaginationMeta | null>(null)
const loading = ref(true)
const error = ref('')
const notice = ref('')

const selected = ref<MediaItem | null>(null)
const altDraft = ref('')
const saving = ref(false)
const deleting = ref(false)
const saved = ref(false)
const panelError = ref('')
const altErrors = ref<ValidationErrors>({})
const panelRef = ref<HTMLElement | null>(null)

const altChanged = computed(
  () => selected.value !== null && altDraft.value.trim() !== (selected.value.alt_text ?? ''),
)

let requestId = 0
let searchTimer: ReturnType<typeof setTimeout> | undefined

const statusMessage = computed(() => {
  if (loading.value) return 'Loading images'
  if (error.value || !meta.value) return ''
  return `Showing ${images.value.length} of ${meta.value.total} images`
})

async function load(): Promise<void> {
  const id = ++requestId
  loading.value = true
  error.value = ''

  try {
    const result = await mediaApi.list({ ...filters.value, per_page: PER_PAGE })
    if (id !== requestId) return // a newer request superseded this one

    // The requested page no longer exists (e.g. its last image was deleted).
    if (
      result.data.length === 0 &&
      result.meta.last_page > 0 &&
      result.meta.current_page > result.meta.last_page
    ) {
      await updateQuery({ page: result.meta.last_page }, true)
      return
    }

    images.value = result.data
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
  if (next.page > 1) query.page = String(next.page)

  return replace ? router.replace({ query }) : router.push({ query })
}

function changePage(page: number) {
  void updateQuery({ page })
}

function clearFilters() {
  searchInput.value = ''
  void updateQuery({ search: '', page: 1 })
}

/** New uploads appear first, so go back to page 1 to show them. */
function onUploadsFinished() {
  if (filters.value.page !== 1) void updateQuery({ page: 1 })
  else void load()
}

async function select(item: MediaItem): Promise<void> {
  selected.value = item
  altDraft.value = item.alt_text ?? ''
  saved.value = false
  panelError.value = ''
  altErrors.value = {}

  await nextTick()

  const panel = panelRef.value
  panel?.focus()
  if (panel && typeof panel.scrollIntoView === 'function') {
    panel.scrollIntoView({ block: 'nearest' })
  }
}

async function closePanel(): Promise<void> {
  const id = selected.value?.id
  selected.value = null

  await nextTick()
  if (id !== undefined) document.getElementById(`media-card-${id}`)?.focus()
}

async function saveAlt(): Promise<void> {
  const item = selected.value
  if (!item || saving.value || readonly.value) return

  saving.value = true
  saved.value = false
  panelError.value = ''
  altErrors.value = {}

  try {
    const { data } = await mediaApi.update(item.id, altDraft.value.trim() || null)

    selected.value = data
    altDraft.value = data.alt_text ?? ''
    images.value = images.value.map((entry) => (entry.id === data.id ? data : entry))
    saved.value = true
  } catch (caught) {
    if (caught instanceof ApiError && caught.status === 422 && Object.keys(caught.errors).length) {
      altErrors.value = caught.errors
    } else {
      panelError.value = errorText(caught)
    }
  } finally {
    saving.value = false
  }
}

async function removeSelected(): Promise<void> {
  const item = selected.value
  if (!item || deleting.value || !auth.isAdmin) return
  if (!window.confirm(`Delete "${item.original_name}"? This cannot be undone.`)) return

  deleting.value = true
  panelError.value = ''
  notice.value = ''

  try {
    await mediaApi.remove(item.id)

    selected.value = null
    notice.value = `Deleted "${item.original_name}".`
    await load()
  } catch (caught) {
    // Includes the server's "this image is used by …" explanation.
    panelError.value = errorText(caught)
  } finally {
    deleting.value = false
  }
}

// Reload whenever the effective filters change (not on unrelated route changes).
watch(
  () => JSON.stringify(filters.value),
  () => {
    if (route.name === 'admin-media') void load()
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
    <h1 class="text-2xl font-semibold">Media</h1>
    <p v-if="meta && !error" class="mt-1 text-sm text-slate-600">
      {{ meta.total }} {{ meta.total === 1 ? 'image' : 'images' }}
    </p>

    <MediaUploader
      v-if="auth.canEdit"
      data-test="uploader"
      class="mt-4"
      @finished="onUploadsFinished"
    />

    <form class="mt-4" role="search" @submit.prevent>
      <label for="media-search" class="sr-only">Search images</label>
      <input
        id="media-search"
        v-model="searchInput"
        type="search"
        maxlength="100"
        autocomplete="off"
        placeholder="Search by name or alt text"
        class="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-300"
      />
    </form>

    <p class="sr-only" aria-live="polite">{{ statusMessage }}</p>

    <p
      v-if="notice"
      role="status"
      class="mt-4 rounded-md bg-green-50 px-3 py-2 text-sm text-green-800"
    >
      {{ notice }}
    </p>

    <div
      class="mt-4"
      :class="selected ? 'lg:grid lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start lg:gap-6' : ''"
    >
      <div>
        <div
          v-if="error"
          role="alert"
          class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
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
          aria-label="Loading images"
          class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4"
        >
          <li
            v-for="n in 8"
            :key="n"
            class="aspect-square animate-pulse rounded-lg bg-slate-200"
          ></li>
        </ul>

        <div
          v-else-if="images.length === 0"
          class="rounded-lg border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-600"
        >
          <template v-if="hasFilters">
            <p>No images match your search.</p>
            <button
              type="button"
              data-test="clear-filters"
              class="mt-3 rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 hover:bg-slate-100"
              @click="clearFilters"
            >
              Clear search
            </button>
          </template>
          <p v-else>
            No images yet.<template v-if="auth.canEdit"> Upload your first image above.</template>
          </p>
        </div>

        <ul
          v-else
          :aria-busy="loading"
          class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4"
          :class="{ 'opacity-60': loading, 'xl:grid-cols-5': !selected }"
        >
          <li v-for="item in images" :key="item.id">
            <button
              :id="`media-card-${item.id}`"
              type="button"
              data-test="media-card"
              :aria-pressed="selected?.id === item.id"
              class="block w-full overflow-hidden rounded-lg border bg-white text-left hover:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-400"
              :class="
                selected?.id === item.id
                  ? 'border-slate-900 ring-2 ring-slate-900'
                  : 'border-slate-200'
              "
              @click="select(item)"
            >
              <img
                :src="item.thumbnail_url ?? item.url"
                :alt="item.alt_text ?? ''"
                loading="lazy"
                decoding="async"
                class="aspect-square w-full bg-slate-100 object-cover"
              />
              <span class="block px-2 py-1.5">
                <span class="block truncate text-xs font-medium text-slate-900">{{
                  item.original_name
                }}</span>
                <span
                  v-if="!item.alt_text"
                  data-test="no-alt"
                  class="mt-0.5 inline-block rounded bg-amber-100 px-1.5 py-0.5 text-[11px] font-medium text-amber-900"
                >
                  No alt text
                </span>
              </span>
            </button>
          </li>
        </ul>

        <PaginationBar
          v-if="meta && !error && images.length > 0"
          :meta="meta"
          class="mt-4"
          @change="changePage"
        />
      </div>

      <aside
        v-if="selected"
        ref="panelRef"
        tabindex="-1"
        data-test="details"
        aria-labelledby="media-details-title"
        class="mt-6 rounded-lg border border-slate-200 bg-white p-4 lg:sticky lg:top-4 lg:mt-0"
      >
        <div class="flex items-start justify-between gap-2">
          <h2 id="media-details-title" class="min-w-0 break-words text-base font-semibold">
            {{ selected.original_name }}
          </h2>
          <button type="button" data-test="close" :class="buttonClass" @click="closePanel">
            Close<span class="sr-only"> details</span>
          </button>
        </div>

        <img
          :src="selected.url"
          :alt="selected.alt_text ?? ''"
          class="mt-3 max-h-64 w-full rounded-md bg-slate-100 object-contain"
        />

        <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
          <dt class="text-slate-500">Size</dt>
          <dd>{{ selected.width }} × {{ selected.height }} px, {{ formatBytes(selected.size) }}</dd>
          <dt class="text-slate-500">Type</dt>
          <dd>{{ selected.mime_type }}</dd>
          <dt class="text-slate-500">Uploaded</dt>
          <dd>{{ formatDate(selected.created_at) }}</dd>
        </dl>

        <form class="mt-4 space-y-3" novalidate @submit.prevent="saveAlt">
          <FormField
            id="media-alt"
            label="Alt text"
            optional
            hint="Describe the image for people who cannot see it. Leave empty if it is purely decorative."
            :errors="altErrors.alt_text"
            v-slot="{ field }"
          >
            <textarea
              v-bind="field"
              v-model="altDraft"
              rows="3"
              maxlength="200"
              :disabled="readonly"
              :class="inputClass"
            />
          </FormField>

          <p
            v-if="saved"
            role="status"
            data-test="saved"
            class="rounded-md bg-green-50 px-3 py-2 text-sm text-green-800"
          >
            Alt text saved.
          </p>
          <p
            v-if="panelError"
            role="alert"
            data-test="panel-error"
            class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700"
          >
            {{ panelError }}
          </p>

          <div class="flex flex-wrap items-center gap-2">
            <button
              v-if="!readonly"
              type="submit"
              data-test="save-alt"
              :disabled="saving || !altChanged"
              class="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
            >
              {{ saving ? 'Saving…' : 'Save alt text' }}
            </button>
            <button
              v-if="auth.isAdmin"
              type="button"
              data-test="delete"
              :disabled="deleting"
              class="rounded-md border border-red-200 bg-white px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50 disabled:opacity-60"
              @click="removeSelected"
            >
              {{ deleting ? 'Deleting…' : 'Delete image' }}
            </button>
          </div>
        </form>
      </aside>
    </div>
  </section>
</template>
