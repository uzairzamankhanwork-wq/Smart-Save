<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\SavingsTransaction;
use App\Models\User;
use App\Notifications\SmartSaveAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Object-level access control: nobody, admins included, can touch another user's records. */
class PrivacyTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $intruder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['name' => 'Owner Person']);
        // The intruder is an admin to prove admin rights grant no data access.
        $this->intruder = User::factory()->admin()->create();
    }

    public function test_cannot_view_or_modify_another_users_goal(): void
    {
        $goal = $this->goalFor($this->owner, ['name' => 'Secret goal']);
        $this->actingAs($this->intruder);

        $this->get("/goals/{$goal->id}")->assertNotFound();
        $this->get("/goals/{$goal->id}/edit")->assertNotFound();
        $this->put("/goals/{$goal->id}", ['name' => 'Hacked', 'category' => 'other', 'icon' => 'star', 'color' => 'rose', 'target_amount' => '1', 'priority' => 'low', 'contribution_frequency' => 'monthly'])->assertNotFound();
        $this->patch("/goals/{$goal->id}/status", ['status' => 'archived'])->assertNotFound();
        $this->delete("/goals/{$goal->id}")->assertNotFound();
        $this->get('/goals?status=all')->assertDontSee('Secret goal');

        $this->assertSame('Secret goal', $goal->fresh()->name);
        $this->assertSame('active', $goal->fresh()->status);
    }

    public function test_cannot_record_into_or_transfer_from_another_users_goal(): void
    {
        $goal = $this->goalFor($this->owner);
        $this->entry($this->owner, $goal, 'deposit', '500');
        $mine = $this->goalFor($this->intruder);
        $this->actingAs($this->intruder);

        $this->post('/transactions', ['type' => 'withdrawal', 'goal_id' => $goal->id, 'amount' => '100', 'transaction_date' => now()->toDateString()])
            ->assertSessionHasErrors('goal_id');
        $this->post('/transfers', ['from_goal_id' => $goal->id, 'to_goal_id' => $mine->id, 'amount' => '100', 'transaction_date' => now()->toDateString()])
            ->assertSessionHasErrors('from_goal_id');

        $this->assertSame('500.00', $this->balanceOf($goal));
        $this->assertSame(1, SavingsTransaction::count());
    }

    public function test_cannot_edit_or_void_another_users_transaction(): void
    {
        $tx = $this->entry($this->owner, null, 'deposit', '100');
        $this->actingAs($this->intruder);

        $this->get("/transactions/{$tx->id}/edit")->assertNotFound();
        $this->put("/transactions/{$tx->id}", ['amount' => '1', 'transaction_date' => now()->toDateString()])->assertNotFound();
        $this->post("/transactions/{$tx->id}/void", ['reason' => 'x'])->assertNotFound();
        $this->get('/transactions')->assertViewHas('transactions', fn ($p) => $p->total() === 0);

        $this->assertNull($tx->fresh()->voided_at);
        $this->assertSame('100.00', (string) $tx->fresh()->amount);
    }

    public function test_cannot_see_edit_or_delete_another_users_expense(): void
    {
        $expense = new Expense(['amount' => '42', 'category' => 'food', 'expense_date' => now()->toDateString(), 'note' => 'private note']);
        $expense->user_id = $this->owner->id;
        $expense->save();
        $this->actingAs($this->intruder);

        $this->get("/expenses/{$expense->id}/edit")->assertNotFound();
        $this->put("/expenses/{$expense->id}", ['amount' => '1', 'category' => 'food', 'expense_date' => now()->toDateString()])->assertNotFound();
        $this->delete("/expenses/{$expense->id}")->assertNotFound();
        $this->get('/expenses')->assertDontSee('private note');
        $this->assertModelExists($expense);
    }

    public function test_cannot_open_another_users_notification(): void
    {
        $this->owner->notify(new SmartSaveAlert('milestone', 'T', 'M', 'k1'));
        $id = $this->owner->notifications()->first()->id;

        $this->actingAs($this->intruder)->get("/notifications/{$id}/open")->assertNotFound();
        $this->assertNull($this->owner->notifications()->first()->read_at);
    }

    public function test_dashboard_reports_and_admin_page_show_only_own_figures(): void
    {
        $goal = $this->goalFor($this->owner, ['name' => 'Owner Goal']);
        $this->entry($this->owner, $goal, 'deposit', '123456.78');
        $this->actingAs($this->intruder);

        $this->get('/dashboard')->assertOk()->assertDontSee('123,456.78')->assertDontSee('Owner Goal');
        $this->get('/reports')->assertOk()->assertDontSee('123,456.78')->assertDontSee('Owner Goal');
        // Admin page lists the account but never balances.
        $this->get('/admin/users')->assertOk()->assertSee('Owner Person')->assertDontSee('123,456.78');
    }

    public function test_goal_id_in_filters_cannot_leak_other_users_rows(): void
    {
        $goal = $this->goalFor($this->owner);
        $this->entry($this->owner, $goal, 'deposit', '10');

        $this->actingAs($this->intruder)->get("/transactions?goal={$goal->id}&show=all")
            ->assertViewHas('transactions', fn ($p) => $p->total() === 0);
    }
}
