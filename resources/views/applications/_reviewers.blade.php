{{--
    Internal panel (Secretariat and Chair only): reviewer assignments,
    conflict declarations, reports and recommendations.
--}}
@use('App\Enums\AssignmentStatus')

<div class="card card-accent border-0 shadow-sm mb-3" id="reviewers">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h3 class="h6 fw-bold mb-0"><i class="bi bi-people"></i> Reviewers <span class="badge text-bg-light border fw-normal">internal</span></h3>
            @if ($application->review_route)
                <span class="small text-body-secondary">Route: {{ config('ucurec.review_routes.'.$application->review_route) }}</span>
            @endif
        </div>

        @if ($application->reviewAssignments->isEmpty())
            <p class="text-body-secondary small">No reviewers proposed yet.</p>
        @else
            <div class="table-responsive mb-3">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Reviewer</th>
                            <th>Version</th>
                            <th>Due</th>
                            <th>Status</th>
                            <th>Review</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($application->reviewAssignments as $assignment)
                            <tr>
                                <td class="small">{{ $assignment->reviewer->name }}</td>
                                <td class="small">v{{ $assignment->documentSet->version }}</td>
                                <td class="small text-nowrap">
                                    {{ $assignment->due_at->format('M j, Y') }}
                                    @if ($assignment->isOverdue())
                                        <span class="badge text-bg-danger">Overdue</span>
                                    @endif
                                </td>
                                <td class="small">
                                    <span class="badge {{ $assignment->status->badgeClass() }}">{{ $assignment->status->label() }}</span>
                                    @if ($assignment->status === AssignmentStatus::Conflicted && $assignment->conflict_details)
                                        <div class="text-body-secondary mt-1">COI: {{ $assignment->conflict_details }}</div>
                                    @endif
                                    @if ($assignment->decline_reason)
                                        <div class="text-body-secondary mt-1">Reason: {{ $assignment->decline_reason }}</div>
                                    @endif
                                    @if ($assignment->authorization_note)
                                        <div class="text-body-secondary mt-1">Chair: {{ $assignment->authorization_note }}</div>
                                    @endif
                                    @if ($assignment->cancel_reason)
                                        <div class="text-body-secondary mt-1">Cancelled: {{ $assignment->cancel_reason }}</div>
                                    @endif
                                </td>
                                <td class="small">
                                    @if ($assignment->status === AssignmentStatus::Submitted)
                                        <div class="fw-semibold">{{ $assignment->recommendationLabel() }}</div>
                                        @if ($assignment->review_comments)
                                            <div class="text-body-secondary">{{ Str::limit($assignment->review_comments, 200) }}</div>
                                        @endif
                                        @foreach ($assignment->files as $file)
                                            <div><a href="{{ route('review-files.download', $file) }}"><i class="bi bi-file-earmark-arrow-down"></i> {{ $file->formLabel() }}</a></div>
                                        @endforeach
                                    @else
                                        <span class="text-body-secondary">—</span>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    @can('approve', $assignment)
                                        <form method="POST" action="{{ route('assignments.approve', $assignment) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> Authorize</button>
                                        </form>
                                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse"
                                                data-bs-target="#reject-{{ $assignment->id }}">Not authorize</button>
                                    @endcan
                                    @can('cancel', $assignment)
                                        <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="collapse"
                                                data-bs-target="#cancel-{{ $assignment->id }}" title="Cancel assignment" aria-label="Cancel assignment">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    @endcan
                                </td>
                            </tr>
                            @can('approve', $assignment)
                                <tr class="collapse" id="reject-{{ $assignment->id }}">
                                    <td colspan="6">
                                        <form method="POST" action="{{ route('assignments.reject', $assignment) }}" class="d-flex gap-2">
                                            @csrf
                                            <input type="text" name="note" class="form-control form-control-sm" placeholder="Reason (shown to the Secretariat)" required>
                                            <button class="btn btn-sm btn-outline-danger text-nowrap">Do not authorize</button>
                                        </form>
                                    </td>
                                </tr>
                            @endcan
                            @can('cancel', $assignment)
                                <tr class="collapse" id="cancel-{{ $assignment->id }}">
                                    <td colspan="6">
                                        <form method="POST" action="{{ route('assignments.cancel', $assignment) }}" class="d-flex gap-2">
                                            @csrf
                                            <input type="text" name="reason" class="form-control form-control-sm" placeholder="Reason for cancelling (e.g. overdue, reassigned)" required>
                                            <button class="btn btn-sm btn-danger text-nowrap">Cancel assignment</button>
                                        </form>
                                    </td>
                                </tr>
                            @endcan
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @error('note')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror
        @error('reason')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror

        @if ($canCoordinate)
            {{-- Propose a reviewer --}}
            <form method="POST" action="{{ route('applications.assignments.store', $application) }}" class="row g-2 align-items-end border-top pt-3" novalidate>
                @csrf
                <div class="col-md-6">
                    <label for="reviewer_id" class="form-label small mb-1">Propose a reviewer</label>
                    <select id="reviewer_id" name="reviewer_id" class="form-select form-select-sm @error('reviewer_id') is-invalid @enderror" required>
                        <option value="">Select a reviewer…</option>
                        @foreach ($reviewers as $reviewer)
                            <option value="{{ $reviewer->id }}" @selected((int) old('reviewer_id') === $reviewer->id)>
                                {{ $reviewer->name }}@if ($reviewer->college_program) ({{ $reviewer->college_program }})@endif
                            </option>
                        @endforeach
                    </select>
                    @error('reviewer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label for="due_at" class="form-label small mb-1">Review due</label>
                    <input id="due_at" type="date" name="due_at" value="{{ old('due_at', $defaultDueDate) }}" min="{{ today()->toDateString() }}"
                           class="form-control form-control-sm @error('due_at') is-invalid @enderror" required>
                    @error('due_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3 d-grid">
                    <button class="btn btn-sm btn-primary"><i class="bi bi-person-plus"></i> Propose</button>
                </div>
                <div class="col-12 form-text">The Chairperson authorizes each proposal before the reviewer is invited.</div>
            </form>

            {{-- Review route --}}
            <form method="POST" action="{{ route('applications.review-route', $application) }}" class="row g-2 align-items-end mt-2">
                @csrf
                <div class="col-md-6">
                    <label for="review_route" class="form-label small mb-1">Review route (as approved by UCUREC)</label>
                    <select id="review_route" name="review_route" class="form-select form-select-sm">
                        <option value="">Not decided yet</option>
                        @foreach (config('ucurec.review_routes') as $value => $label)
                            <option value="{{ $value }}" @selected($application->review_route === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-grid">
                    <button class="btn btn-sm btn-outline-primary">Save route</button>
                </div>
            </form>

            {{-- Forward for decision --}}
            <div class="border-top pt-3 mt-3">
                @error('forward')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror
                <form method="POST" action="{{ route('applications.forward', $application) }}"
                      onsubmit="return confirm('Close the review stage and forward this application for the REC decision?')">
                    @csrf
                    <button class="btn btn-gold"><i class="bi bi-arrow-right-circle"></i> Forward for REC decision</button>
                    <span class="small text-body-secondary ms-2">Needs all assignments closed and at least one submitted review.</span>
                </form>
            </div>
        @endif
    </div>
</div>
