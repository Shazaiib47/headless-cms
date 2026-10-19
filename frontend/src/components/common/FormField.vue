<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  id: string
  label: string
  errors?: string[]
  hint?: string
  optional?: boolean
}>()

defineSlots<{
  default(props: { field: Record<string, string | undefined> }): unknown
}>()

const hintId = computed(() => `${props.id}-hint`)
const errorId = computed(() => `${props.id}-error`)
const hasErrors = computed(() => (props.errors?.length ?? 0) > 0)

/** Attributes the slotted control spreads with v-bind: id plus the ARIA wiring. */
const field = computed(() => {
  const describedBy = [props.hint ? hintId.value : '', hasErrors.value ? errorId.value : '']
    .filter(Boolean)
    .join(' ')

  return {
    id: props.id,
    'aria-describedby': describedBy || undefined,
    'aria-invalid': hasErrors.value ? 'true' : undefined,
  }
})
</script>

<template>
  <div>
    <label :for="id" class="block text-sm font-medium text-slate-700">
      {{ label }}<span v-if="optional" class="font-normal text-slate-500"> (optional)</span>
    </label>
    <slot :field="field" />
    <p v-if="hint" :id="hintId" class="mt-1 text-xs text-slate-500">{{ hint }}</p>
    <p v-if="hasErrors" :id="errorId" class="mt-1 text-sm text-red-700">{{ errors?.join(' ') }}</p>
  </div>
</template>
