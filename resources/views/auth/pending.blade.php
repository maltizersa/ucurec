@extends('layouts.guest')

@section('title', 'Waiting for activation')

@section('content')
    <div class="text-center">
        <i class="bi bi-hourglass-split text-warning fs-1"></i>
        <h2 class="h5 mt-2">Account created</h2>
        <p class="mb-3">
            Your account is waiting for activation by the REC Secretariat.
            You can sign in once it has been activated.
        </p>
        <a href="{{ route('login') }}" class="btn btn-outline-primary">Back to sign in</a>
    </div>
@endsection
