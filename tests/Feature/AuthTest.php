<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_every_protected_page(): void
    {
        foreach (['/dashboard', '/goals', '/transactions', '/expenses', '/budget', '/reports', '/profile', '/notifications', '/admin/users', '/transactions/export'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->post('/transactions', [])->assertRedirect('/login');
    }

    public function test_there_is_no_public_registration(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'x', 'email' => 'x@x.test', 'password' => 'Password123'])->assertNotFound();
    }

    public function test_user_can_log_in_and_out(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'Password123'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => 'auth.login']);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected_with_a_generic_message(): void
    {
        $user = User::factory()->create();

        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_failures(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'Password123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertStringContainsString('Too many sign-in attempts', session('errors')->first('email'));
    }

    public function test_deactivated_user_cannot_log_in_and_is_logged_out_mid_session(): void
    {
        $user = User::factory()->inactive()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'Password123'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $active = User::factory()->create();
        $this->actingAs($active)->get('/dashboard')->assertOk();
        $active->forceFill(['is_active' => false])->save();
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_session_expires_after_idle_timeout(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')->assertOk();

        $this->travel(config('smartsave.idle_timeout') + 1)->minutes();

        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_temporary_password_forces_a_change_before_anything_else(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/change-password');
        $this->get('/goals')->assertRedirect('/change-password');

        $this->put('/change-password', [
            'current_password' => 'Password123', 'password' => 'NewSecret123', 'password_confirmation' => 'NewSecret123',
        ])->assertRedirect('/dashboard');

        $this->assertFalse($user->fresh()->must_change_password);
        $this->get('/dashboard')->assertOk();
    }

    public function test_weak_passwords_are_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'Password123', 'password' => 'short', 'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    }

    public function test_password_reset_link_and_token_flow(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        // Same response for unknown emails (no account enumeration).
        $this->post('/forgot-password', ['email' => 'nobody@example.test'])->assertSessionHas('status');
        Notification::assertNothingSent();

        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class, function ($n) use ($user) {
            $this->post('/reset-password', [
                'token' => $n->token, 'email' => $user->email,
                'password' => 'BrandNew123', 'password_confirmation' => 'BrandNew123',
            ])->assertRedirect('/login');

            return true;
        });

        $this->post('/login', ['email' => $user->email, 'password' => 'BrandNew123'])->assertRedirect('/dashboard');
    }

    public function test_reset_token_expires(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($n) use ($user) {
            $this->travel(config('auth.passwords.users.expire') + 1)->minutes();
            $this->post('/reset-password', [
                'token' => $n->token, 'email' => $user->email, 'password' => 'BrandNew123', 'password_confirmation' => 'BrandNew123',
            ])->assertSessionHasErrors('email');

            return true;
        });
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get('/login')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_production_adds_csp_and_hsts(): void
    {
        $this->app['env'] = 'production';

        $response = $this->get('https://localhost/login');
        $this->assertStringContainsString("frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('max-age=31536000', $response->headers->get('Strict-Transport-Security'));
    }
}
