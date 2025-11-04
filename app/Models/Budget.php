<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// We have intentionally REMOVED 'SoftDeletes' from this model
// because the migration does not support it. This fixes the
// "Unknown column 'deleted_at'" error.

class Budget extends Model
{
    use HasFactory; // <-- Note: SoftDeletes has been removed.

    /**
     * The attributes that are mass assignable.
     * This is critical for our update() method to work.
     */
    protected $fillable = [
        'user_id',
        'category_id',
        'limit',
        'period',
        'start_date',
        'end_date',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'limit' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Get the user that owns the budget.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the category that this budget applies to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
