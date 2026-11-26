<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Researcher self sign-up. New accounts stay inactive until the
 * REC Secretariat activates them.
 */
class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register', [
            'colleges' => config('ucurec.colleges'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'college_program' => ['required', 'string', Rule::in(config('ucurec.colleges'))],
            'contact_number' => ['nullable', 'string', 'max:50', 'regex:/^[0-9+\-\s()]+$/'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            ...$validated,
            'role' => Role::Researcher,
        ]);

        AuditLog::record('auth.registered', $user, 'Researcher signed up; waiting for activation.', user: $user);

        return redirect()->route('register.pending');
    }

    public function pending(): View
    {
        return view('auth.pending');
    }
}
