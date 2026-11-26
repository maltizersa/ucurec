@extends('layouts.app')

@section('title', 'New account')

@section('content')
    <div class="card card-accent border-0 shadow-sm" style="max-width: 820px;">
        <div class="card-body p-4">
            <p class="text-body-secondary small">
                The account is active immediately. A temporary password is emailed to the address below;
                the user must replace it at their first sign-in. If email is not set up, the password is shown
                to you once instead.
            </p>
            <form method="POST" action="{{ route('users.store') }}" novalidate>
                @include('users._form')
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Create account</button>
                    <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
