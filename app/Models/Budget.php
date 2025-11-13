<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Budget extends Model
{
    use HasFactory;

    /**
     * Scope to return only models owned by the given user (or current auth user).
     *
     * Usage: Budget::owned()->get(); or Budget::owned($user)->paginate()
     */
    // public function scopeOwned($query, $user = null)
    // {
    //     $user = $user ?: auth()->user();
    //     if (! $user) {
    //         // if no user, return an empty query
    //         return $query->whereRaw('0 = 1');
    //     }
    //     return $query->where('user_id', $user->id);
    // }

    /**
     * The attributes that aren't mass assignable.
     * We guard 'id' and 'user_id' to prevent unauthorized ownership changes.
     */
    protected $guarded = ['id', 'user_id'];

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
