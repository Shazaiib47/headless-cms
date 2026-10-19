<script setup lang="ts">
import CardGridBlock from '@/components/render/CardGridBlock.vue'
import CtaBlock from '@/components/render/CtaBlock.vue'
import HeroBlock from '@/components/render/HeroBlock.vue'
import ImageBlock from '@/components/render/ImageBlock.vue'
import RichTextBlock from '@/components/render/RichTextBlock.vue'
import type { RenderBlock } from '@/types/render'

defineProps<{ blocks: RenderBlock[] }>()
</script>

<template>
  <p v-if="blocks.length === 0" class="text-center text-slate-600">This page has no content yet.</p>
  <div v-else class="space-y-10">
    <template v-for="block in blocks" :key="block.id">
      <HeroBlock v-if="block.type === 'hero'" :data="block.data" />
      <RichTextBlock v-else-if="block.type === 'rich_text'" :data="block.data" />
      <ImageBlock v-else-if="block.type === 'image'" :data="block.data" />
      <CtaBlock v-else-if="block.type === 'cta'" :data="block.data" />
      <CardGridBlock v-else-if="block.type === 'card_grid'" :data="block.data" />
    </template>
  </div>
</template>
