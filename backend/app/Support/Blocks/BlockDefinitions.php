<?php

namespace App\Support\Blocks;

use App\Enums\BlockType;
use App\Rules\SafeUrl;
use App\Support\Markdown;
use Closure;
use Illuminate\Validation\Rule;

/**
 * The single source of truth for what each content block may contain:
 * validation rules, plus normalisation that rebuilds every block from an
 * allowlist of fields so nothing undeclared is ever stored.
 */
final class BlockDefinitions
{
    public const MAX_BLOCKS = 50;

    public const MAX_CARDS = 12;

    public const CTA_STYLES = ['primary', 'secondary'];

    /** Validation rules for the whole `content` array and every block in it. */
    public static function contentRules(mixed $content): array
    {
        $rules = [
            'content' => ['sometimes', 'array', 'max:'.self::MAX_BLOCKS, self::mustBeList()],
            'content.*' => ['array'],
            'content.*.id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/', 'distinct'],
            'content.*.type' => ['required', Rule::enum(BlockType::class)],
            'content.*.data' => ['present', 'array'],
        ];

        if (! is_array($content)) {
            return $rules;
        }

        foreach (array_slice($content, 0, self::MAX_BLOCKS, true) as $index => $block) {
            if (! is_array($block) || ! is_string($block['type'] ?? null)) {
                continue;
            }

            $type = BlockType::tryFrom($block['type']);

            if ($type === null) {
                continue;
            }

            $data = is_array($block['data'] ?? null) ? $block['data'] : [];
            $rules += self::dataRules($type, "content.{$index}.data", $data);
        }

        return $rules;
    }

    /** Rebuilds validated blocks from allowlisted fields only. */
    public static function normalizeContent(array $content): array
    {
        return array_map(self::normalizeBlock(...), array_values($content));
    }

    private static function dataRules(BlockType $type, string $p, array $data): array
    {
        $mediaId = ['nullable', 'integer', 'exists:media,id'];

        return match ($type) {
            BlockType::Hero => [
                "{$p}.heading" => ['required', 'string', 'max:150'],
                "{$p}.subheading" => ['nullable', 'string', 'max:300'],
                "{$p}.image_id" => $mediaId,
                "{$p}.button_label" => ['nullable', 'string', 'max:60', "required_with:{$p}.button_url"],
                "{$p}.button_url" => ['nullable', new SafeUrl, "required_with:{$p}.button_label"],
            ],
            BlockType::RichText => [
                "{$p}.markdown" => ['required', 'string', 'max:20000'],
            ],
            BlockType::Image => [
                "{$p}.image_id" => $mediaId,
                "{$p}.alt" => ['required', 'string', 'max:200'],
                "{$p}.caption" => ['nullable', 'string', 'max:300'],
            ],
            BlockType::Cta => [
                "{$p}.heading" => ['required', 'string', 'max:150'],
                "{$p}.text" => ['nullable', 'string', 'max:300'],
                "{$p}.button_label" => ['required', 'string', 'max:60'],
                "{$p}.button_url" => ['required', new SafeUrl],
                "{$p}.style" => ['nullable', Rule::in(self::CTA_STYLES)],
            ],
            BlockType::CardGrid => [
                "{$p}.heading" => ['nullable', 'string', 'max:150'],
                "{$p}.cards" => ['required', 'array', 'min:1', 'max:'.self::MAX_CARDS, self::mustBeList()],
                ...self::cardRules("{$p}.cards", $data['cards'] ?? null),
            ],
        };
    }

    private static function cardRules(string $p, mixed $cards): array
    {
        if (! is_array($cards)) {
            return [];
        }

        $rules = [];

        foreach (array_keys(array_slice($cards, 0, self::MAX_CARDS, true)) as $i) {
            $card = "{$p}.{$i}";

            $rules += [
                $card => ['array'],
                "{$card}.title" => ['required', 'string', 'max:100'],
                "{$card}.text" => ['nullable', 'string', 'max:300'],
                "{$card}.image_id" => ['nullable', 'integer', 'exists:media,id'],
                "{$card}.link_label" => ['nullable', 'string', 'max:60', "required_with:{$card}.link_url"],
                "{$card}.link_url" => ['nullable', new SafeUrl, "required_with:{$card}.link_label"],
            ];
        }

        return $rules;
    }

    private static function normalizeBlock(array $block): array
    {
        $type = BlockType::from($block['type']);
        $data = is_array($block['data'] ?? null) ? $block['data'] : [];

        return [
            'id' => (string) $block['id'],
            'type' => $type->value,
            'data' => match ($type) {
                BlockType::Hero => [
                    'heading' => self::text($data, 'heading'),
                    'subheading' => self::text($data, 'subheading'),
                    'image_id' => self::mediaId($data, 'image_id'),
                    'button_label' => self::text($data, 'button_label'),
                    'button_url' => self::text($data, 'button_url'),
                ],
                BlockType::RichText => self::richText($data),
                BlockType::Image => [
                    'image_id' => self::mediaId($data, 'image_id'),
                    'alt' => self::text($data, 'alt'),
                    'caption' => self::text($data, 'caption'),
                ],
                BlockType::Cta => [
                    'heading' => self::text($data, 'heading'),
                    'text' => self::text($data, 'text'),
                    'button_label' => self::text($data, 'button_label'),
                    'button_url' => self::text($data, 'button_url'),
                    'style' => in_array(self::text($data, 'style'), self::CTA_STYLES, true)
                        ? self::text($data, 'style')
                        : 'primary',
                ],
                BlockType::CardGrid => [
                    'heading' => self::text($data, 'heading'),
                    'cards' => self::cards($data['cards'] ?? []),
                ],
            },
        ];
    }

    private static function richText(array $data): array
    {
        $markdown = str_replace(["\r\n", "\r"], "\n", self::text($data, 'markdown') ?? '');

        // Any html sent by the client is ignored: it is always regenerated here.
        return ['markdown' => $markdown, 'html' => Markdown::toHtml($markdown)];
    }

    private static function cards(mixed $cards): array
    {
        if (! is_array($cards)) {
            return [];
        }

        $result = [];

        foreach (array_slice(array_values($cards), 0, self::MAX_CARDS) as $card) {
            if (! is_array($card)) {
                continue;
            }

            $result[] = [
                'title' => self::text($card, 'title'),
                'text' => self::text($card, 'text'),
                'image_id' => self::mediaId($card, 'image_id'),
                'link_label' => self::text($card, 'link_label'),
                'link_url' => self::text($card, 'link_url'),
            ];
        }

        return $result;
    }

    private static function text(array $data, string $key): ?string
    {
        return isset($data[$key]) && is_string($data[$key]) ? $data[$key] : null;
    }

    private static function mediaId(array $data, string $key): ?int
    {
        return isset($data[$key]) && is_numeric($data[$key]) ? (int) $data[$key] : null;
    }

    /** JSON objects with string keys must not pass as lists. */
    private static function mustBeList(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_array($value) && ! array_is_list($value)) {
                $fail('The :attribute field must be a list.');
            }
        };
    }
}
