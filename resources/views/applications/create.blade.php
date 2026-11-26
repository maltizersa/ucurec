@extends('layouts.app')

@section('title', 'New application')

@section('content')
    <div class="card card-accent border-0 shadow-sm" style="max-width: 900px;">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('applications.store') }}" novalidate>
                @include('applications._form')
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save and continue to documents</button>
                    <a href="{{ route('applications.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
