@extends('layouts.app')

@use('App\Models\Screening')

@section('title', 'Screening · '.$application->displayReference())

@section('content')
    <div class="mb-3">
        <a href="{{ route('applications.show', $application) }}" class="small"><i class="bi bi-arrow-left"></i> Back to application</a>
        <h2 class="h5 fw-bold page-title mt-1 mb-0">{{ $application->title }}</h2>
        <div class="small text-body-secondary">
            {{ $application->principal_investigator }} · {{ $application->college_program }} · Version {{ $set->version }} ({{ $set->kindLabel() }})
        </div>
    </div>

    @php($present = $set->documents->pluck('category')->unique()->all())

    <div class="row g-3">
        <div class="col-xl-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h3 class="h6 fw-bold"><i class="bi bi-folder2-open"></i> Submitted files (version {{ $set->version }})</h3>
                    @if ($set->note)
                        <div class="small fst-italic text-body-secondary mb-2">Researcher's message: “{{ $set->note }}”</div>
                    @endif
                    @include('applications._files', ['documents' => $set->documents, 'removable' => false])
                </div>
            </div>

            @if ($previous)
                <div class="card border-0 shadow-sm mt-3">
                    <div class="card-body small">
                        <h3 class="h6 fw-bold">Previous screening ({{ $previous->created_at->format('M j, Y') }})</h3>
                        <div>Missing then:</div>
                        <ul class="mb-0">
                            @forelse ($previous->missingItems() as $item)
                                <li>{{ $item['label'] }}@if ($item['note']) — {{ $item['note'] }}@endif</li>
                            @empty
                                <li>None</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-xl-7">
            <div class="card card-accent border-0 shadow-sm">
                <div class="card-body">
                    <h3 class="h6 fw-bold"><i class="bi bi-list-check"></i> UCUREC completeness checklist</h3>
                    <p class="small text-body-secondary">
                        Open each file and mark every item. If any item is <strong>Missing</strong>, the application goes back to the
                        researcher as <strong>Incomplete</strong> with this list. Otherwise it moves to <strong>For Review</strong>.
                        Completeness is not ethical approval.
                    </p>

                    <form method="POST" action="{{ route('applications.screening.store', $application) }}" novalidate>
                        @csrf

                        @foreach ($checklist as $key => $label)
                            @php($default = in_array($key, $present, true) ? Screening::STATE_PRESENT : null)
                            @php($current = old("items.{$key}.state", $default))
                            <div class="border rounded-3 p-2 mb-2 @error("items.{$key}.state") border-danger @enderror">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <div class="small fw-semibold">
                                        {{ $label }}
                                        @if (in_array($key, $present, true))
                                            <span class="badge text-bg-light border fw-normal">file uploaded</span>
                                        @endif
                                    </div>
                                    <div class="btn-group btn-group-sm" role="group" aria-label="{{ $label }}">
                                        @foreach ([Screening::STATE_PRESENT => 'success', Screening::STATE_MISSING => 'danger', Screening::STATE_NOT_APPLICABLE => 'secondary'] as $state => $variant)
                                            <input type="radio" class="btn-check" name="items[{{ $key }}][state]"
                                                   id="item-{{ $key }}-{{ $state }}" value="{{ $state }}" autocomplete="off" @checked($current === $state)>
                                            <label class="btn btn-outline-{{ $variant }}" for="item-{{ $key }}-{{ $state }}">{{ Screening::stateLabel($state) }}</label>
                                        @endforeach
                                    </div>
                                </div>
                                <input type="text" name="items[{{ $key }}][note]" value="{{ old("items.{$key}.note") }}"
                                       class="form-control form-control-sm mt-2" placeholder="Note for the researcher (optional)">
                                @error("items.{$key}.state")<div class="small text-danger mt-1">{{ $message }}</div>@enderror
                            </div>
                        @endforeach

                        <div class="mb-3 mt-3">
                            <label for="remarks" class="form-label small">Remarks to the researcher <span class="text-body-secondary">(optional)</span></label>
                            <textarea id="remarks" name="remarks" rows="2" class="form-control @error('remarks') is-invalid @enderror">{{ old('remarks') }}</textarea>
                            @error('remarks')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="review_route" class="form-label small">Review route, if already decided <span class="text-body-secondary">(used only when complete)</span></label>
                            <select id="review_route" name="review_route" class="form-select @error('review_route') is-invalid @enderror">
                                <option value="">Not decided yet</option>
                                @foreach ($reviewRoutes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('review_route', $application->review_route) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('review_route')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <button class="btn btn-primary"><i class="bi bi-save"></i> Save screening result</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
