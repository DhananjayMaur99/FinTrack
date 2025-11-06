<?php

namespace App\Console\Commands;

use App\Models\Budget;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupOrphanedData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:cleanup-orphans {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find and clean up orphaned records before adding foreign key constraints';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No data will be deleted');
        } else {
            $this->warn('⚠️  LIVE MODE - Data will be permanently deleted!');
            if (! $this->confirm('Are you sure you want to proceed?')) {
                $this->info('Operation cancelled.');

                return 0;
            }
        }

        $this->info('Starting orphaned data cleanup...');
        $this->newLine();

        $totalOrphans = 0;

        // Check 1: Orphaned Categories
        $this->info('Checking for orphaned categories...');
        $orphanedCategories = DB::table('categories as c')
            ->leftJoin('users as u', 'c.user_id', '=', 'u.id')
            ->whereNull('u.id')
            ->select('c.id', 'c.name', 'c.user_id')
            ->get();

        if ($orphanedCategories->count() > 0) {
            $this->warn("Found {$orphanedCategories->count()} orphaned categories:");
            $this->table(
                ['ID', 'Name', 'Invalid User ID'],
                $orphanedCategories->map(fn ($cat) => [$cat->id, $cat->name, $cat->user_id])
            );

            if (! $dryRun) {
                $deleted = DB::table('categories')
                    ->whereIn('id', $orphanedCategories->pluck('id'))
                    ->delete();
                $this->info("✅ Deleted $deleted orphaned categories");
                $totalOrphans += $deleted;
            }
        } else {
            $this->info('✅ No orphaned categories found');
        }
        $this->newLine();

        // Check 2: Orphaned Transactions (user_id)
        $this->info('Checking for orphaned transactions (invalid user_id)...');
        $orphanedTransactionsByUser = DB::table('transactions as t')
            ->leftJoin('users as u', 't.user_id', '=', 'u.id')
            ->whereNull('u.id')
            ->select('t.id', 't.amount', 't.user_id', 't.date')
            ->get();

        if ($orphanedTransactionsByUser->count() > 0) {
            $this->warn("Found {$orphanedTransactionsByUser->count()} orphaned transactions (invalid user_id):");
            $this->table(
                ['ID', 'Amount', 'Invalid User ID', 'Date'],
                $orphanedTransactionsByUser->map(fn ($t) => [$t->id, $t->amount, $t->user_id, $t->date])
            );

            if (! $dryRun) {
                $deleted = DB::table('transactions')
                    ->whereIn('id', $orphanedTransactionsByUser->pluck('id'))
                    ->delete();
                $this->info("✅ Deleted $deleted orphaned transactions");
                $totalOrphans += $deleted;
            }
        } else {
            $this->info('✅ No orphaned transactions (user_id) found');
        }
        $this->newLine();

        // Check 3: Orphaned Transactions (category_id) - only hard orphans
        $this->info('Checking for orphaned transactions (invalid category_id, excluding soft-deleted)...');
        $orphanedTransactionsByCategory = DB::table('transactions as t')
            ->leftJoin('categories as c', 't.category_id', '=', 'c.id')
            ->whereNotNull('t.category_id')
            ->whereNull('c.id')
            ->select('t.id', 't.amount', 't.category_id', 't.date')
            ->get();

        if ($orphanedTransactionsByCategory->count() > 0) {
            $this->warn("Found {$orphanedTransactionsByCategory->count()} transactions with hard-orphaned category_id:");
            $this->table(
                ['ID', 'Amount', 'Invalid Category ID', 'Date'],
                $orphanedTransactionsByCategory->map(fn ($t) => [$t->id, $t->amount, $t->category_id, $t->date])
            );

            $this->warn('Note: We will NOT add foreign key constraint on category_id, so this is informational only.');
            $this->comment('These transactions reference categories that were force-deleted (not soft-deleted).');
        } else {
            $this->info('✅ No hard-orphaned transactions (category_id) found');
        }
        $this->newLine();

        // Check 4: Transactions with soft-deleted categories (VALID - not orphans)
        $this->info('Checking for transactions with soft-deleted categories (VALID, informational only)...');
        $transactionsWithDeletedCategories = DB::table('transactions as t')
            ->join('categories as c', 't.category_id', '=', 'c.id')
            ->whereNotNull('c.deleted_at')
            ->select('t.id', 't.amount', 't.category_id', 'c.name as category_name')
            ->get();

        if ($transactionsWithDeletedCategories->count() > 0) {
            $this->info("Found {$transactionsWithDeletedCategories->count()} transactions with soft-deleted categories (THIS IS VALID):");
            $this->comment('These transactions correctly reference soft-deleted categories to preserve history.');
            $this->table(
                ['Transaction ID', 'Amount', 'Category ID', 'Category Name (deleted)'],
                $transactionsWithDeletedCategories->map(fn ($t) => [$t->id, $t->amount, $t->category_id, $t->category_name])
            );
        } else {
            $this->info('✅ No transactions with soft-deleted categories');
        }
        $this->newLine();

        // Check 5: Orphaned Budgets (user_id)
        $this->info('Checking for orphaned budgets (invalid user_id)...');
        $orphanedBudgetsByUser = DB::table('budgets as b')
            ->leftJoin('users as u', 'b.user_id', '=', 'u.id')
            ->whereNull('u.id')
            ->select('b.id', 'b.limit', 'b.user_id', 'b.period')
            ->get();

        if ($orphanedBudgetsByUser->count() > 0) {
            $this->warn("Found {$orphanedBudgetsByUser->count()} orphaned budgets (invalid user_id):");
            $this->table(
                ['ID', 'Limit', 'Invalid User ID', 'Period'],
                $orphanedBudgetsByUser->map(fn ($b) => [$b->id, $b->limit, $b->user_id, $b->period])
            );

            if (! $dryRun) {
                $deleted = DB::table('budgets')
                    ->whereIn('id', $orphanedBudgetsByUser->pluck('id'))
                    ->delete();
                $this->info("✅ Deleted $deleted orphaned budgets");
                $totalOrphans += $deleted;
            }
        } else {
            $this->info('✅ No orphaned budgets (user_id) found');
        }
        $this->newLine();

        // Check 6: Orphaned Budgets (category_id)
        $this->info('Checking for orphaned budgets (invalid category_id)...');
        $orphanedBudgetsByCategory = DB::table('budgets as b')
            ->leftJoin('categories as c', 'b.category_id', '=', 'c.id')
            ->whereNotNull('b.category_id')
            ->whereNull('c.id')
            ->select('b.id', 'b.limit', 'b.category_id', 'b.period')
            ->get();

        if ($orphanedBudgetsByCategory->count() > 0) {
            $this->warn("Found {$orphanedBudgetsByCategory->count()} orphaned budgets (invalid category_id):");
            $this->table(
                ['ID', 'Limit', 'Invalid Category ID', 'Period'],
                $orphanedBudgetsByCategory->map(fn ($b) => [$b->id, $b->limit, $b->category_id, $b->period])
            );

            if (! $dryRun) {
                // Set category_id to NULL instead of deleting the budget
                $updated = DB::table('budgets')
                    ->whereIn('id', $orphanedBudgetsByCategory->pluck('id'))
                    ->update(['category_id' => null]);
                $this->info("✅ Set category_id to NULL for $updated orphaned budgets (converted to 'Overall' budgets)");
                $totalOrphans += $updated;
            }
        } else {
            $this->info('✅ No orphaned budgets (category_id) found');
        }
        $this->newLine();

        // Summary
        $this->info('=====================================');
        if ($dryRun) {
            $this->info('🔍 DRY RUN COMPLETE');
            $this->comment("Would have cleaned up $totalOrphans orphaned records");
            $this->newLine();
            $this->info('Run without --dry-run to actually clean up the data:');
            $this->comment('php artisan db:cleanup-orphans');
        } else {
            if ($totalOrphans > 0) {
                $this->info("✅ CLEANUP COMPLETE - Fixed $totalOrphans orphaned records");
            } else {
                $this->info('✅ DATABASE IS CLEAN - No orphaned data found');
            }
        }
        $this->info('=====================================');
        $this->newLine();

        if (! $dryRun && $totalOrphans === 0) {
            $this->info('✅ Your database is ready for foreign key constraint migration!');
            $this->comment('You can now safely run: php artisan migrate');
        }

        return 0;
    }
}
