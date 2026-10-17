<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_users_cannot_reach_admin_pages(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/users')->assertForbidden();
        $this->post('/admin/users', [])->assertForbidden();
    }

    public function test_admin_creates_users_up_to_the_limit_with_temporary_password(): void
    {
        $admin = User::factory()->admin()->create();
        $payload = fn ($email) => ['name' => 'X', 'email' => $email, 'role' => 'user', 'password' => 'TempPass123', 'password_confirmation' => 'TempPass123'];

        $this->actingAs($admin)->post('/admin/users', $payload('a@test.test'))->assertSessionHas('success');
        $this->post('/admin/users', $payload('b@test.test'))->assertSessionHas('success');
        $this->post('/admin/users', $payload('c@test.test'))->assertSessionHas('error');

        $this->assertSame(3, User::count());
        $this->assertTrue(User::where('email', 'a@test.test')->first()->must_change_password);
    }

    public function test_role_cannot_be_mass_assigned_on_profile_update(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->patch('/profile', [
            'name' => 'Me', 'email' => $user->email, 'currency' => 'USD', 'role' => 'admin', 'is_active' => 1,
        ]);
        $this->assertSame('user', $user->fresh()->role);
        $this->assertSame('USD', $user->fresh()->currency);
    }

    public function test_admin_cannot_demote_or_deactivate_themselves(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->patch("/admin/users/{$admin->id}", ['role' => 'user', 'is_active' => 1])->assertSessionHas('error');
        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_first_admin_command_works_once(): void
    {
        $this->artisan('smartsave:create-admin', ['--name' => 'Owner', '--email' => 'owner@test.test'])
            ->expectsQuestion('Password (min 10 chars, upper+lower case and a number)', 'OwnerPass123')
            ->expectsQuestion('Confirm password', 'OwnerPass123')
            ->assertSuccessful();

        $this->assertTrue(User::where('email', 'owner@test.test')->first()->isAdmin());

        $this->artisan('smartsave:create-admin', ['--name' => 'Other', '--email' => 'other@test.test'])->assertFailed();
        $this->assertSame(1, User::count());
    }
}
