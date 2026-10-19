<script setup lang="ts">
import type { ValidationErrors } from '@/api/http'
import FormField from '@/components/common/FormField.vue'
import { inputClass } from '@/components/common/formClasses'
import type { CtaData } from '@/types/blocks'

const data = defineModel<CtaData>('data', { required: true })
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

    <FormField :id="`${id}-text`" label="Text" optional :errors="err('text')" v-slot="{ field }">
      <textarea v-bind="field" v-model="data.text" rows="2" maxlength="300" :class="inputClass" />
    </FormField>

    <div class="grid gap-4 sm:grid-cols-2">
      <FormField
        :id="`${id}-button-label`"
        label="Button label"
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

    <FormField :id="`${id}-style`" label="Button style" :errors="err('style')" v-slot="{ field }">
      <select v-bind="field" v-model="data.style" :class="inputClass">
        <option value="primary">Primary</option>
        <option value="secondary">Secondary</option>
      </select>
    </FormField>
  </div>
</template>
