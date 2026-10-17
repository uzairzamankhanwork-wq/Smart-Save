<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'description', 'category', 'icon', 'color', 'target_amount', 'target_date',
    'priority', 'planned_contribution', 'contribution_frequency', 'notes',
])]
class SavingsGoal extends Model
{
    use HasFactory;

    public const ACTIVE = 'active';

    public const PAUSED = 'paused';

    public const COMPLETED = 'completed';

    public const ARCHIVED = 'archived';

    public const STATUSES = [self::ACTIVE, self::PAUSED, self::COMPLETED, self::ARCHIVED];

    public const PRIORITIES = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'];

    protected $attributes = [
        'status' => self::ACTIVE,
    ];

    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:2',
            'planned_contribution' => 'decimal:2',
            'target_date' => 'date',
            'completed_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SavingsTransaction::class, 'goal_id');
    }

    /** Adds a `balance` column computed with the single ledger rule. */
    public function scopeWithBalance(Builder $query): Builder
    {
        return $query->addSelect([
            'balance' => SavingsTransaction::query()
                ->selectRaw('COALESCE(SUM('.SavingsTransaction::SIGNED_AMOUNT_SQL.'), 0)')
                ->whereColumn('goal_id', 'savings_goals.id')
                ->whereNull('voided_at'),
        ]);
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /** Current saved amount (uses the preloaded `balance` column when present). */
    public function balance(): string
    {
        if (array_key_exists('balance', $this->attributes)) {
            return Money::normalize($this->attributes['balance']);
        }

        return $this->freshBalance();
    }

    public function freshBalance(): string
    {
        $sum = $this->transactions()->whereNull('voided_at')
            ->selectRaw('COALESCE(SUM('.SavingsTransaction::SIGNED_AMOUNT_SQL.'), 0) as total')
            ->value('total');

        return Money::normalize($sum);
    }

    public function remaining(): string
    {
        return Money::max(Money::sub((string) $this->target_amount, $this->balance()), '0');
    }

    public function percent(): float
    {
        return Money::percent($this->balance(), (string) $this->target_amount);
    }

    public function isTargetReached(): bool
    {
        return Money::compare($this->balance(), (string) $this->target_amount) >= 0;
    }

    /** @return int[] milestone percentages already reached */
    public function milestonesReached(): array
    {
        $pct = $this->percent();

        return array_values(array_filter(config('smartsave.milestones'), fn ($m) => $pct >= $m));
    }

    // ---- Status rules -------------------------------------------------------

    public function canReceiveMoney(): bool
    {
        return in_array($this->status, [self::ACTIVE, self::PAUSED], true);
    }

    public function canReleaseMoney(): bool
    {
        return $this->status !== self::ARCHIVED;
    }

    /** @return string[] statuses this goal may move to right now */
    public function allowedTransitions(): array
    {
        return match ($this->status) {
            self::ACTIVE => array_values(array_filter([self::PAUSED, $this->isTargetReached() ? self::COMPLETED : null, self::ARCHIVED])),
            self::PAUSED => array_values(array_filter([self::ACTIVE, $this->isTargetReached() ? self::COMPLETED : null, self::ARCHIVED])),
            self::COMPLETED => [self::ACTIVE, self::ARCHIVED],
            self::ARCHIVED => [self::ACTIVE],
            default => [],
        };
    }

    // ---- Presentation helpers ----------------------------------------------

    public function categoryLabel(): string
    {
        return config("smartsave.goal_categories.{$this->category}.label", ucfirst($this->category));
    }

    public function statusLabel(): string
    {
        return ucfirst($this->status);
    }
}
