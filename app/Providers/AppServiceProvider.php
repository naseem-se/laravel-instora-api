<?php

namespace App\Providers;

use App\Support\DnsResolverInterface;
use App\Support\SystemDnsResolver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DnsResolverInterface::class, SystemDnsResolver::class);
    }

    public function boot(): void
    {
        RateLimiter::for('login', function ($request) {
            return [
                Limit::perMinute(5)->by('login-ip:'.$request->ip()),
                Limit::perMinute(5)->by('login-email:'.strtolower((string) $request->input('email'))),
            ];
        });

        // Password-related endpoints, per Security.md #17.
        RateLimiter::for('password-reset', function ($request) {
            return [
                Limit::perMinutes(15, 5)->by('password-reset-ip:'.$request->ip()),
                Limit::perMinutes(15, 3)->by('password-reset-email:'.strtolower((string) $request->input('email'))),
            ];
        });

        RateLimiter::for('notification-retry', function ($request) {
            return Limit::perMinute(20)->by('notification-retry:'.($request->user()?->id ?? $request->ip()));
        });

        RateLimiter::for('whatsapp-test', function ($request) {
            return Limit::perMinute(3)->by('whatsapp-test:'.($request->user()?->company_id ?? $request->ip()));
        });

        RateLimiter::for('whatsapp-webhook', function ($request) {
            return Limit::perMinute(120)->by('whatsapp-webhook:'.$request->route('provider'));
        });

        // Points the reset link at the SPA frontend, not a Laravel Blade
        // route - the standard pattern for an API-only backend paired with
        // a separate frontend.
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            $frontendUrl = rtrim((string) env('FRONTEND_URL'), '/');

            return "{$frontendUrl}/reset-password?token={$token}&email=".urlencode($notifiable->getEmailForPasswordReset());
        });
    }
}