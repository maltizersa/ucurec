<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link rel="icon" type="image/webp" href="{{ asset('images/ucu-logo.webp') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
@php($authUser = auth()->user())
<div class="app-shell d-flex">
    <nav class="app-sidebar d-flex flex-column flex-shrink-0 p-3 text-white">
        <a href="{{ route('dashboard') }}" class="brand d-flex align-items-center pb-3 mb-3 text-decoration-none">
            <span class="sidebar-logo me-2">
                <img src="{{ asset('images/ucu-logo.webp') }}" alt="Urdaneta City University logo">
            </span>
            <span class="lh-sm">
                <span class="d-block fw-bold fs-5">UCUREC</span>
                <span class="brand-sub small">Ethics Review</span>
            </span>
        </a>

        <ul class="nav nav-pills flex-column gap-1 mb-auto">
            <li class="nav-item">
                <a href="{{ route('dashboard') }}" class="nav-link @if (request()->routeIs('dashboard')) active @endif">
                    <i class="bi bi-speedometer2 me-2"></i>Dashboard
                </a>
            </li>

            @can('viewAny', App\Models\Application::class)
                <li class="sidebar-heading mt-3 mb-1 px-2">Ethics review</li>
                <li class="nav-item">
                    <a href="{{ route('applications.index') }}" class="nav-link @if (request()->routeIs('applications.*')) active @endif">
                        <i class="bi bi-folder2-open me-2"></i>{{ $authUser->can('create', App\Models\Application::class) ? 'My applications' : 'Applications' }}
                    </a>
                </li>
            @endcan

            @if ($authUser->role === App\Enums\Role::Reviewer)
                <li class="sidebar-heading mt-3 mb-1 px-2">Ethics review</li>
                <li class="nav-item">
                    <a href="{{ route('reviews.index') }}" class="nav-link @if (request()->routeIs('reviews.*')) active @endif">
                        <i class="bi bi-clipboard2-check me-2"></i>My reviews
                    </a>
                </li>
            @endif

            @can('viewAny', App\Models\User::class)
                <li class="sidebar-heading mt-3 mb-1 px-2">Accounts</li>
                <li class="nav-item">
                    <a href="{{ route('users.index') }}" class="nav-link @if (request()->routeIs('users.*')) active @endif">
                        <i class="bi bi-people me-2"></i>{{ $authUser->isAdmin() ? 'User accounts' : 'Researcher accounts' }}
                    </a>
                </li>
            @endcan

            @if ($authUser->isAdmin())
                <li class="nav-item">
                    <a href="{{ route('audit-logs.index') }}" class="nav-link @if (request()->routeIs('audit-logs.*')) active @endif">
                        <i class="bi bi-journal-text me-2"></i>Audit log
                    </a>
                </li>
            @endif
        </ul>

        <div class="user-card rounded-3 p-2 mt-3 small">
            <div class="d-flex align-items-center">
                <i class="bi bi-person-circle fs-4 me-2"></i>
                <div class="text-truncate">
                    <div class="fw-semibold text-truncate">{{ $authUser->name }}</div>
                    <div class="role">{{ $authUser->role->label() }}</div>
                </div>
            </div>
        </div>
    </nav>

    <div class="app-main flex-grow-1 d-flex flex-column">
        <header class="app-topbar navbar px-4 py-2">
            <span class="page-title fw-bold fs-5">@yield('title')</span>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('account.password.edit') }}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-key"></i> Change password
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-box-arrow-right"></i> Sign out
                    </button>
                </form>
            </div>
        </header>

        <main class="p-4">
            <div class="print-header">
                <img src="{{ asset('images/ucu-logo.webp') }}" alt="">
                <div>
                    <div class="fw-bold">Urdaneta City University Research Ethics Committee</div>
                    <div class="small">Printed {{ now()->format('F j, Y g:i A') }} by {{ $authUser->name }}</div>
                </div>
            </div>
            @include('partials.flash')
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
