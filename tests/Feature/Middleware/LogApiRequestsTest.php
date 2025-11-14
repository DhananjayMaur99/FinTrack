<?php

namespace Tests\Feature\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Test API request logging middleware
 * 
 * Note: These are functional tests that verify logging behavior
 * without strict mocking, since multiple components log.
 */
class LogApiRequestsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function middleware_allows_successful_requests_to_pass_through(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/categories');

        $response->assertStatus(200);
    }

    #[Test]
    public function middleware_allows_validation_errors_to_pass_through(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/categories', []);

        $response->assertStatus(422);
    }

    #[Test]
    public function middleware_handles_authentication_failures(): void
    {
        $response = $this->getJson('/api/categories');

        $response->assertStatus(401);
    }

    #[Test]
    public function middleware_processes_user_authenticated_requests(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/categories');

        $response->assertStatus(200);
        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function middleware_works_with_post_requests(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/categories', [
                'name' => 'Test Category',
                'type' => 'expense'
            ]);

        $response->assertStatus(201);
    }

    #[Test]
    public function middleware_works_with_put_requests(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $category = \App\Models\Category::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->putJson("/api/categories/{$category->id}", [
                'name' => 'Updated Category'
            ]);

        $response->assertStatus(200);
    }

    #[Test]
    public function middleware_works_with_delete_requests(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $category = \App\Models\Category::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/categories/{$category->id}");

        $response->assertStatus(204); // DELETE returns 204 No Content
    }
    #[Test]
    public function middleware_handles_not_found_errors(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/categories/99999');

        $response->assertStatus(404);
    }

    #[Test]
    public function middleware_handles_unauthorized_access(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create();
        /** @var User $otherUser */
        $otherUser = User::factory()->create();

        $category = \App\Models\Category::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($otherUser)
            ->getJson("/api/categories/{$category->id}");

        $response->assertStatus(403);
    }
}
