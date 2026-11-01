<?php

namespace App\Modules\Budgets\Models;

use App\Modules\Categories\Models\Category;
use App\Modules\Users\Models\User;
use Database\Factories\BudgetFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Budget extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'limit',
        'period',
        'start_date',
        'end_date',
    ];

    /**
     * Moving models out of App\Models breaks Laravel's default factory-name
     * guessing, so the factory class is wired up explicitly here.
     */
    protected static function newFactory(): BudgetFactory
    {
        return BudgetFactory::new();
    }

    protected $casts = [
        'limit' => 'float',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * @return BelongsTo<User, Budget>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Category, Budget>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
