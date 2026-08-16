<?php

namespace App\Providers;

use Filament\Infolists\Infolist;
use Filament\Tables\Table;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
        // SPEC-016 §12: number formatting (decimal separators) is out of
        // scope and must be preserved. The app locale is `es`, but Filament
        // would otherwise format `->numeric()` columns/entries with the
        // Spanish comma decimal separator; pin the number locale to `en` so
        // the existing dot-decimal format stays byte-for-byte unchanged.
        Table::$defaultNumberLocale = 'en';
        Infolist::$defaultNumberLocale = 'en';

        // Framework default security hardening for the login form
        // (SPEC-001 ERR-001; architecture SPEC-001 §5).
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->input('email').'|'.$request->ip());
        });

        RateLimiter::for('registration', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Demo hardening (SPEC-016): cap write requests to the admin panel per
        // IP so a public demo instance cannot be flooded with record creation
        // (clients, plans, memberships, etc.). 120 writes/minute is generous
        // for a human operator yet blocks scripted bulk-abuse in seconds.
        // Only enforced when the panel is publicly reachable (demo mode).
        RateLimiter::for('admin-write', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });
    }
}
