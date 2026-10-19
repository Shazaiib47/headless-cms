<?php

namespace Tests\Unit;

use App\Support\Blocks\PublicBlocks;
use PHPUnit\Framework\TestCase;

class PublicBlocksImagesTest extends TestCase
{
    public function test_adds_resolved_images_when_a_map_is_given(): void
    {
        $image = [
            'url' => 'https://cdn.example/a.jpg',
            'thumbnail_url' => 'https://cdn.example/t/a.jpg',
            'width' => 100,
            'height' => 50,
            'alt' => 'A cat',
        ];

        $result = PublicBlocks::from([
            ['id' => 'h', 'type' => 'hero', 'data' => ['heading' => 'Hi', 'image_id' => 7]],
            ['id' => 'i', 'type' => 'image', 'data' => ['image_id' => 7, 'alt' => 'x']],
            ['id' => 'c', 'type' => 'card_grid', 'data' => ['cards' => [
                ['title' => 'A', 'image_id' => 7],
                ['title' => 'B', 'image_id' => null],
            ]]],
            ['id' => 'u', 'type' => 'cta', 'data' => ['heading' => 'Join']],
        ], [7 => $image]);

        $this->assertSame($image, $result[0]['data']['image']);
        $this->assertSame($image, $result[1]['data']['image']);
        $this->assertSame($image, $result[2]['data']['cards'][0]['image']);
        $this->assertNull($result[2]['data']['cards'][1]['image']);
        $this->assertArrayNotHasKey('image', $result[3]['data']);
    }

    public function test_adds_no_image_data_without_a_map(): void
    {
        $result = PublicBlocks::from([
            ['id' => 'h', 'type' => 'hero', 'data' => ['heading' => 'Hi', 'image_id' => 7]],
        ]);

        $this->assertArrayNotHasKey('image', $result[0]['data']);
    }

    public function test_collects_image_ids_from_hero_image_and_card_blocks_only(): void
    {
        $ids = PublicBlocks::imageIds([
            ['id' => 'a', 'type' => 'hero', 'data' => ['image_id' => 3]],
            ['id' => 'b', 'type' => 'hero', 'data' => ['image_id' => null]],
            ['id' => 'c', 'type' => 'image', 'data' => ['image_id' => 5]],
            ['id' => 'd', 'type' => 'rich_text', 'data' => ['image_id' => 9]],
            ['id' => 'e', 'type' => 'card_grid', 'data' => ['cards' => [
                ['image_id' => 4],
                ['image_id' => '7'],
                ['image_id' => null],
                ['image_id' => 3],
            ]]],
            'not a block',
        ]);

        $this->assertSame([3, 5, 4], $ids);
    }
}
