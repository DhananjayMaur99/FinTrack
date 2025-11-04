<?php

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
}
