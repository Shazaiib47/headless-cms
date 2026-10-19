<script setup lang="ts">
import type { ValidationErrors } from '@/api/http'
import FormField from '@/components/common/FormField.vue'
import ImageField from '@/components/common/ImageField.vue'
import { inputClass } from '@/components/common/formClasses'
import type { HeroData } from '@/types/blocks'

const data = defineModel<HeroData>('data', { required: true })
const props = defineProps<{ id: string; errors: ValidationErrors }>()

function err(field: string): string[] {
  return props.errors[`data.${field}`] ?? []
}
</script>

<template>
  <div class="space-y-4">
    <FormField :id="`${id}-heading`" label="Heading" :errors="err('heading')" v-slot="{ field }">
      <input
        v-bind="field"
        v-model="data.heading"
        type="text"
        maxlength="150"
        :class="inputClass"
      />
    </FormField>

    <FormField
      :id="`${id}-subheading`"
      label="Subheading"
      optional
      :errors="err('subheading')"
      v-slot="{ field }"
    >
      <textarea
        v-bind="field"
        v-model="data.subheading"
        rows="2"
        maxlength="300"
        :class="inputClass"
      />
    </FormField>

    <ImageField :image-id="data.image_id" />

    <div class="grid gap-4 sm:grid-cols-2">
      <FormField
        :id="`${id}-button-label`"
        label="Button label"
        optional
        hint="A button needs both a label and a link."
        :errors="err('button_label')"
        v-slot="{ field }"
      >
        <input
          v-bind="field"
          v-model="data.button_label"
          type="text"
          maxlength="60"
          :class="inputClass"
        />
      </FormField>

      <FormField
        :id="`${id}-button-url`"
        label="Button link"
        optional
        hint="https://…, mailto:…, /page or #section"
        :errors="err('button_url')"
        v-slot="{ field }"
      >
        <input
          v-bind="field"
          v-model="data.button_url"
          type="text"
          maxlength="2048"
          :class="inputClass"
        />
      </FormField>
    </div>
  </div>
</template>
