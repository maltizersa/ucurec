<?php

namespace App\Http\Requests\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public const MAX_ATTEMPTS = 5;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Check the credentials and sign the user in.
     *
     * Wrong credentials count toward the throttle. Correct credentials for an
     * inactive account are refused with a message that says why.
     *
     * @throws ValidationException
     */
    public function authenticate(): User
    {
        $this->ensureIsNotRateLimited();

        $credentials = $this->only('email', 'password');

        if (! Auth::validate($credentials)) {
            RateLimiter::hit($this->throttleKey());

            AuditLog::record('auth.failed', description: 'Failed sign-in attempt.', properties: [
                'email' => Str::lower($this->string('email')),
            ]);

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        /** @var User $user */
        $user = Auth::getLastAttempted();

        if (! $user->is_active) {
            AuditLog::record('auth.blocked_inactive', $user, 'Sign-in refused: account is inactive.', user: $user);

            throw ValidationException::withMessages([
                'email' => $user->isPendingActivation()
                    ? 'Your account is waiting for activation by the REC Secretariat.'
                    : 'Your account has been deactivated. Please contact the REC Secretariat.',
            ]);
        }

        Auth::login($user);
        RateLimiter::clear($this->throttleKey());

        return $user;
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        AuditLog::record('auth.throttled', description: 'Too many sign-in attempts.', properties: [
            'email' => Str::lower($this->string('email')),
        ]);

        throw ValidationException::withMessages([
            'email' => "Too many sign-in attempts. Please try again in {$seconds} seconds.",
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
