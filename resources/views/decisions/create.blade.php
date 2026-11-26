@extends('layouts.app')

@section('title', 'Record REC decision · '.$application->reference_no)

@section('content')
    <div class="mb-3">
        <a href="{{ route('applications.show', $application) }}" class="small"><i class="bi bi-arrow-left"></i> Back to application</a>
        <h2 class="h5 fw-bold page-title mt-1 mb-0">{{ $application->title }}</h2>
        <div class="small text-body-secondary">
            {{ $application->reference_no }} · {{ $application->principal_investigator }}
            @if ($application->review_route) · {{ config('ucurec.review_routes.'.$application->review_route) }}@endif
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card card-accent border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('applications.decisions.store', $application) }}" novalidate>
                        @include('decisions._fields')
                        <div class="mt-4 d-flex gap-2">
                            <button class="btn btn-primary">Save draft and continue</button>
                            <a href="{{ route('applications.show', $application) }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            @include('decisions._recommendations')
        </div>
    </div>
@endsection
