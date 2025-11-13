<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Scope to return only models owned by the given user (or current auth user).
     *
     * Usage: Budget::owned()->get(); or Budget::owned($user)->paginate()
     */
    public function scopeOwned($query, $user = null)
    {
        $user = $user ?: auth()->user();
        if (! $user) {
            // if no user, return an empty query
            return $query->whereRaw('0 = 1');
        }
        return $query->where('user_id', $user->id);
    }

    protected $fillable = [
        'user_id',
        'category_id',
        'amount',
        'description',
        'date',
        // 'date_local',
        // 'occurred_at_utc',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
        // 'date_local' => 'date',
        // 'occurred_at_utc' => 'datetime',
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
