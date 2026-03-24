<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        ResetPassword::createUrlUsing(function ($user, string $token) {
            $frontend = rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/');
            return $frontend . '/reset-password?token=' . $token . '&email=' . urlencode($user->email);
        });

        // ── Rate limiters ────────────────────────────────────────────────────

        // Auth sensible : login, register, forgot-password — 10/min par IP
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Ouverture de packs — 15 req/min par utilisateur
        RateLimiter::for('pack-opening', function (Request $request) {
            return Limit::perMinute(15)->by($request->user()?->id ?: $request->ip());
        });

        // Paiements — 5/min par utilisateur
        RateLimiter::for('payment', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });

        // Demandes d'amis — 20/min par utilisateur
        RateLimiter::for('friend-request', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        // API générale — 120 req/min par utilisateur authentifié
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
    }
}
