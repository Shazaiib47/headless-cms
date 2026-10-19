import type { PageStatus } from '@/types/api'
import type { CardData, CardGridData, CtaData, HeroData, ImageBlockData } from '@/types/blocks'

/** An image as the public and preview APIs describe it (resolved from its media id). */
export interface PublicImage {
  url: string
  thumbnail_url: string | null
  width: number | null
  height: number | null
  alt: string | null
}

interface WithImage {
  image?: PublicImage | null
}

export interface RichTextRenderData {
  /** Server-generated, sanitised HTML. The Markdown source is never sent publicly. */
  html: string
}

export type HeroRenderData = HeroData & WithImage
export type ImageRenderData = ImageBlockData & WithImage
export type CardGridRenderData = Omit<CardGridData, 'cards'> & {
  cards: Array<CardData & WithImage>
}

export type RenderBlock =
  | { id: string; type: 'hero'; data: HeroRenderData }
  | { id: string; type: 'rich_text'; data: RichTextRenderData }
  | { id: string; type: 'image'; data: ImageRenderData }
  | { id: string; type: 'cta'; data: CtaData }
  | { id: string; type: 'card_grid'; data: CardGridRenderData }

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
