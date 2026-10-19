<script setup lang="ts">
import { computed, nextTick, ref } from 'vue'
import type { ValidationErrors } from '@/api/http'
import {
  BLOCK_LABELS,
  BLOCK_TYPES,
  MAX_BLOCKS,
  cloneBlock,
  createBlock,
  moveItem,
} from '@/blocks/model'
import CardGridFields from '@/components/blocks/CardGridFields.vue'
import CtaFields from '@/components/blocks/CtaFields.vue'
import HeroFields from '@/components/blocks/HeroFields.vue'
import ImageFields from '@/components/blocks/ImageFields.vue'
import RichTextFields from '@/components/blocks/RichTextFields.vue'
import { buttonClass } from '@/components/common/formClasses'
import type { Block, BlockType } from '@/types/blocks'

const blocks = defineModel<Block[]>({ required: true })

const props = withDefaults(defineProps<{ errors: ValidationErrors; readonly?: boolean }>(), {
  readonly: false,
})

const editorRef = ref<HTMLElement | null>(null)
const announcement = ref('')
const atLimit = computed(() => blocks.value.length >= MAX_BLOCKS)

/** Server errors for one block, with the "content.N." prefix removed. */
function blockErrors(index: number): ValidationErrors {
  const prefix = `content.${index}.`
  const result: ValidationErrors = {}

  for (const [key, messages] of Object.entries(props.errors)) {
    if (key.startsWith(prefix)) result[key.slice(prefix.length)] = messages
  }
  return result
}

function focusElement(id: string): void {
  void nextTick(() => document.getElementById(id)?.focus())
}

function add(type: BlockType): void {
  if (atLimit.value) return

  const block = createBlock(type)
  const next = [...blocks.value, block]

  blocks.value = next
  announcement.value = `Added ${BLOCK_LABELS[type]} block at position ${next.length}.`
  focusElement(`${block.id}-card`)
}

function duplicate(index: number): void {
  const source = blocks.value[index]
  if (!source || atLimit.value) return

  const copy = cloneBlock(source)
  const next = [...blocks.value]
  next.splice(index + 1, 0, copy)

  blocks.value = next
  announcement.value = `Duplicated ${BLOCK_LABELS[source.type]} block. The copy is at position ${index + 2}.`
  focusElement(`${copy.id}-card`)
}

function move(index: number, delta: -1 | 1): void {
  const block = blocks.value[index]
  if (!block) return

  const target = index + delta
  blocks.value = moveItem(blocks.value, index, delta)
  announcement.value = `Moved ${BLOCK_LABELS[block.type]} block to position ${target + 1} of ${blocks.value.length}.`

  // Keep focus on the button that was used, or on the other one if it is now disabled.
  const same = delta === -1 ? 'up' : 'down'
  const other = delta === -1 ? 'down' : 'up'
  const atEdge = delta === -1 ? target === 0 : target === blocks.value.length - 1
  focusElement(`${block.id}-${atEdge ? other : same}`)
}

function remove(index: number): void {
  const block = blocks.value[index]
  if (!block) return
  if (!window.confirm(`Remove this ${BLOCK_LABELS[block.type]} block?`)) return

  blocks.value = blocks.value.filter((_, i) => i !== index)
  announcement.value = `Removed ${BLOCK_LABELS[block.type]} block.`
  void nextTick(() => editorRef.value?.focus())
}
</script>

<template>
  <div ref="editorRef" tabindex="-1" class="space-y-4">
    <p class="sr-only" role="status" aria-live="polite">{{ announcement }}</p>

    <p
      v-if="errors.content?.length"
      role="alert"
      class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700"
    >
      {{ errors.content.join(' ') }}
    </p>

    <p
      v-if="blocks.length === 0"
      class="rounded-lg border border-dashed border-slate-300 bg-white px-4 py-8 text-center text-sm text-slate-600"
    >
      No content blocks yet.<template v-if="!readonly"> Add one below.</template>
    </p>

    <section
      v-for="(block, index) in blocks"
      :id="`${block.id}-card`"
      :key="block.id"
      tabindex="-1"
      :aria-labelledby="`${block.id}-title`"
      class="rounded-lg border border-slate-200 bg-white"
    >
      <header
        class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-4 py-2"
      >
        <h3 :id="`${block.id}-title`" class="text-sm font-semibold text-slate-900">
          {{ BLOCK_LABELS[block.type] }}
          <span class="font-normal text-slate-500"
            >· block {{ index + 1 }} of {{ blocks.length }}</span
          >
        </h3>

        <div v-if="!readonly" class="flex flex-wrap gap-2">
          <button
            :id="`${block.id}-up`"
            type="button"
            :class="buttonClass"
            :disabled="index === 0"
            @click="move(index, -1)"
          >
            Move up<span class="sr-only">
              ({{ BLOCK_LABELS[block.type] }} block {{ index + 1 }})</span
            >
          </button>
          <button
            :id="`${block.id}-down`"
            type="button"
            :class="buttonClass"
            :disabled="index === blocks.length - 1"
            @click="move(index, 1)"
          >
            Move down<span class="sr-only">
              ({{ BLOCK_LABELS[block.type] }} block {{ index + 1 }})</span
            >
          </button>
          <button
            :id="`${block.id}-duplicate`"
            type="button"
            :class="buttonClass"
            :disabled="atLimit"
            @click="duplicate(index)"
          >
            Duplicate<span class="sr-only">
              ({{ BLOCK_LABELS[block.type] }} block {{ index + 1 }})</span
            >
          </button>
          <button
            :id="`${block.id}-remove`"
            type="button"
            class="rounded-md border border-red-200 bg-white px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50"
            @click="remove(index)"
          >
            Remove<span class="sr-only">
              ({{ BLOCK_LABELS[block.type] }} block {{ index + 1 }})</span
            >
          </button>
        </div>
      </header>

      <div class="p-4">
        <HeroFields
          v-if="block.type === 'hero'"
          :id="block.id"
          :data="block.data"
          :errors="blockErrors(index)"
        />
        <RichTextFields
          v-else-if="block.type === 'rich_text'"
          :id="block.id"
          :data="block.data"
          :errors="blockErrors(index)"
        />
        <ImageFields
          v-else-if="block.type === 'image'"
          :id="block.id"
          :data="block.data"
          :errors="blockErrors(index)"
        />
        <CtaFields
          v-else-if="block.type === 'cta'"
          :id="block.id"
          :data="block.data"
          :errors="blockErrors(index)"
        />
        <CardGridFields
          v-else-if="block.type === 'card_grid'"
          :id="block.id"
          :data="block.data"
          :errors="blockErrors(index)"
        />
      </div>
    </section>

    <div v-if="!readonly" class="rounded-lg border border-dashed border-slate-300 bg-white p-4">
      <p class="text-sm font-medium text-slate-700">Add a block</p>
      <div class="mt-2 flex flex-wrap gap-2">
        <button
          v-for="type in BLOCK_TYPES"
          :key="type"
          type="button"
          :class="buttonClass"
          :disabled="atLimit"
          @click="add(type)"
        >
          + {{ BLOCK_LABELS[type] }}
        </button>
      </div>
      <p v-if="atLimit" class="mt-2 text-xs text-slate-500">
        Maximum of {{ MAX_BLOCKS }} blocks reached.
      </p>
    </div>
  </div>
</template>
