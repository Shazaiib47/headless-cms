<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { ApiError } from '@/api/http'
import { mediaApi } from '@/api/media'
import PaginationBar from '@/components/common/PaginationBar.vue'
import { buttonClass, inputClass } from '@/components/common/formClasses'
import { MAX_UPLOAD_BYTES } from '@/composables/useMediaUploads'
import { useAuthStore } from '@/stores/auth'
import type { MediaItem, PaginationMeta } from '@/types/api'
import { safeImageSrc } from '@/utils/url'

defineProps<{ selectedId: number | null }>()

const emit = defineEmits<{
  select: [media: MediaItem]
  close: []
}>()

const PER_PAGE = 12

const auth = useAuthStore()

const dialog = ref<HTMLDialogElement | null>(null)
const searchRef = ref<HTMLInputElement | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)

const searchInput = ref('')
const search = ref('')
const page = ref(1)
const items = ref<MediaItem[]>([])
const meta = ref<PaginationMeta | null>(null)
const loading = ref(true)
const error = ref('')
const uploading = ref(false)
const uploadError = ref('')

let requestId = 0
let searchTimer: ReturnType<typeof setTimeout> | undefined

function thumbnail(item: MediaItem): string | undefined {
  return safeImageSrc(item.thumbnail_url ?? item.url) ?? undefined
}

async function load(): Promise<void> {
  const id = ++requestId
  loading.value = true
  error.value = ''

  try {
    const result = await mediaApi.list({
      search: search.value,
      page: page.value,
      per_page: PER_PAGE,
    })
    if (id !== requestId) return // a newer request superseded this one

    items.value = result.data
    meta.value = result.meta
  } catch (caught) {
    if (id !== requestId) return
    error.value =
      caught instanceof ApiError ? caught.firstError() : 'Something went wrong. Please try again.'
  } finally {
    if (id === requestId) loading.value = false
  }
}

async function onFile(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = '' // lets the same file be chosen again

  if (!file || uploading.value) return

  uploadError.value = ''

  if (file.size > MAX_UPLOAD_BYTES) {
    uploadError.value = 'This file is over the 5 MB limit.'
    return
  }

  uploading.value = true

  try {
    const { data } = await mediaApi.upload(file)
    emit('select', data)
  } catch (caught) {
    uploadError.value = caught instanceof ApiError ? caught.firstError() : 'The upload failed.'
  } finally {
    uploading.value = false
  }
}

watch([search, page], () => void load())

// Debounce typing in the search box.
watch(searchInput, (value) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    search.value = value.trim()
    page.value = 1
  }, 300)
})

onMounted(() => {
  const element = dialog.value

  if (element) {
    try {
      element.showModal()
    } catch {
      element.setAttribute('open', '') // very old browsers
    }
  }

  void load()
  void nextTick(() => searchRef.value?.focus())
})

onBeforeUnmount(() => {
  clearTimeout(searchTimer)
  requestId++ // ignore any response still in flight
})
</script>

<template>
  <dialog
    ref="dialog"
    aria-labelledby="image-picker-title"
    class="m-auto w-[min(56rem,calc(100vw-2rem))] max-w-none rounded-lg border border-slate-200 p-0 shadow-xl backdrop:bg-slate-900/50"
    @click.self="emit('close')"
    @close="emit('close')"
  >
    <div class="max-h-[85vh] overflow-y-auto p-4">
      <div class="flex items-start justify-between gap-3">
        <h2 id="image-picker-title" class="text-lg font-semibold text-slate-900">
          Choose an image
        </h2>
        <button type="button" data-test="close" :class="buttonClass" @click="emit('close')">
          Close
        </button>
      </div>

      <div class="mt-4">
        <label for="image-picker-search" class="sr-only">Search images</label>
        <input
          id="image-picker-search"
          ref="searchRef"
          v-model="searchInput"
          type="search"
          maxlength="100"
          autocomplete="off"
          placeholder="Search by name or alt text"
          :class="inputClass"
        />
      </div>

      <div v-if="auth.canEdit" data-test="upload" class="mt-3">
        <button
          type="button"
          :class="buttonClass"
          :disabled="uploading"
          @click="fileInput?.click()"
        >
          {{ uploading ? 'Uploading…' : 'Upload a new image' }}
        </button>
        <input
          ref="fileInput"
          type="file"
          accept="image/jpeg,image/png,image/webp"
          data-test="file"
          class="hidden"
          tabindex="-1"
          aria-hidden="true"
          @change="onFile"
        />
        <p class="mt-1 text-xs text-slate-500">
          JPEG, PNG or WebP, up to 5 MB. It is selected as soon as it uploads.
        </p>
        <p
          v-if="uploadError"
          role="alert"
          data-test="upload-error"
          class="mt-1 text-sm text-red-700"
        >
          {{ uploadError }}
        </p>
      </div>

      <div class="mt-4">
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

        <p
          v-else-if="items.length === 0"
          class="rounded-lg border border-dashed border-slate-300 bg-white px-4 py-8 text-center text-sm text-slate-600"
        >
          <template v-if="search">No images match your search.</template>
          <template v-else
            >No images yet.<template v-if="auth.canEdit"> Upload one above.</template></template
          >
        </p>

        <ul
          v-else
          :aria-busy="loading"
          class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4"
          :class="{ 'opacity-60': loading }"
        >
          <li v-for="item in items" :key="item.id">
            <button
              type="button"
              data-test="pick"
              :aria-label="`Select ${item.original_name}`"
              :aria-pressed="item.id === selectedId"
              class="block w-full overflow-hidden rounded-lg border bg-white text-left hover:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-400"
              :class="
                item.id === selectedId
                  ? 'border-slate-900 ring-2 ring-slate-900'
                  : 'border-slate-200'
              "
              @click="emit('select', item)"
            >
              <img
                :src="thumbnail(item)"
                alt=""
                loading="lazy"
                decoding="async"
                class="aspect-square w-full bg-slate-100 object-cover"
              />
              <span class="block truncate px-2 py-1.5 text-xs font-medium text-slate-900">
                {{ item.original_name }}
              </span>
            </button>
          </li>
        </ul>

        <PaginationBar
          v-if="meta && !error && items.length > 0"
          :meta="meta"
          class="mt-4"
          @change="(next: number) => (page = next)"
        />
      </div>
    </div>
  </dialog>
</template>
