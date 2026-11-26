<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link rel="icon" type="image/webp" href="{{ asset('images/ucu-logo.webp') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <main class="auth-wrapper d-flex align-items-center justify-content-center px-3 py-5">
        <div class="auth-card">
            <div class="card shadow-lg border-0">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <img src="{{ asset('images/ucu-logo.webp') }}" alt="Urdaneta City University logo" class="auth-logo">
                        <h1 class="h4 fw-bold page-title mt-3 mb-1">UCUREC Ethics Review</h1>
                        <div class="gold-rule mx-auto my-2"></div>
                        <p class="small text-body-secondary mb-0">Research Ethics Committee</p>
                    </div>

                    @include('partials.flash')
                    @yield('content')
                </div>
            </div>
        </div>
    </main>
</body>
</html>
