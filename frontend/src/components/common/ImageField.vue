<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { ApiError } from '@/api/http'
import { buttonClass } from '@/components/common/formClasses'
import ImagePicker from '@/components/media/ImagePicker.vue'
import { getMedia, rememberMedia } from '@/composables/useMediaCache'
import type { MediaItem } from '@/types/api'
import { safeImageSrc } from '@/utils/url'

const imageId = defineModel<number | null>('imageId', { required: true })

const props = withDefaults(
  defineProps<{
    id: string
    label: string
    errors?: string[]
    hint?: string
    optional?: boolean
  }>(),
  { optional: true },
)

const media = ref<MediaItem | null>(null)
const status = ref<'idle' | 'loading' | 'missing' | 'failed'>('idle')
const open = ref(false)
const chooseButton = ref<HTMLButtonElement | null>(null)
let token = 0

const thumbnail = computed(() =>
  media.value ? safeImageSrc(media.value.thumbnail_url ?? media.value.url) : null,
)

watch(
  imageId,
  async (id) => {
    const mine = ++token

    if (id === null) {
      media.value = null
      status.value = 'idle'
      return
    }

    // Already known (e.g. just chosen in the picker).
    if (media.value?.id === id) {
      status.value = 'idle'
      return
    }

    media.value = null
    status.value = 'loading'

    try {
      const item = await getMedia(id)
      if (mine !== token) return

      media.value = item
      status.value = 'idle'
    } catch (caught) {
      if (mine !== token) return

      status.value = caught instanceof ApiError && caught.status === 404 ? 'missing' : 'failed'
    }
  },
  { immediate: true },
)

function closePicker(): void {
  open.value = false
  void nextTick(() => chooseButton.value?.focus())
}

function choose(item: MediaItem): void {
  rememberMedia(item)
  media.value = item
  status.value = 'idle'
  imageId.value = item.id
  closePicker()
}

function remove(): void {
  imageId.value = null
}
</script>

<template>
  <div>
    <p :id="`${id}-label`" class="text-sm font-medium text-slate-700">
      {{ label }}<span v-if="optional" class="font-normal text-slate-500"> (optional)</span>
    </p>

    <div class="mt-1 flex items-center gap-3">
      <img
        v-if="thumbnail"
        data-test="preview"
        :src="thumbnail"
        alt=""
        class="h-16 w-16 shrink-0 rounded-md bg-slate-100 object-cover"
      />
      <p data-test="status" class="min-w-0 break-words text-sm text-slate-600">
        <template v-if="imageId === null">No image selected.</template>
        <template v-else-if="status === 'loading'">Loading image…</template>
        <template v-else-if="status === 'missing'">
          Image #{{ imageId }} is no longer in the media library.
        </template>
        <template v-else-if="status === 'failed'">
          Could not load a preview of image #{{ imageId }}.
        </template>
        <template v-else-if="media">{{ media.original_name }}</template>
      </p>
    </div>

    <div class="mt-2 flex flex-wrap gap-2">
      <button
        :id="`${id}-choose`"
        ref="chooseButton"
        type="button"
        data-test="choose"
        :class="buttonClass"
        @click="open = true"
      >
        {{ imageId === null ? 'Choose image' : 'Change image'
        }}<span class="sr-only"> for {{ label }}</span>
      </button>
      <button
        v-if="imageId !== null"
        type="button"
        data-test="remove"
        :class="buttonClass"
        @click="remove"
      >
        Remove image<span class="sr-only"> from {{ label }}</span>
      </button>
    </div>

    <p v-if="hint" class="mt-1 text-xs text-slate-500">{{ hint }}</p>
    <p
      v-if="props.errors?.length"
      data-test="errors"
      role="alert"
      class="mt-1 text-sm text-red-700"
    >
      {{ props.errors.join(' ') }}
    </p>

    <ImagePicker v-if="open" :selected-id="imageId" @select="choose" @close="closePicker" />
  </div>
</template>
