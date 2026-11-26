<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_researchers_reviewers_and_chair_cannot_open_account_management(): void
    {
        foreach ([Role::Researcher, Role::Reviewer, Role::Chair] as $role) {
            $user = User::factory()->role($role)->create();

            $this->actingAs($user)->get('/users')->assertForbidden();
            $this->actingAs($user)->get('/audit-logs')->assertForbidden();
        }
    }

    public function test_admin_sees_all_accounts(): void
    {
        $admin = User::factory()->admin()->create();
        $reviewer = User::factory()->reviewer()->create();
        $researcher = User::factory()->create();

        $this->actingAs($admin)->get('/users')
            ->assertOk()
            ->assertSee($reviewer->email)
            ->assertSee($researcher->email);
    }

    public function test_secretariat_sees_only_researcher_accounts(): void
    {
        $secretariat = User::factory()->secretariat()->create();
        $reviewer = User::factory()->reviewer()->create();
        $researcher = User::factory()->pending()->create();

        $this->actingAs($secretariat)->get('/users?role=reviewer')
            ->assertOk()
            ->assertSee($researcher->email)
            ->assertDontSee($reviewer->email);
    }

    public function test_secretariat_can_activate_a_pending_researcher(): void
    {
        $secretariat = User::factory()->secretariat()->create();
        $researcher = User::factory()->pending()->create();

        $this->actingAs($secretariat)->post("/users/{$researcher->id}/activate")->assertRedirect();

        $researcher->refresh();
        $this->assertTrue($researcher->is_active);
        $this->assertSame($secretariat->id, $researcher->activated_by);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.activated',
            'user_id' => $secretariat->id,
            'subject_id' => $researcher->id,
        ]);

        // The researcher can now sign in.
        auth()->logout();
        $this->post('/login', ['email' => $researcher->email, 'password' => 'password'])->assertRedirect('/dashboard');
    }

    public function test_secretariat_cannot_manage_staff_accounts(): void
    {
        $secretariat = User::factory()->secretariat()->create();
        $reviewer = User::factory()->reviewer()->create();
        $pendingStaff = User::factory()->chair()->pending()->create();

        $this->actingAs($secretariat)->post("/users/{$reviewer->id}/deactivate")->assertForbidden();
        $this->actingAs($secretariat)->post("/users/{$pendingStaff->id}/activate")->assertForbidden();
        $this->actingAs($secretariat)->post("/users/{$reviewer->id}/reset-password")->assertForbidden();
        $this->actingAs($secretariat)->get('/users/create')->assertForbidden();
        $this->actingAs($secretariat)->get("/users/{$reviewer->id}/edit")->assertForbidden();

        $this->assertTrue($reviewer->fresh()->is_active);
    }

    public function test_admin_creates_a_staff_account_with_a_temporary_password(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/users', [
            'name' => 'New Reviewer',
            'email' => 'new.reviewer@example.com',
            'role' => Role::Reviewer->value,
            'college_program' => 'College of Nursing',
        ]);

        $response->assertRedirect('/users')->assertSessionHas('temporary_password');

        $user = User::where('email', 'new.reviewer@example.com')->sole();
        $this->assertSame(Role::Reviewer, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check(session('temporary_password')['password'], $user->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.created', 'subject_id' => $user->id]);
    }

    public function test_admin_can_deactivate_but_not_deactivate_themselves(): void
    {
        $admin = User::factory()->admin()->create();
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($admin)->post("/users/{$reviewer->id}/deactivate")->assertRedirect();
        $this->assertFalse($reviewer->fresh()->is_active);

        $this->actingAs($admin)->post("/users/{$admin->id}/deactivate")->assertForbidden();
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_admin_cannot_change_own_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put("/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => Role::Researcher->value,
        ])->assertSessionHasErrors('role');

        $this->assertSame(Role::Admin, $admin->fresh()->role);
    }

    public function test_password_reset_forces_a_new_password_at_next_sign_in(): void
    {
        $admin = User::factory()->admin()->create();
        $researcher = User::factory()->create();

        $this->actingAs($admin)->post("/users/{$researcher->id}/reset-password")
            ->assertSessionHas('temporary_password');

        $temporary = session('temporary_password')['password'];
        $this->assertTrue($researcher->fresh()->must_change_password);

        // The researcher signs in with the temporary password and is held on the change-password page.
        auth()->logout();
        $this->post('/login', ['email' => $researcher->email, 'password' => $temporary]);
        $this->get('/dashboard')->assertRedirect('/account/password');

        $this->put('/account/password', [
            'current_password' => $temporary,
            'password' => 'BrandNew2026',
            'password_confirmation' => 'BrandNew2026',
        ])->assertRedirect('/dashboard');

        $this->assertFalse($researcher->fresh()->must_change_password);
        $this->get('/dashboard')->assertOk();
    }

    public function test_change_password_requires_the_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/account/password', [
            'current_password' => 'not-my-password',
            'password' => 'BrandNew2026',
            'password_confirmation' => 'BrandNew2026',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_admin_can_view_the_audit_log(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post('/logout');

        $this->actingAs($admin)->get('/audit-logs')->assertOk()->assertSee('auth.logout');
    }
}
