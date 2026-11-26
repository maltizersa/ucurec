@extends('layouts.app')

@use('App\Models\Decision')
@use('App\Enums\DecisionStatus')

@section('title', 'REC decision · '.$application->reference_no)

@section('content')
    <div class="mb-3">
        <a href="{{ route('applications.show', $application) }}" class="small"><i class="bi bi-arrow-left"></i> Back to application</a>
        <div class="d-flex align-items-center gap-2 mt-1">
            <span class="fw-semibold text-body-secondary">{{ $application->reference_no }}</span>
            <span class="badge {{ $decision->status->badgeClass() }}">{{ $decision->status->label() }}</span>
        </div>
        <h2 class="h5 fw-bold page-title mb-0">{{ $application->title }}</h2>
    </div>

    @if ($decision->chair_note && $decision->status === DecisionStatus::Draft)
        <div class="alert alert-warning">
            <strong><i class="bi bi-arrow-return-left"></i> Returned by the Chairperson:</strong> {{ $decision->chair_note }}
        </div>
    @endif

    @error('decision')
        <div class="alert alert-danger">
            <div class="fw-semibold">This decision is not ready yet:</div>
            <ul class="mb-0">@foreach ($errors->get('decision') as $message)<li>{{ $message }}</li>@endforeach</ul>
        </div>
    @enderror

    <div class="row g-3">
        <div class="col-xl-8">
            {{-- Decision details --}}
            <div class="card card-accent border-0 shadow-sm mb-3">
                <div class="card-body p-4">
                    @can('update', $decision)
                        <form method="POST" action="{{ route('decisions.update', $decision) }}" novalidate>
                            @method('PUT')
                            @include('decisions._fields')
                            <button class="btn btn-outline-primary mt-3"><i class="bi bi-save"></i> Save changes</button>
                        </form>
                    @else
                        <dl class="row mb-0">
                            <dt class="col-sm-4">REC decision</dt><dd class="col-sm-8 fw-semibold">{{ $decision->categoryLabel() }}</dd>
                            <dt class="col-sm-4">Decision date</dt><dd class="col-sm-8">{{ $decision->decision_date->format('F j, Y') }}</dd>
                            @if ($decision->approval_date)
                                <dt class="col-sm-4">Approval date</dt><dd class="col-sm-8">{{ $decision->approval_date->format('F j, Y') }}</dd>
                            @endif
                            @if ($decision->expiry_date)
                                <dt class="col-sm-4">Approval expiry</dt><dd class="col-sm-8">{{ $decision->expiry_date->format('F j, Y') }}</dd>
                            @endif
                            <dt class="col-sm-4">Decision basis <span class="badge text-bg-light border fw-normal">internal</span></dt>
                            <dd class="col-sm-8">{{ $decision->basis ?: '—' }}</dd>
                            <dt class="col-sm-4">Consolidated comments</dt>
                            <dd class="col-sm-8" style="white-space: pre-line;">{{ $decision->consolidated_comments ?: '—' }}</dd>
                            <dt class="col-sm-4">Recorded by</dt><dd class="col-sm-8">{{ $decision->recorder->name }}</dd>
                            @if ($decision->confirmer)
                                <dt class="col-sm-4">Confirmed by</dt>
                                <dd class="col-sm-8">{{ $decision->confirmer->name }}, {{ $decision->confirmed_at->format('M j, Y g:i A') }}</dd>
                            @endif
                        </dl>
                    @endcan
                </div>
            </div>

            {{-- Files --}}
            <div class="card border-0 shadow-sm mb-3" id="files">
                <div class="card-body">
                    <h3 class="h6 fw-bold"><i class="bi bi-paperclip"></i> Letter and records</h3>
                    @if ($decision->files->isNotEmpty())
                        <ul class="list-group list-group-flush mb-3">
                            @foreach ($decision->files as $file)
                                <li class="list-group-item px-0 d-flex justify-content-between align-items-center small">
                                    <span>
                                        <span class="fw-semibold">{{ $file->kindLabel() }}:</span>
                                        <a href="{{ route('decision-files.download', $file) }}">{{ $file->original_name }}</a>
                                        <span class="text-body-secondary">({{ $file->humanSize() }})</span>
                                        @if ($file->isReleasable())
                                            <span class="badge text-bg-warning fw-normal">researcher can download after release</span>
                                        @endif
                                    </span>
                                    @can('delete', $file)
                                        <form method="POST" action="{{ route('decisions.files.destroy', [$decision, $file]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" title="Remove" aria-label="Remove"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endcan
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="small text-body-secondary">No files yet.</p>
                    @endif

                    @can('update', $decision)
                        <form method="POST" action="{{ route('decisions.files.store', $decision) }}" enctype="multipart/form-data" class="row g-2 align-items-end" novalidate>
                            @csrf
                            <div class="col-md-5">
                                <label for="kind" class="form-label small mb-1">File type</label>
                                <select id="kind" name="kind" class="form-select form-select-sm">
                                    @foreach ([Decision::FILE_LETTER, Decision::FILE_COMMENTS, Decision::FILE_MINUTES] as $kind)
                                        <option value="{{ $kind }}" @selected(old('kind') === $kind)>{{ Decision::fileKindLabel($kind) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label for="file" class="form-label small mb-1">File (Word or PDF)</label>
                                <input id="file" type="file" name="file" class="form-control form-control-sm @error('file') is-invalid @enderror" required>
                                @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-2 d-grid">
                                <button class="btn btn-sm btn-outline-primary"><i class="bi bi-upload"></i> Upload</button>
                            </div>
                            @if ($application->review_route === 'full')
                                <div class="col-12 form-text">Full committee review: attach the approved decision record or a minutes extract.</div>
                            @endif
                        </form>
                    @endcan
                </div>
            </div>

            {{-- Actions --}}
            @can('update', $decision)
                <form method="POST" action="{{ route('decisions.send', $decision) }}" class="mb-3"
                      onsubmit="return confirm('Send this decision to the Chairperson for confirmation?')">
                    @csrf
                    <button class="btn btn-primary"><i class="bi bi-send"></i> Send to Chairperson for confirmation</button>
                </form>
            @endcan

            @can('confirm', $decision)
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <h3 class="h6 fw-bold"><i class="bi bi-patch-check"></i> Chairperson confirmation</h3>
                        <p class="small">
                            Confirming releases this decision, the signed letter and the consolidated comments to the researcher.
                            The decision basis and minutes stay internal.
                        </p>
                        <form method="POST" action="{{ route('decisions.confirm', $decision) }}" class="d-inline"
                              onsubmit="return confirm('Confirm and release this decision to the researcher?')">
                            @csrf
                            <button class="btn btn-success"><i class="bi bi-check2-circle"></i> Confirm and release</button>
                        </form>
                        <form method="POST" action="{{ route('decisions.return', $decision) }}" class="mt-3" novalidate>
                            @csrf
                            <label for="chair_note" class="form-label small">Or return it to the Secretariat</label>
                            <textarea id="chair_note" name="chair_note" rows="2" class="form-control form-control-sm @error('chair_note') is-invalid @enderror"
                                      placeholder="What needs to change?">{{ old('chair_note') }}</textarea>
                            @error('chair_note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <button class="btn btn-sm btn-outline-secondary mt-2"><i class="bi bi-arrow-return-left"></i> Return to Secretariat</button>
                        </form>
                    </div>
                </div>
            @endcan
        </div>

        <div class="col-xl-4">
            @include('decisions._recommendations')

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h3 class="h6 fw-bold"><i class="bi bi-folder-check"></i> Version decided: {{ $decision->documentSet->version }}</h3>
                    <p class="small text-body-secondary mb-2">For an approval, these are the exact approved files.</p>
                    <ul class="list-unstyled small mb-0">
                        @foreach ($decision->documentSet->documents as $document)
                            <li><a href="{{ route('documents.download', $document) }}"><i class="bi bi-file-earmark"></i> {{ $document->original_name }}</a>
                                <span class="text-body-secondary">({{ $document->categoryLabel() }})</span></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
