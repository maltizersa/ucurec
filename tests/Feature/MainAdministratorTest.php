<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MainAdministratorTest extends TestCase
{
    use RefreshDatabase;

    private function runMainAdminMigration(): void
    {
        $migration = require database_path('migrations/2026_11_26_000003_create_main_administrator_account.php');
        $migration->up();
    }

    private function mainAdmin(): User
    {
        return User::factory()->admin()->create(['is_main_admin' => true])->refresh();
    }

    public function test_migration_creates_the_main_administrator_once(): void
    {
        config(['ucurec.main_admin' => [
            'name' => 'Main Admin',
            'email' => 'Main@Example.com',
            'password' => 'StartPass2026',
        ]]);

        $this->runMainAdminMigration();
        $this->runMainAdminMigration();

        $admin = User::where('email', 'main@example.com')->sole();

        $this->assertSame(Role::Admin, $admin->role);
        $this->assertTrue($admin->isMainAdmin());
        $this->assertTrue($admin->is_active);
        $this->assertTrue($admin->must_change_password);
        $this->assertTrue(Hash::check('StartPass2026', $admin->password));
    }

    public function test_migration_promotes_an_existing_account_without_changing_its_password(): void
    {
        $existing = User::factory()->reviewer()->deactivated()->create(['email' => 'main@example.com']);
        config(['ucurec.main_admin.email' => 'main@example.com']);

        $this->runMainAdminMigration();

        $existing->refresh();
        $this->assertTrue($existing->isMainAdmin());
        $this->assertTrue($existing->is_active);
        $this->assertTrue(Hash::check('password', $existing->password));
    }

    public function test_migration_is_skipped_when_no_email_is_configured(): void
    {
        config(['ucurec.main_admin.email' => '']);

        $this->runMainAdminMigration();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_main_administrator_can_sign_in_and_must_change_the_default_password(): void
    {
        config(['ucurec.main_admin.email' => 'main@example.com', 'ucurec.main_admin.password' => 'StartPass2026']);
        $this->runMainAdminMigration();

        $this->post('/login', ['email' => 'main@example.com', 'password' => 'StartPass2026'])->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertRedirect('/account/password');
    }

    public function test_other_administrators_cannot_touch_the_main_administrator(): void
    {
        $main = $this->mainAdmin();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get("/users/{$main->id}/edit")->assertForbidden();
        $this->actingAs($admin)->post("/users/{$main->id}/deactivate")->assertForbidden();
        $this->actingAs($admin)->post("/users/{$main->id}/reset-password")->assertForbidden();

        $this->assertTrue($main->fresh()->is_active);
    }

    public function test_only_the_main_administrator_manages_administrator_accounts(): void
    {
        $main = $this->mainAdmin();
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();

        // A regular administrator cannot manage another administrator...
        $this->actingAs($admin)->post("/users/{$otherAdmin->id}/deactivate")->assertForbidden();
        $this->actingAs($admin)->post("/users/{$otherAdmin->id}/reset-password")->assertForbidden();

        // ...or create a new administrator.
        $this->actingAs($admin)->post('/users', [
            'name' => 'Another Admin',
            'email' => 'another.admin@example.com',
            'role' => Role::Admin->value,
        ])->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'another.admin@example.com']);

        // The main administrator can do both.
        $this->actingAs($main)->post("/users/{$otherAdmin->id}/deactivate")->assertRedirect();
        $this->assertFalse($otherAdmin->fresh()->is_active);

        $this->actingAs($main)->post('/users', [
            'name' => 'Another Admin',
            'email' => 'another.admin@example.com',
            'role' => Role::Admin->value,
        ])->assertRedirect('/users');
        $this->assertSame(Role::Admin, User::where('email', 'another.admin@example.com')->sole()->role);
    }

    public function test_main_administrator_cannot_deactivate_or_demote_themselves(): void
    {
        $main = $this->mainAdmin();

        $this->actingAs($main)->post("/users/{$main->id}/deactivate")->assertForbidden();
        $this->actingAs($main)->put("/users/{$main->id}", [
            'name' => $main->name,
            'email' => $main->email,
            'role' => Role::Reviewer->value,
        ])->assertSessionHasErrors('role');

        $this->assertTrue($main->fresh()->isMainAdmin());
    }

    public function test_main_administrator_flag_cannot_be_mass_assigned(): void
    {
        $admin = $this->mainAdmin();

        $this->actingAs($admin)->post('/users', [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'role' => Role::Reviewer->value,
            'is_main_admin' => 1,
        ]);

        $this->assertFalse(User::where('email', 'sneaky@example.com')->sole()->is_main_admin);
    }
}
