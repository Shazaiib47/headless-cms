<script setup lang="ts">
defineProps<{ imageId: number | null }>()
</script>

<template>
  <div>
    <p class="text-sm font-medium text-slate-700">
      Image <span class="font-normal text-slate-500">(optional)</span>
    </p>
    <p class="mt-1 text-sm text-slate-600">
      <template v-if="imageId !== null">Image #{{ imageId }} selected.</template>
      <template v-else>No image selected.</template>
    </p>
    <button
      type="button"
      disabled
      class="mt-1 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
    >
      Choose image
    </button>
    <p class="mt-1 text-xs text-slate-500">Image selection is coming soon.</p>
  </div>
</template>
