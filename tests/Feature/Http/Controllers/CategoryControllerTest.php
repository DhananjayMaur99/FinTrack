<?php

/**
 * Developer notes:
 * - Routes: expects named routes categories.index|store|show|update|destroy.
 * - Auth: Sanctum::actingAs enforces per-user scoping; cross-user data must not appear.
 * - Validation: asserts controller uses CategoryStoreRequest/CategoryUpdateRequest.
 * - Soft deletes: destroy should soft-delete categories (kept for historical relations).
 */

namespace Tests\Feature\Http\Controllers;

use App\Http\Requests\CategoryStoreRequest;
use App\Http\Requests\CategoryUpdateRequest;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CategoryControllerTest extends TestCase
{
    use AdditionalAssertions;
    use RefreshDatabase;
    use WithFaker;

    #[Test]
    public function index_returns_only_authenticated_users_categories(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownedCategories = Category::factory()->count(2)->for($user)->create();
        $foreignCategory = Category::factory()->for($otherUser)->create();

        Sanctum::actingAs($user);

        $response = $this->getJson(route('categories.index'));

        $response->assertOk();
        $response->assertJsonCount($ownedCategories->count(), 'data');
        foreach ($ownedCategories as $category) {
            $response->assertJsonFragment(['id' => $category->id]);
        }
        $response->assertJsonMissing(['id' => $foreignCategory->id]);
    }

    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\CategoryController::class,
            'store',
            CategoryStoreRequest::class
        );
    }

    #[Test]
    public function store_creates_category_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $payload = [
            'name' => $this->faker->words(2, true),
            'icon' => $this->faker->optional()->lexify('icon-????'),
        ];

        $response = $this->postJson(route('categories.store'), $payload);

        $response->assertCreated();
        $response->assertJsonFragment([
            'name' => $payload['name'],
            'icon' => $payload['icon'],
        ]);

        $this->assertDatabaseHas('categories', [
            'user_id' => $user->id,
            'name' => $payload['name'],
            'icon' => $payload['icon'],
        ]);
    }

    #[Test]
    public function show_returns_category_for_owner(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $response = $this->getJson(route('categories.show', $category));

        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $category->id,
            'name' => $category->name,
        ]);
    }

    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\CategoryController::class,
            'update',
            CategoryUpdateRequest::class
        );
    }

    #[Test]
    public function update_modifies_category(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create([
            'name' => 'Groceries',
            'icon' => 'cart',
        ]);

        Sanctum::actingAs($user);

        $payload = [
            'name' => 'Travel',
            'icon' => 'plane',
        ];

        $response = $this->putJson(route('categories.update', $category), $payload);

        $response->assertOk();

        $category->refresh();

        $this->assertSame($payload['name'], $category->name);
        $this->assertSame($payload['icon'], $category->icon);
    }

    #[Test]
    public function destroy_soft_deletes_category(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $response = $this->deleteJson(route('categories.destroy', $category));

        $response->assertNoContent();

        $this->assertSoftDeleted('categories', ['id' => $category->id]);
    }

    // ========================================
    // ADDITIONAL INDEX TESTS
    // ========================================

    #[Test]
    public function index_returns_empty_array_when_user_has_no_categories(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson(route('categories.index'));

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function index_without_authentication_returns_401(): void
    {
        $response = $this->getJson(route('categories.index'));

        $response->assertStatus(401);
    }

    #[Test]
    public function index_excludes_soft_deleted_categories(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $activeCategory = Category::factory()->for($user)->create();
        $deletedCategory = Category::factory()->for($user)->create();
        $deletedCategory->delete();

        $response = $this->getJson(route('categories.index'));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['id' => $activeCategory->id])
            ->assertJsonMissing(['id' => $deletedCategory->id]);
    }

    // ========================================
    // ADDITIONAL STORE TESTS
    // ========================================

    #[Test]
    public function store_allows_duplicate_category_names_for_same_user(): void
    {
        // Note: Unique validation is commented out in CategoryStoreRequest
        $user = User::factory()->create();
        Category::factory()->for($user)->create(['name' => 'Groceries']);

        Sanctum::actingAs($user);

        $payload = [
            'name' => 'Groceries',
            'icon' => 'cart',
        ];

        $response = $this->postJson(route('categories.store'), $payload);

        $response->assertStatus(201);
        $this->assertEquals(2, Category::where('user_id', $user->id)->where('name', 'Groceries')->count());
    }

    #[Test]
    public function store_allows_duplicate_category_name_for_different_users(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Category::factory()->for($user1)->create(['name' => 'Food']);

        Sanctum::actingAs($user2);

        $payload = [
            'name' => 'Food',
            'icon' => 'utensils',
        ];

        $response = $this->postJson(route('categories.store'), $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('categories', [
            'user_id' => $user2->id,
            'name' => 'Food',
        ]);
    }

    #[Test]
    public function store_fails_with_missing_name(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson(route('categories.store'), [
            'icon' => 'icon',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function store_succeeds_without_icon(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'name' => 'Transport',
        ];

        $response = $this->postJson(route('categories.store'), $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('categories', [
            'user_id' => $user->id,
            'name' => 'Transport',
            'icon' => null,
        ]);
    }

    #[Test]
    public function store_with_special_characters_in_name_succeeds(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'name' => "Coffee & Donuts @ Joe's 🎉",
            'icon' => 'coffee',
        ];

        $response = $this->postJson(route('categories.store'), $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('categories', [
            'name' => "Coffee & Donuts @ Joe's 🎉",
        ]);
    }

    #[Test]
    public function store_without_authentication_returns_401(): void
    {
        $response = $this->postJson(route('categories.store'), [
            'name' => 'Category',
        ]);

        $response->assertStatus(401);
    }

    // ========================================
    // ADDITIONAL SHOW TESTS
    // ========================================

    #[Test]
    public function show_for_non_owner_returns_403(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = Category::factory()->for($owner)->create();

        Sanctum::actingAs($otherUser);

        $response = $this->getJson(route('categories.show', $category));

        $response->assertStatus(403);
    }

    #[Test]
    public function show_for_nonexistent_category_returns_404(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson(route('categories.show', 99999));

        $response->assertStatus(404);
    }

    #[Test]
    public function show_without_authentication_returns_401(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $response = $this->getJson(route('categories.show', $category));

        $response->assertStatus(401);
    }

    #[Test]
    public function show_for_soft_deleted_category_returns_404(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $category->delete();

        Sanctum::actingAs($user);

        $response = $this->getJson(route('categories.show', $category));

        $response->assertStatus(404);
    }

    // ========================================
    // ADDITIONAL UPDATE TESTS
    // ========================================

    #[Test]
    public function update_with_only_name_updates_name_only(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create([
            'name' => 'Original',
            'icon' => 'original-icon',
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson(route('categories.update', $category), [
            'name' => 'Updated',
        ]);

        $response->assertStatus(200);

        $category->refresh();
        $this->assertEquals('Updated', $category->name);
        $this->assertEquals('original-icon', $category->icon);
    }

    #[Test]
    public function update_with_only_icon_updates_icon_only(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create([
            'name' => 'Transport',
            'icon' => 'car',
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson(route('categories.update', $category), [
            'icon' => 'plane',
        ]);

        $response->assertStatus(200);

        $category->refresh();
        $this->assertEquals('Transport', $category->name);
        $this->assertEquals('plane', $category->icon);
    }

    #[Test]
    public function update_by_non_owner_returns_403(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = Category::factory()->for($owner)->create();

        Sanctum::actingAs($otherUser);

        $response = $this->putJson(route('categories.update', $category), [
            'name' => 'Hacked',
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function update_without_authentication_returns_401(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $response = $this->putJson(route('categories.update', $category), [
            'name' => 'Updated',
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function update_with_duplicate_name_for_same_user_fails(): void
    {
        $user = User::factory()->create();
        $category1 = Category::factory()->for($user)->create(['name' => 'Food']);
        $category2 = Category::factory()->for($user)->create(['name' => 'Transport']);

        Sanctum::actingAs($user);

        $response = $this->putJson(route('categories.update', $category2), [
            'name' => 'Food',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function update_with_same_name_succeeds(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['name' => 'Food']);

        Sanctum::actingAs($user);

        $response = $this->putJson(route('categories.update', $category), [
            'name' => 'Food',
            'icon' => 'new-icon',
        ]);

        $response->assertStatus(200);

        $category->refresh();
        $this->assertEquals('Food', $category->name);
        $this->assertEquals('new-icon', $category->icon);
    }

    #[Test]
    public function update_with_empty_payload_returns_422(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $response = $this->putJson(route('categories.update', $category), []);

        $response->assertStatus(422);
    }

    // ========================================
    // ADDITIONAL DESTROY TESTS
    // ========================================

    #[Test]
    public function destroy_by_non_owner_returns_403(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = Category::factory()->for($owner)->create();

        Sanctum::actingAs($otherUser);

        $response = $this->deleteJson(route('categories.destroy', $category));

        $response->assertStatus(403);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function destroy_without_authentication_returns_401(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $response = $this->deleteJson(route('categories.destroy', $category));

        $response->assertStatus(401);
    }

    #[Test]
    public function destroy_nonexistent_category_returns_404(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->deleteJson(route('categories.destroy', 99999));

        $response->assertStatus(404);
    }

    #[Test]
    public function destroy_preserves_category_data_with_soft_delete(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create([
            'name' => 'Important Category',
            'icon' => 'star',
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson(route('categories.destroy', $category));

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Important Category',
            'icon' => 'star',
        ]);

        $category->refresh();
        $this->assertNotNull($category->deleted_at);
    }

    #[Test]
    public function destroy_category_with_transactions_soft_deletes_successfully(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        // Create transactions using this category
        \App\Models\Transaction::factory()->count(3)->for($user)->for($category)->create();

        Sanctum::actingAs($user);

        $response = $this->deleteJson(route('categories.destroy', $category));

        $response->assertStatus(204);
        $this->assertSoftDeleted('categories', ['id' => $category->id]);

        // Transactions should still exist and reference the soft-deleted category
        $this->assertEquals(3, \App\Models\Transaction::where('category_id', $category->id)->count());
    }
}
