<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\Fluent\AssertableJson;
use PHPUnit\Framework\Attributes\Test;


class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_register_creates_user_and_returns_token(): void
    {
        $payload = [
            'name' => 'Test User',
            'email' => 'register@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123'
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(201)
            ->assertJson(
                fn(AssertableJson $json) =>
                $json->has('user')
                    ->has('token')
                    ->whereType('token', 'string')
                    ->has('expires_at')
                    ->has('expires_in')
            );

        $this->assertDatabaseHas('users', ['email' => $payload['email'], 'name' => $payload['name']]);

        $user = User::where('email', $payload['email'])->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check($payload['password'], $user->password));
    }

    public function test_register_validates_required_fields(): void
    {
        $response = $this->postJson('/api/register', []);
        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_login_returns_token_and_user(): void
    {
        $user = User::factory()->create(['password' => Hash::make('mypassword')]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'mypassword',
        ]);

        $response->assertStatus(200)
            ->assertJson(
                fn(AssertableJson $json) =>
                $json->has('user')
                    ->has('token')
                    ->has('expires_at')
                    ->has('expires_in')
            );

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_invalid_credentials_returns_401(): void
    {
        $user = User::factory()->create(['password' => Hash::make('rightpass')]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrongpass',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid credentials']);
    }

    public function test_login_revokes_old_tokens(): void
    {
        $user = User::factory()->create(['password' => Hash::make('safepass')]);

        // create an old token
        $first = $user->createToken('first-token');
        $this->assertDatabaseCount('personal_access_tokens', 1);

        // login again
        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'safepass',
        ]);

        // Used ->etc() to ignore other properties we don't care about in this assertion
        $response->assertStatus(200)->assertJson(fn(AssertableJson $json) => $json->has('token')->etc());

        // Only a single active token should exist (old tokens revoked)
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_refresh_rotates_token(): void
    {
        $user = User::factory()->create();
        $tokenResult = $user->createToken('refresh-token');
        $plain = $tokenResult->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $plain)
            ->postJson('/api/refresh');

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'expires_at', 'expires_in']);

        // After refresh, only one token should remain (old token deleted)
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('logout-token');
        $plain = $token->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $plain)
            ->postJson('/api/logout');

        $response->assertStatus(200)->assertJson(['message' => 'Logged out successfully']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_destroy_soft_deletes_user_and_revokes_tokens(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('destroy-token');
        $plain = $token->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $plain)
            ->deleteJson('/api/user');

        $response->assertStatus(200)->assertJson(['message' => 'Account deleted successfully']);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
