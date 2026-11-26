<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\DeliversTemporaryPasswords;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Mail\AccountMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Activate, deactivate, and reset the password of an account.
 */
class UserAccountActionController extends Controller
{
    use DeliversTemporaryPasswords;

    public function activate(Request $request, User $user): RedirectResponse
    {
        $this->authorize('activate', $user);

        $user->activate($request->user());
        AuditLog::record('user.activated', $user, 'Activated account.');

        return back()->with('success', "{$user->name}'s account is now active.");
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        $this->authorize('deactivate', $user);

        $user->deactivate($request->user());
        AuditLog::record('user.deactivated', $user, 'Deactivated account.');

        return back()->with('success', "{$user->name}'s account has been deactivated.");
    }

    public function resetPassword(User $user, AccountMailer $mailer): RedirectResponse
    {
        $this->authorize('resetPassword', $user);

        $temporaryPassword = Str::password(12, symbols: false);

        $user->forceFill([
            'password' => $temporaryPassword,
            'must_change_password' => true,
        ])->save();

        AuditLog::record('user.password_reset', $user, 'Reset password; user must set a new one at next sign-in.');

        return $this->deliverTemporaryPassword(
            back(),
            $mailer,
            $user,
            $temporaryPassword,
            isReset: true,
            message: "Password reset for {$user->name}.",
        );
    }
}
