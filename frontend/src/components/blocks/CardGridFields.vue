<script setup lang="ts">
import type { ValidationErrors } from '@/api/http'
import { MAX_CARDS, emptyCard } from '@/blocks/model'
import CardFields from '@/components/blocks/CardFields.vue'
import FormField from '@/components/common/FormField.vue'
import { buttonClass, inputClass } from '@/components/common/formClasses'
import type { CardGridData } from '@/types/blocks'

const data = defineModel<CardGridData>('data', { required: true })
const props = defineProps<{ id: string; errors: ValidationErrors }>()

function err(path: string): string[] {
  return props.errors[`data.${path}`] ?? []
}

/** Server errors for one card, with the "data.cards.N." prefix removed. */
function cardErrors(index: number): ValidationErrors {
  const prefix = `data.cards.${index}.`
  const result: ValidationErrors = {}

  for (const [key, messages] of Object.entries(props.errors)) {
    if (key.startsWith(prefix)) result[key.slice(prefix.length)] = messages
  }
  return result
}

function addCard(): void {
  if (data.value.cards.length < MAX_CARDS) data.value.cards.push(emptyCard())
}

function removeCard(index: number): void {
  data.value.cards.splice(index, 1)
}
</script>

<template>
  <div class="space-y-4">
    <FormField
      :id="`${id}-heading`"
      label="Heading"
      optional
      :errors="err('heading')"
      v-slot="{ field }"
    >
      <input
        v-bind="field"
        v-model="data.heading"
        type="text"
        maxlength="150"
        :class="inputClass"
      />
    </FormField>

    <p v-if="err('cards').length" role="alert" class="text-sm text-red-700">
      {{ err('cards').join(' ') }}
    </p>

    <fieldset
      v-for="(card, index) in data.cards"
      :key="index"
      class="min-w-0 space-y-4 rounded-md border border-slate-200 p-4"
    >
      <legend class="px-1 text-sm font-medium text-slate-700">Card {{ index + 1 }}</legend>

      <CardFields
        :id="`${id}-card-${index}`"
        :card="card"
        :position="index + 1"
        :errors="cardErrors(index)"
      />

      <button
        type="button"
        :class="buttonClass"
        :disabled="data.cards.length === 1"
        @click="removeCard(index)"
      >
        Remove card {{ index + 1 }}
      </button>
    </fieldset>

    <button
      type="button"
      :class="buttonClass"
      :disabled="data.cards.length >= MAX_CARDS"
      @click="addCard"
    >
      Add card
    </button>
  </div>
</template>
