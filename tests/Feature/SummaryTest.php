<?php

namespace Tests\Feature;

use App\Models\SavingsGoal;
use App\Models\User;
use App\Services\FinanceSummary;
use App\Services\SavingsLedger;
use App\Support\GoalPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_month_boundaries_are_inclusive_and_exact(): void
    {
        $user = User::factory()->create();
        $this->entry($user, null, 'deposit', '100', '2026-01-31');
        $this->entry($user, null, 'deposit', '200', '2026-02-01');
        $this->entry($user, null, 'deposit', '300', '2026-02-28');
        $this->entry($user, null, 'deposit', '400', '2026-03-01');
        $this->entry($user, null, 'deposit', '50', '2024-02-29'); // leap day, previous window

        $s = app(FinanceSummary::class);
        $feb = $s->savingsBetween($user, Carbon::parse('2026-02-01'), Carbon::parse('2026-02-01')->endOfMonth());
        $this->assertSame('500.00', $feb['contributed']);

        $series = $s->monthlySeries($user, Carbon::parse('2026-01-01'), Carbon::parse('2026-03-31'));
        $this->assertSame(['Jan 2026', 'Feb 2026', 'Mar 2026'], array_column($series, 'label'));
        $this->assertSame(['100.00', '500.00', '400.00'], array_column($series, 'contributed'));
        // Running balance includes the 2024 entry from before the window.
        $this->assertSame(['150.00', '650.00', '1050.00'], array_column($series, 'balance'));
    }

    public function test_year_boundary(): void
    {
        $user = User::factory()->create();
        $this->entry($user, null, 'deposit', '10', '2025-12-31');
        $this->entry($user, null, 'deposit', '20', '2026-01-01');

        $y = app(FinanceSummary::class)->savingsBetween($user, Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'));
        $this->assertSame('20.00', $y['contributed']);
    }

    public function test_voided_entries_are_excluded_everywhere(): void
    {
        $user = User::factory()->create();
        $goal = $this->goalFor($user);
        $tx = $this->entry($user, $goal, 'deposit', '500');
        $this->entry($user, $goal, 'deposit', '100');
        app(SavingsLedger::class)->void($tx, 'duplicate');

        $this->assertSame('100.00', $this->balanceOf($goal));
        $this->assertSame('100.00', app(SavingsLedger::class)->totalBalance($user));
        $m = app(FinanceSummary::class)->savingsBetween($user, now()->startOfMonth(), now()->endOfMonth());
        $this->assertSame('100.00', $m['contributed']);
    }

    public function test_overview_is_consistent_with_goal_balances(): void
    {
        $user = User::factory()->create();
        $a = $this->goalFor($user, ['target_amount' => '1000']);
        $b = $this->goalFor($user, ['target_amount' => '1000']);
        $c = $this->goalFor($user, ['target_amount' => '500']);
        $this->entry($user, $a, 'deposit', '1500'); // over-funded
        $this->entry($user, $b, 'deposit', '250');
        $this->entry($user, $c, 'deposit', '500');
        $this->entry($user, null, 'deposit', '75');
        $c->forceFill(['status' => SavingsGoal::PAUSED])->save();

        $o = app(FinanceSummary::class)->overview($user);
        $this->assertSame('2325.00', $o['total']);
        $this->assertSame('75.00', $o['unallocated']);
        $this->assertSame('1750.00', $o['allocated_active']);          // a + b (c is paused)
        $this->assertSame('2000.00', $o['target_active']);
        $this->assertSame(62.5, $o['overall_percent']);                // (1000 capped + 250) / 2000
        $this->assertSame(['active' => 2, 'paused' => 1, 'completed' => 0, 'archived' => 0], $o['counts']);

        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('Rs 1,750.00');
    }

    public function test_dashboard_renders_with_no_data_and_with_every_range(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('first savings goal');
        foreach (['3m', '6m', '12m', 'ytd', 'bogus'] as $r) {
            $this->get("/dashboard?range={$r}")->assertOk();
        }
    }

    public function test_planner_edge_cases(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15'));
        $user = User::factory()->create();

        $noDate = $this->goalFor($user, ['target_amount' => '1200', 'target_date' => null]);
        $this->assertNull(GoalPlanner::monthlyNeeded($noDate));
        $this->assertNull(GoalPlanner::monthsLeft($noDate));

        $past = $this->goalFor($user, ['target_amount' => '1200', 'target_date' => '2026-09-01']);
        $this->assertSame(0, GoalPlanner::monthsLeft($past));
        $this->assertSame('1200.00', GoalPlanner::monthlyNeeded($past), 'Overdue: everything is due now');
        $this->assertSame('danger', GoalPlanner::warnings($past)[0]['level']);

        $future = $this->goalFor($user, ['target_amount' => '1000', 'target_date' => '2027-01-15']);
        $this->assertSame(4, GoalPlanner::monthsLeft($future)); // Oct, Nov, Dec, Jan
        $this->assertSame('250.00', GoalPlanner::monthlyNeeded($future));

        $odd = $this->goalFor($user, ['target_amount' => '100', 'target_date' => '2027-01-15']);
        $this->assertSame('25.00', GoalPlanner::monthlyNeeded($odd));
        $third = $this->goalFor($user, ['target_amount' => '100', 'target_date' => '2026-12-15']);
        $this->assertSame('33.34', GoalPlanner::monthlyNeeded($third), 'Rounds up so the plan never falls short');

        $done = $this->goalFor($user, ['target_amount' => '100', 'target_date' => '2027-01-15']);
        $this->entry($user, $done, 'deposit', '150');
        $this->assertSame('0.00', GoalPlanner::monthlyNeeded($done->fresh()));
        $this->assertSame([], GoalPlanner::warnings($done->fresh()));
        $this->assertNull(GoalPlanner::schedule($done->fresh())['per_period']);
    }

    public function test_estimated_completion_needs_history_and_uses_recent_rate(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15'));
        $user = User::factory()->create();
        $goal = $this->goalFor($user, ['target_amount' => '10000', 'target_date' => '2027-01-01']);

        $this->entry($user, $goal, 'initial', '1000', '2026-10-01');
        $this->entry($user, $goal, 'deposit', '1500', '2026-09-01');
        $this->assertNull(GoalPlanner::estimatedCompletion($goal->fresh()), 'One contribution is not enough history');

        $this->entry($user, $goal, 'deposit', '1500', '2026-10-01');
        // 3000 over 90 days = 1000/month; 6000 remaining → 180 days
        $this->assertSame('1000.00', GoalPlanner::recentMonthlyRate($goal->fresh()));
        $this->assertSame('2027-04-13', GoalPlanner::estimatedCompletion($goal->fresh())->toDateString());
        $this->assertContains('warning', array_column(GoalPlanner::warnings($goal->fresh()), 'level'), 'Should warn the rate is too slow');
    }

    public function test_weekly_schedule(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15'));
        $user = User::factory()->create();
        $goal = $this->goalFor($user, ['target_amount' => '700', 'target_date' => '2026-11-26', 'contribution_frequency' => 'weekly']);

        $s = GoalPlanner::schedule($goal);
        $this->assertSame(7, $s['periods']); // 42 days → 6 full weeks + today
        $this->assertSame('100.00', $s['per_period']);
        $this->assertSame('2026-10-22', $s['dates'][1]->toDateString());
    }
}
