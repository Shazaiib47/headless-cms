import type { Block, BlockType, CardData } from '@/types/blocks'

export const BLOCK_TYPES: readonly BlockType[] = ['hero', 'rich_text', 'image', 'cta', 'card_grid']

export const BLOCK_LABELS: Record<BlockType, string> = {
  hero: 'Hero',
  rich_text: 'Rich text',
  image: 'Image',
  cta: 'Call to action',
  card_grid: 'Card grid',
}

/** Mirror the server limits in App\Support\Blocks\BlockDefinitions. */
export const MAX_BLOCKS = 50
export const MAX_CARDS = 12

export function newId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }
  return `b${Date.now().toString(36)}${Math.random().toString(36).slice(2, 10)}`
}

export function emptyCard(): CardData {
  return { title: '', text: '', image_id: null, link_label: '', link_url: '' }
}

export function createBlock(type: BlockType): Block {
  const id = newId()

  switch (type) {
    case 'hero':
      return {
        id,
        type,
        data: { heading: '', subheading: '', image_id: null, button_label: '', button_url: '' },
      }
    case 'rich_text':
      return { id, type, data: { markdown: '' } }
    case 'image':
      return { id, type, data: { image_id: null, alt: '', caption: '' } }
    case 'cta':
      return {
        id,
        type,
        data: { heading: '', text: '', button_label: '', button_url: '', style: 'primary' },
      }
    case 'card_grid':
      return { id, type, data: { heading: '', cards: [emptyCard()] } }
  }
}

/** A deep copy with a fresh id. */
export function cloneBlock(block: Block): Block {
  const copy = JSON.parse(JSON.stringify(block)) as Block
  return { ...copy, id: newId() } as Block
}

/** Moves an item by `delta` places, returning a new array. Out-of-range moves change nothing. */
export function moveItem<T>(items: readonly T[], from: number, delta: number): T[] {
  const to = from + delta
  const next = [...items]

  if (from < 0 || from >= items.length || to < 0 || to >= items.length) return next

  const [item] = next.splice(from, 1)
  next.splice(to, 0, item as T)
  return next
}

/** A plain-JSON copy of the blocks, safe to send to the API. */
export function serializeBlocks(blocks: readonly Block[]): Block[] {
  return JSON.parse(JSON.stringify(blocks)) as Block[]
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

const str = (value: unknown): string => (typeof value === 'string' ? value : '')

const intOrNull = (value: unknown): number | null =>
  typeof value === 'number' && Number.isInteger(value) ? value : null

function cardFromApi(raw: Record<string, unknown>): CardData {
  return {
    title: str(raw.title),
    text: str(raw.text),
    image_id: intOrNull(raw.image_id),
    link_label: str(raw.link_label),
    link_url: str(raw.link_url),
  }
}

function blockFromApi(raw: unknown): Block | null {
  if (!isRecord(raw)) return null

  const type = BLOCK_TYPES.find((candidate) => candidate === raw.type)
  if (!type) return null

  const id = typeof raw.id === 'string' && raw.id !== '' ? raw.id : newId()
  const data = isRecord(raw.data) ? raw.data : {}

  switch (type) {
    case 'hero':
      return {
        id,
        type,
        data: {
          heading: str(data.heading),
          subheading: str(data.subheading),
          image_id: intOrNull(data.image_id),
          button_label: str(data.button_label),
          button_url: str(data.button_url),
        },
      }
    case 'rich_text':
      // The generated html is ignored: only the Markdown source is edited.
      return { id, type, data: { markdown: str(data.markdown) } }
    case 'image':
      return {
        id,
        type,
        data: {
          image_id: intOrNull(data.image_id),
          alt: str(data.alt),
          caption: str(data.caption),
        },
      }
    case 'cta':
      return {
        id,
        type,
        data: {
          heading: str(data.heading),
          text: str(data.text),
          button_label: str(data.button_label),
          button_url: str(data.button_url),
          style: data.style === 'secondary' ? 'secondary' : 'primary',
        },
      }
    case 'card_grid':
      return {
        id,
        type,
        data: {
          heading: str(data.heading),
          cards: (Array.isArray(data.cards) ? data.cards : [])
            .filter(isRecord)
            .slice(0, MAX_CARDS)
            .map(cardFromApi),
        },
      }
  }
}

/** Converts API content into editable blocks. Unknown or malformed entries are skipped. */
export function fromApiBlocks(raw: unknown): Block[] {
  if (!Array.isArray(raw)) return []

  const blocks: Block[] = []
  for (const item of raw) {
    const block = blockFromApi(item)
    if (block) blocks.push(block)
  }
  return blocks
}
