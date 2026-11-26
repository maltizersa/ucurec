@extends('layouts.app')

@section('title', 'Audit log')

@section('content')
    <form method="GET" action="{{ route('audit-logs.index') }}" class="d-flex gap-2 mb-3">
        <select name="action" class="form-select form-select-sm" style="width: 200px;">
            <option value="">All actions</option>
            @foreach ($actionGroups as $prefix => $label)
                <option value="{{ $prefix }}." @selected($action === $prefix.'.')>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-funnel"></i> Filter</button>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>When</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>IP address</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="small text-nowrap">{{ $log->created_at->format('M j, Y g:i:s A') }}</td>
                            <td class="small">
                                {{ $log->user?->name ?? '—' }}
                                @if ($log->user)<div class="text-body-secondary">{{ $log->user->role->label() }}</div>@endif
                            </td>
                            <td><code>{{ $log->action }}</code></td>
                            <td class="small">
                                {{ $log->description }}
                                @if ($log->subject_type)
                                    <div class="text-body-secondary">{{ class_basename($log->subject_type) }} #{{ $log->subject_id }}</div>
                                @endif
                                @if ($log->properties)
                                    <div class="text-body-secondary">
                                        @foreach ($log->properties as $key => $value)
                                            {{ $key }}: {{ is_array($value) ? implode(', ', $value) : $value }}@if (! $loop->last); @endif
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="small text-body-secondary">{{ $log->ip_address }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-4">No entries yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $logs->links() }}</div>
@endsection
