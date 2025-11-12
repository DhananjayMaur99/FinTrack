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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            // Intentionally NOT adding a foreign key for category_id to allow historical integrity
            // even if a category is hard-deleted. Validation enforces ownership & existence.
            $table->foreignId('category_id');
            $table->decimal('amount', 10, 2);
            $table->string('description')->nullable();
            $table->date('date');
            // $table->date('date_local')->nullable();
            // $table->timestamp('occurred_at_utc')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
