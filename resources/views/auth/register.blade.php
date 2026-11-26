@extends('layouts.guest')

@section('title', 'Researcher sign-up')

@section('content')
    <h2 class="h5 mb-1">Researcher sign-up</h2>
    <p class="small text-body-secondary">The REC Secretariat activates new accounts before you can sign in.</p>

    <form method="POST" action="{{ route('register') }}" novalidate>
        @csrf

        <div class="mb-3">
            <label for="name" class="form-label">Full name</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}"
                   class="form-control @error('name') is-invalid @enderror" required autofocus autocomplete="name">
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                   class="form-control @error('email') is-invalid @enderror" required autocomplete="username">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label for="college_program" class="form-label">College or program</label>
            <select id="college_program" name="college_program"
                    class="form-select @error('college_program') is-invalid @enderror" required>
                <option value="">Select…</option>
                @foreach ($colleges as $college)
                    <option value="{{ $college }}" @selected(old('college_program') === $college)>{{ $college }}</option>
                @endforeach
            </select>
            @error('college_program')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label for="contact_number" class="form-label">Contact number <span class="text-body-secondary">(optional)</span></label>
            <input id="contact_number" type="text" name="contact_number" value="{{ old('contact_number') }}"
                   class="form-control @error('contact_number') is-invalid @enderror" autocomplete="tel">
            @error('contact_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <x-password-input name="password" autocomplete="new-password" />
            <div class="form-text">At least 8 characters, with letters and numbers.</div>
        </div>

        <div class="mb-4">
            <label for="password_confirmation" class="form-label">Confirm password</label>
            <x-password-input name="password_confirmation" autocomplete="new-password" />
        </div>

        <button type="submit" class="btn btn-primary w-100">Create account</button>
    </form>

    <hr>
    <p class="small text-center mb-0">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
@endsection
