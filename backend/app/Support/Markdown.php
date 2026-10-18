<?php

namespace App\Support;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\MarkdownConverter;

/** Converts editor Markdown to HTML that is safe to render on a public page. */
final class Markdown
{
    private static ?MarkdownConverter $converter = null;

    public static function toHtml(string $markdown): string
    {
        return trim(self::converter()->convert($markdown)->getContent());
    }

    private static function converter(): MarkdownConverter
    {
        if (self::$converter === null) {
            $environment = new Environment([
                'html_input' => 'strip',          // raw HTML is removed, not escaped
                'allow_unsafe_links' => false,    // blocks javascript:, data:, vbscript:
                'max_nesting_level' => 20,        // limits parse time on hostile input
                'max_delimiters_per_line' => 100,
            ]);
            $environment->addExtension(new CommonMarkCoreExtension);

            // Remote images would bypass the media library (tracking, mixed content).
            $environment->addRenderer(Image::class, new AltTextImageRenderer, 10);

            self::$converter = new MarkdownConverter($environment);
        }

        return self::$converter;
    }
}
