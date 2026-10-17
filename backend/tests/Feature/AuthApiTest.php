<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    private const SPA_ORIGIN = 'http://localhost:5173';

    /** Requests from the SPA origin are "stateful" and get a session. */
    private function spa(): static
    {
        return $this->withHeader('Origin', self::SPA_ORIGIN);
    }

    public function test_login_succeeds_with_valid_credentials(): void
    {
        $user = User::factory()->editor()->create(['email' => 'ed@example.com']);

        $this->spa()
            ->postJson('/api/login', ['email' => 'ed@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.email', 'ed@example.com')
            ->assertJsonPath('data.role', 'editor')
            ->assertJsonMissingPath('data.password');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'ed@example.com']);

        $this->spa()
            ->postJson('/api/login', ['email' => 'ed@example.com', 'password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_login_error_is_identical_for_unknown_email(): void
    {
        User::factory()->create(['email' => 'real@example.com']);

        $known = $this->spa()
            ->postJson('/api/login', ['email' => 'real@example.com', 'password' => 'nope'])
            ->json('errors.email.0');

        $unknown = $this->spa()
            ->postJson('/api/login', ['email' => 'ghost@example.com', 'password' => 'nope'])
            ->json('errors.email.0');

        $this->assertNotNull($known);
        $this->assertSame($known, $unknown);
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->spa()
            ->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        User::factory()->create(['email' => 'ed@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->spa()
                ->postJson('/api/login', ['email' => 'ed@example.com', 'password' => 'wrong'])
                ->assertUnprocessable();
        }

        // Even the correct password is refused while locked out.
        $this->spa()
            ->postJson('/api/login', ['email' => 'ed@example.com', 'password' => 'password'])
            ->assertStatus(429);

        $this->assertGuest();
    }

    public function test_login_requires_a_stateful_request(): void
    {
        User::factory()->create(['email' => 'ed@example.com']);

        $this->postJson('/api/login', ['email' => 'ed@example.com', 'password' => 'password'])
            ->assertStatus(400);
    }

    public function test_csrf_cookie_endpoint_is_available(): void
    {
        $this->spa()->get('/sanctum/csrf-cookie')->assertNoContent();
    }

    public function test_user_endpoint_returns_the_current_user(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role', 'admin');
    }

    public function test_user_endpoint_rejects_guests(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_logout_ends_the_session(): void
    {
        $user = User::factory()->editor()->create();

        $this->actingAs($user)
            ->spa()
            ->postJson('/api/logout')
            ->assertNoContent();

        $this->assertGuest('web');
    }
}
