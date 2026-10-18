import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import PaginationBar from '../PaginationBar.vue'
import type { PaginationMeta } from '@/types/api'

function meta(overrides: Partial<PaginationMeta> = {}): PaginationMeta {
  return { current_page: 1, last_page: 3, per_page: 10, total: 25, from: 1, to: 10, ...overrides }
}

describe('PaginationBar', () => {
  it('shows the visible range and the total', () => {
    const wrapper = mount(PaginationBar, { props: { meta: meta() } })

    expect(wrapper.text()).toContain('Showing 1 to 10 of 25')
    expect(wrapper.text()).toContain('Page 1 of 3')
  })

  it('disables Previous on the first page', () => {
    const wrapper = mount(PaginationBar, { props: { meta: meta() } })
    const buttons = wrapper.findAll('button')

    expect(buttons[0]?.attributes('disabled')).toBeDefined()
    expect(buttons[1]?.attributes('disabled')).toBeUndefined()
  })

  it('emits the next page number', async () => {
    const wrapper = mount(PaginationBar, { props: { meta: meta() } })

    await wrapper.findAll('button')[1]?.trigger('click')

    expect(wrapper.emitted('change')).toEqual([[2]])
  })

  it('hides the paging buttons when everything fits on one page', () => {
    const wrapper = mount(PaginationBar, {
      props: { meta: meta({ last_page: 1, total: 4, to: 4 }) },
    })

    expect(wrapper.findAll('button')).toHaveLength(0)
  })
})
