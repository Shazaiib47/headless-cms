import type { PageStatus } from '@/types/api'
import type { CardGridData, CtaData, HeroData, ImageBlockData } from '@/types/blocks'

export interface RichTextRenderData {
  /** Server-generated, sanitised HTML. The Markdown source is never sent publicly. */
  html: string
}

export type RenderBlock =
  | { id: string; type: 'hero'; data: HeroData }
  | { id: string; type: 'rich_text'; data: RichTextRenderData }
  | { id: string; type: 'image'; data: ImageBlockData }
  | { id: string; type: 'cta'; data: CtaData }
  | { id: string; type: 'card_grid'; data: CardGridData }

/** A page as returned by the preview endpoint (the public shape plus its status). */
export interface PreviewPage {
  title: string
  slug: string
  status: PageStatus
  content: unknown
  meta_title: string | null
  meta_description: string | null
  og_image_id: number | null
  published_at: string | null
  updated_at: string
}
