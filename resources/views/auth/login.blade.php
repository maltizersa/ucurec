@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
    <h2 class="h5 mb-3">Sign in</h2>

    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                   class="form-control @error('email') is-invalid @enderror" required autofocus autocomplete="username">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-4">
            <label for="password" class="form-label">Password</label>
            <x-password-input name="password" autocomplete="current-password" />
        </div>

        <button type="submit" class="btn btn-primary w-100">Sign in</button>
    </form>

    <hr>
    <p class="small text-center mb-0">
        Researcher without an account? <a href="{{ route('register') }}">Sign up</a>
    </p>
    <p class="small text-center text-body-secondary mt-2 mb-0">
        Forgot your password? Contact the REC Secretariat or the IT administrator.
    </p>
@endsection
