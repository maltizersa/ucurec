@foreach (['success' => 'success', 'warning' => 'warning', 'error' => 'danger', 'info' => 'info'] as $key => $variant)
    @if (session($key))
        <div class="alert alert-{{ $variant }} alert-dismissible fade show" role="alert">
            {{ session($key) }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
@endforeach

@if (session('temporary_password'))
    <div class="alert alert-warning" role="alert">
        <div class="fw-semibold mb-1"><i class="bi bi-key"></i> Temporary password for {{ session('temporary_password.email') }}</div>
        <code class="fs-5 user-select-all">{{ session('temporary_password.password') }}</code>
        <div class="small mt-1">This is shown only once. Give it to the user privately; they must change it at their next sign-in.</div>
    </div>
@endif
