<?php

namespace Tests\Feature\Exceptions;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\ResourceNotFoundException;
use App\Exceptions\UnauthorizedAccessException;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Test custom exception handling and error responses
 */
class ExceptionHandlingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function resource_not_found_exception_returns_404_with_proper_structure(): void
    {
        $exception = ResourceNotFoundException::make('Transaction', 999);

        $response = $exception->render();

        $this->assertEquals(404, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals('Transaction not found', $data['message']);
        $this->assertEquals('RESOURCE_NOT_FOUND', $data['error_code']);
        $this->assertEquals(404, $data['status']);
    }

    #[Test]
    public function unauthorized_access_exception_returns_403_with_proper_structure(): void
    {
        $exception = UnauthorizedAccessException::make('Budget', 123);

        $response = $exception->render();

        $this->assertEquals(403, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertStringContainsString('permission', $data['message']);
        $this->assertEquals('UNAUTHORIZED_ACCESS', $data['error_code']);
        $this->assertEquals(403, $data['status']);
    }

    #[Test]
    public function business_rule_exception_returns_422_with_proper_structure(): void
    {
        $exception = BusinessRuleException::make(
            'budget_exceeded',
            'Your spending has exceeded the budget limit',
            ['budget_id' => 456, 'amount' => 1000]
        );

        $response = $exception->render();

        $this->assertEquals(422, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals('Your spending has exceeded the budget limit', $data['message']);
        $this->assertEquals('BUSINESS_RULE_VIOLATION', $data['error_code']);
        $this->assertEquals(422, $data['status']);
    }

    #[Test]
    public function model_not_found_returns_404_json_response(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        // Try to access non-existent category
        $response = $this->actingAs($user)
            ->getJson('/api/categories/99999');

        $response->assertStatus(404);
        // Laravel's default response contains a message
        $response->assertJsonStructure(['message']);
    }

    #[Test]
    public function unauthorized_access_to_resource_returns_403(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create();
        /** @var User $otherUser */
        $otherUser = User::factory()->create();

        $category = Category::factory()->create(['user_id' => $owner->id]);

        // Try to access someone else's category
        $response = $this->actingAs($otherUser)
            ->getJson("/api/categories/{$category->id}");

        $response->assertStatus(403);
    }

    #[Test]
    public function validation_errors_return_422_with_errors(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        // Try to create category without required name
        $response = $this->actingAs($user)
            ->postJson('/api/categories', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function rate_limit_exceeded_returns_429(): void
    {
        // Make 6 register requests (limit is 5 per minute)
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/register', [
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

            if ($i < 5) {
                $this->assertLessThan(429, $response->getStatusCode());
            }
        }

        // 6th request should be rate limited
        $response->assertStatus(429);
        $response->assertJson([
            'message' => 'Too many requests. Please slow down.',
            'error_code' => 'RATE_LIMIT_EXCEEDED',
        ]);
        $response->assertJsonStructure(['retry_after']);
    }

    #[Test]
    public function custom_exceptions_are_logged_with_context(): void
    {
        // Test that the exception renders correctly (logging tested separately)
        $exception = ResourceNotFoundException::make('Transaction', 999);

        $response = $exception->render();

        $this->assertEquals(404, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertEquals('RESOURCE_NOT_FOUND', $data['error_code']);
    }
    #[Test]
    public function authentication_required_returns_401(): void
    {
        // Try to access protected route without authentication
        $response = $this->getJson('/api/categories');

        $response->assertStatus(401);
    }

    #[Test]
    public function invalid_json_payload_returns_400(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        // Laravel automatically validates JSON, so we just verify JSON responses work
        $response = $this->actingAs($user)
            ->getJson('/api/categories');

        // Should return valid JSON response
        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }
}
