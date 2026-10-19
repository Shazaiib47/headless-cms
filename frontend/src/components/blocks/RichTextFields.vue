<script setup lang="ts">
import type { ValidationErrors } from '@/api/http'
import FormField from '@/components/common/FormField.vue'
import { inputClass } from '@/components/common/formClasses'
import type { RichTextData } from '@/types/blocks'

const data = defineModel<RichTextData>('data', { required: true })
const props = defineProps<{ id: string; errors: ValidationErrors }>()

function err(field: string): string[] {
  return props.errors[`data.${field}`] ?? []
}
</script>

<template>
  <FormField
    :id="`${id}-markdown`"
    label="Content"
    hint="Markdown: **bold**, *italic*, ## headings, - lists and [links](https://example.com). Raw HTML and images are removed."
    :errors="err('markdown')"
    v-slot="{ field }"
  >
    <textarea
      v-bind="field"
      v-model="data.markdown"
      rows="10"
      maxlength="20000"
      :class="[inputClass, 'font-mono']"
    />
  </FormField>
</template>
