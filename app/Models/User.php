<?php

namespace App\Models;

// This is the class we must extend for authentication
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes; // <-- 1. Import SoftDeletes
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

// This class MUST extend "Authenticatable"
/**
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Category[] $categories
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Transaction[] $transactions
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Budget[] $budgets
 */
class User extends Authenticatable
{
    // 2. Add HasApiTokens, HasFactory, Notifiable, AND SoftDeletes
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed', // <-- 3. This auto-hashes new passwords
    ];

    // --- 4. ADD ALL THE RELATIONSHIPS ---

    /**
     * Get the categories for the user.
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * Get the transactions for the user.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get the budgets for the user.
     */
    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }
}
