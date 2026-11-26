<?php

namespace App\Console\Commands;

use App\Contracts\MailTransport;
use Illuminate\Console\Command;
use Throwable;

/**
 * Checks the SMTP settings by sending a short test email.
 */
class MailTestCommand extends Command
{
    protected $signature = 'ucurec:mail-test {email : Where to send the test email}';

    protected $description = 'Send a test email to check the SMTP (PHPMailer) settings';

    public function handle(MailTransport $transport): int
    {
        if (! $transport->isConfigured()) {
            $this->components->error('Email is not set up. In .env set MAIL_MAILER=smtp, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_ENCRYPTION and MAIL_FROM_ADDRESS, then run: php artisan config:clear');

            return self::FAILURE;
        }

        $email = (string) $this->argument('email');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->components->error("Not a valid email address: {$email}");

            return self::FAILURE;
        }

        try {
            $transport->send(
                $email,
                $email,
                'UCUREC Ethics Review: test email',
                '<p>This is a test email from the UCUREC Ethics Review system. Email is working.</p>',
                'This is a test email from the UCUREC Ethics Review system. Email is working.',
            );
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Test email sent to {$email}. Check the inbox (and the spam folder).");

        return self::SUCCESS;
    }
}
