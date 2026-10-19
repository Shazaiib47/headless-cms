<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPageApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_published_page_is_available_without_logging_in(): void
    {
        Page::factory()->published()->create(['slug' => 'about', 'title' => 'About us']);

        $this->getJson('/api/public/pages/about')
            ->assertOk()
            ->assertJsonPath('data.title', 'About us')
            ->assertJsonPath('data.slug', 'about')
            ->assertJsonStructure(['data' => ['content']]);
    }

    public function test_only_public_fields_are_exposed(): void
    {
        Page::factory()->published()->create(['slug' => 'about']);

        $data = $this->getJson('/api/public/pages/about')->assertOk()->json('data');

        $this->assertSame(
            ['title', 'slug', 'content', 'meta_title', 'meta_description', 'og_image_id', 'published_at', 'updated_at'],
            array_keys($data),
        );
    }

    public function test_rich_text_exposes_html_but_never_the_markdown_source(): void
    {
        Page::factory()->published()->create(['slug' => 'rt', 'content' => [[
            'id' => 'a',
            'type' => 'rich_text',
            'data' => ['markdown' => '**secret source**', 'html' => '<p><strong>secret source</strong></p>'],
        ]]]);

        $response = $this->getJson('/api/public/pages/rt')->assertOk();

        $this->assertSame(['html'], array_keys($response->json('data.content.0.data')));
        $this->assertStringNotContainsString('"markdown"', $response->getContent());
    }

    public function test_drafts_return_404_even_for_signed_in_users(): void
    {
        Page::factory()->create(['slug' => 'secret-draft']);

        $this->getJson('/api/public/pages/secret-draft')->assertNotFound();

        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/public/pages/secret-draft')
            ->assertNotFound();
    }

    public function test_an_unpublished_page_stops_being_public(): void
    {
        $page = Page::factory()->published()->create(['slug' => 'live']);

        $this->getJson('/api/public/pages/live')->assertOk();

        $this->actingAs(User::factory()->editor()->create())
            ->postJson("/api/pages/{$page->id}/unpublish")
            ->assertOk();

        $this->getJson('/api/public/pages/live')->assertNotFound();
    }

    public function test_drafts_and_unknown_pages_are_indistinguishable(): void
    {
        Page::factory()->create(['slug' => 'draft-page']);

        $draft = $this->getJson('/api/public/pages/draft-page')->assertNotFound();
        $missing = $this->getJson('/api/public/pages/does-not-exist')->assertNotFound();

        $this->assertSame($draft->json('message'), $missing->json('message'));
    }

    public function test_malformed_slugs_return_404(): void
    {
        Page::factory()->published()->create(['slug' => 'about']);

        foreach (['About', 'a_b', 'a.b', 'a%20b'] as $slug) {
            $this->getJson("/api/public/pages/{$slug}")->assertNotFound();
        }
    }

    public function test_responses_carry_an_etag_and_unchanged_pages_return_304(): void
    {
        Page::factory()->published()->create(['slug' => 'about']);

        $etag = $this->getJson('/api/public/pages/about')->assertOk()->headers->get('ETag');
        $this->assertNotEmpty($etag);

        $response = $this->getJson('/api/public/pages/about', ['If-None-Match' => $etag])
            ->assertStatus(304);

        $this->assertSame('', $response->getContent());
    }

    public function test_the_etag_changes_when_the_page_changes(): void
    {
        $page = Page::factory()->published()->create(['slug' => 'about']);

        $old = $this->getJson('/api/public/pages/about')->headers->get('ETag');

        $page->update(['title' => 'A different title']);

        $response = $this->getJson('/api/public/pages/about', ['If-None-Match' => $old])->assertOk();

        $this->assertNotSame($old, $response->headers->get('ETag'));
    }

    public function test_caches_must_revalidate_every_time(): void
    {
        Page::factory()->published()->create(['slug' => 'about']);

        $cache = $this->getJson('/api/public/pages/about')->headers->get('Cache-Control');

        $this->assertStringContainsString('public', $cache);
        $this->assertStringContainsString('no-cache', $cache);
        $this->assertStringNotContainsString('private', $cache);
    }

    public function test_requests_from_the_frontend_origin_never_start_a_session(): void
    {
        Page::factory()->published()->create(['slug' => 'home']);

        $this->withHeader('Origin', 'http://localhost:5173')
            ->getJson('/api/public/pages/home')
            ->assertOk()
            ->assertHeaderMissing('Set-Cookie');
    }

    public function test_the_endpoint_is_rate_limited(): void
    {
        Page::factory()->published()->create(['slug' => 'busy']);

        for ($i = 0; $i < 120; $i++) {
            $this->getJson('/api/public/pages/busy')->assertOk();
        }

        $this->getJson('/api/public/pages/busy')->assertStatus(429);
    }

    public function test_a_deleted_page_returns_404(): void
    {
        $page = Page::factory()->published()->create(['slug' => 'gone']);

        $this->actingAs(User::factory()->admin()->create())
            ->deleteJson("/api/pages/{$page->id}")
            ->assertNoContent();

        $this->getJson('/api/public/pages/gone')->assertNotFound();
    }

    public function test_edits_to_a_published_page_are_public_straight_away(): void
    {
        $page = Page::factory()->published()->create(['slug' => 'about', 'title' => 'Before']);

        $this->actingAs(User::factory()->editor()->create())
            ->patchJson("/api/pages/{$page->id}", ['title' => 'After'])
            ->assertOk();

        $this->getJson('/api/public/pages/about')->assertJsonPath('data.title', 'After');
    }
}
