<script setup lang="ts">
import type { ValidationErrors } from '@/api/http'
import FormField from '@/components/common/FormField.vue'
import ImageField from '@/components/common/ImageField.vue'
import { inputClass } from '@/components/common/formClasses'
import type { CardData } from '@/types/blocks'

const card = defineModel<CardData>('card', { required: true })
const props = defineProps<{
  /** Prefix for the field ids, unique per card. */
  id: string
  /** The card's number as shown to the user (starting at 1). */
  position: number
  /** Server errors for this card, keyed by field name (e.g. "title"). */
  errors: ValidationErrors
}>()

function err(field: string): string[] {
  return props.errors[field] ?? []
}
</script>

<template>
  <div class="space-y-4">
    <FormField
      :id="`${id}-title`"
      :label="`Card ${position} title`"
      :errors="err('title')"
      v-slot="{ field }"
    >
      <input v-bind="field" v-model="card.title" type="text" maxlength="100" :class="inputClass" />
    </FormField>

    <FormField
      :id="`${id}-text`"
      :label="`Card ${position} text`"
      optional
      :errors="err('text')"
      v-slot="{ field }"
    >
      <textarea v-bind="field" v-model="card.text" rows="2" maxlength="300" :class="inputClass" />
    </FormField>

    <ImageField
      :id="`${id}-image`"
      :label="`Card ${position} image`"
      v-model:image-id="card.image_id"
      :errors="err('image_id')"
    />

    <div class="grid gap-4 sm:grid-cols-2">
      <FormField
        :id="`${id}-link-label`"
        :label="`Card ${position} link label`"
        optional
        :errors="err('link_label')"
        v-slot="{ field }"
      >
        <input
          v-bind="field"
          v-model="card.link_label"
          type="text"
          maxlength="60"
          :class="inputClass"
        />
      </FormField>

      <FormField
        :id="`${id}-link-url`"
        :label="`Card ${position} link`"
        optional
        hint="https://…, mailto:…, /page or #section"
        :errors="err('link_url')"
        v-slot="{ field }"
      >
        <input
          v-bind="field"
          v-model="card.link_url"
          type="text"
          maxlength="2048"
          :class="inputClass"
        />
      </FormField>
    </div>
  </div>
</template>
