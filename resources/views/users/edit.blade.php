@extends('layouts.app')

@section('title', 'Edit account')

@section('content')
    <div class="card card-accent border-0 shadow-sm" style="max-width: 820px;">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('users.update', $user) }}" novalidate>
                @method('PUT')
                @include('users._form')
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
