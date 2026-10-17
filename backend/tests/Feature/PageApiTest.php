<?php

namespace Tests\Feature;

use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Summer Sale',
            'slug' => 'summer-sale',
            'content' => [
                ['id' => 'block-1', 'type' => 'hero', 'data' => ['heading' => 'Summer Sale']],
            ],
            'meta_title' => 'Summer Sale',
            'meta_description' => 'Big savings on summer stock.',
        ], $overrides);
    }

    // ---- index ----

    public function test_index_returns_paginated_summaries_without_content(): void
    {
        Page::factory()->count(3)->create();

        $this->getJson('/api/pages')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'title', 'slug', 'status', 'published_at', 'created_at', 'updated_at']],
                'links',
                'meta',
            ])
            ->assertJsonMissingPath('data.0.content');
    }

    public function test_index_respects_per_page(): void
    {
        Page::factory()->count(5)->create();

        $this->getJson('/api/pages?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 5);
    }

    public function test_index_rejects_excessive_per_page(): void
    {
        $this->getJson('/api/pages?per_page=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_index_searches_title_and_slug(): void
    {
        Page::factory()->create(['title' => 'Summer sale', 'slug' => 'summer-sale']);
        Page::factory()->create(['title' => 'Winter coats', 'slug' => 'winter-coats']);

        $this->getJson('/api/pages?search=summer')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'summer-sale');

        $this->getJson('/api/pages?search=winter-coats')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'winter-coats');
    }

    public function test_index_filters_by_status(): void
    {
        Page::factory()->create(['slug' => 'draft-page']);
        Page::factory()->published()->create(['slug' => 'live-page']);

        $this->getJson('/api/pages?status=published')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'live-page');
    }

    public function test_index_rejects_invalid_status(): void
    {
        $this->getJson('/api/pages?status=archived')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_index_sorts_by_whitelisted_column(): void
    {
        Page::factory()->create(['title' => 'B page']);
        Page::factory()->create(['title' => 'A page']);

        $this->getJson('/api/pages?sort=title')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'A page');

        $this->getJson('/api/pages?sort=-title')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'B page');
    }

    public function test_index_rejects_non_whitelisted_sort(): void
    {
        $this->getJson('/api/pages?sort=content')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sort');
    }

    // ---- show ----

    public function test_show_returns_full_page_with_content(): void
    {
        $page = Page::factory()->create();

        $this->getJson("/api/pages/{$page->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $page->id)
            ->assertJsonPath('data.slug', $page->slug)
            ->assertJsonStructure(['data' => ['content', 'meta_title', 'meta_description', 'status']]);
    }

    public function test_show_returns_404_for_missing_page(): void
    {
        $this->getJson('/api/pages/999999')->assertNotFound();
    }

    // ---- store ----

    public function test_store_creates_a_draft_page(): void
    {
        $this->postJson('/api/pages', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.slug', 'summer-sale')
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('pages', ['slug' => 'summer-sale', 'status' => 'draft']);
    }

    public function test_store_ignores_status_and_client_supplied_audit_fields(): void
    {
        $this->postJson('/api/pages', $this->validPayload([
            'status' => 'published',
            'published_at' => now()->toIso8601String(),
            'created_by' => 99,
        ]))->assertCreated();

        $page = Page::firstOrFail();

        $this->assertSame(PageStatus::Draft, $page->status);
        $this->assertNull($page->published_at);
        $this->assertSame($this->admin->id, $page->created_by);
    }

    public function test_store_requires_title_and_slug(): void
    {
        $this->postJson('/api/pages', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'slug']);
    }

    public function test_store_rejects_invalid_slug_format(): void
    {
        $this->postJson('/api/pages', $this->validPayload(['slug' => 'Not A Slug!']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    }

    public function test_store_rejects_reserved_slug(): void
    {
        $this->postJson('/api/pages', $this->validPayload(['slug' => 'admin']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    }

    public function test_store_rejects_duplicate_slug(): void
    {
        Page::factory()->create(['slug' => 'summer-sale']);

        $this->postJson('/api/pages', $this->validPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    }

    public function test_store_rejects_unknown_block_type(): void
    {
        $payload = $this->validPayload([
            'content' => [['id' => 'b1', 'type' => 'script', 'data' => []]],
        ]);

        $this->postJson('/api/pages', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content.0.type');
    }

    public function test_store_rejects_nonexistent_og_image(): void
    {
        $this->postJson('/api/pages', $this->validPayload(['og_image_id' => 999999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('og_image_id');
    }

    public function test_validation_errors_are_json_without_accept_header(): void
    {
        $this->post('/api/pages', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('title');
    }

    // ---- update ----

    public function test_update_changes_a_page(): void
    {
        $page = Page::factory()->create();

        $this->putJson("/api/pages/{$page->id}", $this->validPayload())
            ->assertOk()
            ->assertJsonPath('data.title', 'Summer Sale')
            ->assertJsonPath('data.slug', 'summer-sale');

        $this->assertDatabaseHas('pages', ['id' => $page->id, 'slug' => 'summer-sale']);
    }

    public function test_patch_allows_partial_updates(): void
    {
        $page = Page::factory()->create(['title' => 'Original title']);

        $this->patchJson("/api/pages/{$page->id}", ['meta_title' => 'New SEO title'])
            ->assertOk()
            ->assertJsonPath('data.meta_title', 'New SEO title')
            ->assertJsonPath('data.title', 'Original title');
    }

    public function test_update_allows_keeping_own_slug(): void
    {
        $page = Page::factory()->create(['slug' => 'keep-me']);

        $this->putJson("/api/pages/{$page->id}", ['slug' => 'keep-me'])
            ->assertOk();
    }

    public function test_update_rejects_slug_used_by_another_page(): void
    {
        Page::factory()->create(['slug' => 'taken']);
        $page = Page::factory()->create(['slug' => 'mine']);

        $this->putJson("/api/pages/{$page->id}", ['slug' => 'taken'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    }

    public function test_update_cannot_publish_a_page(): void
    {
        $page = Page::factory()->create();

        $this->putJson("/api/pages/{$page->id}", ['status' => 'published'])
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $this->assertSame(PageStatus::Draft, $page->fresh()->status);
    }

    // ---- destroy ----

    public function test_destroy_deletes_a_page(): void
    {
        $page = Page::factory()->create();

        $this->deleteJson("/api/pages/{$page->id}")->assertNoContent();

        $this->assertDatabaseMissing('pages', ['id' => $page->id]);
    }
}
