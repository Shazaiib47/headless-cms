import { MAX_CARDS, fromApiBlocks, newId } from '@/blocks/model'
import type { PublicImage, RenderBlock } from '@/types/render'
import { safeImageSrc } from '@/utils/url'

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

const positiveNumber = (value: unknown): number | null =>
  typeof value === 'number' && Number.isFinite(value) && value > 0 ? value : null

/** Validates a resolved image from the API. Anything without a safe address becomes null. */
function parseImage(raw: unknown): PublicImage | null {
  if (!isRecord(raw)) return null

  const url = safeImageSrc(typeof raw.url === 'string' ? raw.url : null)
  if (url === null) return null

  return {
    url,
    thumbnail_url: safeImageSrc(typeof raw.thumbnail_url === 'string' ? raw.thumbnail_url : null),
    width: positiveNumber(raw.width),
    height: positiveNumber(raw.height),
    alt: typeof raw.alt === 'string' ? raw.alt : null,
  }
}

/**
 * Converts blocks from the public or preview API into renderable blocks. Rich text
 * keeps only its generated HTML, images are validated, and unknown or malformed
 * blocks are skipped.
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

    // Every other block has the same shape as the editor's, apart from nulls and images.
    const [block] = fromApiBlocks([item])
    if (!block || block.type === 'rich_text') continue

    const data = isRecord(item) && isRecord(item.data) ? item.data : {}

    switch (block.type) {
      case 'hero':
        blocks.push({ ...block, data: { ...block.data, image: parseImage(data.image) } })
        break
      case 'image':
        blocks.push({ ...block, data: { ...block.data, image: parseImage(data.image) } })
        break
      case 'card_grid': {
        // fromApiBlocks keeps the same cards in the same order, so positions line up.
        const rawCards = (Array.isArray(data.cards) ? data.cards : [])
          .filter(isRecord)
          .slice(0, MAX_CARDS)

        blocks.push({
          ...block,
          data: {
            ...block.data,
            cards: block.data.cards.map((card, index) => ({
              ...card,
              image: parseImage(rawCards[index]?.image),
            })),
          },
        })
        break
      }
      default:
        blocks.push(block)
    }
  }

  return blocks
}
