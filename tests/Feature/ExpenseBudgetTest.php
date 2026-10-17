<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\MonthlyBudget;
use App\Models\User;
use App\Services\FinanceSummary;
use App\Services\SavingsLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExpenseBudgetTest extends TestCase
{
    use RefreshDatabase;

    private function expense(User $user, string $amount, string $date, string $category = 'food'): Expense
    {
        $e = new Expense(['amount' => $amount, 'category' => $category, 'expense_date' => $date]);
        $e->user_id = $user->id;
        $e->save();

        return $e;
    }

    public function test_expense_crud_does_not_touch_savings(): void
    {
        $user = User::factory()->create();
        $goal = $this->goalFor($user);
        $this->entry($user, $goal, 'deposit', '1000');

        $this->actingAs($user)->post('/expenses', ['amount' => '250.50', 'category' => 'bills', 'expense_date' => now()->toDateString(), 'idempotency_key' => (string) Str::uuid()])
            ->assertRedirect('/expenses');
        $expense = Expense::firstOrFail();
        $this->assertSame($user->id, $expense->user_id);

        $this->put("/expenses/{$expense->id}", ['amount' => '300', 'category' => 'food', 'expense_date' => now()->toDateString()])->assertRedirect('/expenses');
        $this->assertSame('300.00', (string) $expense->fresh()->amount);

        $this->assertSame('1000.00', $this->balanceOf($goal), 'Expenses must never reduce a savings goal');
        $this->assertSame('1000.00', app(SavingsLedger::class)->totalBalance($user));

        $this->delete("/expenses/{$expense->id}")->assertSessionHas('success');
        $this->assertModelMissing($expense);
        $this->assertDatabaseHas('activity_logs', ['action' => 'expense.deleted', 'subject_id' => $expense->id]);
    }

    public function test_expense_validation_and_duplicates(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/expenses', ['amount' => '0', 'category' => 'yachts', 'expense_date' => now()->addDay()->toDateString()])
            ->assertSessionHasErrors(['amount', 'category', 'expense_date']);

        $data = ['amount' => '10', 'category' => 'food', 'expense_date' => now()->toDateString(), 'idempotency_key' => (string) Str::uuid()];
        $this->post('/expenses', $data);
        $this->post('/expenses', $data)->assertSessionHas('info');
        $this->assertSame(1, Expense::count());
    }

    public function test_budget_status_calculation(): void
    {
        $this->travelTo(Carbon::parse('2026-03-15'));
        $user = User::factory()->create();
        $this->actingAs($user)->put('/budget', ['savings_target' => '20000', 'expense_budget' => '10000', 'savings_frequency' => 'monthly'])
            ->assertSessionHas('success');

        $this->entry($user, null, 'deposit', '15000', '2026-03-02');
        $this->entry($user, null, 'withdrawal', '2000', '2026-03-05');
        $this->entry($user, null, 'initial', '50000', '2026-03-01'); // opening balance is not "saved this month"
        $this->expense($user, '8500', '2026-03-10');
        $this->expense($user, '3000', '2026-02-28'); // previous month

        $s = app(FinanceSummary::class)->budgetStatus($user, now());
        $this->assertSame('13000.00', $s['saved']);
        $this->assertSame('7000.00', $s['savings_remaining']);
        $this->assertSame(65.0, $s['savings_percent']);
        $this->assertSame('8500.00', $s['spent']);
        $this->assertSame(85.0, $s['budget_percent']);
        $this->assertFalse($s['over_budget']);

        $this->expense($user, '2000', '2026-03-14');
        $s = app(FinanceSummary::class)->budgetStatus($user, now());
        $this->assertTrue($s['over_budget']);
        $this->assertSame('-500.00', $s['budget_left']);
        $this->assertSame(105.0, $s['budget_percent']);
    }

    public function test_budget_plan_carries_forward_but_past_months_keep_their_plan(): void
    {
        $user = User::factory()->create();
        $make = function (string $month, string $target) use ($user) {
            $b = new MonthlyBudget(['month' => $month, 'savings_target' => $target, 'savings_frequency' => 'monthly']);
            $b->user_id = $user->id;
            $b->save();
        };
        $make('2026-01-01', '1000');
        $make('2026-04-01', '2000');

        $this->assertNull(MonthlyBudget::forMonth($user, Carbon::parse('2025-12-20')));
        $this->assertSame('1000.00', (string) MonthlyBudget::forMonth($user, Carbon::parse('2026-03-31'))->savings_target);
        $this->assertSame('2000.00', (string) MonthlyBudget::forMonth($user, Carbon::parse('2026-04-01'))->savings_target);
        $this->assertSame('2000.00', (string) MonthlyBudget::forMonth($user, Carbon::parse('2026-09-10'))->savings_target);
    }

    public function test_over_budget_creates_one_warning_notification(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->put('/budget', ['expense_budget' => '100', 'savings_frequency' => 'monthly']);

        $post = fn ($amount) => $this->post('/expenses', ['amount' => $amount, 'category' => 'food', 'expense_date' => now()->toDateString()]);
        $post('50');
        $this->assertSame(0, $user->notifications()->count());
        $post('35'); // 85%
        $post('30'); // 115%
        $post('30'); // still over: no duplicate

        $keys = $user->notifications()->get()->pluck('data.key')->all();
        $this->assertCount(2, $keys);
        $this->assertContains('budget:80:'.now()->format('Y-m'), $keys);
        $this->assertContains('budget:100:'.now()->format('Y-m'), $keys);
    }

    public function test_budget_page_and_expense_pages_render(): void
    {
        $user = User::factory()->create();
        $this->goalFor($user);
        $this->expense($user, '10', now()->toDateString());
        $this->actingAs($user)->get('/budget')->assertOk();
        $this->get('/expenses')->assertOk();
        $this->get('/expenses/create')->assertOk();
    }
}
