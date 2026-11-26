@extends('layouts.app')

@section('title', 'Change password')

@section('content')
    <div class="card card-accent border-0 shadow-sm" style="max-width: 520px;">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('account.password.update') }}" novalidate>
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label for="current_password" class="form-label">
                        {{ auth()->user()->must_change_password ? 'Temporary password' : 'Current password' }}
                    </label>
                    <x-password-input name="current_password" autocomplete="current-password" />
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">New password</label>
                    <x-password-input name="password" autocomplete="new-password" />
                    <div class="form-text">At least 8 characters, with letters and numbers.</div>
                </div>

                <div class="mb-4">
                    <label for="password_confirmation" class="form-label">Confirm new password</label>
                    <x-password-input name="password_confirmation" autocomplete="new-password" />
                </div>

                <button type="submit" class="btn btn-primary">Save new password</button>
            </form>
        </div>
    </div>
@endsection
