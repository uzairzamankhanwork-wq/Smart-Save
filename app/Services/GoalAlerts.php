<?php

namespace App\Services;

use App\Models\SavingsGoal;
use App\Models\SavingsTransaction;
use App\Models\User;
use App\Notifications\SmartSaveAlert;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Creates in-app notifications. Event-driven alerts (milestones, budget) fire
 * right after the change; time-based ones run from `smartsave:reminders` daily.
 * Every alert has a dedupe key so it is created at most once.
 */
class GoalAlerts
{
    public const DEADLINE_DAYS = 14;

    public const INACTIVE_DAYS = 30;

    public const MONTHLY_CHECK_DAY = 20;

    /** Milestones crossed by the latest balance change. */
    public function afterBalanceChange(SavingsGoal $goal, float $percentBefore): void
    {
        $user = $goal->user;
        $after = $goal->percent();

        foreach (config('smartsave.milestones') as $m) {
            if ($percentBefore < $m && $after >= $m) {
                $message = $m === 100
                    ? "You've fully funded “{$goal->name}”. You can mark it complete whenever you're ready."
                    : "“{$goal->name}” is now {$m}% funded. Keep it up!";
                $this->notifyOnce($user, 'milestone', new SmartSaveAlert(
                    'milestone', $m === 100 ? 'Goal fully funded' : "{$m}% milestone reached", $message,
                    "milestone:{$goal->id}:{$m}", route('goals.show', $goal), 'success',
                ));
            }
        }
    }

    public function goalCompleted(SavingsGoal $goal): void
    {
        $this->notifyOnce($goal->user, 'goal_completed', new SmartSaveAlert(
            'goal_completed', 'Goal completed', "Congratulations! “{$goal->name}” is complete.",
            "completed:{$goal->id}:".$goal->completed_at?->format('YmdHis'), route('goals.show', $goal), 'success',
        ));
    }

    /** Budget warnings at 80% and 100% utilization, once per month each. */
    public function checkBudget(User $user, Carbon $month): void
    {
        $status = app(FinanceSummary::class)->budgetStatus($user, $month);
        if ($status['budget_percent'] === null) {
            return;
        }
        $ym = $month->format('Y-m');
        $label = $month->format('F');

        if ($status['budget_percent'] >= 100) {
            $this->notifyOnce($user, 'budget', new SmartSaveAlert(
                'budget', 'Over budget', "You've spent {$user->money($status['spent'])} in {$label}, over your budget of {$user->money($status['expense_budget'])}.",
                "budget:100:{$ym}", route('budget.index'), 'warning',
            ));
        } elseif ($status['budget_percent'] >= 80) {
            $this->notifyOnce($user, 'budget', new SmartSaveAlert(
                'budget', 'Budget almost used', "You've used {$status['budget_percent']}% of your {$label} spending budget.",
                "budget:80:{$ym}", route('budget.index'), 'warning',
            ));
        }
    }

    /** Daily time-based reminders for one user. */
    public function runDaily(User $user, ?Carbon $today = null): void
    {
        $today = ($today ?? now())->copy()->startOfDay();
        $goals = $user->goals()->select('savings_goals.*')->withBalance()->where('status', SavingsGoal::ACTIVE)->get();

        foreach ($goals as $goal) {
            // Approaching deadline.
            if ($goal->target_date && ! $goal->isTargetReached()) {
                $days = (int) $today->diffInDays($goal->target_date, false);
                if ($days >= 0 && $days <= self::DEADLINE_DAYS) {
                    $this->notifyOnce($user, 'deadline', new SmartSaveAlert(
                        'deadline', 'Deadline approaching',
                        "“{$goal->name}” is due in {$days} day(s) with {$user->money($goal->remaining())} still to go.",
                        "deadline:{$goal->id}:{$goal->target_date->toDateString()}", route('goals.show', $goal), 'warning',
                    ));
                }
            }

            // Inactive goal: nothing added for INACTIVE_DAYS.
            if (! $goal->isTargetReached() && $goal->created_at->lte($today->copy()->subDays(self::INACTIVE_DAYS))) {
                $last = $goal->transactions()->whereNull('voided_at')
                    ->whereIn('type', [SavingsTransaction::DEPOSIT, SavingsTransaction::TRANSFER_IN, SavingsTransaction::INITIAL])
                    ->max('transaction_date');
                if (! $last || Carbon::parse($last)->lte($today->copy()->subDays(self::INACTIVE_DAYS))) {
                    $window = intdiv($today->timestamp, 86400 * self::INACTIVE_DAYS);
                    $this->notifyOnce($user, 'inactive_goal', new SmartSaveAlert(
                        'inactive_goal', 'Goal needs attention', "No money has gone into “{$goal->name}” for over ".self::INACTIVE_DAYS.' days.',
                        "inactive:{$goal->id}:{$window}", route('goals.show', $goal), 'info',
                    ));
                }
            }
        }

        // Monthly target check from the 20th onwards.
        if ($today->day >= self::MONTHLY_CHECK_DAY) {
            $status = app(FinanceSummary::class)->budgetStatus($user, $today);
            if ($status['savings_target'] !== null && Money::compare($status['saved'], $status['savings_target']) < 0) {
                $this->notifyOnce($user, 'monthly_target', new SmartSaveAlert(
                    'monthly_target', 'Monthly savings target',
                    "You've saved {$user->money($status['saved'])} of your {$user->money($status['savings_target'])} target this month. {$user->money($status['savings_remaining'])} to go.",
                    'monthly:'.$today->format('Y-m'), route('budget.index'), 'info',
                ));
            }
        }

        $this->checkBudget($user, $today);
    }

    private function notifyOnce(User $user, string $preference, SmartSaveAlert $alert): void
    {
        if (! $user->is_active || ! $user->wantsNotification($preference)) {
            return;
        }
        if ($user->notifications()->where('data->key', $alert->key)->exists()) {
            return;
        }
        $user->notify($alert);
    }
}
