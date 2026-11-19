<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
                \Illuminate\Support\Facades\RateLimiter::for('login', function (\Illuminate\Http\Request $request) {
            return [
                \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by($request->ip()),
                \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by('login:' . strtolower((string) $request->input('email')) . '|' . $request->ip()), // per email AND ip: a stranger spamming an address can no longer lock its owner out
            ];
        });
        \App\Models\Donation::observe(\App\Observers\DonationObserver::class);

        foreach ([\App\Models\Program::class, \App\Models\Axis::class, \App\Models\CaseModel::class, \App\Models\Story::class, \App\Models\Content::class] as $model) {
            $model::observe(\App\Observers\PublicContentObserver::class);
        }
    }
}
