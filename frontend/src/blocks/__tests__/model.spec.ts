import { describe, expect, it } from 'vitest'
import {
  BLOCK_TYPES,
  cloneBlock,
  createBlock,
  emptyCard,
  fromApiBlocks,
  moveItem,
  serializeBlocks,
} from '@/blocks/model'

describe('block model', () => {
  it('creates a blank block of every type, each with a unique id', () => {
    const blocks = BLOCK_TYPES.map((type) => createBlock(type))

    expect(blocks.map((block) => block.type)).toEqual([...BLOCK_TYPES])
    expect(new Set(blocks.map((block) => block.id)).size).toBe(BLOCK_TYPES.length)
  })

  it('starts a card grid with one empty card', () => {
    const block = createBlock('card_grid')
    if (block.type !== 'card_grid') throw new Error('unexpected block type')

    expect(block.data.cards).toEqual([emptyCard()])
  })

  it('turns API nulls into empty strings', () => {
    const blocks = fromApiBlocks([
      {
        id: 'a',
        type: 'hero',
        data: {
          heading: 'Hi',
          subheading: null,
          image_id: 4,
          button_label: null,
          button_url: null,
        },
      },
    ])

    expect(blocks).toEqual([
      {
        id: 'a',
        type: 'hero',
        data: { heading: 'Hi', subheading: '', image_id: 4, button_label: '', button_url: '' },
      },
    ])
  })

  it('keeps only the Markdown source of rich text', () => {
    const blocks = fromApiBlocks([
      {
        id: 'r',
        type: 'rich_text',
        data: { markdown: '**x**', html: '<p><strong>x</strong></p>' },
      },
    ])

    expect(blocks).toEqual([{ id: 'r', type: 'rich_text', data: { markdown: '**x**' } }])
  })

  it('skips unknown types and malformed entries', () => {
    const blocks = fromApiBlocks([
      'nope',
      null,
      { id: 'x', type: 'script', data: {} },
      { id: 'ok', type: 'rich_text', data: { markdown: 'a' } },
    ])

    expect(blocks).toHaveLength(1)
    expect(fromApiBlocks('nope')).toEqual([])
  })

  it('defaults the call-to-action style to primary', () => {
    const [block] = fromApiBlocks([{ id: 'c', type: 'cta', data: { style: 'bogus' } }])

    expect(block?.type === 'cta' && block.data.style).toBe('primary')
  })

  it('serialises to an independent plain copy', () => {
    const blocks = [createBlock('hero')]
    const copy = serializeBlocks(blocks)

    expect(copy).toEqual(blocks)
    expect(copy[0]).not.toBe(blocks[0])
  })

  it('moves items and ignores out-of-range moves', () => {
    expect(moveItem(['a', 'b', 'c'], 1, -1)).toEqual(['b', 'a', 'c'])
    expect(moveItem(['a', 'b', 'c'], 1, 1)).toEqual(['a', 'c', 'b'])
    expect(moveItem(['a', 'b', 'c'], 0, -1)).toEqual(['a', 'b', 'c'])
    expect(moveItem(['a', 'b', 'c'], 2, 1)).toEqual(['a', 'b', 'c'])
  })

  it('clones a block with a new id and independent data', () => {
    const original = createBlock('card_grid')
    const copy = cloneBlock(original)
    if (original.type !== 'card_grid' || copy.type !== 'card_grid') {
      throw new Error('unexpected block type')
    }

    copy.data.cards.push(emptyCard())

    expect(copy.id).not.toBe(original.id)
    expect(original.data.cards).toHaveLength(1)
  })
})
