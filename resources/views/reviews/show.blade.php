@extends('layouts.app')

@use('App\Enums\AssignmentStatus')

@section('title', 'Review · '.$application->reference_no)

@section('content')
    <div class="mb-3">
        <a href="{{ route('reviews.index') }}" class="small"><i class="bi bi-arrow-left"></i> My reviews</a>
        <div class="d-flex align-items-center gap-2 mt-1">
            <span class="fw-semibold text-body-secondary">{{ $application->reference_no }}</span>
            <span class="badge {{ $assignment->status->badgeClass() }}">{{ $assignment->status->label() }}</span>
            @if ($assignment->isOverdue())<span class="badge text-bg-danger">Overdue</span>@endif
        </div>
        <h2 class="h4 fw-bold page-title mb-0">{{ $application->title }}</h2>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            {{-- 1. Invitation: conflict of interest declaration and response --}}
            @if ($assignment->status === AssignmentStatus::Authorized)
                <div class="card card-accent border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <h3 class="h6 fw-bold"><i class="bi bi-envelope-open"></i> Review invitation</h3>
                        <p class="small">
                            You have been asked to review <strong>version {{ $assignment->documentSet->version }}</strong> of this application,
                            due <strong>{{ $assignment->due_at->format('F j, Y') }}</strong>.
                            Declare any conflict of interest before you can open the confidential files.
                        </p>

                        <form method="POST" action="{{ route('reviews.respond', $assignment) }}" novalidate>
                            @csrf
                            <fieldset class="mb-3">
                                <legend class="form-label fw-semibold small">Conflict of interest declaration</legend>
                                <div class="form-check">
                                    <input class="form-check-input @error('has_conflict') is-invalid @enderror" type="radio" name="has_conflict" id="coi-no" value="no" @checked(old('has_conflict') === 'no')>
                                    <label class="form-check-label small" for="coi-no">
                                        I have <strong>no</strong> conflict of interest with this research, its investigators, or its sponsor.
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input @error('has_conflict') is-invalid @enderror" type="radio" name="has_conflict" id="coi-yes" value="yes" @checked(old('has_conflict') === 'yes')>
                                    <label class="form-check-label small" for="coi-yes">I <strong>have</strong> a conflict of interest.</label>
                                    @error('has_conflict')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <textarea name="conflict_details" rows="2" class="form-control form-control-sm mt-2 @error('conflict_details') is-invalid @enderror"
                                          placeholder="If you have a conflict, describe it briefly (e.g. I am the adviser).">{{ old('conflict_details') }}</textarea>
                                @error('conflict_details')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </fieldset>

                            <div class="mb-3">
                                <label for="decline_reason" class="form-label small">Reason, if declining <span class="text-body-secondary">(optional)</span></label>
                                <input id="decline_reason" type="text" name="decline_reason" value="{{ old('decline_reason') }}" class="form-control form-control-sm">
                            </div>

                            <div class="d-flex flex-wrap gap-2">
                                <button name="decision" value="accept" class="btn btn-primary"><i class="bi bi-check-lg"></i> Accept assignment</button>
                                <button name="decision" value="decline" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i> Decline</button>
                            </div>
                            <p class="small text-body-secondary mt-2 mb-0">If you declare a conflict, the assignment is closed and you will not see the files, even if you click Accept.</p>
                        </form>
                    </div>
                </div>
            @endif

            {{-- Closed without access --}}
            @if (in_array($assignment->status, [AssignmentStatus::Conflicted, AssignmentStatus::Declined, AssignmentStatus::Cancelled], true))
                <div class="alert alert-secondary">
                    @switch($assignment->status)
                        @case(AssignmentStatus::Conflicted)
                            You declared a conflict of interest. You do not have access to this application, and the Secretariat will reassign it.
                            @break
                        @case(AssignmentStatus::Declined)
                            You declined this assignment.
                            @break
                        @default
                            The Secretariat cancelled this assignment. You no longer have access to its files.
                    @endswitch
                </div>
            @endif

            {{-- 2. Assigned documents (only after accepting without conflict) --}}
            @if ($canSeeFiles)
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <h3 class="h6 fw-bold"><i class="bi bi-folder2-open"></i> Assigned documents · version {{ $assignment->documentSet->version }}</h3>
                        <p class="small text-body-secondary">Confidential. Use these files only for this review.</p>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light"><tr><th>Category</th><th>File</th><th>Size</th></tr></thead>
                                <tbody>
                                    @foreach ($assignment->documentSet->documents as $document)
                                        <tr>
                                            <td class="small">{{ $document->categoryLabel() }}</td>
                                            <td class="small"><a href="{{ route('documents.download', $document) }}"><i class="bi bi-file-earmark-arrow-down"></i> {{ $document->original_name }}</a></td>
                                            <td class="small">{{ $document->humanSize() }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- 3. The review --}}
                <div class="card card-accent border-0 shadow-sm" id="review">
                    <div class="card-body">
                        <h3 class="h6 fw-bold"><i class="bi bi-clipboard2-check"></i> Your review</h3>

                        @if ($assignment->files->isNotEmpty())
                            <ul class="list-group list-group-flush mb-3">
                                @foreach ($assignment->files as $file)
                                    <li class="list-group-item px-0 d-flex justify-content-between align-items-center small">
                                        <span>
                                            <span class="fw-semibold">{{ $file->formLabel() }}:</span>
                                            <a href="{{ route('review-files.download', $file) }}">{{ $file->original_name }}</a>
                                            <span class="text-body-secondary">({{ $file->humanSize() }})</span>
                                        </span>
                                        @can('delete', $file)
                                            <form method="POST" action="{{ route('reviews.files.destroy', [$assignment, $file]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger" title="Remove" aria-label="Remove"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endcan
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @can('review', $assignment)
                            <form method="POST" action="{{ route('reviews.files.store', $assignment) }}" enctype="multipart/form-data" class="row g-2 align-items-end mb-3" novalidate>
                                @csrf
                                <div class="col-md-4">
                                    <label for="form_type" class="form-label small mb-1">Form</label>
                                    <select id="form_type" name="form_type" class="form-select form-select-sm">
                                        @foreach ($formTypes as $value => $label)
                                            <option value="{{ $value }}" @selected(old('form_type') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="file" class="form-label small mb-1">Completed form (Word or PDF)</label>
                                    <input id="file" type="file" name="file" accept="{{ collect($uploadRules['extensions'])->map(fn ($e) => '.'.$e)->implode(',') }}"
                                           class="form-control form-control-sm @error('file') is-invalid @enderror" required>
                                    @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-2 d-grid">
                                    <button class="btn btn-sm btn-outline-primary"><i class="bi bi-upload"></i> Upload</button>
                                </div>
                            </form>

                            <form method="POST" action="{{ route('reviews.submit', $assignment) }}" class="border-top pt-3" novalidate>
                                @csrf
                                @error('submit')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror
                                <div class="mb-3">
                                    <label for="recommendation" class="form-label small">Recommendation</label>
                                    <select id="recommendation" name="recommendation" class="form-select @error('recommendation') is-invalid @enderror" required>
                                        <option value="">Select…</option>
                                        @foreach ($recommendations as $value => $label)
                                            <option value="{{ $value }}" @selected(old('recommendation') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('recommendation')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3">
                                    <label for="review_comments" class="form-label small">Brief comments for the Committee <span class="text-body-secondary">(optional)</span></label>
                                    <textarea id="review_comments" name="review_comments" rows="3" class="form-control @error('review_comments') is-invalid @enderror">{{ old('review_comments') }}</textarea>
                                    @error('review_comments')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-check mb-3">
                                    <input class="form-check-input @error('confirm') is-invalid @enderror" type="checkbox" name="confirm" value="1" id="confirm">
                                    <label class="form-check-label small" for="confirm">My review is final. I understand it cannot be changed after submitting.</label>
                                    @error('confirm')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <button class="btn btn-primary"><i class="bi bi-send"></i> Submit review</button>
                                <p class="small text-body-secondary mt-2 mb-0">Your recommendation goes to the Committee. It does not become the REC decision by itself.</p>
                            </form>
                        @else
                            <dl class="small mb-0">
                                <dt>Recommendation</dt><dd>{{ $assignment->recommendationLabel() }}</dd>
                                @if ($assignment->review_comments)<dt>Comments</dt><dd>{{ $assignment->review_comments }}</dd>@endif
                                <dt>Submitted</dt><dd>{{ $assignment->submitted_at?->format('M j, Y g:i A') }}</dd>
                            </dl>
                        @endcan
                    </div>
                </div>
            @endif
        </div>

        <div class="col-xl-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h3 class="h6 fw-bold"><i class="bi bi-card-text"></i> Application</h3>
                    <dl class="small mb-0">
                        <dt>Principal investigator</dt><dd>{{ $application->principal_investigator }}</dd>
                        <dt>Adviser</dt><dd>{{ $application->adviser ?: '—' }}</dd>
                        <dt>College / program</dt><dd>{{ $application->college_program }}</dd>
                        <dt>Research type</dt><dd>{{ $application->research_type }}</dd>
                        @if ($application->review_route)
                            <dt>Review route</dt><dd>{{ config('ucurec.review_routes.'.$application->review_route) }}</dd>
                        @endif
                        <dt>Version to review</dt><dd>Version {{ $assignment->documentSet->version }}</dd>
                        <dt>Due</dt><dd>{{ $assignment->due_at->format('F j, Y') }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
@endsection
