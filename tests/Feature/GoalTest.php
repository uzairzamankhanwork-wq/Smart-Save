<?php

namespace Tests\Feature;

use App\Models\SavingsGoal;
use App\Models\SavingsTransaction;
use App\Models\User;
use App\Services\SavingsLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class GoalTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Emergency fund', 'category' => 'emergency', 'icon' => 'shield', 'color' => 'rose',
            'target_amount' => '300000', 'target_date' => now()->addMonths(10)->toDateString(),
            'priority' => 'high', 'contribution_frequency' => 'monthly',
        ], $overrides);
    }

    public function test_create_goal_with_starting_amount_records_one_opening_balance(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/goals', $this->payload(['starting_amount' => '50000', 'idempotency_key' => (string) Str::uuid()]))
            ->assertRedirect()->assertSessionHas('success');

        $goal = SavingsGoal::firstOrFail();
        $this->assertSame($user->id, $goal->user_id);
        $this->assertSame('50000.00', $this->balanceOf($goal));
        $this->assertSame(1, SavingsTransaction::where('type', 'initial')->count());
        $this->assertDatabaseHas('activity_logs', ['action' => 'goal.created', 'subject_id' => $goal->id]);
    }

    public function test_double_submitted_goal_form_creates_only_one_goal(): void
    {
        $user = User::factory()->create();
        $data = $this->payload(['starting_amount' => '1000', 'idempotency_key' => (string) Str::uuid()]);

        $this->actingAs($user)->post('/goals', $data);
        $this->post('/goals', $data)->assertSessionHas('info');

        $this->assertSame(1, SavingsGoal::count());
        $this->assertSame(1, SavingsTransaction::count());
    }

    public function test_goal_validation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/goals', $this->payload([
            'target_amount' => '-5', 'target_date' => now()->subDay()->toDateString(), 'category' => 'yacht', 'color' => '#fff',
        ]))->assertSessionHasErrors(['target_amount', 'target_date', 'category', 'color']);
        $this->assertSame(0, SavingsGoal::count());
    }

    public function test_goal_creation_rolls_back_if_the_opening_balance_fails(): void
    {
        $user = User::factory()->create();
        $this->mock(SavingsLedger::class)->shouldReceive('record')->andThrow(new RuntimeException('disk full'));
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($user)->post('/goals', $this->payload(['starting_amount' => '1000']));
            $this->fail('Expected exception');
        } catch (RuntimeException) {
        }

        $this->assertSame(0, SavingsGoal::count(), 'Goal insert must be rolled back with the failed entry');
    }

    public function test_edit_goal_keeps_transactions(): void
    {
        $user = User::factory()->create();
        $goal = $this->goalFor($user);
        $this->entry($user, $goal, 'deposit', '2500');

        $this->actingAs($user)->put("/goals/{$goal->id}", $this->payload(['name' => 'Renamed', 'target_amount' => '1000']))
            ->assertRedirect("/goals/{$goal->id}");

        $this->assertSame('Renamed', $goal->fresh()->name);
        $this->assertSame('2500.00', $this->balanceOf($goal));
        $this->assertSame(100.0, $goal->fresh()->percent(), 'Progress caps at 100% when over-funded');
    }

    public function test_pause_and_resume(): void
    {
        $user = User::factory()->create();
        $goal = $this->goalFor($user);

        $this->actingAs($user)->patch("/goals/{$goal->id}/status", ['status' => 'paused'])->assertSessionHas('success');
        $this->assertSame('paused', $goal->fresh()->status);

        $this->patch("/goals/{$goal->id}/status", ['status' => 'active'])->assertSessionHas('success');
        $this->assertSame('active', $goal->fresh()->status);
    }

    public function test_goal_can_only_be_completed_once_target_is_reached(): void
    {
        $user = User::factory()->create();
        $goal = $this->goalFor($user, ['target_amount' => '1000']);
        $this->entry($user, $goal, 'deposit', '999.99');

        $this->actingAs($user)->patch("/goals/{$goal->id}/status", ['status' => 'completed'])->assertSessionHas('error');
        $this->assertSame('active', $goal->fresh()->status);

        $this->entry($user, $goal, 'deposit', '0.01');
        $this->patch("/goals/{$goal->id}/status", ['status' => 'completed'])->assertSessionHas('success');
        $this->assertSame('completed', $goal->fresh()->status);
        $this->assertNotNull($goal->fresh()->completed_at);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $user->id]);
    }

    public function test_completed_goal_rejects_deposits_but_allows_withdrawal(): void
    {
        $user = User::factory()->create();
        $goal = $this->goalFor($user, ['target_amount' => '100']);
        $this->entry($user, $goal, 'deposit', '100');
        $goal->forceFill(['status' => 'completed'])->save();

        $this->actingAs($user)->post('/transactions', ['type' => 'deposit', 'goal_id' => $goal->id, 'amount' => '5', 'transaction_date' => now()->toDateString()])
            ->assertSessionHasErrors('goal_id');
        $this->post('/transactions', ['type' => 'withdrawal', 'goal_id' => $goal->id, 'amount' => '100', 'transaction_date' => now()->toDateString()])
            ->assertSessionHasNoErrors();
        $this->assertSame('0.00', $this->balanceOf($goal));
    }

    public function test_archived_goal_is_read_only_but_still_counts_in_total(): void
    {
        $user = User::factory()->create();
        $goal = $this->goalFor($user);
        $tx = $this->entry($user, $goal, 'deposit', '500');

        $this->actingAs($user)->patch("/goals/{$goal->id}/status", ['status' => 'archived'])->assertSessionHas('success');

        $this->post('/transactions', ['type' => 'withdrawal', 'goal_id' => $goal->id, 'amount' => '1', 'transaction_date' => now()->toDateString()])
            ->assertSessionHasErrors('goal_id');
        $this->post("/transactions/{$tx->id}/void", ['reason' => 'test'])->assertSessionHasErrors('reason');
        $this->put("/goals/{$goal->id}", $this->payload())->assertSessionHas('error');

        $this->assertSame('500.00', app(SavingsLedger::class)->totalBalance($user));
        $this->assertSame(1, SavingsTransaction::count(), 'Archiving never deletes history');
    }

    public function test_goal_with_history_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $goal = $this->goalFor($user);
        $this->entry($user, $goal, 'deposit', '10');

        $this->actingAs($user)->delete("/goals/{$goal->id}")->assertSessionHas('error');
        $this->assertModelExists($goal);

        $empty = $this->goalFor($user);
        $this->delete("/goals/{$empty->id}")->assertRedirect('/goals');
        $this->assertModelMissing($empty);
    }

    public function test_goal_pages_render(): void
    {
        $user = User::factory()->create();
        $goal = $this->goalFor($user);
        $this->entry($user, $goal, 'deposit', '1000', now()->subDays(40)->toDateString());
        $this->entry($user, $goal, 'deposit', '1000', now()->subDays(10)->toDateString());

        $this->actingAs($user)->get('/goals')->assertOk()->assertSee($goal->name);
        $this->get("/goals/{$goal->id}")->assertOk()->assertSee('Contribution history');
        $this->get("/goals/{$goal->id}/edit")->assertOk();
        $this->get('/goals/create')->assertOk();
    }
}
