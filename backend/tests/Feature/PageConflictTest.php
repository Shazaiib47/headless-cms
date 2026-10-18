<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageConflictTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->editor()->create());
    }

    public function test_a_stale_updated_at_is_rejected_with_409(): void
    {
        $page = Page::factory()->create(['title' => 'Original']);

        $this->patchJson("/api/pages/{$page->id}", [
            'title' => 'Overwrite',
            'updated_at' => now()->subHour()->toIso8601String(),
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'This page was changed by someone else after you opened it. Reload to see their changes.');

        $this->assertSame('Original', $page->fresh()->title);
    }

    public function test_a_matching_updated_at_is_accepted_then_becomes_stale(): void
    {
        $page = Page::factory()->create(['title' => 'Original']);
        $opened = $page->fresh()->updated_at->toIso8601String();

        $this->travel(5)->minutes();

        $this->patchJson("/api/pages/{$page->id}", ['title' => 'Mine', 'updated_at' => $opened])
            ->assertOk();

        $this->patchJson("/api/pages/{$page->id}", ['title' => 'Theirs', 'updated_at' => $opened])
            ->assertStatus(409);

        $this->assertSame('Mine', $page->fresh()->title);
    }

    public function test_updated_at_is_optional_for_other_api_clients(): void
    {
        $page = Page::factory()->create();

        $this->patchJson("/api/pages/{$page->id}", ['title' => 'No timestamp sent'])
            ->assertOk();
    }

    public function test_a_malformed_updated_at_is_a_validation_error(): void
    {
        $page = Page::factory()->create();

        $this->patchJson("/api/pages/{$page->id}", ['updated_at' => 'not-a-date'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('updated_at');
    }
}
