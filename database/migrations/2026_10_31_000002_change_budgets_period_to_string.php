<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `period` moves from a DB-level ENUM('monthly','yearly') to a plain string column so
     * adding new periods (e.g. "weekly", which BudgetStoreRequest already computes end dates
     * for) doesn't require a schema migration. Allowed values are validated once, in
     * BudgetStoreRequest/BudgetUpdateRequest.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->sqliteSwapColumn('period', 'period_new');
        } else {
            DB::statement('ALTER TABLE budgets MODIFY period VARCHAR(20) NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("UPDATE budgets SET period = 'monthly' WHERE period = 'weekly'");

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->sqliteSwapColumn('period', 'period_old');
        } else {
            DB::statement("ALTER TABLE budgets MODIFY period ENUM('monthly','yearly') NOT NULL");
        }
    }

    private function sqliteSwapColumn(string $column, string $tempColumn): void
    {
        Schema::table('budgets', function ($table) use ($tempColumn, $column) {
            $table->string($tempColumn, 20)->nullable()->after($column);
        });

        DB::statement("UPDATE budgets SET {$tempColumn} = {$column}");

        Schema::table('budgets', function ($table) use ($column) {
            $table->dropColumn($column);
        });

        Schema::table('budgets', function ($table) use ($tempColumn, $column) {
            $table->renameColumn($tempColumn, $column);
        });
    }
};
