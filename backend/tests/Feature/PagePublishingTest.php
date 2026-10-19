<?php

namespace Tests\Feature;

use App\Enums\PageStatus;
use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagePublishingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_receive_401_on_publish_and_unpublish(): void
    {
        $page = Page::factory()->create();

        $this->postJson("/api/pages/{$page->id}/publish")->assertUnauthorized();
        $this->postJson("/api/pages/{$page->id}/unpublish")->assertUnauthorized();

        $this->assertSame(PageStatus::Draft, $page->fresh()->status);
    }

    public function test_viewers_cannot_publish_or_unpublish(): void
    {
        $draft = Page::factory()->create();
        $live = Page::factory()->published()->create();

        $this->actingAs(User::factory()->viewer()->create());

        $this->postJson("/api/pages/{$draft->id}/publish")->assertForbidden();
        $this->postJson("/api/pages/{$live->id}/unpublish")->assertForbidden();

        $this->assertSame(PageStatus::Draft, $draft->fresh()->status);
        $this->assertSame(PageStatus::Published, $live->fresh()->status);
    }

    public function test_editor_can_publish_a_draft(): void
    {
        $editor = User::factory()->editor()->create();
        $page = Page::factory()->create();

        $this->actingAs($editor)
            ->postJson("/api/pages/{$page->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.updated_by', $editor->id)
            ->assertJsonPath('data.slug', $page->slug);

        $fresh = $page->fresh();
        $this->assertSame(PageStatus::Published, $fresh->status);
        $this->assertNotNull($fresh->published_at);
    }

    public function test_admin_can_unpublish_a_page(): void
    {
        $page = Page::factory()->published()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson("/api/pages/{$page->id}/unpublish")
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.published_at', null);

        $this->assertSame(PageStatus::Draft, $page->fresh()->status);
        $this->assertNull($page->fresh()->published_at);
    }

    public function test_a_page_with_no_content_cannot_be_published(): void
    {
        $page = Page::factory()->create(['content' => []]);

        $this->actingAs(User::factory()->editor()->create())
            ->postJson("/api/pages/{$page->id}/publish")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content')
            ->assertJsonPath('errors.content.0', 'A page needs at least one content block before it can be published.');

        $this->assertSame(PageStatus::Draft, $page->fresh()->status);
    }

    public function test_every_image_block_needs_an_image_to_publish(): void
    {
        $page = Page::factory()->create(['content' => [
            ['id' => 'a', 'type' => 'hero', 'data' => ['heading' => 'Hi']],
            ['id' => 'b', 'type' => 'image', 'data' => ['image_id' => null, 'alt' => 'A cat', 'caption' => null]],
        ]]);

        $this->actingAs(User::factory()->editor()->create())
            ->postJson("/api/pages/{$page->id}/publish")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content.1.data.image_id');

        $this->assertSame(PageStatus::Draft, $page->fresh()->status);
    }

    public function test_a_page_with_a_complete_image_block_can_be_published(): void
    {
        $media = Media::factory()->create();
        $page = Page::factory()->create(['content' => [
            ['id' => 'a', 'type' => 'image', 'data' => ['image_id' => $media->id, 'alt' => 'A cat', 'caption' => null]],
        ]]);

        $this->actingAs(User::factory()->editor()->create())
            ->postJson("/api/pages/{$page->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');
    }

    public function test_publishing_twice_is_harmless(): void
    {
        $page = Page::factory()->create();
        $this->actingAs(User::factory()->editor()->create());

        $first = $this->postJson("/api/pages/{$page->id}/publish")->assertOk()->json('data.published_at');

        $this->travel(10)->minutes();

        $second = $this->postJson("/api/pages/{$page->id}/publish")->assertOk()->json('data.published_at');

        $this->assertSame($first, $second);
    }

    public function test_unpublishing_a_draft_is_harmless(): void
    {
        $page = Page::factory()->create();

        $this->actingAs(User::factory()->editor()->create())
            ->postJson("/api/pages/{$page->id}/unpublish")
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_a_stale_updated_at_is_rejected_and_changes_nothing(): void
    {
        $page = Page::factory()->create();

        $this->actingAs(User::factory()->editor()->create())
            ->postJson("/api/pages/{$page->id}/publish", [
                'updated_at' => now()->subHour()->toIso8601String(),
            ])
            ->assertStatus(409);

        $this->assertSame(PageStatus::Draft, $page->fresh()->status);
    }

    public function test_a_retry_with_a_stale_timestamp_still_succeeds_but_a_different_action_does_not(): void
    {
        $page = Page::factory()->create();
        $opened = $page->fresh()->updated_at->toIso8601String();

        $this->actingAs(User::factory()->editor()->create());
        $this->travel(5)->minutes();

        $this->postJson("/api/pages/{$page->id}/publish", ['updated_at' => $opened])->assertOk();

        // A dropped connection makes the client retry with the same, now stale, value.
        $this->postJson("/api/pages/{$page->id}/publish", ['updated_at' => $opened])->assertOk();

        // But acting on a page that has since changed is a conflict.
        $this->postJson("/api/pages/{$page->id}/unpublish", ['updated_at' => $opened])->assertStatus(409);

        $this->assertSame(PageStatus::Published, $page->fresh()->status);
    }

    public function test_a_malformed_updated_at_is_a_validation_error(): void
    {
        $page = Page::factory()->create();

        $this->actingAs(User::factory()->editor()->create())
            ->postJson("/api/pages/{$page->id}/publish", ['updated_at' => 'not-a-date'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('updated_at');
    }

    public function test_publishing_ignores_every_other_body_field(): void
    {
        $page = Page::factory()->create(['title' => 'Original']);

        $this->actingAs(User::factory()->editor()->create())
            ->postJson("/api/pages/{$page->id}/publish", [
                'status' => 'draft',
                'title' => 'Hacked',
                'slug' => 'hacked',
            ])
            ->assertOk();

        $fresh = $page->fresh();
        $this->assertSame('Original', $fresh->title);
        $this->assertNotSame('hacked', $fresh->slug);
        $this->assertSame(PageStatus::Published, $fresh->status);
    }

    public function test_editing_a_published_page_keeps_it_published(): void
    {
        $page = Page::factory()->published()->create();

        $this->actingAs(User::factory()->editor()->create())
            ->patchJson("/api/pages/{$page->id}", ['title' => 'New title'])
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->assertNotNull($page->fresh()->published_at);
    }

    public function test_publishing_a_missing_page_returns_404(): void
    {
        $this->actingAs(User::factory()->editor()->create())
            ->postJson('/api/pages/999999/publish')
            ->assertNotFound();
    }
}
