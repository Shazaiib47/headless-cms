<?php

namespace Tests\Unit;

use App\Support\Markdown;
use PHPUnit\Framework\TestCase;

class MarkdownTest extends TestCase
{
    public function test_renders_basic_formatting(): void
    {
        $html = Markdown::toHtml("Hello **bold** and _italic_\n\n- one\n- two");

        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringContainsString('<em>italic</em>', $html);
        $this->assertStringContainsString('<li>one</li>', $html);
    }

    public function test_strips_raw_html(): void
    {
        $html = Markdown::toHtml(
            "<script>alert(1)</script>\n\nhi <img src=x onerror=alert(2)> there <b onclick=\"x()\">bold</b>"
        );

        foreach (['<script', '<img', 'onerror', 'onclick', '<b'] as $needle) {
            $this->assertStringNotContainsString($needle, $html);
        }
        $this->assertStringContainsString('hi', $html);
    }

    public function test_neutralises_dangerous_link_schemes(): void
    {
        $html = Markdown::toHtml(
            '[a](javascript:alert(1)) [b](data:text/html;base64,PHNjcmlwdD4=) [c](vbscript:x)'
        );

        foreach (['javascript:', 'data:', 'vbscript:'] as $needle) {
            $this->assertStringNotContainsString($needle, $html);
        }
    }

    public function test_images_are_replaced_by_their_alt_text(): void
    {
        $html = Markdown::toHtml('![a cat](https://evil.example/pixel.png)');

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('evil.example', $html);
        $this->assertStringContainsString('a cat', $html);
    }

    public function test_keeps_safe_links(): void
    {
        $html = Markdown::toHtml('[Home](/about) and [Shop](https://example.com/shop)');

        $this->assertStringContainsString('<a href="/about">Home</a>', $html);
        $this->assertStringContainsString('<a href="https://example.com/shop">Shop</a>', $html);
    }

    public function test_survives_pathological_input(): void
    {
        $this->assertIsString(Markdown::toHtml(str_repeat('> ', 2000).'deep'));
        $this->assertIsString(Markdown::toHtml(str_repeat('[', 5000)));
        $this->assertIsString(Markdown::toHtml(str_repeat('*a ', 5000)));
    }
}
