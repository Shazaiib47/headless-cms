import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import BlockRenderer from '../BlockRenderer.vue'
import type { RenderBlock } from '@/types/render'

function render(blocks: RenderBlock[]) {
  return mount(BlockRenderer, { props: { blocks } })
}

function hero(overrides: Partial<{ button_label: string; button_url: string }> = {}): RenderBlock {
  return {
    id: 'h',
    type: 'hero',
    data: {
      heading: 'Welcome',
      subheading: 'Fresh stock',
      image_id: null,
      button_label: 'Shop now',
      button_url: '/shop',
      ...overrides,
    },
  }
}

function cta(style: 'primary' | 'secondary', url = '/join'): RenderBlock {
  return {
    id: 'c',
    type: 'cta',
    data: { heading: 'Join us', text: 'Today', button_label: 'Sign up', button_url: url, style },
  }
}

describe('BlockRenderer', () => {
  it('renders a hero with its heading, subheading and button', () => {
    const wrapper = render([hero()])

    expect(wrapper.find('h2').text()).toBe('Welcome')
    expect(wrapper.text()).toContain('Fresh stock')
    expect(wrapper.find('a').attributes('href')).toBe('/shop')
    expect(wrapper.find('a').text()).toBe('Shop now')
  })

  it('renders the server-generated rich text html', () => {
    const wrapper = render([
      { id: 'r', type: 'rich_text', data: { html: '<p>Hello <strong>world</strong></p>' } },
    ])

    expect(wrapper.find('strong').text()).toBe('world')
  })

  it('styles the call-to-action button by its style', () => {
    const primary = render([cta('primary')]).find('a')
    const secondary = render([cta('secondary')]).find('a')

    expect(primary.classes()).toContain('bg-slate-900')
    expect(secondary.classes()).toContain('border-slate-900')
    expect(secondary.classes()).not.toContain('bg-slate-900')
  })

  it('renders every card, and links only where a card has one', () => {
    const wrapper = render([
      {
        id: 'g',
        type: 'card_grid',
        data: {
          heading: 'Our range',
          cards: [
            { title: 'One', text: 'First', image_id: null, link_label: 'More', link_url: '/one' },
            { title: 'Two', text: '', image_id: null, link_label: '', link_url: '' },
          ],
        },
      },
    ])

    expect(wrapper.findAll('h3').map((heading) => heading.text())).toEqual(['One', 'Two'])
    expect(wrapper.findAll('a')).toHaveLength(1)
    expect(wrapper.find('a').attributes('href')).toBe('/one')
  })

  it('shows an image block as a labelled placeholder with its caption', () => {
    const wrapper = render([
      { id: 'i', type: 'image', data: { image_id: null, alt: 'A cat', caption: 'Cute' } },
    ])

    expect(wrapper.find('[role="img"]').attributes('aria-label')).toBe('A cat')
    expect(wrapper.find('figcaption').text()).toBe('Cute')
  })

  it('never renders an unsafe URL as a link', () => {
    const wrapper = render([hero({ button_url: 'javascript:alert(1)' })])

    expect(wrapper.find('a').exists()).toBe(false)
    expect(wrapper.text()).toContain('Shop now')
    expect(wrapper.html()).not.toContain('javascript:')
  })

  it('marks external links to open safely, but not internal ones', () => {
    const external = render([cta('primary', 'https://example.com/join')]).find('a')
    const internal = render([cta('primary', '/join')]).find('a')

    expect(external.attributes('rel')).toBe('noopener noreferrer')
    expect(internal.attributes('rel')).toBeUndefined()
  })

  it('says so when a page has no content', () => {
    const wrapper = render([])

    expect(wrapper.text()).toContain('This page has no content yet.')
  })
})
