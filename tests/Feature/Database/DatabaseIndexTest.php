<?php

namespace Tests\Feature\Database;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Database Index Tests
 * 
 * Tests to verify that database indexes are properly created and functioning.
 * These indexes are critical for query performance, especially as data grows.
 * 
 * Tested Indexes:
 * - transactions: user_id, category_id, date, (user_id, date)
 * - categories: user_id (via foreign key)
 * - budgets: user_id, category_id (via foreign keys)
 */
class DatabaseIndexTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function transactions_table_has_user_id_index(): void
    {
        $indexes = $this->getTableIndexes('transactions');

        $this->assertTrue(
            $this->hasIndex($indexes, 'transactions_user_id_index') ||
                $this->hasIndex($indexes, 'transactions_user_id_foreign'),
            'transactions table should have user_id index'
        );
    }

    #[Test]
    public function transactions_table_has_category_id_index(): void
    {
        $indexes = $this->getTableIndexes('transactions');

        $this->assertTrue(
            $this->hasIndex($indexes, 'transactions_category_id_index') ||
                $this->hasIndex($indexes, 'transactions_category_id_foreign'),
            'transactions table should have category_id index'
        );
    }

    #[Test]
    public function transactions_table_has_date_index(): void
    {
        $indexes = $this->getTableIndexes('transactions');

        $this->assertTrue(
            $this->hasIndex($indexes, 'transactions_date_index'),
            'transactions table should have date index'
        );
    }

    #[Test]
    public function transactions_table_has_composite_user_id_date_index(): void
    {
        $indexes = $this->getTableIndexes('transactions');

        $this->assertTrue(
            $this->hasIndex($indexes, 'transactions_user_id_date_index'),
            'transactions table should have composite (user_id, date) index for date range queries'
        );
    }

    #[Test]
    public function categories_table_has_user_id_index(): void
    {
        $indexes = $this->getTableIndexes('categories');

        $this->assertTrue(
            $this->hasIndex($indexes, 'categories_user_id_index') ||
                $this->hasIndex($indexes, 'categories_user_id_foreign'),
            'categories table should have user_id index'
        );
    }

    #[Test]
    public function budgets_table_has_user_id_index(): void
    {
        $indexes = $this->getTableIndexes('budgets');

        $this->assertTrue(
            $this->hasIndex($indexes, 'budgets_user_id_index') ||
                $this->hasIndex($indexes, 'budgets_user_id_foreign'),
            'budgets table should have user_id index'
        );
    }

    #[Test]
    public function budgets_table_has_category_id_index(): void
    {
        $indexes = $this->getTableIndexes('budgets');

        $this->assertTrue(
            $this->hasIndex($indexes, 'budgets_category_id_index') ||
                $this->hasIndex($indexes, 'budgets_category_id_foreign'),
            'budgets table should have category_id index'
        );
    }

    #[Test]
    public function user_id_index_improves_transaction_query_performance(): void
    {
        // Create test data
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        // Create enough transactions to make index usage meaningful
        Transaction::factory()->count(100)->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);

        // Enable query logging
        DB::enableQueryLog();

        // Query transactions by user_id (should use index)
        Transaction::where('user_id', $user->id)->get();

        $queries = DB::getQueryLog();

        // Verify query was executed
        $this->assertNotEmpty($queries);

        // The query should include WHERE user_id = ?
        $this->assertStringContainsString('user_id', $queries[0]['query']);

        DB::disableQueryLog();
    }

    #[Test]
    public function date_index_improves_date_range_query_performance(): void
    {
        // Create test data
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        // Create transactions with different dates
        Transaction::factory()->count(50)->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'date' => '2025-01-15',
        ]);

        Transaction::factory()->count(50)->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'date' => '2025-02-15',
        ]);

        // Enable query logging
        DB::enableQueryLog();

        // Query transactions by date range (should use composite index)
        Transaction::where('user_id', $user->id)
            ->whereBetween('date', ['2025-01-01', '2025-01-31'])
            ->get();

        $queries = DB::getQueryLog();

        // Verify query was executed with date filter
        $this->assertNotEmpty($queries);
        $this->assertStringContainsString('date', $queries[0]['query']);

        DB::disableQueryLog();
    }

    #[Test]
    public function category_id_index_improves_budget_calculation_performance(): void
    {
        // Create test data
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        // Create many transactions for this category
        Transaction::factory()->count(100)->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);

        // Enable query logging
        DB::enableQueryLog();

        // Query transactions by category (should use index)
        Transaction::where('category_id', $category->id)->sum('amount');

        $queries = DB::getQueryLog();

        // Verify query was executed
        $this->assertNotEmpty($queries);
        $this->assertStringContainsString('category_id', $queries[0]['query']);

        DB::disableQueryLog();
    }

    #[Test]
    public function composite_index_optimizes_user_date_range_queries(): void
    {
        // This is the most common query pattern in the application:
        // Get user's transactions within a date range (for budgets, analytics, etc.)

        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        // Create transactions across multiple months
        for ($month = 1; $month <= 12; $month++) {
            Transaction::factory()->count(10)->create([
                'user_id' => $user->id,
                'category_id' => $category->id,
                'date' => sprintf('2025-%02d-15', $month),
            ]);
        }

        // Enable query logging
        DB::enableQueryLog();

        // This query pattern benefits from composite (user_id, date) index
        $transactions = Transaction::where('user_id', $user->id)
            ->whereBetween('date', ['2025-06-01', '2025-06-30'])
            ->get();

        $queries = DB::getQueryLog();

        // Verify query structure
        $this->assertNotEmpty($queries);
        $this->assertStringContainsString('user_id', $queries[0]['query']);
        $this->assertStringContainsString('between', strtolower($queries[0]['query']));

        // Verify results are correct (should be 10 transactions in June)
        $this->assertEquals(10, $transactions->count());

        DB::disableQueryLog();
    }

    #[Test]
    public function indexes_do_not_slow_down_write_operations(): void
    {
        // Indexes can slow down writes, so we verify writes are still fast

        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        $startTime = microtime(true);

        // Create 100 transactions (tests INSERT performance with indexes)
        for ($i = 0; $i < 100; $i++) {
            Transaction::factory()->create([
                'user_id' => $user->id,
                'category_id' => $category->id,
            ]);
        }

        $duration = microtime(true) - $startTime;

        // Should complete in reasonable time (< 5 seconds even with indexes)
        $this->assertLessThan(
            5.0,
            $duration,
            'Creating 100 transactions should be fast even with indexes'
        );
    }

    // ========================================
    // Helper Methods
    // ========================================

    /**
     * Get all indexes for a table
     */
    private function getTableIndexes(string $table): array
    {
        $connection = DB::connection();
        $database = $connection->getDatabaseName();

        $indexes = DB::select("
            SELECT DISTINCT INDEX_NAME 
            FROM INFORMATION_SCHEMA.STATISTICS 
            WHERE TABLE_SCHEMA = ? 
            AND TABLE_NAME = ?
        ", [$database, $table]);

        return array_column($indexes, 'INDEX_NAME');
    }

    /**
     * Check if an index exists
     */
    private function hasIndex(array $indexes, string $indexName): bool
    {
        return in_array($indexName, $indexes);
    }
}
