<?php

namespace App\Providers;

use App\Http\Middleware\ActivityTrail;
use App\Models\ActivityLog;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

/** Turns the activity trail on for the whole API without touching bootstrap/app.php or any controller. */
class ActivityTrailServiceProvider extends ServiceProvider
{
    public function boot(Router $router): void
    {
        $router->pushMiddlewareToGroup('api', ActivityTrail::class);

        // A controller that logged its own (richer) entry in this request: the trail stays quiet.
        ActivityLog::created(function () {
            if (! app()->bound('activity.logged')) {
                app()->instance('activity.logged', true);
            }
        });
    }
}
