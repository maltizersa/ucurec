@extends('layouts.app')

@section('title', $isResearcher ? 'My applications' : 'Applications')

@section('content')
    <form method="GET" action="{{ route('applications.index') }}" class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="q" class="form-label small mb-1">Search</label>
                    <input id="q" type="search" name="q" value="{{ $filters['q'] }}" class="form-control form-control-sm"
                           placeholder="Reference no., title, PI, or adviser">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label small mb-1">Status</label>
                    <select id="status" name="status" class="form-select form-select-sm">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            @continue(! $isResearcher && $status === App\Enums\ApplicationStatus::Draft)
                            <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="sort" class="form-label small mb-1">Sort by</label>
                    <select id="sort" name="sort" class="form-select form-select-sm">
                        <option value="updated" @selected($filters['sort'] === 'updated')>Last activity</option>
                        <option value="submitted" @selected($filters['sort'] === 'submitted')>Submission date</option>
                        <option value="reference" @selected($filters['sort'] === 'reference')>Reference no.</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-search"></i> Search</button>
                    @if ($isFiltered)
                        <a href="{{ route('applications.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                    @endif
                </div>
            </div>

            @if ($isStaff)
                <div class="row g-2 align-items-end mt-1">
                    <div class="col-md-3">
                        <label for="college" class="form-label small mb-1">College / program</label>
                        <select id="college" name="college" class="form-select form-select-sm">
                            <option value="">All</option>
                            @foreach (config('ucurec.colleges') as $college)
                                <option value="{{ $college }}" @selected($filters['college'] === $college)>{{ $college }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="type" class="form-label small mb-1">Research type</label>
                        <select id="type" name="type" class="form-select form-select-sm">
                            <option value="">All</option>
                            @foreach (config('ucurec.research_types') as $type)
                                <option value="{{ $type }}" @selected($filters['type'] === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="route" class="form-label small mb-1">Review route</label>
                        <select id="route" name="route" class="form-select form-select-sm">
                            <option value="">All</option>
                            @foreach (config('ucurec.review_routes') as $value => $label)
                                <option value="{{ $value }}" @selected($filters['route'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="from" class="form-label small mb-1">Submitted from</label>
                        <input id="from" type="date" name="from" value="{{ $filters['from'] }}" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-2">
                        <label for="to" class="form-label small mb-1">to</label>
                        <input id="to" type="date" name="to" value="{{ $filters['to'] }}" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-1">
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" name="overdue" value="1" id="overdue" @checked($filters['overdue'])>
                            <label class="form-check-label small" for="overdue">Overdue review</label>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </form>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <div class="small text-body-secondary">
            {{ $applications->total() }} {{ Str::plural('application', $applications->total()) }}
            @if ($isFiltered)
                match these filters
            @endif
        </div>
        <div class="d-flex gap-2">
            @can('exportRegister', App\Models\Application::class)
                <a href="{{ route('applications.register', $exportQuery) }}" class="btn btn-sm btn-gold">
                    <i class="bi bi-filetype-csv"></i> Download CSV register
                </a>
            @endcan
            @can('create', App\Models\Application::class)
                <a href="{{ route('applications.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> New application</a>
            @endcan
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Reference no.</th>
                        <th>Title</th>
                        @unless ($isResearcher)<th>Principal investigator</th>@endunless
                        <th>Submitted</th>
                        <th>Status</th>
                        <th>In status since</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($applications as $application)
                        @php($since = $application->latestStatusChange?->created_at)
                        <tr>
                            <td class="text-nowrap">
                                <a href="{{ route('applications.show', $application) }}" class="fw-semibold">{{ $application->displayReference() }}</a>
                            </td>
                            <td>
                                <a href="{{ route('applications.show', $application) }}" class="text-body">{{ Str::limit($application->title, 90) }}</a>
                                @unless ($isResearcher)
                                    <div class="small text-body-secondary">{{ $application->college_program }} · {{ $application->research_type }}</div>
                                @endunless
                            </td>
                            @unless ($isResearcher)<td class="small">{{ $application->principal_investigator }}</td>@endunless
                            <td class="small text-nowrap">{{ $application->submitted_at?->format('M j, Y') ?? '—' }}</td>
                            <td>
                                <x-status-badge :status="$application->status" />
                                @if ($application->has_overdue_review ?? false)
                                    <span class="badge text-bg-danger">Overdue review</span>
                                @endif
                            </td>
                            <td class="small text-nowrap text-body-secondary">
                                @if ($since)
                                    {{ $since->format('M j, Y') }}
                                    <div>{{ $since->diffForHumans(['parts' => 1]) }}</div>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isResearcher ? 5 : 6 }}" class="text-center text-body-secondary py-4">
                                @if ($isResearcher && ! $isFiltered)
                                    You have no applications yet. Click <strong>New application</strong> to start one.
                                @else
                                    No applications match these filters.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $applications->links() }}</div>
@endsection
