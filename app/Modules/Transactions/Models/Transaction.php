<?php

namespace App\Modules\Transactions\Models;

use App\Modules\Categories\Models\Category;
use App\Modules\Users\Models\User;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'type',
        'amount',
        'description',
        'date',
    ];

    /**
     * Moving models out of App\Models breaks Laravel's default factory-name
     * guessing, so the factory class is wired up explicitly here.
     */
    protected static function newFactory(): TransactionFactory
    {
        return TransactionFactory::new();
    }

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
    ];

    // Correct relation: Transaction belongs to a User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Correct relation: Transaction belongs to a Category
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
