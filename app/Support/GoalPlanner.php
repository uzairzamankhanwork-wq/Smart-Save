<?php

namespace App\Support;

use App\Models\SavingsGoal;
use App\Models\SavingsTransaction;
use Illuminate\Support\Carbon;

/**
 * Planning estimates for a goal. These are guides, not forecasts.
 * Every method handles: no target date, past deadlines, completed goals, zero values.
 */
final class GoalPlanner
{
    /** Days of history used to measure the recent saving rate. */
    public const RATE_WINDOW_DAYS = 90;

    /**
     * Whole months left until the target date, counting the current month.
     * Null when there is no target date; 0 when the deadline has passed.
     */
    public static function monthsLeft(SavingsGoal $goal, ?Carbon $today = null): ?int
    {
        if (! $goal->target_date) {
            return null;
        }
        $today = ($today ?? now())->copy()->startOfDay();
        if ($goal->target_date->lt($today)) {
            return 0;
        }
        $months = ($goal->target_date->year - $today->year) * 12 + ($goal->target_date->month - $today->month);

        return max(1, $months + ($goal->target_date->day >= $today->day ? 1 : 0));
    }

    /**
     * Amount per month needed to hit the target by the deadline.
     * Null if there is no deadline; the full remaining amount if the deadline passed.
     */
    public static function monthlyNeeded(SavingsGoal $goal, ?Carbon $today = null): ?string
    {
        $remaining = $goal->remaining();
        if (Money::isZero($remaining)) {
            return '0.00';
        }
        $months = self::monthsLeft($goal, $today);
        if ($months === null) {
            return null;
        }

        return Money::divideUp($remaining, max(1, $months));
    }

    /** Net amount added per month over the recent window (excludes opening balances). */
    public static function recentMonthlyRate(SavingsGoal $goal, ?Carbon $today = null): ?string
    {
        $today = ($today ?? now())->copy()->startOfDay();
        $from = $today->copy()->subDays(self::RATE_WINDOW_DAYS);

        $rows = $goal->transactions()->whereNull('voided_at')
            ->where('type', '!=', SavingsTransaction::INITIAL)
            ->whereDate('transaction_date', '>=', $from->toDateString())
            ->whereDate('transaction_date', '<=', $today->toDateString());

        // Need at least two contributions before a rate means anything.
        $deposits = (clone $rows)->whereIn('type', [SavingsTransaction::DEPOSIT, SavingsTransaction::TRANSFER_IN])->count();
        if ($deposits < 2) {
            return null;
        }

        $net = Money::normalize($rows->selectRaw('COALESCE(SUM('.SavingsTransaction::SIGNED_AMOUNT_SQL.'), 0) as t')->value('t'));
        $months = self::RATE_WINDOW_DAYS / 30;

        return Money::normalize(bcdiv($net, (string) $months, 4));
    }

    /** Estimated completion date at the recent rate, or null when not estimable. */
    public static function estimatedCompletion(SavingsGoal $goal, ?Carbon $today = null): ?Carbon
    {
        $today = ($today ?? now())->copy()->startOfDay();
        $remaining = $goal->remaining();
        if (Money::isZero($remaining)) {
            return $today;
        }
        $rate = self::recentMonthlyRate($goal, $today);
        if ($rate === null || Money::compare($rate, '0') <= 0) {
            return null;
        }
        $days = (int) ceil((float) bcdiv(bcmul($remaining, '30', 4), $rate, 4));

        return $today->copy()->addDays($days);
    }

    /**
     * Warnings to show on the goal. Each is ['level' => warning|danger|info, 'message' => ...].
     */
    public static function warnings(SavingsGoal $goal, ?Carbon $today = null): array
    {
        $today = ($today ?? now())->copy()->startOfDay();
        $warnings = [];
        if (! in_array($goal->status, [SavingsGoal::ACTIVE, SavingsGoal::PAUSED], true) || $goal->isTargetReached()) {
            return $warnings;
        }

        if ($goal->target_date) {
            $daysLeft = (int) $today->diffInDays($goal->target_date, false);
            if ($daysLeft < 0) {
                $warnings[] = ['level' => 'danger', 'message' => 'The target date has passed. Consider extending the deadline or adjusting the target.'];
            } elseif ($daysLeft <= 30) {
                $warnings[] = ['level' => 'warning', 'message' => "Only {$daysLeft} day(s) left until the target date."];
            }

            $estimate = self::estimatedCompletion($goal, $today);
            if ($daysLeft >= 0 && $estimate && $estimate->gt($goal->target_date)) {
                $warnings[] = ['level' => 'warning', 'message' => 'At your recent saving rate you would finish around '.$estimate->format('M Y').', after the target date.'];
            }
        }

        if ($goal->status === SavingsGoal::PAUSED) {
            $warnings[] = ['level' => 'info', 'message' => 'This goal is paused. Reminders are off until you resume it.'];
        }

        return $warnings;
    }

    /**
     * Suggested contribution schedule using the goal's frequency.
     *
     * @return array{per_period: ?string, periods: int, frequency: string, dates: Carbon[]}
     */
    public static function schedule(SavingsGoal $goal, ?Carbon $today = null, int $preview = 4): array
    {
        $today = ($today ?? now())->copy()->startOfDay();
        $frequency = $goal->contribution_frequency ?: 'monthly';
        $result = ['per_period' => null, 'periods' => 0, 'frequency' => $frequency, 'dates' => []];

        $remaining = $goal->remaining();
        if (Money::isZero($remaining) || ! $goal->target_date || $goal->target_date->lt($today)) {
            return $result;
        }

        $days = (int) $today->diffInDays($goal->target_date);
        $periods = match ($frequency) {
            'weekly' => intdiv($days, 7) + 1,
            'biweekly' => intdiv($days, 14) + 1,
            default => max(1, self::monthsLeft($goal, $today) ?? 1),
        };

        $date = $today->copy();
        for ($i = 0; $i < min($preview, $periods); $i++) {
            $result['dates'][] = $date->copy();
            $date = match ($frequency) {
                'weekly' => $date->addWeek(),
                'biweekly' => $date->addWeeks(2),
                default => $date->addMonthNoOverflow(),
            };
        }

        $result['periods'] = $periods;
        $result['per_period'] = Money::divideUp($remaining, $periods);

        return $result;
    }
}
