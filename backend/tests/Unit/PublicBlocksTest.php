<?php

namespace Tests\Unit;

use App\Support\Blocks\PublicBlocks;
use PHPUnit\Framework\TestCase;

class PublicBlocksTest extends TestCase
{
    public function test_strips_the_markdown_source_from_rich_text(): void
    {
        $result = PublicBlocks::from([
            ['id' => 'a', 'type' => 'rich_text', 'data' => ['markdown' => '**x**', 'html' => '<p><strong>x</strong></p>']],
        ]);

        $this->assertSame(
            [['id' => 'a', 'type' => 'rich_text', 'data' => ['html' => '<p><strong>x</strong></p>']]],
            $result,
        );
    }

    public function test_leaves_other_block_data_unchanged(): void
    {
        $data = ['heading' => 'Hi', 'subheading' => null, 'image_id' => 3, 'button_label' => null, 'button_url' => null];

        $result = PublicBlocks::from([['id' => 'h', 'type' => 'hero', 'data' => $data]]);

        $this->assertSame($data, $result[0]['data']);
    }

    public function test_drops_block_level_extras_and_skips_malformed_blocks(): void
    {
        $result = PublicBlocks::from([
            'not a block',
            ['type' => 'hero', 'data' => []],
            ['id' => 'ok', 'type' => 'cta', 'data' => ['heading' => 'x'], 'secret' => 'leak'],
        ]);

        $this->assertCount(1, $result);
        $this->assertSame(['id', 'type', 'data'], array_keys($result[0]));
    }

    public function test_gives_empty_html_when_rich_text_has_none(): void
    {
        $result = PublicBlocks::from([['id' => 'a', 'type' => 'rich_text', 'data' => ['markdown' => 'only source']]]);

        $this->assertSame(['html' => ''], $result[0]['data']);
    }
}
