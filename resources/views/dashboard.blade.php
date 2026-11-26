@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <h2 class="h4 fw-bold page-title mb-1">Welcome, {{ $user->name }}</h2>
    <p class="text-body-secondary">Signed in as {{ $user->role->label() }}.</p>

    @if ($tiles)
        <div class="row g-3 mb-4">
            @foreach ($tiles as $tile)
                <div class="col-sm-6 col-xl-3">
                    <a href="{{ $tile['url'] }}" class="card stat-card border-0 shadow-sm text-decoration-none h-100">
                        <div class="card-body">
                            <div class="text-body-secondary small">{{ $tile['label'] }}</div>
                            <div class="display-6 fw-bold stat-value @if ($tile['danger'] && $tile['value'] > 0) text-danger @endif">{{ $tile['value'] }}</div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    @if ($charts)
        <h3 class="h6 fw-bold text-body-secondary text-uppercase small mb-2">By college / program</h3>
        <div class="row g-3 mb-4">
            @foreach ($charts as $chart)
                <div class="col-lg-6 col-xxl-4">
                    @include('partials.pie-chart', ['chart' => $chart])
                </div>
            @endforeach
        </div>
    @endif

    @if ($backup)
        @php($latest = $backup['latest'])
        @php($stale = ! $latest || $latest['created_at']->lt(now()->subHours(36)))
        <div class="card border-0 shadow-sm mb-4 {{ $stale ? 'border-start border-danger border-4' : 'stat-card' }}">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <div class="text-body-secondary small">Last backup of the database and documents</div>
                    @if ($latest)
                        <div class="fw-semibold {{ $stale ? 'text-danger' : '' }}">
                            {{ $latest['created_at']->format('F j, Y g:i A') }} ({{ $latest['created_at']->diffForHumans() }})
                            · {{ App\Support\FileSize::format($latest['size']) }}
                        </div>
                    @else
                        <div class="fw-semibold text-danger">No backup yet</div>
                    @endif
                    @if ($stale)
                        <div class="small text-danger">Check the scheduled task, or run <code>php artisan ucurec:backup</code> on the server.</div>
                    @endif
                </div>
                <i class="bi bi-hdd-stack fs-2 {{ $stale ? 'text-danger' : 'text-success' }}"></i>
            </div>
        </div>
    @endif

    <div class="card card-accent border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h3 class="h6 fw-bold mb-0">Pending work</h3>
                @can('create', App\Models\Application::class)
                    <a href="{{ route('applications.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> New application</a>
                @endcan
            </div>

            @if ($actionItems->isNotEmpty())
                <div class="list-group list-group-flush">
                    @foreach ($actionItems as $item)
                        <a href="{{ $item['url'] }}" class="list-group-item list-group-item-action px-0">
                            <div class="d-flex justify-content-between gap-2">
                                <span class="fw-semibold">{{ $item['reference'] }}</span>
                                <span class="badge {{ $item['badgeClass'] }}">{{ $item['badge'] }}</span>
                            </div>
                            <div class="d-flex justify-content-between gap-2 small text-body-secondary">
                                <span>{{ Str::limit($item['title'], 120) }}</span>
                                @if (! empty($item['since']))
                                    <span class="text-nowrap" title="{{ $item['since']->format('M j, Y g:i A') }}">waiting {{ $item['since']->diffForHumans(['parts' => 1, 'syntax' => Carbon\CarbonInterface::DIFF_ABSOLUTE]) }}</span>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <p class="text-body-secondary mb-0">
                    @switch($user->role)
                        @case(App\Enums\Role::Researcher)
                            Nothing needs your action right now.
                            @break
                        @case(App\Enums\Role::Secretariat)
                            Nothing to screen, forward, or follow up right now.
                            @break
                        @case(App\Enums\Role::Reviewer)
                            No invitations or reviews in progress.
                            @break
                        @case(App\Enums\Role::Chair)
                            No reviewer proposals are waiting for your authorization.
                            @break
                        @default
                            Use the menu to manage accounts and review the audit log.
                    @endswitch
                </p>
            @endif
        </div>
    </div>
@endsection
