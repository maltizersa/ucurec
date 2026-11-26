<?php

namespace App\Services\Mail;

use App\Contracts\MailTransport;
use App\Models\AuditLog;
use App\Models\User;
use Throwable;

/**
 * Emails a temporary password to a new or reset account. The password is
 * never written to the log or the audit log, and the user must replace it
 * at the first sign-in.
 */
class AccountMailer
{
    public function __construct(private MailTransport $transport) {}

    public function isAvailable(): bool
    {
        return $this->transport->isConfigured();
    }

    /**
     * @return bool true if the email was sent
     */
    public function sendTemporaryPassword(User $user, string $temporaryPassword, bool $isReset): bool
    {
        if (! $this->transport->isConfigured()) {
            return false;
        }

        $data = [
            'user' => $user,
            'password' => $temporaryPassword,
            'isReset' => $isReset,
            'signInUrl' => route('login'),
        ];

        $subject = $isReset
            ? 'Your UCUREC Ethics Review password was reset'
            : 'Your UCUREC Ethics Review account';

        try {
            $this->transport->send(
                $user->email,
                $user->name,
                $subject,
                view('emails.temporary-password', $data)->render(),
                view('emails.temporary-password-text', $data)->render(),
            );
        } catch (Throwable $e) {
            AuditLog::record('user.credentials_email_failed', $user, 'Could not email the temporary password.', [
                'error' => mb_substr($e->getMessage(), 0, 250),
            ]);

            return false;
        }

        AuditLog::record('user.credentials_emailed', $user, 'Emailed the temporary password to the account email.', [
            'reset' => $isReset,
        ]);

        return true;
    }
}
