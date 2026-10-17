<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable(['month', 'savings_target', 'expense_budget', 'savings_frequency'])]
class MonthlyBudget extends Model
{
    protected function casts(): array
    {
        return [
            'month' => 'date',
            'savings_target' => 'decimal:2',
            'expense_budget' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The plan in force for a month: that month's row, else the latest earlier one. */
    public static function forMonth(User $user, Carbon $month): ?self
    {
        return $user->monthlyBudgets()
            ->whereDate('month', '<=', $month->copy()->startOfMonth()->toDateString())
            ->orderByDesc('month')
            ->first();
    }
}
