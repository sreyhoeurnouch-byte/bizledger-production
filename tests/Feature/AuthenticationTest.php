<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_active_user_can_sign_in(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id, 'password' => 'secret-password', 'active' => true]);
        $this->post('/login', ['email' => $user->email, 'password' => 'secret-password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_an_active_user_can_request_a_password_reset_link(): void
    {
        Notification::fake();
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id, 'active' => true]);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'user_id' => $user->id, 'action' => 'password_reset_requested']);
    }
}
