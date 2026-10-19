<script setup lang="ts">
import type { RichTextRenderData } from '@/types/render'

defineProps<{ data: RichTextRenderData }>()
</script>

<template>
  <!--
    The HTML is generated and sanitised by the server: Markdown with raw HTML
    stripped, unsafe links removed and images reduced to alt text. Only ever
    pass the server's `html` field here, never anything a user typed directly.
  -->
  <div
    class="max-w-none space-y-4 text-slate-800 [&_a]:text-blue-700 [&_a]:underline [&_blockquote]:border-l-4 [&_blockquote]:border-slate-300 [&_blockquote]:pl-4 [&_blockquote]:text-slate-600 [&_code]:rounded [&_code]:bg-slate-100 [&_code]:px-1 [&_h1]:text-3xl [&_h1]:font-bold [&_h2]:text-2xl [&_h2]:font-semibold [&_h3]:text-xl [&_h3]:font-semibold [&_ol]:list-decimal [&_ol]:pl-6 [&_pre]:overflow-x-auto [&_pre]:rounded [&_pre]:bg-slate-100 [&_pre]:p-3 [&_ul]:list-disc [&_ul]:pl-6"
    v-html="data.html"
  ></div>
</template>
