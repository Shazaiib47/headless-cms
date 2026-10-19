import { afterEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import BlockEditor from '../BlockEditor.vue'
import type { ValidationErrors } from '@/api/http'
import type { Block } from '@/types/blocks'

function hero(id: string): Block {
  return {
    id,
    type: 'hero',
    data: {
      heading: `Heading ${id}`,
      subheading: '',
      image_id: null,
      button_label: '',
      button_url: '',
    },
  }
}

function text(id: string): Block {
  return { id, type: 'rich_text', data: { markdown: `Text ${id}` } }
}

function mountEditor(
  blocks: Block[],
  options: { errors?: ValidationErrors; readonly?: boolean } = {},
) {
  return mount(BlockEditor, {
    props: {
      modelValue: blocks,
      errors: options.errors ?? {},
      readonly: options.readonly ?? false,
    },
  })
}

function lastModel(wrapper: ReturnType<typeof mountEditor>): Block[] | undefined {
  const events = wrapper.emitted<[Block[]]>('update:modelValue')
  return events?.[events.length - 1]?.[0]
}

describe('BlockEditor', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('renders each block with its type and position', () => {
    const wrapper = mountEditor([hero('a'), text('b')])
    const cards = wrapper.findAll('section')

    expect(cards).toHaveLength(2)
    expect(cards[0]?.text()).toContain('Hero')
    expect(cards[0]?.text()).toContain('block 1 of 2')
    expect(cards[1]?.text()).toContain('Rich text')
    expect((wrapper.find('#a-heading').element as HTMLInputElement).value).toBe('Heading a')
  })

  it('adds a block of the chosen type', async () => {
    const wrapper = mountEditor([hero('a')])

    await wrapper
      .findAll('button')
      .find((button) => button.text() === '+ Call to action')
      ?.trigger('click')

    const model = lastModel(wrapper)
    expect(model).toHaveLength(2)
    expect(model?.[1]?.type).toBe('cta')
  })

  it('disables moving the first block up and the last block down', () => {
    const wrapper = mountEditor([hero('a'), text('b')])

    expect(wrapper.find('#a-up').attributes('disabled')).toBeDefined()
    expect(wrapper.find('#a-down').attributes('disabled')).toBeUndefined()
    expect(wrapper.find('#b-down').attributes('disabled')).toBeDefined()
  })

  it('moves a block down', async () => {
    const wrapper = mountEditor([hero('a'), text('b')])

    await wrapper.find('#a-down').trigger('click')

    expect(lastModel(wrapper)?.map((block) => block.id)).toEqual(['b', 'a'])
  })

  it('duplicates a block next to the original with a new id', async () => {
    const wrapper = mountEditor([hero('a'), text('b')])

    await wrapper.find('#a-duplicate').trigger('click')

    const model = lastModel(wrapper)
    expect(model).toHaveLength(3)
    expect(model?.[0]?.id).toBe('a')
    expect(model?.[1]?.id).not.toBe('a')
    expect(model?.[1]?.type).toBe('hero')
    expect(model?.[2]?.id).toBe('b')
  })

  it('removes a block after confirmation', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true)
    const wrapper = mountEditor([hero('a'), text('b')])

    await wrapper.find('#a-remove').trigger('click')

    expect(lastModel(wrapper)?.map((block) => block.id)).toEqual(['b'])
  })

  it('keeps the block when the confirmation is declined', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(false)
    const wrapper = mountEditor([hero('a'), text('b')])

    await wrapper.find('#a-remove').trigger('click')

    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('shows server errors on the block they belong to', () => {
    const wrapper = mountEditor([hero('a'), text('b')], {
      errors: { 'content.1.data.markdown': ['Content is required.'] },
    })
    const cards = wrapper.findAll('section')

    expect(cards[1]?.text()).toContain('Content is required.')
    expect(cards[0]?.text()).not.toContain('Content is required.')
    expect(wrapper.find('#b-markdown').attributes('aria-invalid')).toBe('true')
  })

  it('hides every editing control in read-only mode', () => {
    const wrapper = mountEditor([hero('a')], { readonly: true })

    expect(wrapper.find('#a-up').exists()).toBe(false)
    expect(wrapper.find('#a-remove').exists()).toBe(false)
    expect(wrapper.findAll('button').some((button) => button.text().startsWith('+ '))).toBe(false)
  })
})
