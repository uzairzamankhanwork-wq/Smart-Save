<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SmartSaveAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_milestones_fire_once_as_balance_crosses_them(): void
    {
        $user = User::factory()->create();
        $goal = $this->goalFor($user, ['target_amount' => '100']);

        $this->entry($user, $goal, 'deposit', '30');   // crosses 25
        $this->entry($user, $goal, 'deposit', '30');   // crosses 50
        $this->entry($user, $goal, 'deposit', '50');   // crosses 75 and 100

        $keys = $user->notifications()->get()->pluck('data.key')->sort()->values()->all();
        $this->assertSame(["milestone:{$goal->id}:100", "milestone:{$goal->id}:25", "milestone:{$goal->id}:50", "milestone:{$goal->id}:75"], $keys);
    }

    public function test_daily_reminders_for_deadline_inactivity_and_monthly_target(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01'));
        $user = User::factory()->create();
        $soon = $this->goalFor($user, ['name' => 'Soon', 'target_amount' => '1000', 'target_date' => '2026-11-25']);
        $this->entry($user, $soon, 'deposit', '100', '2026-10-01');
        $this->actingAs($user)->put('/budget', ['savings_target' => '5000', 'savings_frequency' => 'monthly']);

        $this->travelTo(Carbon::parse('2026-11-20'));
        $this->artisan('smartsave:reminders')->assertSuccessful();
        $this->artisan('smartsave:reminders')->assertSuccessful(); // idempotent

        $kinds = $user->notifications()->get()->pluck('data.kind')->countBy()->all();
        $this->assertSame(1, $kinds['deadline']);
        $this->assertSame(1, $kinds['inactive_goal']);
        $this->assertSame(1, $kinds['monthly_target']);
    }

    public function test_preferences_turn_reminders_off(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->patch('/profile/preferences', ['notifications' => ['milestone' => '0', 'deadline' => '1']])->assertSessionHas('success');
        $this->assertFalse($user->fresh()->wantsNotification('milestone'));

        $goal = $this->goalFor($user->fresh(), ['target_amount' => '10']);
        $this->entry($user->fresh(), $goal, 'deposit', '10');
        $this->assertSame(0, $user->notifications()->count());
    }

    public function test_notification_links_cannot_redirect_off_site(): void
    {
        $user = User::factory()->create();
        $user->notify(new SmartSaveAlert('milestone', 'T', 'M', 'k', rtrim(url('/'), '/').'.evil.test/phish'));
        $id = $user->notifications()->first()->id;

        $this->actingAs($user)->get("/notifications/{$id}/open")->assertRedirect(route('notifications.index'));
    }

    public function test_notifications_page_and_mark_read(): void
    {
        $user = User::factory()->create();
        $goal = $this->goalFor($user, ['target_amount' => '10']);
        $this->entry($user, $goal, 'deposit', '10');
        $n = $user->notifications()->first();

        $this->actingAs($user)->get('/notifications')->assertOk()->assertSee('Goal fully funded');
        $this->get("/notifications/{$n->id}/open")->assertRedirect(route('goals.show', $goal));
        $this->assertNotNull($n->fresh()->read_at);
        $this->post('/notifications/read-all')->assertSessionHas('success');
        $this->assertSame(0, $user->unreadNotifications()->count());
    }
}
