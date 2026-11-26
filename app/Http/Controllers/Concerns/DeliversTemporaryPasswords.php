<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use App\Services\Mail\AccountMailer;
use Illuminate\Http\RedirectResponse;

/**
 * Emails a temporary password to the user. Only if email is not set up or
 * fails is the password shown once to the administrator instead.
 */
trait DeliversTemporaryPasswords
{
    private function deliverTemporaryPassword(
        RedirectResponse $redirect,
        AccountMailer $mailer,
        User $user,
        string $temporaryPassword,
        bool $isReset,
        string $message,
    ): RedirectResponse {
        if ($mailer->sendTemporaryPassword($user, $temporaryPassword, $isReset)) {
            return $redirect->with('success', "{$message} The temporary password was emailed to {$user->email}.");
        }

        return $redirect
            ->with('success', $message)
            ->with('warning', $mailer->isAvailable()
                ? "The email to {$user->email} could not be sent. Give the temporary password below to the user privately, and check the email settings."
                : 'Email is not set up yet, so the temporary password is shown below. Give it to the user privately.')
            ->with('temporary_password', ['email' => $user->email, 'password' => $temporaryPassword]);
    }
}
