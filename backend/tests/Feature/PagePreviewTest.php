<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_receive_401(): void
    {
        $page = Page::factory()->create();

        $this->getJson("/api/pages/{$page->id}/preview")->assertUnauthorized();
    }

    public function test_every_signed_in_role_can_preview_a_draft(): void
    {
        $page = Page::factory()->create(['title' => 'Work in progress']);

        foreach ([User::factory()->viewer()->create(), User::factory()->editor()->create(), User::factory()->admin()->create()] as $user) {
            $this->actingAs($user)
                ->getJson("/api/pages/{$page->id}/preview")
                ->assertOk()
                ->assertJsonPath('data.title', 'Work in progress')
                ->assertJsonPath('data.status', 'draft');
        }
    }

    public function test_a_published_page_previews_with_its_status(): void
    {
        $page = Page::factory()->published()->create();

        $this->actingAs(User::factory()->viewer()->create())
            ->getJson("/api/pages/{$page->id}/preview")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');
    }

    public function test_previews_are_never_cached_or_indexed(): void
    {
        $page = Page::factory()->create();

        $response = $this->actingAs(User::factory()->editor()->create())
            ->getJson("/api/pages/{$page->id}/preview")
            ->assertOk();

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_previews_use_the_public_shape_and_hide_internals(): void
    {
        $page = Page::factory()->create(['content' => [[
            'id' => 'a',
            'type' => 'rich_text',
            'data' => ['markdown' => 'source', 'html' => '<p>source</p>'],
        ]]]);

        $response = $this->actingAs(User::factory()->editor()->create())
            ->getJson("/api/pages/{$page->id}/preview")
            ->assertOk();

        $this->assertSame(
            ['title', 'slug', 'content', 'meta_title', 'meta_description', 'og_image_id', 'og_image', 'published_at', 'updated_at', 'status'],
            array_keys($response->json('data')),
        );
    }

    public function test_previewing_a_missing_page_returns_404(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/pages/999999/preview')
            ->assertNotFound();
    }
}
