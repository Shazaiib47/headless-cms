<?php

namespace App\Support\Blocks;

use App\Enums\BlockType;

/** Reduces stored blocks to what the public may see. */
final class PublicBlocks
{
    /**
     * @param  array<int, array>|null  $images  Resolved images keyed by media id. When
     *                                          null, no image data is added to blocks.
     */
    public static function from(array $blocks, ?array $images = null): array
    {
        $result = [];

        foreach ($blocks as $block) {
            if (! is_array($block) || ! isset($block['id'], $block['type'])) {
                continue;
            }

            $data = is_array($block['data'] ?? null) ? $block['data'] : [];
            $type = BlockType::tryFrom((string) $block['type']);

            if ($type === BlockType::RichText) {
                // Rich text exposes only the generated HTML, never the Markdown source.
                $data = ['html' => is_string($data['html'] ?? null) ? $data['html'] : ''];
            } elseif ($images !== null) {
                $data = self::withImages($type, $data, $images);
            }

            $result[] = ['id' => $block['id'], 'type' => $block['type'], 'data' => $data];
        }

        return $result;
    }

    /** The media ids referenced by hero, image and card blocks, in order, without duplicates. */
    public static function imageIds(array $blocks): array
    {
        $ids = [];

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $data = is_array($block['data'] ?? null) ? $block['data'] : [];
            $type = BlockType::tryFrom((string) ($block['type'] ?? ''));

            if ($type === BlockType::Hero || $type === BlockType::Image) {
                $ids[] = $data['image_id'] ?? null;
            }

            if ($type === BlockType::CardGrid && is_array($data['cards'] ?? null)) {
                foreach ($data['cards'] as $card) {
                    $ids[] = is_array($card) ? ($card['image_id'] ?? null) : null;
                }
            }
        }

        return array_values(array_unique(array_filter($ids, fn ($id) => is_int($id) && $id > 0)));
    }

    private static function withImages(?BlockType $type, array $data, array $images): array
    {
        return match ($type) {
            BlockType::Hero, BlockType::Image => [
                ...$data,
                'image' => self::lookup($images, $data['image_id'] ?? null),
            ],
            BlockType::CardGrid => [
                ...$data,
                'cards' => array_map(
                    fn ($card) => is_array($card)
                        ? [...$card, 'image' => self::lookup($images, $card['image_id'] ?? null)]
                        : $card,
                    is_array($data['cards'] ?? null) ? $data['cards'] : [],
                ),
            ],
            default => $data,
        };
    }

    private static function lookup(array $images, mixed $id): ?array
    {
        return is_int($id) && isset($images[$id]) ? $images[$id] : null;
    }
}
