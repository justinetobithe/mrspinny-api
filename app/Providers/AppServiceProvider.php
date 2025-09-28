<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $perMinute = (int) env('MAIL_RATE_PER_MINUTE', 120);
        $perHour = (int) env('MAIL_RATE_PER_HOUR', 3000);

        RateLimiter::for('mail-sends', function () use ($perMinute, $perHour) {
            return [
                Limit::perMinute($perMinute)->by('mailer'),
                Limit::perHour($perHour)->by('mailer'),
            ];
        });
    }
}
