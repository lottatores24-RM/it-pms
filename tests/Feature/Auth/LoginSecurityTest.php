<?php

namespace Tests\Feature\Auth;

use App\Enums\ActivityAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_wrong_password_and_unknown_email_get_the_same_generic_error(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => 'Email atau password salah.']);

        $this->post('/login', ['email' => 'nobody@itpms.test', 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => 'Email atau password salah.']);

        $this->assertGuest();
    }

    public function test_fifth_failed_attempt_locks_the_account_for_fifteen_minutes(): void
    {
        $user = User::factory()->create();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->travel(14)->minutes();
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->assertGuest();

        $this->travel(2)->minutes();
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_four_failed_attempts_do_not_lock_the_account(): void
    {
        $user = User::factory()->create();

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Akun Anda telah dinonaktifkan. Hubungi Administrator.']);

        $this->assertGuest();
    }

    public function test_user_deactivated_during_a_session_is_logged_out_on_next_request(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->update(['is_active' => false]);

        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_and_logout_are_recorded_in_the_activity_log(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post('/logout')
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Anda telah logout.');

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => ActivityAction::Login->value,
            'entity_type' => 'User',
            'entity_id' => $user->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => ActivityAction::Logout->value,
        ]);
    }

    public function test_dashboard_redirects_guests_to_login_after_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout');

        $this->get('/dashboard')->assertRedirect(route('login'));
    }
}
