<script setup lang="ts">
import type { ValidationErrors } from '@/api/http'
import FormField from '@/components/common/FormField.vue'
import ImageField from '@/components/common/ImageField.vue'
import { inputClass } from '@/components/common/formClasses'
import type { ImageBlockData } from '@/types/blocks'

const data = defineModel<ImageBlockData>('data', { required: true })
const props = defineProps<{ id: string; errors: ValidationErrors }>()

function err(field: string): string[] {
  return props.errors[`data.${field}`] ?? []
}
</script>

<template>
  <div class="space-y-4">
    <ImageField
      :id="`${id}-image`"
      label="Image"
      :optional="false"
      hint="Required before the page can be published."
      v-model:image-id="data.image_id"
      :errors="err('image_id')"
    />

    <FormField
      :id="`${id}-alt`"
      label="Alt text"
      hint="Describes the image for people who cannot see it."
      :errors="err('alt')"
      v-slot="{ field }"
    >
      <input v-bind="field" v-model="data.alt" type="text" maxlength="200" :class="inputClass" />
    </FormField>

    <FormField
      :id="`${id}-caption`"
      label="Caption"
      optional
      :errors="err('caption')"
      v-slot="{ field }"
    >
      <input
        v-bind="field"
        v-model="data.caption"
        type="text"
        maxlength="300"
        :class="inputClass"
      />
    </FormField>
  </div>
</template>
