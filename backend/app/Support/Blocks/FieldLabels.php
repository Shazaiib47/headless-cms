<?php

namespace App\Support\Blocks;

use Illuminate\Support\Str;

/** Human-readable names for content fields, used in validation messages. */
final class FieldLabels
{
    private const LABELS = [
        'content' => 'content',
        'id' => 'block ID',
        'type' => 'block type',
        'data' => 'block data',
        'heading' => 'heading',
        'subheading' => 'subheading',
        'image_id' => 'image',
        'button_label' => 'button label',
        'button_url' => 'button link',
        'markdown' => 'content',
        'alt' => 'alt text',
        'caption' => 'caption',
        'text' => 'text',
        'style' => 'button style',
        'cards' => 'cards',
        'title' => 'card title',
        'link_label' => 'card link label',
        'link_url' => 'card link',
    ];

    /** e.g. "content.3.data.cards.0.title" becomes "card title". */
    public static function for(string $key): string
    {
        $segments = explode('.', $key);
        $last = (string) array_pop($segments);

        // A list index (or wildcard) names the item itself: a block or a card.
        if (ctype_digit($last) || $last === '*') {
            return end($segments) === 'cards' ? 'card' : 'block';
        }

        return self::LABELS[$last] ?? Str::of($last)->replace('_', ' ')->toString();
    }
}
