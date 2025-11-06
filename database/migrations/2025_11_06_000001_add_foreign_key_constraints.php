<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration adds proper foreign key constraints to ensure referential integrity.
     * Without these constraints, orphaned records and data anomalies can occur.
     */
    public function up(): void
    {
        // Add foreign key constraint to categories table
        Schema::table('categories', function (Blueprint $table) {
            // If user is deleted, cascade delete all their categories
            $table->foreign('user_id', 'fk_categories_user_id')
                ->references('id')->on('users')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });

        // Add foreign key constraints to transactions table
        Schema::table('transactions', function (Blueprint $table) {
            // If user is deleted, cascade delete their transactions
            $table->foreign('user_id', 'fk_transactions_user_id')
                ->references('id')->on('users')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            // NOTE: We do NOT add a foreign key constraint for category_id
            // Reason: Categories use soft deletes. When a user "deletes" a category,
            // it should remain hidden but historical transactions should still reference it.
            // This preserves transaction history while preventing the category from being
            // used for new transactions.
            //
            // To load deleted categories in transaction history, use:
            // $transaction->category()->withTrashed()->first();
        });

        // Add foreign key constraints to budgets table
        Schema::table('budgets', function (Blueprint $table) {
            // If user is deleted, cascade delete their budgets
            $table->foreign('user_id', 'fk_budgets_user_id')
                ->references('id')->on('users')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            // If category is deleted, set category_id to NULL
            // This allows the budget to continue as an "overall" budget
            $table->foreign('category_id', 'fk_budgets_category_id')
                ->references('id')->on('categories')
                ->onDelete('set null')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign('fk_categories_user_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign('fk_transactions_user_id');
            // Note: No category_id foreign key to drop (by design)
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->dropForeign('fk_budgets_user_id');
            $table->dropForeign('fk_budgets_category_id');
        });
    }
};
