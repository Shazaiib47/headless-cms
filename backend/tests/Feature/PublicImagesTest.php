<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicImagesTest extends TestCase
{
    use RefreshDatabase;

    private function media(): Media
    {
        return Media::factory()->create([
            'path' => 'media/a.jpg',
            'thumbnail_path' => 'media/thumbs/a.jpg',
            'width' => 1200,
            'height' => 800,
            'alt_text' => 'A cat',
        ]);
    }

    private function gallery(Media $media): Page
    {
        return Page::factory()->published()->create([
            'slug' => 'gallery',
            'og_image_id' => $media->id,
            'content' => [
                ['id' => 'a', 'type' => 'hero', 'data' => ['heading' => 'Hi', 'image_id' => $media->id]],
                ['id' => 'b', 'type' => 'image', 'data' => ['image_id' => $media->id, 'alt' => 'Cat']],
                ['id' => 'c', 'type' => 'card_grid', 'data' => ['cards' => [
                    ['title' => 'One', 'image_id' => $media->id],
                    ['title' => 'Two', 'image_id' => null],
                ]]],
            ],
        ]);
    }

    public function test_the_public_api_resolves_images_in_every_block_and_the_social_image(): void
    {
        $media = $this->media();
        $this->gallery($media);

        $data = $this->getJson('/api/public/pages/gallery')->assertOk()->json('data');

        $this->assertSame(1200, $data['content'][0]['data']['image']['width']);
        $this->assertSame($media->id, $data['content'][0]['data']['image_id']);
        $this->assertStringContainsString('media/a.jpg', $data['content'][0]['data']['image']['url']);
        $this->assertStringContainsString('media/thumbs/a.jpg', $data['content'][0]['data']['image']['thumbnail_url']);
        $this->assertSame('A cat', $data['content'][1]['data']['image']['alt']);
        $this->assertSame(800, $data['content'][2]['data']['cards'][0]['image']['height']);
        $this->assertNull($data['content'][2]['data']['cards'][1]['image']);
        $this->assertStringContainsString('media/a.jpg', $data['og_image']['url']);
    }

    public function test_resolved_images_expose_only_public_fields(): void
    {
        $this->gallery($this->media());

        $image = $this->getJson('/api/public/pages/gallery')->json('data.content.0.data.image');

        $this->assertSame(['url', 'thumbnail_url', 'width', 'height', 'alt'], array_keys($image));
    }

    public function test_a_reference_to_a_missing_image_resolves_to_null(): void
    {
        Page::factory()->published()->create([
            'slug' => 'dangling',
            'og_image_id' => null,
            'content' => [['id' => 'a', 'type' => 'hero', 'data' => ['heading' => 'Hi', 'image_id' => 999999]]],
        ]);

        $this->getJson('/api/public/pages/dangling')
            ->assertOk()
            ->assertJsonPath('data.content.0.data.image', null);
    }

    public function test_pages_without_images_have_a_null_social_image(): void
    {
        Page::factory()->published()->create(['slug' => 'plain']);

        $this->getJson('/api/public/pages/plain')
            ->assertOk()
            ->assertJsonPath('data.og_image', null);
    }

    public function test_the_preview_resolves_images_too(): void
    {
        $page = $this->gallery($this->media());

        $this->actingAs(User::factory()->viewer()->create())
            ->getJson("/api/pages/{$page->id}/preview")
            ->assertOk()
            ->assertJsonPath('data.content.0.data.image.width', 1200);
    }
}
