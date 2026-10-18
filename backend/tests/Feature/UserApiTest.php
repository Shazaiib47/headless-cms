<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New Person',
            'email' => 'new.person@example.com',
            'password' => 'Correct-Horse-9',
            'role' => 'editor',
        ], $overrides);
    }

    // ---- access control ----

    public function test_guests_receive_401_on_every_user_endpoint(): void
    {
        $target = User::factory()->create();

        $this->getJson('/api/users')->assertUnauthorized();
        $this->getJson("/api/users/{$target->id}")->assertUnauthorized();
        $this->postJson('/api/users', $this->payload())->assertUnauthorized();
        $this->patchJson("/api/users/{$target->id}", ['name' => 'X'])->assertUnauthorized();
        $this->deleteJson("/api/users/{$target->id}")->assertUnauthorized();

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_non_admins_receive_403_on_every_user_endpoint(): void
    {
        $target = User::factory()->create(['name' => 'Original']);

        foreach ([User::factory()->editor()->create(), User::factory()->viewer()->create()] as $user) {
            $this->actingAs($user);

            $this->getJson('/api/users')->assertForbidden();
            $this->getJson("/api/users/{$target->id}")->assertForbidden();
            $this->postJson('/api/users', $this->payload())->assertForbidden();
            $this->patchJson("/api/users/{$target->id}", ['name' => 'Hacked'])->assertForbidden();
            $this->deleteJson("/api/users/{$target->id}")->assertForbidden();
        }

        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => 'Original']);
        $this->assertDatabaseMissing('users', ['email' => 'new.person@example.com']);
    }

    // ---- index ----

    public function test_admin_can_list_users_without_sensitive_fields(): void
    {
        User::factory()->count(2)->create();

        $this->actingAs($this->admin)
            ->getJson('/api/users')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'name', 'email', 'role', 'created_at']],
                'links',
                'meta',
            ])
            ->assertJsonMissingPath('data.0.password')
            ->assertJsonMissingPath('data.0.remember_token');
    }

    public function test_index_searches_name_and_email(): void
    {
        User::factory()->create(['name' => 'Zyxwvu Quark', 'email' => 'quark@findme.example']);
        User::factory()->create();

        $this->actingAs($this->admin);

        $this->getJson('/api/users?search=zyxwvu')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'quark@findme.example');

        $this->getJson('/api/users?search=findme.example')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_role(): void
    {
        User::factory()->editor()->create();
        User::factory()->viewer()->create();

        $this->actingAs($this->admin)
            ->getJson('/api/users?role=editor')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.role', 'editor');
    }

    public function test_index_rejects_invalid_role_and_sort(): void
    {
        $this->actingAs($this->admin);

        $this->getJson('/api/users?role=superuser')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->getJson('/api/users?sort=password')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sort');
    }

    // ---- store ----

    public function test_admin_can_create_a_user(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/users', $this->payload(['email' => 'New.Person@Example.com']))
            ->assertCreated()
            ->assertJsonPath('data.email', 'new.person@example.com')
            ->assertJsonPath('data.role', 'editor')
            ->assertJsonMissingPath('data.password');

        $user = User::where('email', 'new.person@example.com')->firstOrFail();

        $this->assertSame(Role::Editor, $user->role);
        $this->assertNotSame('Correct-Horse-9', $user->password);
        $this->assertTrue(Hash::check('Correct-Horse-9', $user->password));
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/users', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password', 'role']);
    }

    public function test_store_rejects_weak_passwords(): void
    {
        $this->actingAs($this->admin);

        $this->postJson('/api/users', $this->payload(['password' => 'Short1a']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->postJson('/api/users', $this->payload(['password' => 'alllowercase12345']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_store_rejects_duplicate_email_regardless_of_case(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($this->admin)
            ->postJson('/api/users', $this->payload(['email' => 'TAKEN@example.com']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_store_rejects_an_unknown_role(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/users', $this->payload(['role' => 'owner']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');
    }

    // ---- show ----

    public function test_admin_can_view_a_user(): void
    {
        $target = User::factory()->editor()->create();

        $this->actingAs($this->admin)
            ->getJson("/api/users/{$target->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $target->id)
            ->assertJsonPath('data.role', 'editor');
    }

    public function test_show_returns_404_for_a_missing_user(): void
    {
        $this->actingAs($this->admin)->getJson('/api/users/999999')->assertNotFound();
    }

    // ---- update ----

    public function test_admin_can_update_name_email_and_role(): void
    {
        $target = User::factory()->viewer()->create();

        $this->actingAs($this->admin)
            ->patchJson("/api/users/{$target->id}", [
                'name' => 'Renamed',
                'email' => 'renamed@example.com',
                'role' => 'editor',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.role', 'editor');

        $fresh = $target->fresh();
        $this->assertSame('renamed@example.com', $fresh->email);
        $this->assertSame(Role::Editor, $fresh->role);
    }

    public function test_update_can_set_a_new_password(): void
    {
        $target = User::factory()->create();

        $this->actingAs($this->admin)
            ->patchJson("/api/users/{$target->id}", ['password' => 'Another-Passw0rd'])
            ->assertOk();

        $fresh = $target->fresh();
        $this->assertTrue(Hash::check('Another-Passw0rd', $fresh->password));
        $this->assertFalse(Hash::check('password', $fresh->password));
    }

    public function test_update_without_a_password_keeps_the_existing_one(): void
    {
        $target = User::factory()->create(); // factory password is "password"

        $this->actingAs($this->admin)
            ->patchJson("/api/users/{$target->id}", ['name' => 'Only the name'])
            ->assertOk();

        $this->assertTrue(Hash::check('password', $target->fresh()->password));
    }

    public function test_update_allows_keeping_the_same_email(): void
    {
        $target = User::factory()->create(['email' => 'keep@example.com']);

        $this->actingAs($this->admin)
            ->patchJson("/api/users/{$target->id}", ['email' => 'keep@example.com'])
            ->assertOk();
    }

    public function test_update_rejects_an_email_used_by_another_user(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $target = User::factory()->create();

        $this->actingAs($this->admin)
            ->patchJson("/api/users/{$target->id}", ['email' => 'taken@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_admin_cannot_change_their_own_role(): void
    {
        $this->actingAs($this->admin)
            ->patchJson("/api/users/{$this->admin->id}", ['role' => 'viewer'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->assertSame(Role::Admin, $this->admin->fresh()->role);
    }

    public function test_admin_can_edit_their_own_name(): void
    {
        $this->actingAs($this->admin)
            ->patchJson("/api/users/{$this->admin->id}", ['name' => 'Renamed Admin', 'role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed Admin')
            ->assertJsonPath('data.role', 'admin');
    }

    public function test_password_change_ends_the_users_sessions(): void
    {
        config(['session.driver' => 'database']);

        $target = User::factory()->editor()->create();
        $other = User::factory()->editor()->create();

        foreach ([$target, $other] as $user) {
            DB::table('sessions')->insert([
                'id' => 'session-'.$user->id,
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'test',
                'payload' => 'x',
                'last_activity' => time(),
            ]);
        }

        $this->actingAs($this->admin)
            ->patchJson("/api/users/{$target->id}", ['password' => 'Another-Passw0rd'])
            ->assertOk();

        $this->assertDatabaseMissing('sessions', ['id' => 'session-'.$target->id]);
        $this->assertDatabaseHas('sessions', ['id' => 'session-'.$other->id]);
    }

    // ---- destroy ----

    public function test_admin_cannot_delete_themselves(): void
    {
        $this->actingAs($this->admin)
            ->deleteJson("/api/users/{$this->admin->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user');

        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    public function test_admin_can_delete_another_user(): void
    {
        $target = User::factory()->create();

        $this->actingAs($this->admin)
            ->deleteJson("/api/users/{$target->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }
}
