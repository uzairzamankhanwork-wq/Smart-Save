<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ledger entry. `amount` is always positive; the sign comes from `type`.
 * user_id is not fillable: it is always set from the authenticated user.
 */
#[Fillable(['goal_id', 'type', 'amount', 'transaction_date', 'note', 'transfer_group', 'idempotency_key'])]
class SavingsTransaction extends Model
{
    use HasFactory;

    public const INITIAL = 'initial';

    public const DEPOSIT = 'deposit';

    public const WITHDRAWAL = 'withdrawal';

    public const TRANSFER_IN = 'transfer_in';

    public const TRANSFER_OUT = 'transfer_out';

    public const TYPES = [self::INITIAL, self::DEPOSIT, self::WITHDRAWAL, self::TRANSFER_IN, self::TRANSFER_OUT];

    public const NEGATIVE_TYPES = [self::WITHDRAWAL, self::TRANSFER_OUT];

    /**
     * THE single rule for balances: sum of this expression over non-voided rows.
     * Used for goal balances, total savings, and every report.
     */
    public const SIGNED_AMOUNT_SQL = "CASE WHEN type IN ('withdrawal','transfer_out') THEN -amount ELSE amount END";

    public const LABELS = [
        self::INITIAL => 'Opening balance',
        self::DEPOSIT => 'Contribution',
        self::WITHDRAWAL => 'Withdrawal',
        self::TRANSFER_IN => 'Transfer in',
        self::TRANSFER_OUT => 'Transfer out',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
            'voided_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(SavingsGoal::class, 'goal_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }

    public function isNegative(): bool
    {
        return in_array($this->type, self::NEGATIVE_TYPES, true);
    }

    public function isTransfer(): bool
    {
        return $this->transfer_group !== null;
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function signedAmount(): string
    {
        return $this->isNegative() ? '-'.$this->amount : (string) $this->amount;
    }

    public function label(): string
    {
        return self::LABELS[$this->type] ?? $this->type;
    }
}
