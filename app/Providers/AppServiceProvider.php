<?php

namespace App\Providers;

use App\Contracts\MailTransport;
use App\Services\Mail\PhpMailerTransport;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MailTransport::class, PhpMailerTransport::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        // The live server is reached over HTTPS only.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
