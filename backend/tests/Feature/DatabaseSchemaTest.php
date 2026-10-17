<?php

namespace Tests\Feature;

use App\Enums\PageStatus;
use App\Enums\Role;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_relationships_and_casts_work(): void
    {
        $user = User::factory()->editor()->create();
        $media = Media::factory()->create(['uploaded_by' => $user->id]);
        $page = Page::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'og_image_id' => $media->id,
        ]);

        $page = $page->fresh();

        $this->assertSame(PageStatus::Draft, $page->status);
        $this->assertIsArray($page->content);
        $this->assertTrue($page->creator->is($user));
        $this->assertTrue($page->updater->is($user));
        $this->assertTrue($page->ogImage->is($media));
        $this->assertSame(Role::Editor, $user->fresh()->role);
    }

    public function test_slug_must_be_unique(): void
    {
        Page::factory()->create(['slug' => 'about']);

        $this->expectException(QueryException::class);

        Page::factory()->create(['slug' => 'about']);
    }

    public function test_status_cannot_be_mass_assigned(): void
    {
        $page = new Page(['title' => 'X', 'slug' => 'x', 'status' => 'published']);

        $this->assertSame(PageStatus::Draft, $page->status);
    }

    public function test_published_scope_returns_only_published_pages(): void
    {
        Page::factory()->create(['slug' => 'draft-page']);
        Page::factory()->published()->create(['slug' => 'live-page']);

        $slugs = Page::published()->pluck('slug')->all();

        $this->assertSame(['live-page'], $slugs);
    }

    public function test_deleting_a_page_deletes_its_revisions(): void
    {
        $page = Page::factory()->create();
        PageRevision::factory()->create(['page_id' => $page->id]);

        $page->delete();

        $this->assertDatabaseCount('page_revisions', 0);
    }

    public function test_deleting_a_user_keeps_their_pages(): void
    {
        $user = User::factory()->editor()->create();
        $page = Page::factory()->create(['created_by' => $user->id]);

        $user->delete();

        $this->assertNull($page->fresh()->created_by);
        $this->assertDatabaseHas('pages', ['id' => $page->id]);
    }

    public function test_revision_version_is_unique_per_page(): void
    {
        $page = Page::factory()->create();
        PageRevision::factory()->create(['page_id' => $page->id, 'version' => 1]);

        $this->expectException(QueryException::class);

        PageRevision::factory()->create(['page_id' => $page->id, 'version' => 1]);
    }
}
