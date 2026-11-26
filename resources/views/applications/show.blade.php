@extends('layouts.app')

@use('App\Models\Screening')

@section('title', $application->displayReference())

@section('content')
    {{-- Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="fw-semibold text-body-secondary">{{ $application->displayReference() }}</span>
                <x-status-badge :status="$application->status" />
            </div>
            <h2 class="h4 fw-bold page-title mb-0">{{ $application->title }}</h2>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                <i class="bi bi-printer"></i> Print record
            </button>
            @can('startScreening', $application)
                <form method="POST" action="{{ route('applications.screening.start', $application) }}">
                    @csrf
                    <button class="btn btn-primary"><i class="bi bi-clipboard-check"></i> Start screening</button>
                </form>
            @endcan
            @can('screen', $application)
                <a href="{{ route('applications.screening.create', $application) }}" class="btn btn-primary">
                    <i class="bi bi-clipboard-check"></i> Continue screening
                </a>
            @endcan
            @can('recordDecision', $application)
                <a href="{{ route('applications.decisions.create', $application) }}" class="btn btn-primary">
                    <i class="bi bi-journal-check"></i> Record REC decision
                </a>
            @endcan
            @if ($isInternal && $openDecision)
                <a href="{{ route('decisions.show', $openDecision) }}" class="btn btn-primary">
                    <i class="bi bi-journal-check"></i>
                    {{ auth()->user()->can('confirm', $openDecision) ? 'Review decision for release' : 'Open draft decision' }}
                </a>
            @endif
            @can('delete', $application)
                <form method="POST" action="{{ route('applications.destroy', $application) }}"
                      onsubmit="return confirm('Delete this draft and its uploaded files? This cannot be undone.')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-outline-danger"><i class="bi bi-trash"></i> Delete draft</button>
                </form>
            @endcan
        </div>
    </div>

    {{-- Missing requirements returned by the Secretariat --}}
    @if ($application->status === App\Enums\ApplicationStatus::Incomplete && $latestScreening && ! $latestScreening->isComplete())
        <div class="alert alert-warning border-warning">
            <h3 class="h6 fw-bold"><i class="bi bi-exclamation-triangle"></i> The REC Secretariat returned your application as incomplete</h3>
            <p class="mb-2">Please provide the following, then submit again:</p>
            <ul class="mb-2">
                @foreach ($latestScreening->missingItems() as $item)
                    <li><strong>{{ $item['label'] }}</strong>@if ($item['note']) — {{ $item['note'] }}@endif</li>
                @endforeach
            </ul>
            @if ($latestScreening->remarks)
                <p class="mb-0"><strong>Remarks:</strong> {{ $latestScreening->remarks }}</p>
            @endif
        </div>
    @endif

    {{-- Revisions requested: the released consolidated comments --}}
    @if ($application->status === App\Enums\ApplicationStatus::ForRevision && $releasedDecisions->isNotEmpty() && ! $isInternal)
        @php($revisionDecision = $releasedDecisions->first())
        <div class="alert alert-warning border-warning">
            <h3 class="h6 fw-bold"><i class="bi bi-pencil-square"></i> UCUREC requests revisions: {{ $revisionDecision->categoryLabel() }}</h3>
            <p class="mb-2">
                Read the REC letter and comments below, upload your revised files together with a
                <strong>response to REC comments</strong>, then submit the revised version.
            </p>
        </div>
    @endif

    {{-- Revised version waiting to be routed --}}
    @can('routeRevision', $application)
        <div class="alert alert-info">
            <h3 class="h6 fw-bold"><i class="bi bi-signpost-split"></i> A revised version was submitted</h3>
            <p class="small mb-2">Send it where UCUREC requires. It stays the same application ({{ $application->reference_no }}).</p>
            <form method="POST" action="{{ route('applications.route-revision', $application) }}" class="d-flex flex-wrap gap-2">
                @csrf
                <button name="target" value="screening" class="btn btn-sm btn-outline-primary"><i class="bi bi-clipboard-check"></i> Screen again</button>
                <button name="target" value="review" class="btn btn-sm btn-outline-primary"><i class="bi bi-people"></i> Send for further review</button>
                <button name="target" value="decision" class="btn btn-sm btn-primary"><i class="bi bi-journal-check"></i> Send for REC decision</button>
            </form>
        </div>
    @endcan

    <div class="row g-3">
        <div class="col-xl-8">
            {{-- REC decisions: researchers see released ones only --}}
            @php($shownDecisions = $isInternal ? $application->decisions : $releasedDecisions)
            @if ($shownDecisions->isNotEmpty())
                <div class="card card-accent border-0 shadow-sm mb-3" id="decisions">
                    <div class="card-body">
                        <h3 class="h6 fw-bold mb-3"><i class="bi bi-journal-check"></i> REC decisions</h3>
                        @foreach ($shownDecisions as $decision)
                            <div class="border rounded-3 p-3 mb-2">
                                <div class="d-flex flex-wrap justify-content-between gap-2 mb-1">
                                    <span class="fw-bold">{{ $decision->categoryLabel() }}</span>
                                    <span class="small">
                                        @if ($isInternal)
                                            <span class="badge {{ $decision->status->badgeClass() }}">{{ $decision->status->label() }}</span>
                                        @endif
                                        <span class="text-body-secondary">Version {{ $decision->documentSet->version }}</span>
                                    </span>
                                </div>
                                <dl class="row small mb-2">
                                    <dt class="col-sm-4">Decision date</dt><dd class="col-sm-8">{{ $decision->decision_date->format('F j, Y') }}</dd>
                                    @if ($decision->approval_date)
                                        <dt class="col-sm-4">Approval date</dt><dd class="col-sm-8">{{ $decision->approval_date->format('F j, Y') }}</dd>
                                    @endif
                                    @if ($decision->expiry_date)
                                        <dt class="col-sm-4">Approval valid until</dt><dd class="col-sm-8">{{ $decision->expiry_date->format('F j, Y') }}</dd>
                                    @endif
                                    @if ($decision->released_at)
                                        <dt class="col-sm-4">Released</dt><dd class="col-sm-8">{{ $decision->released_at->format('M j, Y g:i A') }}</dd>
                                    @endif
                                </dl>
                                @if ($decision->consolidated_comments)
                                    <div class="small mb-2">
                                        <div class="fw-semibold">Comments from UCUREC</div>
                                        <div style="white-space: pre-line;">{{ $decision->consolidated_comments }}</div>
                                    </div>
                                @endif
                                @if ($decision->isApproval() && $decision->isReleased())
                                    <div class="small mb-2 text-success"><i class="bi bi-patch-check"></i> Approved files: version {{ $decision->documentSet->version }} (listed under Documents).</div>
                                @endif
                                <div class="d-flex flex-wrap gap-3 small">
                                    @foreach ($decision->files as $file)
                                        @continue(! $isInternal && ! $file->isReleasable())
                                        <a href="{{ route('decision-files.download', $file) }}"><i class="bi bi-file-earmark-arrow-down"></i> {{ $file->kindLabel() }}</a>
                                    @endforeach
                                    @if ($isInternal)
                                        <a href="{{ route('decisions.show', $decision) }}"><i class="bi bi-box-arrow-up-right"></i> Open decision record</a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                        <p class="small text-body-secondary mb-0">The decision is the Committee's. Reviewer recommendations do not become decisions automatically.</p>
                    </div>
                </div>
            @endif

            {{-- Reviewers (internal; never rendered for researchers) --}}
            @if ($isInternal && ($canCoordinate || $application->reviewAssignments->isNotEmpty()))
                @include('applications._reviewers')
            @endif

            {{-- Documents --}}
            <div class="card card-accent border-0 shadow-sm mb-3" id="documents">
                <div class="card-body">
                    <h3 class="h6 fw-bold mb-3"><i class="bi bi-folder2-open"></i> Documents</h3>

                    @if ($canEdit)
                        @php($draftSet = $draft)
                        {{-- Without a draft yet, the next submission starts from the last submitted files. --}}
                        @php($nextFiles = $draftSet?->documents ?? ($submittedSets->first()?->documents ?? collect()))
                        @php($present = $nextFiles->pluck('category')->unique()->all())
                        <div class="border rounded-3 p-3 mb-3 bg-light-subtle">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="fw-semibold">
                                    Files for your {{ $application->reference_no ? 'next submission' : 'submission' }}
                                    <span class="text-body-secondary fw-normal">
                                        (Version {{ $draftSet?->version ?? ($submittedSets->first()?->version + 1) }}, draft)
                                    </span>
                                </div>
                            </div>
                            @if ($application->reference_no)
                                <p class="small text-body-secondary">
                                    Your previously submitted files are listed below and stay on record. Remove a file here only if you are replacing it.
                                </p>
                            @endif

                            @include('applications._files', [
                                'documents' => $nextFiles,
                                'removable' => (bool) $draftSet,
                            ])

                            {{-- Upload --}}
                            <form method="POST" action="{{ route('applications.documents.store', $application) }}"
                                  enctype="multipart/form-data" class="row g-2 align-items-end mt-2" novalidate>
                                @csrf
                                <div class="col-md-4">
                                    <label for="category" class="form-label small mb-1">Document type</label>
                                    <select id="category" name="category" class="form-select form-select-sm @error('category') is-invalid @enderror" required>
                                        @foreach ($categories as $key => $category)
                                            <option value="{{ $key }}" @selected(old('category') === $key)>
                                                {{ $category['label'] }}{{ in_array($key, $requiredCategories, true) ? ' *' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="file" class="form-label small mb-1">File</label>
                                    <input id="file" type="file" name="file" required
                                           accept="{{ collect($uploadRules['extensions'])->map(fn ($e) => '.'.$e)->implode(',') }}"
                                           class="form-control form-control-sm @error('file') is-invalid @enderror">
                                    @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-2 d-grid">
                                    <button class="btn btn-sm btn-outline-primary"><i class="bi bi-upload"></i> Upload</button>
                                </div>
                                <div class="col-12 form-text">
                                    {{ strtoupper(implode(', ', $uploadRules['extensions'])) }} only, up to {{ $uploadRules['max_kb'] / 1024 }} MB each.
                                    * required before submitting.
                                </div>
                            </form>

                            {{-- Required files --}}
                            <div class="d-flex flex-wrap gap-2 mt-3 small">
                                @foreach ($categories as $key => $category)
                                    @continue(! in_array($key, $requiredCategories, true))
                                    @if (in_array($key, $present, true))
                                        <span class="badge text-bg-success"><i class="bi bi-check-lg"></i> {{ $category['label'] }}</span>
                                    @else
                                        <span class="badge text-bg-light border"><i class="bi bi-circle"></i> {{ $category['label'] }}</span>
                                    @endif
                                @endforeach
                            </div>

                            {{-- Submit --}}
                            <hr>
                            <form method="POST" action="{{ route('applications.submit', $application) }}" novalidate>
                                @csrf
                                @error('submit')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
                                <div class="mb-2">
                                    <label for="note" class="form-label small mb-1">Message to the REC Secretariat <span class="text-body-secondary">(optional)</span></label>
                                    <textarea id="note" name="note" rows="2" class="form-control form-control-sm @error('note') is-invalid @enderror">{{ old('note') }}</textarea>
                                    @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input @error('confirm') is-invalid @enderror" type="checkbox" name="confirm" value="1" id="confirm">
                                    <label class="form-check-label small" for="confirm">
                                        The files above are complete and final for this submission. I understand they cannot be changed after submitting.
                                    </label>
                                    @error('confirm')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <button class="btn btn-primary"><i class="bi bi-send"></i> Submit to UCUREC</button>
                            </form>
                        </div>
                    @endif

                    {{-- Submitted versions (never change) --}}
                    @forelse ($submittedSets as $set)
                        <div class="mb-3">
                            <div class="d-flex flex-wrap justify-content-between small mb-1">
                                <span class="fw-semibold">Version {{ $set->version }} · {{ $set->kindLabel() }}</span>
                                <span class="text-body-secondary">
                                    Submitted {{ $set->submitted_at->format('M j, Y g:i A') }}@if ($set->submitter) by {{ $set->submitter->name }}@endif
                                </span>
                            </div>
                            @if ($set->note)
                                <div class="small fst-italic text-body-secondary mb-1">“{{ $set->note }}”</div>
                            @endif
                            @include('applications._files', ['documents' => $set->documents, 'removable' => false])
                        </div>
                    @empty
                        @unless ($canEdit)
                            <p class="text-body-secondary mb-0">No submitted documents yet.</p>
                        @endunless
                    @endforelse
                </div>
            </div>

            {{-- Screening results --}}
            @if ($application->screenings->isNotEmpty())
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <h3 class="h6 fw-bold mb-3"><i class="bi bi-clipboard-check"></i> Completeness screening</h3>
                        @foreach ($application->screenings as $screening)
                            <div class="border rounded-3 p-3 mb-2">
                                <div class="d-flex flex-wrap justify-content-between small mb-2">
                                    <span>
                                        @if ($screening->isComplete())
                                            <span class="badge text-bg-success">Complete</span>
                                        @else
                                            <span class="badge text-bg-warning">Incomplete</span>
                                        @endif
                                        Version {{ $screening->documentSet->version ?? '' }}
                                    </span>
                                    <span class="text-body-secondary">
                                        {{ $screening->created_at->format('M j, Y g:i A') }}
                                        · {{ $isInternal ? $screening->screener->name : 'REC Secretariat' }}
                                    </span>
                                </div>
                                <ul class="list-unstyled small mb-0">
                                    @foreach ($screening->checklist as $item)
                                        <li>
                                            @switch($item['state'])
                                                @case(Screening::STATE_PRESENT)<i class="bi bi-check-circle-fill text-success"></i>@break
                                                @case(Screening::STATE_MISSING)<i class="bi bi-x-circle-fill text-danger"></i>@break
                                                @default<i class="bi bi-dash-circle text-secondary"></i>
                                            @endswitch
                                            {{ $item['label'] }}
                                            <span class="text-body-secondary">({{ Screening::stateLabel($item['state']) }})</span>
                                            @if ($item['note'])<span class="text-body-secondary"> — {{ $item['note'] }}</span>@endif
                                        </li>
                                    @endforeach
                                </ul>
                                @if ($screening->remarks)
                                    <div class="small mt-2"><strong>Remarks:</strong> {{ $screening->remarks }}</div>
                                @endif
                            </div>
                        @endforeach
                        <p class="small text-body-secondary mb-0">A complete screening means the requirements are present. It is not an ethics approval.</p>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-xl-4">
            {{-- Application record --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h3 class="h6 fw-bold mb-0"><i class="bi bi-card-text"></i> Application record</h3>
                        @can('update', $application)
                            <a href="{{ route('applications.edit', $application) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
                        @endcan
                    </div>
                    <dl class="small mb-0">
                        <dt>Principal investigator</dt><dd>{{ $application->principal_investigator }}</dd>
                        <dt>Contact</dt><dd>{{ $application->pi_contact }}</dd>
                        <dt>Adviser</dt><dd>{{ $application->adviser ?: '—' }}</dd>
                        <dt>College / program</dt><dd>{{ $application->college_program }}</dd>
                        <dt>Research type</dt><dd>{{ $application->research_type }}</dd>
                        <dt>Submitted</dt><dd>{{ $application->submitted_at?->format('M j, Y') ?? 'Not yet submitted' }}</dd>
                        @if ($application->review_route)
                            <dt>Review route</dt><dd>{{ config('ucurec.review_routes.'.$application->review_route, $application->review_route) }}</dd>
                        @endif
                        @if ($isInternal)
                            <dt>Researcher account</dt><dd>{{ $application->researcher->name }} ({{ $application->researcher->email }})</dd>
                        @endif
                    </dl>
                </div>
            </div>

            {{-- Status history --}}
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h3 class="h6 fw-bold mb-3"><i class="bi bi-clock-history"></i> Status history</h3>
                    <ul class="list-unstyled small mb-0">
                        @foreach ($application->statusHistories->reverse() as $history)
                            <li class="mb-2 pb-2 border-bottom">
                                <x-status-badge :status="$history->to_status" />
                                <span class="text-body-secondary ms-1">{{ $history->created_at->format('M j, Y g:i A') }}</span>
                                @if ($history->note)<div>{{ $history->note }}</div>@endif
                                @if ($isInternal && $history->user)
                                    <div class="text-body-secondary">by {{ $history->user->name }}</div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
