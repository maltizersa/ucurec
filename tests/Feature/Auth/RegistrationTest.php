<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function validData(array $overrides = []): array
    {
        return [
            'name' => 'Test Researcher',
            'email' => 'researcher@example.com',
            'college_program' => config('ucurec.colleges')[0],
            'contact_number' => '0917 123 4567',
            'password' => 'Secret1234',
            'password_confirmation' => 'Secret1234',
            ...$overrides,
        ];
    }

    public function test_sign_up_creates_an_inactive_researcher_account(): void
    {
        $this->post('/register', $this->validData())->assertRedirect('/register/pending');

        $user = User::where('email', 'researcher@example.com')->sole();

        $this->assertSame(Role::Researcher, $user->role);
        $this->assertFalse($user->is_active);
        $this->assertTrue($user->isPendingActivation());
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.registered', 'user_id' => $user->id]);
    }

    public function test_sign_up_cannot_choose_a_role_or_activate_itself(): void
    {
        $this->post('/register', $this->validData(['role' => 'admin', 'is_active' => 1]));

        $user = User::where('email', 'researcher@example.com')->sole();

        $this->assertSame(Role::Researcher, $user->role);
        $this->assertFalse($user->is_active);
    }

    public function test_sign_up_requires_a_listed_college_and_a_strong_password(): void
    {
        $this->post('/register', $this->validData([
            'college_program' => 'Not A Real College',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]))->assertSessionHasErrors(['college_program', 'password']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'researcher@example.com']);

        $this->post('/register', $this->validData())->assertSessionHasErrors('email');
    }
}
