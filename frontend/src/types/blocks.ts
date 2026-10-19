export type BlockType = 'hero' | 'rich_text' | 'image' | 'cta' | 'card_grid'

export interface HeroData {
  heading: string
  subheading: string
  image_id: number | null
  button_label: string
  button_url: string
}

export interface RichTextData {
  markdown: string
}

export interface ImageBlockData {
  image_id: number | null
  alt: string
  caption: string
}

export type CtaStyle = 'primary' | 'secondary'

export interface CtaData {
  heading: string
  text: string
  button_label: string
  button_url: string
  style: CtaStyle
}

export interface CardData {
  title: string
  text: string
  image_id: number | null
  link_label: string
  link_url: string
}

export interface CardGridData {
  heading: string
  cards: CardData[]
}

export type Block =
  | { id: string; type: 'hero'; data: HeroData }
  | { id: string; type: 'rich_text'; data: RichTextData }
  | { id: string; type: 'image'; data: ImageBlockData }
  | { id: string; type: 'cta'; data: CtaData }
  | { id: string; type: 'card_grid'; data: CardGridData }
