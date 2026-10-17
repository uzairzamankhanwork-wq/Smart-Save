<?php

namespace App\Models;

use App\Support\Money;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// role, is_active and must_change_password are deliberately NOT fillable:
// they are only changed explicitly by admin actions (mass-assignment protection).
#[Fillable(['name', 'email', 'password', 'currency', 'preferences'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_USER = 'user';

    protected $attributes = [
        'role' => self::ROLE_USER,
        'currency' => 'PKR',
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'preferences' => 'array',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function goals(): HasMany
    {
        return $this->hasMany(SavingsGoal::class);
    }

    public function savingsTransactions(): HasMany
    {
        return $this->hasMany(SavingsTransaction::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function monthlyBudgets(): HasMany
    {
        return $this->hasMany(MonthlyBudget::class);
    }

    /** Notification preference, falling back to config defaults. */
    public function wantsNotification(string $type): bool
    {
        $prefs = $this->preferences['notifications'] ?? [];

        return (bool) ($prefs[$type] ?? config("smartsave.notification_defaults.$type", false));
    }

    public function money(mixed $amount, bool $withSymbol = true): string
    {
        return Money::format($amount, $this->currency, $withSymbol);
    }
}
