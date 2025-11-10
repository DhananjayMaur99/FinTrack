<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Carbon\Carbon;

class TokenExpirationTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_expires_after_configured_ttl(): void
    {
        Config::set('token.ttl_minutes', 1);

        $user = User::factory()->create([
            'password' => bcrypt('password'),
        ]);

        // Login
        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $token = $response->json('token');
        $this->assertNotEmpty($token);

        // Should work immediately
        $this->withToken($token)->getJson('/api/user')->assertOk();

        // Fast-forward beyond TTL
        Carbon::setTestNow(now()->addMinutes(2));

        // Should now be unauthorized
        $this->withToken($token)->getJson('/api/user')->assertStatus(401);
    }

    public function test_refresh_issues_new_token_with_new_expiry(): void
    {
        Config::set('token.ttl_minutes', 1);
        $user = User::factory()->create([
            'password' => bcrypt('password'),
        ]);

        $login = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $oldToken = $login->json('token');

        // Refresh before expiry
        $refresh = $this->withToken($oldToken)->postJson('/api/refresh')->assertOk();
        $newToken = $refresh->json('token');
        $this->assertNotEquals($oldToken, $newToken);

        // Old token should be invalid now (revoked in controller)
        $this->withToken($oldToken)->getJson('/api/user')->assertStatus(401);
        // New token valid
        $this->withToken($newToken)->getJson('/api/user')->assertOk();
    }
}
