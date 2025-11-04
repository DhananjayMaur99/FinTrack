<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'category_id',
        'amount',
        'description',
        'date',
    ];

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
