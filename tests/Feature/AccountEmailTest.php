<?php

namespace Tests\Feature;

use App\Contracts\MailTransport;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Mail\PhpMailerTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\FakeMailTransport;
use Tests\TestCase;

/**
 * Temporary passwords go to the user's email (PHPMailer over SMTP),
 * not to the administrator's screen.
 */
class AccountEmailTest extends TestCase
{
    use RefreshDatabase;

    private FakeMailTransport $mail;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mail = new FakeMailTransport;
        $this->app->instance(MailTransport::class, $this->mail);
        $this->admin = User::factory()->admin()->create();
    }

    private function createReviewer(): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->post('/users', [
            'name' => "Ana O'Brien",
            'email' => 'ana.reviewer@example.com',
            'role' => Role::Reviewer->value,
        ]);
    }

    private function emailedPassword(): string
    {
        $this->assertCount(1, $this->mail->sent);
        preg_match('/Temporary password: (\S+)/', $this->mail->sent[0]['text'], $match);

        return $match[1];
    }

    public function test_new_account_password_is_emailed_not_shown(): void
    {
        $response = $this->createReviewer();

        $response->assertRedirect('/users')
            ->assertSessionMissing('temporary_password')
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'emailed to ana.reviewer@example.com'));

        $message = $this->mail->sent[0];
        $this->assertSame('ana.reviewer@example.com', $message['to']);
        $this->assertSame('Your UCUREC Ethics Review account', $message['subject']);
        $this->assertStringContainsString('Reviewer', $message['html']);
        $this->assertStringContainsString(route('login'), $message['html']);
        $this->assertStringContainsString("Hello Ana O'Brien", $message['text'], 'Plain text is not HTML-escaped.');

        $user = User::where('email', 'ana.reviewer@example.com')->sole();
        $password = $this->emailedPassword();
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertStringContainsString($password, $message['html']);
        $this->assertTrue($user->must_change_password);

        $this->assertDatabaseHas('audit_logs', ['action' => 'user.credentials_emailed', 'subject_id' => $user->id]);
        $this->assertStringNotContainsString($password, AuditLog::all()->toJson(), 'The password is never logged.');
    }

    public function test_reset_password_is_emailed(): void
    {
        $researcher = User::factory()->create();

        $this->actingAs($this->admin)->post("/users/{$researcher->id}/reset-password")
            ->assertSessionMissing('temporary_password');

        $this->assertSame('Your UCUREC Ethics Review password was reset', $this->mail->sent[0]['subject']);
        $this->assertTrue(Hash::check($this->emailedPassword(), $researcher->fresh()->password));
        $this->assertTrue($researcher->fresh()->must_change_password);
    }

    public function test_emailed_password_works_and_must_be_changed(): void
    {
        $this->createReviewer();
        $password = $this->emailedPassword();
        auth()->logout();

        $this->post('/login', ['email' => 'ana.reviewer@example.com', 'password' => $password])->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertRedirect('/account/password');
    }

    public function test_when_sending_fails_the_password_is_shown_once_instead(): void
    {
        $this->mail->fail = true;

        $this->createReviewer()
            ->assertSessionHas('temporary_password')
            ->assertSessionHas('warning', fn ($m) => str_contains($m, 'could not be sent'));

        $user = User::where('email', 'ana.reviewer@example.com')->sole();
        $this->assertTrue(Hash::check(session('temporary_password')['password'], $user->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.credentials_email_failed', 'subject_id' => $user->id]);
    }

    public function test_when_email_is_not_set_up_the_password_is_shown_once(): void
    {
        $this->mail->configured = false;

        $this->createReviewer()
            ->assertSessionHas('temporary_password')
            ->assertSessionHas('warning', fn ($m) => str_contains($m, 'not set up'));

        $this->assertCount(0, $this->mail->sent);
    }

    public function test_phpmailer_transport_needs_smtp_settings(): void
    {
        $transport = new PhpMailerTransport;

        config(['ucurec.mail.enabled' => false]);
        $this->assertFalse($transport->isConfigured());

        config(['ucurec.mail.enabled' => true, 'ucurec.mail.host' => 'smtp.example.com', 'ucurec.mail.from_address' => 'rec@example.com']);
        $this->assertTrue($transport->isConfigured());
    }

    public function test_mail_test_command(): void
    {
        $this->artisan('ucurec:mail-test', ['email' => 'it@example.com'])
            ->expectsOutputToContain('Test email sent')
            ->assertSuccessful();
        $this->assertSame('it@example.com', $this->mail->sent[0]['to']);

        $this->mail->configured = false;
        $this->artisan('ucurec:mail-test', ['email' => 'it@example.com'])->assertFailed();
    }
}
