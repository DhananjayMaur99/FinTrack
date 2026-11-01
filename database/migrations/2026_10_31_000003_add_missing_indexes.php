<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->unique(['user_id', 'name'], 'categories_user_id_name_unique');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['user_id', 'date'], 'transactions_user_id_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique('categories_user_id_name_unique');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_user_id_date_index');
        });
    }
};
