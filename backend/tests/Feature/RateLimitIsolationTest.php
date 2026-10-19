<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_page_traffic_does_not_use_up_the_login_limit(): void
    {
        Page::factory()->published()->create(['slug' => 'home']);

        for ($i = 0; $i < 25; $i++) {
            $this->getJson('/api/public/pages/home')->assertOk();
        }

        // A wrong password is a 422. A shared counter would make this a 429.
        $this->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => 'wrong-password'])
            ->assertUnprocessable();
    }
}
