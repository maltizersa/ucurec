<?php

namespace Tests\Feature\Security;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoApplicationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesApplications;
use Tests\TestCase;

/**
 * The proposal's "minimum safeguards to implement and test" that are not
 * covered elsewhere.
 */
class SafeguardsTest extends TestCase
{
    use CreatesApplications, RefreshDatabase;

    public function test_security_headers_are_sent(): void
    {
        $this->get('/login')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'same-origin');
    }

    public function test_production_adds_a_content_security_policy(): void
    {
        $this->app['env'] = 'production';

        $policy = $this->get('/login')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
    }

    public function test_passwords_are_hashed_by_the_framework(): void
    {
        $this->post('/register', [
            'name' => 'Hash Check',
            'email' => 'hash@example.com',
            'college_program' => config('ucurec.colleges')[0],
            'password' => 'PlainText123',
            'password_confirmation' => 'PlainText123',
        ]);

        $stored = User::where('email', 'hash@example.com')->value('password');

        $this->assertNotSame('PlainText123', $stored);
        $this->assertTrue(Hash::check('PlainText123', $stored));
        $this->assertStringStartsWith('$2y$', $stored); // bcrypt
    }

    public function test_sessions_expire_and_are_protected(): void
    {
        $this->assertLessThanOrEqual(30, config('session.lifetime'));
        $this->assertTrue(config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
    }

    public function test_key_actions_are_logged_with_user_and_time_and_no_secrets(): void
    {
        Storage::fake('documents');
        $this->awaitingDecisionApplication();

        $required = [
            'application.submitted',
            'document.uploaded',
            'screening.recorded',
            'assignment.proposed',
            'assignment.authorized',
            'assignment.accepted',
            'review.submitted',
            'application.status_changed',
        ];
        foreach ($required as $action) {
            $log = AuditLog::where('action', $action)->first();
            $this->assertNotNull($log, "Missing audit entry: {$action}");
            $this->assertNotNull($log->user_id, "{$action} has no user");
            $this->assertNotNull($log->created_at, "{$action} has no time");
        }

        $everything = AuditLog::all()->toJson();
        $this->assertStringNotContainsString('password', strtolower(json_encode(AuditLog::pluck('properties'))));
        $this->assertStringNotContainsString('%PDF', $everything, 'No document contents in the log.');
    }

    public function test_demo_seeders_refuse_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        // Called directly: `db:seed` itself also asks for confirmation in production.
        app()->call([new DatabaseSeeder, 'run']);
        app()->call([new DemoApplicationSeeder, 'run']);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('applications', 0);
    }
}
