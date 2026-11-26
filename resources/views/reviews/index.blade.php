@extends('layouts.app')

@section('title', 'My reviews')

@section('content')
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Reference no.</th>
                        <th>Title</th>
                        <th>Version</th>
                        <th>Due</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($assignments as $assignment)
                        <tr>
                            <td class="text-nowrap">
                                <a href="{{ route('reviews.show', $assignment) }}" class="fw-semibold">{{ $assignment->application->reference_no }}</a>
                            </td>
                            <td><a href="{{ route('reviews.show', $assignment) }}" class="text-body">{{ Str::limit($assignment->application->title, 90) }}</a></td>
                            <td class="small">v{{ $assignment->documentSet->version }}</td>
                            <td class="small text-nowrap">
                                {{ $assignment->due_at->format('M j, Y') }}
                                @if ($assignment->isOverdue())<span class="badge text-bg-danger">Overdue</span>@endif
                            </td>
                            <td><span class="badge {{ $assignment->status->badgeClass() }}">{{ $assignment->status->label() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-4">No review assignments yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $assignments->links() }}</div>
@endsection
