<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { buttonClass } from '@/components/common/formClasses'
import { useMediaUploads, type UploadStatus } from '@/composables/useMediaUploads'
import type { MediaItem } from '@/types/api'

const emit = defineEmits<{
  uploaded: [media: MediaItem]
  /** The queue has just become empty: time to refresh the list. */
  finished: []
}>()

const { items, busy, add, dismiss, clearFinished } = useMediaUploads((media) =>
  emit('uploaded', media),
)

const input = ref<HTMLInputElement | null>(null)
const dragging = ref(false)

const statusLabels: Record<UploadStatus, string> = {
  queued: 'Waiting',
  uploading: 'Uploading…',
  done: 'Uploaded',
  error: 'Failed',
}

const summary = computed(() => {
  const total = items.value.length
  if (total === 0) return ''

  const waiting = items.value.filter(
    (i) => i.status === 'queued' || i.status === 'uploading',
  ).length
  const failed = items.value.filter((i) => i.status === 'error').length
  const done = items.value.filter((i) => i.status === 'done').length

  if (waiting > 0) return `Uploading… ${total - waiting} of ${total} finished`

  return failed > 0 ? `${done} uploaded, ${failed} failed` : `${done} uploaded`
})

const hasFinished = computed(() =>
  items.value.some((item) => item.status === 'done' || item.status === 'error'),
)

function onPick(event: Event): void {
  const target = event.target as HTMLInputElement
  add(Array.from(target.files ?? []))
  target.value = '' // lets the same file be chosen again
}

function onDrop(event: DragEvent): void {
  dragging.value = false
  add(Array.from(event.dataTransfer?.files ?? []))
}

watch(busy, (now, before) => {
  if (before && !now) emit('finished')
})
</script>

<template>
  <div>
    <div
      data-test="dropzone"
      class="rounded-lg border-2 border-dashed bg-white px-4 py-6 text-center"
      :class="dragging ? 'border-slate-500 bg-slate-50' : 'border-slate-300'"
      @dragover.prevent="dragging = true"
      @dragleave="dragging = false"
      @drop.prevent="onDrop"
    >
      <p class="text-sm text-slate-600">
        Drop images here, or
        <button
          type="button"
          data-test="choose"
          class="font-medium text-slate-900 underline"
          @click="input?.click()"
        >
          choose images
        </button>
      </p>
      <p class="mt-1 text-xs text-slate-500">JPEG, PNG or WebP, up to 5 MB each.</p>
      <input
        ref="input"
        type="file"
        accept="image/jpeg,image/png,image/webp"
        multiple
        class="hidden"
        tabindex="-1"
        aria-hidden="true"
        @change="onPick"
      />
    </div>

    <p role="status" class="mt-2 text-sm text-slate-700">{{ summary }}</p>

    <ul
      v-if="items.length"
      class="mt-2 divide-y divide-slate-200 rounded-lg border border-slate-200 bg-white"
    >
      <li
        v-for="item in items"
        :key="item.id"
        class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm"
      >
        <div class="min-w-0">
          <p class="truncate font-medium text-slate-900">{{ item.name }}</p>
          <p v-if="item.message" class="text-red-700">{{ item.message }}</p>
        </div>
        <div class="flex items-center gap-2">
          <span
            class="rounded-full px-2 py-0.5 text-xs font-medium"
            :class="{
              'bg-slate-100 text-slate-700':
                item.status === 'queued' || item.status === 'uploading',
              'bg-green-100 text-green-800': item.status === 'done',
              'bg-red-100 text-red-800': item.status === 'error',
            }"
          >
            {{ statusLabels[item.status] }}
          </span>
          <button
            v-if="item.status === 'done' || item.status === 'error'"
            type="button"
            data-test="dismiss"
            :class="buttonClass"
            @click="dismiss(item.id)"
          >
            Dismiss<span class="sr-only"> {{ item.name }}</span>
          </button>
        </div>
      </li>
    </ul>

    <button
      v-if="hasFinished"
      type="button"
      data-test="clear-finished"
      class="mt-2 text-sm text-slate-600 underline"
      @click="clearFinished"
    >
      Clear finished
    </button>
  </div>
</template>
