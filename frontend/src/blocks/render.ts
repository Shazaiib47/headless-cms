import { fromApiBlocks, newId } from '@/blocks/model'
import type { RenderBlock } from '@/types/render'

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

/**
 * Converts blocks from the public or preview API into renderable blocks. Rich text
 * keeps only its generated HTML. Unknown or malformed blocks are skipped.
 */
export function toRenderBlocks(raw: unknown): RenderBlock[] {
  if (!Array.isArray(raw)) return []

  const blocks: RenderBlock[] = []

  for (const item of raw) {
    if (isRecord(item) && item.type === 'rich_text') {
      const data = isRecord(item.data) ? item.data : {}

      blocks.push({
        id: typeof item.id === 'string' && item.id !== '' ? item.id : newId(),
        type: 'rich_text',
        data: { html: typeof data.html === 'string' ? data.html : '' },
      })
      continue
    }

    // Every other block has the same shape as the editor's, apart from nulls.
    const [block] = fromApiBlocks([item])
    if (block && block.type !== 'rich_text') blocks.push(block)
  }

  return blocks
}
