<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'New page',
            'slug' => 'new-page',
            'content' => [],
        ], $overrides);
    }

    public function test_guests_receive_401_on_every_page_endpoint(): void
    {
        $page = Page::factory()->create();

        $this->getJson('/api/pages')->assertUnauthorized();
        $this->getJson("/api/pages/{$page->id}")->assertUnauthorized();
        $this->postJson('/api/pages', $this->payload())->assertUnauthorized();
        $this->putJson("/api/pages/{$page->id}", $this->payload())->assertUnauthorized();
        $this->deleteJson("/api/pages/{$page->id}")->assertUnauthorized();

        $this->assertDatabaseHas('pages', ['id' => $page->id]);
    }

    public function test_guest_401_is_json_without_accept_header(): void
    {
        $this->get('/api/pages')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_viewer_can_list_and_view_pages(): void
    {
        $page = Page::factory()->create();

        $this->actingAs(User::factory()->viewer()->create());

        $this->getJson('/api/pages')->assertOk();
        $this->getJson("/api/pages/{$page->id}")->assertOk();
    }

    public function test_viewer_cannot_create_update_or_delete(): void
    {
        $page = Page::factory()->create(['title' => 'Original']);

        $this->actingAs(User::factory()->viewer()->create());

        $this->postJson('/api/pages', $this->payload())->assertForbidden();
        $this->putJson("/api/pages/{$page->id}", $this->payload())->assertForbidden();
        $this->deleteJson("/api/pages/{$page->id}")->assertForbidden();

        $this->assertDatabaseHas('pages', ['id' => $page->id, 'title' => 'Original']);
        $this->assertDatabaseMissing('pages', ['slug' => 'new-page']);
    }

    public function test_forbidden_comes_before_validation(): void
    {
        $this->actingAs(User::factory()->viewer()->create());

        // An invalid body must still produce 403, not a 422 that leaks rules.
        $this->postJson('/api/pages', [])->assertForbidden();
    }

    public function test_editor_can_create_and_update_pages(): void
    {
        $this->actingAs(User::factory()->editor()->create());

        $id = $this->postJson('/api/pages', $this->payload())
            ->assertCreated()
            ->json('data.id');

        $this->patchJson("/api/pages/{$id}", ['title' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Renamed');
    }

    public function test_editor_cannot_delete_pages(): void
    {
        $page = Page::factory()->create();

        $this->actingAs(User::factory()->editor()->create());

        $this->deleteJson("/api/pages/{$page->id}")->assertForbidden();

        $this->assertDatabaseHas('pages', ['id' => $page->id]);
    }

    public function test_admin_can_delete_pages(): void
    {
        $page = Page::factory()->create();

        $this->actingAs(User::factory()->admin()->create());

        $this->deleteJson("/api/pages/{$page->id}")->assertNoContent();

        $this->assertDatabaseMissing('pages', ['id' => $page->id]);
    }

    public function test_create_sets_audit_fields_from_the_authenticated_user(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)
            ->postJson('/api/pages', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.created_by', $editor->id)
            ->assertJsonPath('data.updated_by', $editor->id);
    }

    public function test_update_sets_updated_by_but_keeps_created_by(): void
    {
        $creator = User::factory()->editor()->create();
        $other = User::factory()->editor()->create();
        $page = Page::factory()->create(['created_by' => $creator->id, 'updated_by' => $creator->id]);

        $this->actingAs($other)
            ->patchJson("/api/pages/{$page->id}", ['title' => 'Edited by someone else'])
            ->assertOk()
            ->assertJsonPath('data.created_by', $creator->id)
            ->assertJsonPath('data.updated_by', $other->id);
    }
}
