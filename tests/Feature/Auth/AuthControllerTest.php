<?php

/**
 * Comprehensive Authentication Controller Tests
 * 
 * Test Coverage:
 * - Registration: valid, invalid, edge cases, timezone handling
 * - Login: valid, invalid credentials, missing fields, token management
 * - Logout: valid token, invalid token, already logged out scenarios
 * - Refresh: valid token, expired token, invalid token
 * - Destroy: account deletion with data, token revocation
 * - Update Profile: all fields, partial updates, validation
 * - Status codes verified for all scenarios
 */

namespace Tests\Feature\Auth;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\Fluent\AssertableJson;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    // ========================================
    // REGISTRATION TESTS
    // ========================================

    #[Test]
    public function register_creates_user_with_valid_data_and_returns_201(): void
    {
        $payload = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'timezone', 'created_at', 'updated_at'],
                'token',
                'expires_at',
                'expires_in',
            ])
            ->assertJson(
                fn(AssertableJson $json) =>
                $json->where('user.name', $payload['name'])
                    ->where('user.email', $payload['email'])
                    ->whereType('token', 'string')
                    ->whereType('expires_at', 'string')
                    ->whereType('expires_in', 'integer')
                    ->etc()
            );

        $this->assertDatabaseHas('users', [
            'email' => $payload['email'],
            'name' => $payload['name'],
        ]);

        $user = User::where('email', $payload['email'])->first();
        $this->assertTrue(Hash::check($payload['password'], $user->password));
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    #[Test]
    public function register_with_timezone_saves_user_timezone(): void
    {
        $payload = [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'timezone' => 'America/New_York',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => $payload['email'],
            'timezone' => 'America/New_York',
        ]);
    }

    #[Test]
    public function register_without_timezone_creates_user_with_null_timezone(): void
    {
        $payload = [
            'name' => 'Bob Johnson',
            'email' => 'bob@example.com',
            'password' => 'MyPass456!',
            'password_confirmation' => 'MyPass456!',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => $payload['email'],
            'timezone' => null,
        ]);
    }

    #[Test]
    public function register_fails_with_missing_name_and_returns_422(): void
    {
        $payload = [
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name'])
            ->assertJsonStructure(['message', 'errors' => ['name']]);
    }

    #[Test]
    public function register_fails_with_missing_email_and_returns_422(): void
    {
        $payload = [
            'name' => 'Test User',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function register_fails_with_invalid_email_format_and_returns_422(): void
    {
        $payload = [
            'name' => 'Test User',
            'email' => 'not-an-email',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function register_fails_with_duplicate_email_and_returns_422(): void
    {
        $existingUser = User::factory()->create(['email' => 'duplicate@example.com']);

        $payload = [
            'name' => 'New User',
            'email' => 'duplicate@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function register_fails_with_missing_password_and_returns_422(): void
    {
        $payload = [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    #[Test]
    public function register_fails_with_password_mismatch_and_returns_422(): void
    {
        $payload = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'DifferentPassword456!',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    #[Test]
    public function register_fails_with_short_password_and_returns_422(): void
    {
        $payload = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    #[Test]
    public function register_fails_with_all_missing_fields_and_returns_422(): void
    {
        $response = $this->postJson('/api/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password'])
            ->assertJsonStructure(['message', 'errors']);
    }

    #[Test]
    public function register_creates_exactly_one_token(): void
    {
        $payload = [
            'name' => 'Token Test User',
            'email' => 'token@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $this->postJson('/api/register', $payload);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    // ========================================
    // LOGIN TESTS
    // ========================================

    #[Test]
    public function login_with_valid_credentials_returns_200_and_token(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => Hash::make('MyPassword123!'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'login@example.com',
            'password' => 'MyPassword123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'timezone', 'created_at', 'updated_at'],
                'token',
                'expires_at',
                'expires_in',
            ])
            ->assertJson(
                fn(AssertableJson $json) =>
                $json->where('user.id', $user->id)
                    ->where('user.email', $user->email)
                    ->whereType('token', 'string')
                    ->etc()
            );

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    #[Test]
    public function login_with_invalid_password_returns_422(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('CorrectPassword123!'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'WrongPassword456!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function login_with_nonexistent_email_returns_422(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function login_with_missing_email_returns_422(): void
    {
        $response = $this->postJson('/api/login', [
            'password' => 'Password123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function login_with_missing_password_returns_422(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    #[Test]
    public function login_with_empty_credentials_returns_422(): void
    {
        $response = $this->postJson('/api/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    #[Test]
    public function login_revokes_all_previous_tokens(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Password123!'),
        ]);

        // Create multiple tokens
        $user->createToken('token1');
        $user->createToken('token2');
        $user->createToken('token3');

        $this->assertDatabaseCount('personal_access_tokens', 3);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200);

        // Should only have the new token
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    #[Test]
    public function login_with_soft_deleted_user_fails_with_422(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Password123!'),
        ]);

        $user->delete(); // Soft delete

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function login_token_expires_in_configured_time(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Password123!'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertJson(
                fn(AssertableJson $json) =>
                $json->where('expires_in', 3600) // 60 minutes * 60 seconds
                    ->etc()
            );
    }

    // ========================================
    // LOGOUT TESTS
    // ========================================

    #[Test]
    public function logout_with_valid_token_revokes_token_and_returns_200(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
            ->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Logged out successfully']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[Test]
    public function logout_without_token_returns_401(): void
    {
        $response = $this->postJson('/api/logout');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    #[Test]
    public function logout_with_invalid_token_returns_401(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer invalid-token-string')
            ->postJson('/api/logout');

        $response->assertStatus(401);
    }

    #[Test]
    public function logout_with_expired_token_returns_401(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token', ['*'], now()->subHour());

        $response = $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
            ->postJson('/api/logout');

        $response->assertStatus(401);
    }

    #[Test]
    public function logout_only_revokes_current_token_not_other_sessions(): void
    {
        $user = User::factory()->create();
        $token1 = $user->createToken('session1');
        $token2 = $user->createToken('session2');

        $this->assertDatabaseCount('personal_access_tokens', 2);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token1->plainTextToken)
            ->postJson('/api/logout');

        $response->assertStatus(200);

        // Only one token should remain
        $this->assertDatabaseCount('personal_access_tokens', 1);

        // Verify it's token2 that remains
        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'session2',
        ]);
    }

    // ========================================
    // REFRESH TOKEN TESTS
    // ========================================

    #[Test]
    public function refresh_with_valid_token_returns_new_token_and_200(): void
    {
        $user = User::factory()->create();
        $oldToken = $user->createToken('old-token');

        $response = $this->withHeader('Authorization', 'Bearer ' . $oldToken->plainTextToken)
            ->postJson('/api/refresh');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'token',
                'expires_at',
                'expires_in',
            ]);

        // Old token should be deleted, new one created
        $this->assertDatabaseCount('personal_access_tokens', 1);

        // The returned token should be different
        $this->assertNotEquals($oldToken->plainTextToken, $response->json('token'));
    }

    #[Test]
    public function refresh_without_token_returns_401(): void
    {
        $response = $this->postJson('/api/refresh');

        $response->assertStatus(401);
    }

    #[Test]
    public function refresh_with_invalid_token_returns_401(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer invalid-token')
            ->postJson('/api/refresh');

        $response->assertStatus(401);
    }

    #[Test]
    public function refresh_deletes_old_token_before_creating_new_one(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('refresh-token');
        $tokenId = $token->accessToken->id;

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $tokenId]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
            ->postJson('/api/refresh');

        $response->assertStatus(200);

        // Old token should not exist
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);

        // New token should exist
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    // ========================================
    // ACCOUNT DELETION TESTS
    // ========================================

    #[Test]
    public function destroy_soft_deletes_user_and_returns_200(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
            ->deleteJson('/api/user');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Account deleted successfully']);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    #[Test]
    public function destroy_revokes_all_user_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('token1');
        $user->createToken('token2');
        $token = $user->createToken('token3');

        $this->assertDatabaseCount('personal_access_tokens', 3);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
            ->deleteJson('/api/user');

        $response->assertStatus(200);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[Test]
    public function destroy_without_authentication_returns_401(): void
    {
        $response = $this->deleteJson('/api/user');

        $response->assertStatus(401);
    }

    #[Test]
    public function destroy_preserves_user_data_with_soft_delete(): void
    {
        $user = User::factory()->create([
            'name' => 'Preserved User',
            'email' => 'preserved@example.com',
        ]);
        $token = $user->createToken('test-token');

        $this->deleteJson('/api/user', [], [
            'Authorization' => 'Bearer ' . $token->plainTextToken,
        ]);

        // Data should still exist in database with deleted_at timestamp
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Preserved User',
            'email' => 'preserved@example.com',
        ]);

        $user->refresh();
        $this->assertNotNull($user->deleted_at);
    }

    #[Test]
    public function destroy_with_existing_transactions_soft_deletes_user(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        Transaction::factory()->for($user)->for($category)->count(5)->create();

        $token = $user->createToken('test-token');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
            ->deleteJson('/api/user');

        $response->assertStatus(200);

        $this->assertSoftDeleted('users', ['id' => $user->id]);

        // Transactions should still exist
        $this->assertDatabaseCount('transactions', 5);
    }

    // ========================================
    // UPDATE PROFILE TESTS
    // ========================================

    #[Test]
    public function update_profile_with_all_fields_returns_200(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'timezone' => 'UTC',
            'password' => Hash::make('OldPassword123!'),
        ]);

        $token = $user->createToken('test-token');

        $payload = [
            'name' => 'New Name',
            'email' => 'new@example.com',
            'timezone' => 'America/New_York',
            'password' => 'NewPassword456!',
            'password_confirmation' => 'NewPassword456!',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
            ->putJson('/api/user', $payload);

        $response->assertStatus(200)
            ->assertJson(
                fn(AssertableJson $json) =>
                $json->has('user')
                    ->where('user.name', 'New Name')
                    ->where('user.email', 'new@example.com')
                    ->where('user.timezone', 'America/New_York')
                    ->etc()
            );

        $user->refresh();
        $this->assertEquals('New Name', $user->name);
        $this->assertEquals('new@example.com', $user->email);
        $this->assertEquals('America/New_York', $user->timezone);
        $this->assertTrue(Hash::check('NewPassword456!', $user->password));
    }

    #[Test]
    public function update_profile_with_only_name_returns_200(): void
    {
        $user = User::factory()->create(['name' => 'Original Name']);
        $token = $user->createToken('test-token');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
            ->putJson('/api/user', ['name' => 'Updated Name']);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertEquals('Updated Name', $user->name);
    }

    #[Test]
    public function update_profile_with_only_email_returns_200(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com']);
        $token = $user->createToken('test-token');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
            ->putJson('/api/user', ['email' => 'updated@example.com']);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertEquals('updated@example.com', $user->email);
    }

    #[Test]
    public function update_profile_with_only_timezone_returns_200(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC']);
        $token = $user->createToken('test-token');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
            ->putJson('/api/user', ['timezone' => 'Europe/London']);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertEquals('Europe/London', $user->timezone);
    }

    #[Test]
    public function update_profile_with_only_password_returns_200(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('OldPassword123!'),
        ]);
        $token = $user->createToken('test-token');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
            ->putJson('/api/user', [
                'password' => 'NewPassword456!',
                'password_confirmation' => 'NewPassword456!',
            ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword456!', $user->password));
    }

    #[Test]
    public function update_profile_with_duplicate_email_returns_422(): void
    {
        $existingUser = User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['email' => 'original@example.com']);
        $token = $user->createToken('test-token');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
            ->putJson('/api/user', ['email' => 'taken@example.com']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function update_profile_with_invalid_email_format_returns_422(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
            ->putJson('/api/user', ['email' => 'not-an-email']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function update_profile_with_password_mismatch_returns_422(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
            ->putJson('/api/user', [
                'password' => 'NewPassword123!',
                'password_confirmation' => 'DifferentPassword456!',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    #[Test]
    public function update_profile_with_short_password_returns_422(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
            ->putJson('/api/user', [
                'password' => 'short',
                'password_confirmation' => 'short',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    #[Test]
    public function update_profile_without_authentication_returns_401(): void
    {
        $response = $this->putJson('/api/user', ['name' => 'New Name']);

        $response->assertStatus(401);
    }

    #[Test]
    public function update_profile_with_empty_payload_returns_422(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
            ->putJson('/api/user', []);

        $response->assertStatus(422);
    }

    #[Test]
    public function update_profile_preserves_other_fields_when_updating_one(): void
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'timezone' => 'America/New_York',
        ]);
        $token = $user->createToken('test-token');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
            ->putJson('/api/user', ['name' => 'Jane Doe']);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertEquals('Jane Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email); // Preserved
        $this->assertEquals('America/New_York', $user->timezone); // Preserved
    }
}
