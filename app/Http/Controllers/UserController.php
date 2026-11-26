<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Controllers\Concerns\DeliversTemporaryPasswords;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Mail\AccountMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    use DeliversTemporaryPasswords;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $actor = $request->user();
        $status = $request->query('status');
        $role = $actor->isAdmin() ? Role::tryFrom((string) $request->query('role')) : Role::Researcher;
        $search = trim((string) $request->query('q'));

        $users = User::query()
            ->when($role, fn ($query) => $query->role($role))
            ->when($status === 'pending', fn ($query) => $query->pendingActivation())
            ->when($status === 'active', fn ($query) => $query->active())
            ->when($status === 'deactivated', fn ($query) => $query->where('is_active', false)->whereNotNull('activated_at'))
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderByRaw('CASE WHEN is_active = 0 AND activated_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'filters' => ['status' => $status, 'role' => $role?->value, 'q' => $search],
            'isAdmin' => $actor->isAdmin(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('users.create', [
            'user' => new User(['role' => Role::Reviewer]),
            'roles' => $this->roleOptions(),
            'colleges' => config('ucurec.colleges'),
        ]);
    }

    /**
     * Accounts created by the IT administrator are active immediately. A
     * one-time temporary password is emailed to the user, who must change it
     * at the first sign-in.
     */
    public function store(Request $request, AccountMailer $mailer): RedirectResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validate($this->rules($request->user()));
        $temporaryPassword = Str::password(12, symbols: false);

        $user = new User([...$validated, 'password' => $temporaryPassword]);
        $user->must_change_password = true;
        $user->save();
        $user->activate($request->user());

        AuditLog::record('user.created', $user, "Created {$user->role->label()} account.", ['role' => $user->role->value]);

        return $this->deliverTemporaryPassword(
            redirect()->route('users.index'),
            $mailer,
            $user,
            $temporaryPassword,
            isReset: false,
            message: "Account created for {$user->name}.",
        );
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('users.edit', [
            'user' => $user,
            'roles' => $this->roleOptions(),
            'colleges' => config('ucurec.colleges'),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validate($this->rules($request->user(), $user));

        if ($request->user()->is($user) && $validated['role'] !== $user->role->value) {
            return back()->withInput()->withErrors(['role' => 'You cannot change your own role.']);
        }

        $user->fill($validated);
        $changed = array_keys($user->getDirty());
        $user->save();

        if ($changed) {
            AuditLog::record('user.updated', $user, 'Updated account details.', [
                'fields' => $changed,
                'role' => $user->role->value,
            ]);
        }

        return redirect()->route('users.index')->with('success', "Account updated for {$user->name}.");
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(User $actor, ?User $user = null): array
    {
        $role = Rule::enum(Role::class);

        if ($actor->cannot('assignAdminRole', User::class)) {
            $role->except([Role::Admin]);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'role' => ['required', $role],
            'college_program' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50', 'regex:/^[0-9+\-\s()]+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function roleOptions(): array
    {
        $options = Role::options();

        if (request()->user()->cannot('assignAdminRole', User::class)) {
            unset($options[Role::Admin->value]);
        }

        return $options;
    }
}
