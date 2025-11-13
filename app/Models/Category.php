<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, SoftDeletes;
    /**
     * The attributes that aren't mass assignable.
     * We guard 'id' and 'user_id' to prevent unauthorized ownership changes.
     */
    protected $guarded = ['id', 'user_id'];

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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'user_id' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
