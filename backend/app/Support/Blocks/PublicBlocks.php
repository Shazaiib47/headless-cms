<?php

namespace App\Support\Blocks;

use App\Enums\BlockType;

/** Reduces stored blocks to what the public may see. */
final class PublicBlocks
{
    public static function from(array $blocks): array
    {
        $result = [];

        foreach ($blocks as $block) {
            if (! is_array($block) || ! isset($block['id'], $block['type'])) {
                continue;
            }

            $data = is_array($block['data'] ?? null) ? $block['data'] : [];

            // Rich text exposes only the generated HTML, never the Markdown source.
            if ($block['type'] === BlockType::RichText->value) {
                $data = ['html' => is_string($data['html'] ?? null) ? $data['html'] : ''];
            }

            $result[] = ['id' => $block['id'], 'type' => $block['type'], 'data' => $data];
        }

        return $result;
    }
}
