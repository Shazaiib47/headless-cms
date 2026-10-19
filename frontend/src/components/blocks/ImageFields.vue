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
    <ImageField :image-id="data.image_id" />
    <p v-if="err('image_id').length" class="text-sm text-red-700">
      {{ err('image_id').join(' ') }}
    </p>

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
