<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\MonthlyBudget;
use App\Models\SavingsGoal;
use App\Models\User;
use App\Services\SavingsLedger;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * LOCAL DEVELOPMENT ONLY. Creates a demo admin + user with sample data,
 * written through SavingsLedger so balances follow the real rules.
 * Credentials are printed to the console; never run this in production.
 */
class DemoSeeder extends Seeder
{
    public function run(SavingsLedger $ledger): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder must not run in production.');
        }

        $admin = $this->user('Demo Admin', 'admin@smartsave.test', 'admin');
        $user = $this->user('Ayesha Khan', 'ayesha@smartsave.test', 'user');

        foreach ([$admin, $user] as $u) {
            $this->seedFinances($u, $ledger);
        }

        $this->command?->info('Demo accounts: admin@smartsave.test / ayesha@smartsave.test, password: DemoPass123');
    }

    private function user(string $name, string $email, string $role): User
    {
        $user = User::where('email', $email)->first() ?? new User;
        $user->fill(['name' => $name, 'email' => $email, 'password' => 'DemoPass123', 'currency' => 'PKR']);
        $user->role = $role;
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    private function seedFinances(User $user, SavingsLedger $ledger): void
    {
        if ($user->goals()->exists()) {
            return;
        }

        $goals = [
            ['Emergency fund', 'emergency', 'shield', 'rose', '300000', 10, 'high', '120000'],
            ['New laptop', 'electronics', 'device', 'indigo', '250000', 5, 'medium', '40000'],
            ['Hajj / Umrah', 'travel', 'plane', 'cyan', '800000', 24, 'high', '0'],
            ['Car down payment', 'car', 'car', 'sky', '500000', 14, 'low', '60000'],
        ];

        $created = [];
        foreach ($goals as [$name, $cat, $icon, $color, $target, $months, $priority, $start]) {
            $g = new SavingsGoal([
                'name' => $name, 'category' => $cat, 'icon' => $icon, 'color' => $color, 'target_amount' => $target,
                'target_date' => now()->addMonths($months)->toDateString(), 'priority' => $priority,
                'planned_contribution' => '15000', 'contribution_frequency' => 'monthly',
            ]);
            $g->user_id = $user->id;
            $g->created_at = now()->subMonths(8);
            $g->save();
            $created[] = $g;

            if ($start > 0) {
                $ledger->record($user, ['type' => 'initial', 'goal_id' => $g->id, 'amount' => $start, 'transaction_date' => now()->subMonths(8)->toDateString(), 'note' => 'Starting amount']);
            }
        }

        // Eight months of contributions, a withdrawal, and a transfer.
        for ($m = 7; $m >= 0; $m--) {
            $date = now()->subMonthsNoOverflow($m)->startOfMonth()->addDays(rand(0, 20));
            $date = $date->gt(now()) ? now() : $date;
            foreach ($created as $i => $g) {
                $ledger->record($user, ['type' => 'deposit', 'goal_id' => $g->id, 'amount' => (string) (5000 + 2500 * (($i + $m) % 4)), 'transaction_date' => $date->toDateString(), 'note' => 'Monthly saving']);
            }
            foreach (['food' => [18000, 26000], 'bills' => [9000, 14000], 'transport' => [4000, 8000], 'shopping' => [2000, 15000], 'health' => [0, 5000]] as $cat => [$lo, $hi]) {
                $amount = rand($lo, $hi);
                if ($amount > 0) {
                    $e = new Expense(['amount' => $amount, 'category' => $cat, 'expense_date' => $date->copy()->addDays(rand(0, 5))->min(now())->toDateString(), 'note' => null]);
                    $e->user_id = $user->id;
                    $e->save();
                }
            }
        }

        $ledger->record($user, ['type' => 'withdrawal', 'goal_id' => $created[3]->id, 'amount' => '20000', 'transaction_date' => now()->subMonths(2)->toDateString(), 'note' => 'Car repair']);
        $ledger->transfer($user, $created[1]->id, $created[0]->id, '10000', now()->subMonth()->toDateString(), 'Topping up emergency fund');
        $ledger->record($user, ['type' => 'deposit', 'goal_id' => null, 'amount' => '15000', 'transaction_date' => now()->toDateString(), 'note' => 'Not yet allocated']);

        $budget = new MonthlyBudget(['month' => now()->subMonths(8)->startOfMonth()->toDateString(), 'savings_target' => '40000', 'expense_budget' => '70000', 'savings_frequency' => 'monthly']);
        $budget->user_id = $user->id;
        $budget->save();
    }
}
