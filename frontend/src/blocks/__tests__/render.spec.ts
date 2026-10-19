import { describe, expect, it } from 'vitest'
import { toRenderBlocks } from '@/blocks/render'

describe('toRenderBlocks', () => {
  it('keeps only the generated html of rich text', () => {
    const blocks = toRenderBlocks([
      { id: 'r', type: 'rich_text', data: { html: '<p>Hi</p>', markdown: '**x**' } },
    ])

    expect(blocks).toEqual([{ id: 'r', type: 'rich_text', data: { html: '<p>Hi</p>' } }])
  })

  it('turns nulls from the API into empty strings for the other blocks', () => {
    const blocks = toRenderBlocks([
      {
        id: 'h',
        type: 'hero',
        data: {
          heading: 'Hi',
          subheading: null,
          image_id: null,
          button_label: null,
          button_url: null,
        },
      },
    ])

    expect(blocks).toEqual([
      {
        id: 'h',
        type: 'hero',
        data: {
          heading: 'Hi',
          subheading: '',
          image_id: null,
          button_label: '',
          button_url: '',
          image: null,
        },
      },
    ])
  })

  it('skips unknown and malformed blocks', () => {
    const blocks = toRenderBlocks([
      'nope',
      null,
      { id: 'x', type: 'script', data: {} },
      { id: 'ok', type: 'rich_text', data: { html: '<p>a</p>' } },
    ])

    expect(blocks).toHaveLength(1)
    expect(blocks[0]?.id).toBe('ok')
  })

  it('gives empty html when rich text has none, and nothing for non-arrays', () => {
    expect(toRenderBlocks([{ id: 'a', type: 'rich_text', data: {} }])).toEqual([
      { id: 'a', type: 'rich_text', data: { html: '' } },
    ])
    expect(toRenderBlocks('nope')).toEqual([])
  })

  it('resolves images on hero, image and card blocks', () => {
    const image = {
      url: 'http://localhost:8000/storage/media/a.jpg',
      thumbnail_url: 'http://localhost:8000/storage/media/thumbs/a.jpg',
      width: 1200,
      height: 800,
      alt: 'A cat',
    }

    const blocks = toRenderBlocks([
      { id: 'h', type: 'hero', data: { heading: 'Hi', image_id: 1, image } },
      { id: 'i', type: 'image', data: { image_id: 1, alt: 'x', image } },
      {
        id: 'g',
        type: 'card_grid',
        data: {
          cards: [
            { title: 'One', image_id: 1, image },
            { title: 'Two', image_id: null, image: null },
          ],
        },
      },
    ])

    expect(blocks[0]).toMatchObject({ type: 'hero', data: { image } })
    expect(blocks[1]).toMatchObject({ type: 'image', data: { image } })
    expect(blocks[2]).toMatchObject({
      type: 'card_grid',
      data: { cards: [{ image }, { image: null }] },
    })
  })

  it('drops images that do not have a safe address', () => {
    const blocks = toRenderBlocks([
      {
        id: 'h',
        type: 'hero',
        data: {
          heading: 'Hi',
          image: {
            url: 'javascript:alert(1)',
            thumbnail_url: 'data:image/png;base64,AAAA',
            width: 10,
            height: 10,
            alt: null,
          },
        },
      },
    ])

    expect(blocks[0]).toMatchObject({ data: { image: null } })
  })

  it('treats missing or malformed image data as no image', () => {
    const blocks = toRenderBlocks([
      { id: 'a', type: 'hero', data: { heading: 'No image key' } },
      { id: 'b', type: 'hero', data: { heading: 'String', image: 'nope' } },
      { id: 'c', type: 'image', data: { alt: 'x', image: { url: 5 } } },
    ])

    expect(blocks).toHaveLength(3)
    for (const block of blocks) {
      expect(block).toMatchObject({ data: { image: null } })
    }
  })
})
