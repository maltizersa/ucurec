@extends('layouts.app')

@section('title', $isAdmin ? 'User accounts' : 'Researcher accounts')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <form method="GET" action="{{ route('users.index') }}" class="d-flex flex-wrap gap-2">
            <input type="search" name="q" value="{{ $filters['q'] }}" class="form-control form-control-sm" placeholder="Search name or email" style="width: 220px;">
            @if ($isAdmin)
                <select name="role" class="form-select form-select-sm" style="width: 180px;">
                    <option value="">All roles</option>
                    @foreach (App\Enums\Role::cases() as $role)
                        <option value="{{ $role->value }}" @selected($filters['role'] === $role->value)>{{ $role->label() }}</option>
                    @endforeach
                </select>
            @endif
            <select name="status" class="form-select form-select-sm" style="width: 190px;">
                <option value="">All statuses</option>
                <option value="pending" @selected($filters['status'] === 'pending')>Waiting for activation</option>
                <option value="active" @selected($filters['status'] === 'active')>Active</option>
                <option value="deactivated" @selected($filters['status'] === 'deactivated')>Deactivated</option>
            </select>
            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-funnel"></i> Filter</button>
        </form>

        @can('create', App\Models\User::class)
            <a href="{{ route('users.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-person-plus"></i> New account</a>
        @endcan
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        @if ($isAdmin)<th>Role</th>@endif
                        <th>College / program</th>
                        <th>Status</th>
                        <th>Last sign-in</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $listedUser)
                        <tr>
                            <td>{{ $listedUser->name }}</td>
                            <td>{{ $listedUser->email }}</td>
                            @if ($isAdmin)
                                <td>
                                    {{ $listedUser->role->label() }}
                                    @if ($listedUser->isMainAdmin())
                                        <span class="badge badge-main-admin d-block mt-1"><i class="bi bi-star-fill"></i> Main administrator</span>
                                    @endif
                                </td>
                            @endif
                            <td class="small">{{ $listedUser->college_program ?? '—' }}</td>
                            <td>
                                @if ($listedUser->is_active)
                                    <span class="badge text-bg-success">Active</span>
                                @elseif ($listedUser->isPendingActivation())
                                    <span class="badge text-bg-warning">Waiting for activation</span>
                                @else
                                    <span class="badge text-bg-secondary">Deactivated</span>
                                @endif
                            </td>
                            <td class="small text-body-secondary">{{ $listedUser->last_login_at?->format('M j, Y') ?? 'Never' }}</td>
                            <td class="text-end text-nowrap">
                                @can('activate', $listedUser)
                                    <form method="POST" action="{{ route('users.activate', $listedUser) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-success"><i class="bi bi-check-circle"></i> Activate</button>
                                    </form>
                                @endcan
                                @can('deactivate', $listedUser)
                                    <form method="POST" action="{{ route('users.deactivate', $listedUser) }}" class="d-inline"
                                          onsubmit="return confirm(@js('Deactivate '.$listedUser->name.'? They will be signed out and cannot sign in until reactivated.'))">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary" title="Deactivate" aria-label="Deactivate"><i class="bi bi-slash-circle"></i></button>
                                    </form>
                                @endcan
                                @can('resetPassword', $listedUser)
                                    <form method="POST" action="{{ route('users.reset-password', $listedUser) }}" class="d-inline"
                                          onsubmit="return confirm(@js('Reset the password of '.$listedUser->name.'? A temporary password will be emailed to '.$listedUser->email.'.'))">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-warning" title="Reset password" aria-label="Reset password"><i class="bi bi-key"></i></button>
                                    </form>
                                @endcan
                                @can('update', $listedUser)
                                    <a href="{{ route('users.edit', $listedUser) }}" class="btn btn-sm btn-outline-primary" title="Edit" aria-label="Edit"><i class="bi bi-pencil"></i></a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isAdmin ? 7 : 6 }}" class="text-center text-body-secondary py-4">No accounts match these filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $users->links() }}</div>
@endsection
